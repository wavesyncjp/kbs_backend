<?php
require '../header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");

$param = json_decode($postparam);

$user = ORM::for_table("tbluser")->where(array(
		'loginId' => $param->id,
		'password' => $param->pwd
	))->where_null('deleteDate')->find_one();


$ret = new stdClass();
if($user != null){
	
	//トークン作成
	$token = ORM::for_table("tbltoken")->find_one($user->userId);
	if($token != null){
		$token->token = getGUID();
		$token->startTime = date("Y-m-d H:i:s");
		$token->save();
	}
	else {
		$token = ORM::for_table("tbltoken")->create();
		$token->userId = $user->userId;
		$token->token = getGUID();
		$token->startTime = date("Y-m-d H:i:s");
		$token->save();
	}
	
	$ret->result = true;
	$ret->userId = $user->userId;
	$ret->loginId = $user->loginId;
	$ret->password = $user->password;
	$ret->userName = $user->userName;
	$ret->token = $token->token;
	$ret->authority = $user->authority;
	echo json_encode($ret);
}
else{
	
	$ret = new stdClass();
	$ret->result = false;
	$ret->msg = '※ログインIDまたはパスワードは不正です。';
	echo json_encode($ret);
}


function getGUID(){
	if (function_exists('com_create_guid')){
		return com_create_guid();
	}else{
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = chr(123)// "{"
		.substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12)
		.chr(125);// "}"
		return $uuid;
	}
}

?>