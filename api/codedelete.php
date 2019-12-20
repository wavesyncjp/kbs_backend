<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->codeDetail) && $param->codeDetail != ''){
	$info = ORM::for_table(TBLCODE)->find_one(array('code'=>$param->code, 'codeDetail'=>$param->codeDetail));
	setDelete($info, $param->deleteUserId);
	$info->save();
}
else
{
	echo "DELETE ERROR";
}

?>