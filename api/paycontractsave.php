<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = null;// 20210728 Add

//更新
if(isset($param->pid) && $param->pid > 0){
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->find_one($param->pid);
	setUpdate($paycontract, $param->updateUserId);
	$userId = $param->updateUserId;// 20210728 Add
}
//登録
else {
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->create();	
	setInsert($paycontract, $param->createUserId);
	$userId = $param->createUserId;// 20210728 Add
}

copyData($param, $paycontract, array('pid', 'details', 'land', 'contractDayMap','contractFixDayMap','taxEffectiveDayMap', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$paycontract->save();

//支払管理詳細
if(isset($param->details)){
	foreach ($param->details as $detail){
		//削除
		if(isset($detail->deleteUserId) && $detail->deleteUserId > 0) {
			$detailSave = ORM::for_table(TBLPAYCONTRACTDETAIL)->find_one($detail->pid);
			$detailSave->delete();			
		}
		else {
			if(isset($detail->pid) && $detail->pid > 0){
				$detailSave = ORM::for_table(TBLPAYCONTRACTDETAIL)->find_one($detail->pid);
				setUpdate($detailSave, $param->updateUserId);			
			}
			else {
				$detailSave = ORM::for_table(TBLPAYCONTRACTDETAIL)->create();
				setInsert($detailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
			}		
			copyData($detail, $detailSave, array('pid', 'deleteUserId', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));		
			$detailSave->payContractPid = $paycontract->pid;
			if($paycontract->tempLandInfoPid > 0){
				$detailSave->tempLandInfoPid = $paycontract->tempLandInfoPid;
			}
			$detailSave->save();
		}
	}
}

// 20210728 S_Add
setContractByPay($paycontract, $userId);
setSaleByPay($paycontract, $userId);
// 20210728 E_Add

echo json_encode(getPayContractInfo($paycontract->pid));

?>