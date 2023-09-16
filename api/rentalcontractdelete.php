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

ORM::get_db()->beginTransaction();

$obj = ORM::for_table(TBLRENTALCONTRACT)->find_one($pid);
if (isset($obj)) {
	setDelete($obj, $userId);
	$obj->save();
}
$receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalContractPid', $pid)->where_null('deleteDate')->find_many();
if ($receives != null) {
	foreach ($receives as $rev) {
		setDelete($rev, $userId);
		$rev->save();
	}
}

ORM::get_db()->commit();

echo json_encode(array('status' => 'OK'));
?>
