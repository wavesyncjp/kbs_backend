<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$sale = json_decode($postparam);

if(isset($sale->deleteUserId) && $sale->deleteUserId > 0) {
	// 20220523 S_Update
	// ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid)->delete();
	$saleSave = ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid);
	setDelete($saleSave, $sale->deleteUserId);
	$saleSave->save();
	// 20220523 E_Update
}
else {
	$userId = null;// 20210728 Add
	if(isset($sale->pid) && $sale->pid > 0){
		$saleSave = ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid);
		setUpdate($saleSave, $sale->updateUserId);
		$userId = $sale->updateUserId;// 20210728 Add
	}
	else {
		$saleSave = ORM::for_table(TBLBUKKENSALESINFO)->create();
		setInsert($saleSave, isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId);
		$userId = isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId;// 20210728 Add
	}
	// 20221116 S_Update
	// copyData($sale, $saleSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'salesLocationStr', 'sharingStartDayYYYY', 'sharingStartDayMMDD'));
	copyData($sale, $saleSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'salesLocationStr', 'sharingStartDayYYYY', 'sharingStartDayMMDD', 'csvSelected'));
	// 20221116 E_Update
	$saleSave->save();

	setPayBySale($saleSave, $userId);// 20210728 Add
}

$ret = ORM::for_table(TBLBUKKENSALESINFO)->find_one($saleSave->pid)->asArray();
echo json_encode($ret);

?>