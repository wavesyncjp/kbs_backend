<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$count_retry = 0;// 202321110 Add

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
	// 202321110 S_Add
	RETRY_HERE:
	$count_retry++;
	// 202321110 E_Add
	//000002
	$land = ORM::for_table(TBLTEMPLANDINFO)->create();	
	$maxNo = ORM::for_table(TBLTEMPLANDINFO)->where_not_equal('createUserId', '9999')->max('bukkenNo');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 6, '0', STR_PAD_LEFT);
	$land->bukkenNo = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $land, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
// 202321110 S_Update
// $land->save();
if($param->pid > 0){
	$land->save();
}
else{
	try {
		$land->save();
	} catch (Exception $e) {
		if($count_retry < 3){
			sleep(1); 
			goto RETRY_HERE;
		}
		else{
			exitByDuplicate();
		}
	}
}
// 202321110 E_Update

echo json_encode(getLandInfo($land->pid));


?>