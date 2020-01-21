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
	$land = ORM::for_table(TBLTEMPLANDINFO)->find_one($param->pid);
	setUpdate($land, $param->updateUserId);
}
//登録
else {
	//000002
	$land = ORM::for_table(TBLTEMPLANDINFO)->create();	
	$maxNo = ORM::for_table(TBLTEMPLANDINFO)->max('bukkenNo');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 6, '0', STR_PAD_LEFT);
	$land->bukkenNo = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $land, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles'));
$land->save();

echo json_encode(getLandInfo($land->pid));


?>