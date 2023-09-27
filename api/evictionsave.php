<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$userId = null;

// 更新
if (isset($param->pid) && $param->pid > 0) {
	$evi = ORM::for_table(TBLEVICTIONINFO)->find_one($param->pid);
	setUpdate($evi, $param->updateUserId);
	$userId = $param->updateUserId;
}
// 登録
else {
	$evi = ORM::for_table(TBLEVICTIONINFO)->create();
	setInsert($evi, $param->createUserId);
	$userId = $param->createUserId;
}

copyData($param, $evi, array('pid', 'roomNo', 'borrowerName', 'apartmentName', 'evictionFiles', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$evi->save();

echo json_encode(getEvictionInfos($evi->contractInfoPid,$evi->pid));
?>
