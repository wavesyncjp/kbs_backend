<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->pid) && $param->pid != ''){
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->findOne($param->pid);
	setDelete($paycontract, $param->deleteUserId);
	$paycontract->save();
}
else
{
	echo "DELETE ERROR";
}

?>