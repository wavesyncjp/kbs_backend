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

	//20200928 S_Add
	//ファイル添付
	$attachFiles = $param->attachFiles;
	foreach($attachFiles as $attachFile){
		$attach = ORM::for_table(TBLFILEATTACH)->find_one($attachFile->pid);
		setUpdate($attach, $param->updateUserId);
		$attach->attachFileRemark = $attachFile->attachFileRemark;
		$attach->save();
	}
	//20200928 E_Add
}
//登録
else {
	//000002
	$land = ORM::for_table(TBLTEMPLANDINFO)->create();	
	$maxNo = ORM::for_table(TBLTEMPLANDINFO)->where_not_equal('createUserId', '9999')->max('bukkenNo');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 6, '0', STR_PAD_LEFT);
	$land->bukkenNo = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $land, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$land->save();

echo json_encode(getLandInfo($land->pid));


?>