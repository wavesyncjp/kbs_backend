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
if(isset($param->pid) && $param->pid > 0){
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->find_one($param->pid);
	setUpdate($paycontract, $param->updateUserId);
}
//登録
else {
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->create();	
	setInsert($paycontract, $param->createUserId);
}

copyData($param, $paycontract, array('pid', 'details', 'contractDayMap','contractFixDayMap','taxEffectiveDayMap'));
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
			copyData($detail, $detailSave, array('pid', 'deleteUserId'));		
			$detailSave->payContractPid = $paycontract->pid;
			$detailSave->save();
		}
	}
}

echo json_encode(getPayContractInfo($paycontract->pid));

?>