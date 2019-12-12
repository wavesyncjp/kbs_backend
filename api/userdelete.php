<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if($param->userId > 0){
	$info = ORM::for_table(TBLUSER)->find_one($param->userId);
	setDelete($info, $param->deleteUserId);
}
$info->save();

?>