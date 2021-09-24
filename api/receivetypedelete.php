<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->receiveCode) && $param->receiveCode != ''){
	$info = ORM::for_table(TBLRECEIVETYPE)->find_one($param->receiveCode);
	setDelete($info, $param->deleteUserId);
	$info->save();
}
else
{
	echo "DELETE ERROR";
}

?>