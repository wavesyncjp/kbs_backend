<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
if(isset($param->depCode) && $param->depCode != ''){
	$info = ORM::for_table(TBLDEPARTMENT)->find_one($param->depCode);
	setDelete($info, $param->deleteUserId);
}
$info->save();

?>