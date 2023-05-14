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

		// 20230511 S_Add
		if(isset($sale->salesAttaches)){
			foreach ($sale->salesAttaches as $salesAttach){
				if(isset($salesAttach->pid) && $salesAttach->pid > 0){
					$salesAttachSave = ORM::for_table(TBLBUKKENSALESATTACH)->find_one($salesAttach->pid);
					$action = -1;
					if($salesAttachSave->attachFileChk != $salesAttach->attachFileChk){
						$salesAttachSave->attachFileChk = $salesAttach->attachFileChk;
						$action = 1;
					}
					if($salesAttachSave->attachFileDay != $salesAttach->attachFileDay){
						$salesAttachSave->attachFileDay = $salesAttach->attachFileDay;
						$action = 1;
					}
					if($salesAttachSave->attachFileDisplayName != $salesAttach->attachFileDisplayName){
						$salesAttachSave->attachFileDisplayName = $salesAttach->attachFileDisplayName;
						$action = 1;
					}
					//更新
					if($action == 1){
						setUpdate($salesAttachSave, $userId);
						$salesAttachSave->save();
					}
				}
			}
		}
		// 20230511 E_Add
	}
	else {
		$saleSave = ORM::for_table(TBLBUKKENSALESINFO)->create();
		setInsert($saleSave, isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId);
		$userId = isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId;// 20210728 Add
	}
	// 20230227 S_Update
	// copyData($sale, $saleSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'salesLocationStr', 'sharingStartDayYYYY', 'sharingStartDayMMDD'));
	copyData($sale, $saleSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'salesLocationStr', 'sharingStartDayYYYY', 'sharingStartDayMMDD', 'csvSelected', 'salesAttaches'));
	// 20230227 E_Update
	$saleSave->save();

	setPayBySale($saleSave, $userId);// 20210728 Add

}

$ret = ORM::for_table(TBLBUKKENSALESINFO)->find_one($saleSave->pid)->asArray();
// 20230511 S_Add
// 物件売契約添付ファイル
$salesAttaches = ORM::for_table(TBLBUKKENSALESATTACH)->where('bukkenSalesInfoPid', $saleSave->pid)->where('attachFileType', '0')->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
if(isset($salesAttaches)){
	$ret['salesAttaches'] = $salesAttaches;
}
else
{
	$ret['salesAttaches'] = [];
}
// 20230511 E_Add
echo json_encode($ret);

?>