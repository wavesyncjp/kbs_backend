<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//更新
if($param->updateUserId > 0){
	$info = ORM::for_table(TBLUSER)->find_one($param->userId);
	setUpdate($info, $param->updateUserId);
}
//登録
else {
	$info = ORM::for_table(TBLUSER)->create();
	setInsert($info, $param->createUserId);
}
copyData($param, $info, array('updateUserId', 'updateDate', 'createUserId', 'createDate'));
$info->save();

$ret = ORM::for_table(TBLUSER)->findOne($info->userId)->asArray();
echo json_encode($ret);

?>