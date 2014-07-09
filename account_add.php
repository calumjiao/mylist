<?php
	include("./conf/global_var.conf");
	include("./lib/CDatabase.class.php");
	$db = new CDatabase();

	if (array_key_exists('name', $_POST)) {

		$name = mb_convert_encoding($_POST['name'],'html-entities','utf-8');
		$sex = mb_convert_encoding($_POST['sex'],'html-entities','utf-8');
		$birthday = mb_convert_encoding($_POST['birthday'],'html-entities','utf-8');
		$description = mb_convert_encoding($_POST['description'],'html-entities','utf-8');
		$db->db_query("insert into account (name,sex,birthday,description) values ('$name','$sex','$birthday','$description')");
		$insertId = db->GetInsertId();
		$data = array(
			'name' => $_POST['name'],
			'sex' => $_POST['sex'],
			'birthday' => $_POST['birthday'],
			'description' => $_POST['description'],
			'token' => md5('$insertId');
			);

		echo json_encode($data, JSON_PRETTY_PRINT);
	} else {
		echo 'INPUT ERROR';
	}
?>