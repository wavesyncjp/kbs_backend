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
	$contract = ORM::for_table(TBLCONTRACTINFO)->find_one($param->pid);
	setUpdate($land, $param->updateUserId);
}
//登録
else {
	//000002
	$contract = ORM::for_table(TBLCONTRACTINFO)->create();	
	$maxNo = ORM::for_table(TBLCONTRACTINFO)->max('contractNumber');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 3, '0', STR_PAD_LEFT);
	$contract->contractNumber = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $contract, array('pid', 'land', 'details', 'depends'));
$contract->save();

//契約詳細
if(isset($param->details)){
	foreach ($param->details as $detail){

		//削除
		if($detail->deleteUserId > 0) {
			$detailSave = ORM::for_table(TBLCONTRACTDETAILINFO)->find_one($detail->pid);
			$detailSave->delete();
		}
		else {
			if($detail->pid > 0){
				$detailSave = ORM::for_table(TBLCONTRACTDETAILINFO)->find_one($detail->pid);
				setUpdate($detailSave, $param->updateUserId);			
			}
			else {
				$detailSave = ORM::for_table(TBLCONTRACTDETAILINFO)->create();
				setInsert($detailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
			}		
			copyData($detail, $detailSave, array('pid'));		
			$detailSave->contractInfoPid = $contract->pid;
			$detailSave->save();
		}		
	}
}

//不可分詳細
if(isset($param->depends)){
	foreach ($param->depends as $depend){

		//削除
		if($depend->deleteUserPid > 0) {
			$dependSave = ORM::for_table(TBLCONTRACTDEPENDINFO)->find_one($depend->pid);
			$dependSave->delete();
		}
		else {
			if($depend->pid > 0){
				$dependSave = ORM::for_table(TBLCONTRACTDEPENDINFO)->find_one($depend->pid);
				setUpdate($dependSave, $param->updateUserId);			
			}
			else {
				$dependSave = ORM::for_table(TBLCONTRACTDEPENDINFO)->create();
				setInsert($dependSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId );			
			}		
			copyData($depend, $dependSave, array('pid'));		
			$dependSave->contractInfoPid = $contract->pid;
			$dependSave->save();
		}		
	}
}

echo json_encode(getContractInfo($contract->pid));

?>