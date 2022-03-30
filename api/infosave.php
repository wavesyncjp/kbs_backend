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
if($param->pid > 0){
	$info = ORM::for_table(TBLINFORMATION)->find_one($param->pid);
	setUpdate($info, $param->updateUserId);
}
//登録
else {
	$info = ORM::for_table(TBLINFORMATION)->create();
	setInsert($info, $param->createUserId);
}
copyData($param, $info, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'attachFiles'));
$info->save();

$ret = ORM::for_table(TBLINFORMATION)->findOne($info->pid)->asArray();
echo json_encode($ret);

?>