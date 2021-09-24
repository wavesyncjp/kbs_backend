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

//更新
if(isset($param->pid) && $param->pid > 0){
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->find_one($param->pid);
	setUpdate($receivecontract, $param->updateUserId);
	$userId = $param->updateUserId;
}
//登録
else {
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->create();
	setInsert($receivecontract, $param->createUserId);
	$userId = $param->createUserId;
}

copyData($param, $receivecontract, array('pid', 'details', 'land', 'contractDayMap','contractFixDayMap','taxEffectiveDayMap', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$receivecontract->save();

//入金管理詳細
if(isset($param->details)){
	foreach ($param->details as $detail){
		//削除
		if(isset($detail->deleteUserId) && $detail->deleteUserId > 0) {
			$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->find_one($detail->pid);
			$detailSave->delete();
		}
		else {
			if(isset($detail->pid) && $detail->pid > 0){
				$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->find_one($detail->pid);
				setUpdate($detailSave, $param->updateUserId);
			}
			else {
				$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->create();
				setInsert($detailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);
			}
			copyData($detail, $detailSave, array('pid', 'deleteUserId', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
			$detailSave->receiveContractPid = $receivecontract->pid;
			if($receivecontract->tempLandInfoPid > 0){
				$detailSave->tempLandInfoPid = $receivecontract->tempLandInfoPid;
			}
			$detailSave->save();
		}
	}
}

//setContractByReceive($receivecontract, $userId);
//setSaleByReceive($receivecontract, $userId);

echo json_encode(getReceiveContractInfo($receivecontract->pid));

?>