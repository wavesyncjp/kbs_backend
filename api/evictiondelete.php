<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = $param->userId;
$pid = $param->pid;

$obj = ORM::for_table(TBLEVICTIONINFO)->find_one($pid);
if (isset($obj)) {
	setDelete($obj, $userId);
	$obj->save();
}

echo json_encode(array('status' => 'OK'));
?>
