<?php
// +------------------------------------------------------------------------+
// |                 通用数据库访问类(MySQL/MSSQL Server) v2.0              |
// +------------------------------------------------------------------------+
// | 作者：刘月明                                                           |
// | 修改日志：                                                             |
// |   2004-3-5                                                             |
// |     创建                                                               |
// |   2005-7-21                                                            |
// |     简化部分代码                                                       |
// |     增加部分注释                                                       |
// |   2005-7-29                                                            |
// |     增加GetInsertId()                                                  |
// |   2005-8-19                                                            |
// |     增加对全局变量$_sql_debug_的判断，如果在执行分页语句的时候         |
// |     对该变量赋值， 那么将输出SQL语句                                   |
// |   2006-5-10                                                            |
// |     //if(eregi("select",$query)) 2006-5-10                             |
// |     if(eregi("^select",$query))                                        |
// |   2008-4-7                                                             |
// |     mysql_connect($this->sql_host,$this->sql_user,$this->sql_pass)     |
// |     mysql_connect($this->sql_host,$this->sql_user,$this->sql_pass,true)|
// |   2008-07-10                                                           |
// |     bugfix exit_with_error()  add $this->conn_handle                   |
// +------------------------------------------------------------------------+
//
/*

需要使用的全局变量：
$DB_TAG 	     数据库种类标记（可能取值：mssql，mysql）
$SQL_HOST		 数据库服务器的IP
$SQL_DBNAME	     数据库名称
$SQL_USER	     数据库用户名
$SQL_PASS	     数据库密码

*/

class CDatabase
{
//---------------
//private members
//---------------
	var $sql_host;		//数据库服务器的IP
	var $sql_db;		//数据库的名称
	var $sql_user;		//数据库用户名
	var $sql_pass;		//数据库密码

	var $conn_handle;	//当前所连接的句柄
	var $result_handle;	//查询返回的句柄

//---------------
//public members
//---------------
	var $row;			//保存查询返回的某一记录行，一维数组，下标为0开始的数组或者查询出来的列名称字符串（建议使用列名称）
	var $rows;			//保存查询的所有记录，使用二维数组结构$rows[$i][$j],$i表示是第$i个记录行而$j表示是该记录中的第$j个字段，或者$j为列名称（建议使用列名称）
	var $num_rows;		//保存查询的所有记录的条数（select）
	var $affected_rows; //保存影响到的条数（insert update delete）

//---------------
//private functions
//---------------

	//显示错误信息并停止执行脚本
	function exit_with_error($err_msg)
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
			echo "<P> $err_msg errno:[".mysql_errno($this->conn_handle)."] error:[".mysql_error($this->conn_handle)."]</P>";
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			echo "<P> $err_msg last_message:[".mssql_get_last_message()."]</P>";
		}
		exit();
	}

	/*
	功能说明：
		组成分页所需的sql语句
	参数说明：
		$page_info 分页信息数组
			$page_info["page_size"]; 每页显示的行数
			$page_info["page_index"]; 当前页数，取值从1开始
		$table 要分页的表
		$key_column 递增索引列，分页使用
		$where="" where条件
		$order="DESC" 排序
	返回值：
		分页所需的sql语句数组
			$sqls["total_count"]
			$sqls["content"]
	*/
	function get_depart_page_sqls($page_info,$table,$key_column,$where="",$order="DESC",$select_column=" * ")
	{
		$page_size = $page_info["page_size"];
		$page_index = $page_info["page_index"];

		$page_row_count = (int)$page_size*((int)$page_index-1);
		if($page_row_count <= 0)
			$page_row_count = "0";

		if($where != "")
		{
			$where1 = "where ".$where;
			$where2 = " and ".$where;
		}
		else
		{
			$where1 = "";
			$where2 = "";
		}

		$sqls = array();

		$sqls["total_count"] = "select count(*) from $table $where1";

		if($GLOBALS["DB_TAG"] == "mysql")
		{
			$offset = (int)($page_size*($page_index-1));
			$sqls["content"] = "select $select_column from $table $where1 order by $key_column $order limit $offset,$page_size";
		}

		if($GLOBALS["DB_TAG"] == "mssql")
		{
			$sqls["content"] = "select top $page_size $select_column from $table where $key_column not in ( select top $page_row_count $key_column from $table $where1 order by $key_column $order ) $where2 order by $key_column $order";
		}

		return $sqls;
	}

//---------------
//public functions
//---------------

	//构造函数，从配置文件读取全局变量值连接数据库
	function CDatabase()
	{
		$this->sql_host=$GLOBALS["SQL_HOST"];
		$this->sql_db=$GLOBALS["SQL_DBNAME"];
		$this->sql_user=$GLOBALS["SQL_USER"];
		$this->sql_pass=$GLOBALS["SQL_PASS"];

		$this->row=array();
		$this->rows=array();

		if($GLOBALS["DB_TAG"] == "mysql")
		{
			if(!$this->conn_handle = mysql_connect($this->sql_host,$this->sql_user,$this->sql_pass,true))
				$this->exit_with_error("database connect fail!");

			if(!mysql_select_db($this->sql_db,$this->conn_handle))
				$this->exit_with_error("database [$this->sql_db] select fail!");
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			if(!$this->conn_handle = mssql_connect($this->sql_host,$this->sql_user,$this->sql_pass))
				$this->exit_with_error("database connect fail!");

			if(!mssql_select_db($this->sql_db,$this->conn_handle))
				$this->exit_with_error("database [$this->sql_db] select fail!");
		}

		if(isset($GLOBALS['g_mysql_ver']) && $GLOBALS['g_mysql_ver'] == "4.x")
			$this->db_query("set names 'gb2312'");

	}

	//执行数据库操作，例如： select insert update delete，不成功返回假值
	function db_query($query)
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
				if(!$this->result_handle = mysql_query($query,$this->conn_handle))
					$this->exit_with_error("database query: [$query] fail!");

				if(eregi("^select|^desc",$query))
					$this->num_rows=mysql_num_rows($this->result_handle);
				else
					$this->affected_rows=mysql_affected_rows($this->conn_handle);
				return $this->result_handle;
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
				if(!$this->result_handle=mssql_query($query,$this->conn_handle))
					$this->exit_with_error("database query: [$query] fail!");

				//if(eregi("select",$query)) 2006-5-10
				if(eregi("^select",$query))
					$this->num_rows=mssql_num_rows($this->result_handle);
				else
					$this->affected_rows=0;//mssql_affected_rows($this->conn_handle);???
				return $this->result_handle;
		}
	}

	//执行db_query("select ...") 语句后获取一条查询结果，最后一条之后db_fetch()调用返回假值， 结果数据：$this->row 或 db_fetch()的返回值
	function db_fetch()
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
			$this->row=mysql_fetch_array($this->result_handle);
			return $this->row;
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			$this->row=mssql_fetch_array($this->result_handle);
			return $this->row;
		}
	}

	//执行db_query("select ...") 语句后获取全部查询结果，总行数：$this->num_rows， 结果数据：$this->rows 或 db_fetch_all()的返回值
	function db_fetch_all()
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
			if($this->num_rows > 0)
			{
				mysql_data_seek($this->result_handle,0);
				$i=0;
				$this->rows = array();
				while($row = mysql_fetch_array($this->result_handle))
					$this->rows[$i++]=$row;
				return $this->rows;
			}
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			if($this->num_rows > 0)
			{
				mssql_data_seek($this->result_handle,0);
				$i=0;
				$this->rows = array();
				while($row = mssql_fetch_array($this->result_handle))
					$this->rows[$i++]=$row;
				return $this->rows;
			}
		}
	}

	//关闭与数据库的连接
	function db_close()
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
			if(!mysql_close($this->conn_handle))
				$this->exit_with_error("database close: [$this->sql_db] fail!");
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			if(!mssql_close($this->conn_handle))
				$this->exit_with_error("database close: [$this->sql_db] fail!");
		}
	}

	/*
	功能说明：
		分页函数，根据提供的参数，返回搜索出来的内容
	参数说明：
		同get_depart_page_sqls()
	返回值：
		$results['total_count'] 记录总行数
		$results['total_page'] 总页数
		$results['content'] 记录数据内容，二位数组
	*/
	function depart_page($page_info,$table,$key_column,$where="",$order="DESC",$select_column=" * ")
	{
		$sql = $this->get_depart_page_sqls($page_info,$table,$key_column,$where,$order,$select_column);

		global $_sql_debug_;
		if(isset($_sql_debug_)) print_r($sql);

		//获得记录总行数
		$results = array();
		if($this->db_query($sql['total_count']))
		{
			$this->db_fetch();
			$results['total_count'] = $this->row[0];
		}

		//计算总页数
		$tmp_total_count = (int)$results['total_count'];
		$tmp_page_size = (int)$page_info['page_size'];
		if(0 != $tmp_page_size)
			$tmp_total_page = (int)((int)$tmp_total_count/(int)$tmp_page_size);
		if($tmp_total_page * $tmp_page_size < $tmp_total_count)
			$tmp_total_page++;
		$results['total_page'] = $tmp_total_page;

		//获得记录数据内容
		if($this->db_query($sql['content']))
			$results['content'] = $this->db_fetch_all();

		return $results;
	}

	function GetInsertId()
	{
		if($GLOBALS["DB_TAG"] == "mysql")
		{
			return mysql_insert_id($this->conn_handle);
		}
		if($GLOBALS["DB_TAG"] == "mssql")
		{
			$this->db_query("select @@identity");
			$this->db_fetch();
			return $this->row[0];
		}
		return false;
	}

}
?>