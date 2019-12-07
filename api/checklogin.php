<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
if(!isset($param->userId) || $param->userId ==''){
	echo '0';
	exit;
}
$token = ORM::for_table("tbltoken")->where(array(
		'userId' => $param->userId,
		'token' => $param->token
))->find_one();

if($token != null){
	$token->startTime = date("Y-m-d H:i:s");
	$token->save();
	echo '1';
}
else {
	echo '0';
}
?>