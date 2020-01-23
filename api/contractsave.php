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
	$contract = ORM::for_table(TBLCONTRACTINFO)->find_one($param->pid);
	setUpdate($contract, $param->updateUserId);
}
//登録
else {
	//000002
	$contract = ORM::for_table(TBLCONTRACTINFO)->create();	
	$maxNo = ORM::for_table(TBLCONTRACTINFO)->max('contractNumber');
	if(!isset($maxNo)) {
		$maxNo = '001';
	}
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 3, '0', STR_PAD_LEFT);
	$contract->contractNumber = $nextNo;
	setInsert($contract, $param->createUserId);
}


copyData($param, $contract, array('pid', 'contractNumber', 'land', 'details', 'sellers', 'contractFiles'));
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

//契約者
if(isset($param->sellers)){
	foreach ($param->sellers as $seller){		
		//削除
		if($seller->deleteUserId > 0) {
			ORM::for_table(TBLCONTRACTSELLERINFO)->find_one($seller->pid)->delete();			
		}
		else {
			if($seller->pid > 0){
				$sellerSave = ORM::for_table(TBLCONTRACTSELLERINFO)->find_one($seller->pid);
				setUpdate($sellerSave, $param->updateUserId);			
			}
			else {
				$sellerSave = ORM::for_table(TBLCONTRACTSELLERINFO)->create();
				setInsert($sellerSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
			}		
			copyData($seller, $sellerSave, array('pid'));		
			$sellerSave->contractInfoPid = $contract->pid;
			$sellerSave->save();
		}		
	}
}

echo json_encode(getContractInfo($contract->pid));

?>