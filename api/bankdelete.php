<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->pid) && $param->pid > 0){
	$info = ORM::for_table(TBLBANK)->find_one($param->pid);
	setDelete($info, $param->deleteUserId);
	$info->save();
}
else
{
	echo "DELETE ERROR";
}

?>
