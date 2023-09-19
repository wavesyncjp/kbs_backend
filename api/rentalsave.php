<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$rentalReceivesChanged = $param->rentalReceivesChanged;

$userId = null;

ORM::get_db()->beginTransaction();

// 更新
if (isset($param->pid) && $param->pid > 0) {
	$rental = ORM::for_table(TBLRENTALINFO)->find_one($param->pid);
	setUpdate($rental, $param->updateUserId);
	$userId = $param->updateUserId;
	//所在地情報PID変更チェック
	if($rental->locationInfoPid != $param->locationInfoPid){
		$isChangedLocPid = true;
		$locationInfoPid = $rental->locationInfoPid;
		//賃貸契約
		$rentalContracts = ORM::for_table(TBLRENTALCONTRACT)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
		//賃貸入金
		$rentalReceives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
	}
}
// 登録
else {
	$rental = ORM::for_table(TBLRENTALINFO)->create();	
	setInsert($rental, $param->createUserId);
	$userId = $param->createUserId;
}

copyData($param, $rental, array('pid', 'rentalContracts', 'rentalReceives', 'rentalReceivesChanged', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$rental->save();

// 所在地情報PIDを変更の場合、賃貸契約の入居者情報PID、所在地情報PIDをクリア
if ($isChangedLocPid) {
	//賃貸契約
	if ($rentalContracts != null) {
		foreach ($rentalContracts as $con) {
			$con->locationInfoPid = null;
			$con->residentInfoPid = null;
			setUpdate($con, $userId);
			$con->save();
		}
	}

	// 賃貸入金
	if ($rentalReceives != null) {
		foreach ($rentalReceives as $rev) {
			setDelete($rev, $userId);
			$rev->save();
		}
	}
}

// 賃貸入金を更新
if (isset($rentalReceivesChanged)) {
	foreach ($rentalReceivesChanged as $rev) {
		$revDB = ORM::for_table(TBLRENTALRECEIVE)->find_one($rev->pid);
		if($isChangedLocPid && $revDB.locationInfoPid == $locationInfoPid){
			continue;
		}
		else {
			setUpdate($revDB, $userId);
			$revDB->receiveFlg = $rev->receiveFlg;
			$revDB->save();
		}
	}
}

ORM::get_db()->commit();

echo json_encode(getRental($rental->pid));
?>
