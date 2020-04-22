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
if(isset($param->updateUserId) && $param->updateUserId > 0){
	$plan = ORM::for_table(TBLPLAN)->find_one($param->pid);
	setUpdate($plan, $param->updateUserId);
}

//登録
else {
	$plan = ORM::for_table(TBLPLAN)->create();	
	setInsert($plan, $param->createUserId);
}
/*画面に入力項目があって（.ts）planのカラムにないものを('')で除外。
'updateUserId', 'updateDate', 'createUserId', 'createDate'は上でセットしているので*/
copyData($param, $plan, array('pid','cratedDayMap','startDayMap','upperWingDayMap','completionDayMap',
'scheduledDayMap','details','rent','rentdetails','updateUserId', 'updateDate', 'createUserId', 'createDate'));
$plan->save();

//事業収支詳細
if(isset($param->details)){
	foreach ($param->details as $detail){
		//削除
		if(isset($detail->deleteUserId) && $detail->deleteUserId > 0) {
			$detailSave = ORM::for_table(TBLPLANDETAIL)->find_one($detail->pid);
			$detailSave->delete();			
		}
		else {
			if(isset($detail->pid) && $detail->pid > 0){
				$detailSave = ORM::for_table(TBLPLANDETAIL)->find_one($detail->pid);
				setUpdate($detailSave, $param->updateUserId);			
			}
			else {
				$detailSave = ORM::for_table(TBLPLANDETAIL)->create();
				setInsert($detailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
			}		
			copyData($detail, $detailSave, array('pid','updateUserId', 'updateDate', 'createUserId', 'createDate','deleteUserId'));		
			$detailSave->planPid = $plan->pid;
			if($plan->tempLandInfoPid > 0){
				$detailSave->tempLandInfoPid = $plan->tempLandInfoPid;
			}
			$detailSave->save();
		}
	}
}

//rent
if(isset($param->rent)) {
	$rent = $param->rent;
	if(isset($rent->pid) && $rent->pid > 0){
		$rentSave = ORM::for_table(TBLPLANRENTROLL)->find_one($rent->pid);
		setUpdate($rentSave, $param->updateUserId);			
	}
	else {
		$rentSave = ORM::for_table(TBLPLANRENTROLL)->create();
		setInsert($rentSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
	}		
	copyData($rent, $rentSave, array('pid','updateUserId', 'updateDate', 'createUserId', 'createDate','deleteUserId'));		
	$rentSave->planPid = $plan->pid;
	if($plan->tempLandInfoPid > 0){
		$rentSave->tempLandInfoPid = $plan->tempLandInfoPid;
	}
	$rentSave->save();
}

//rentdetail
if(isset($param->rentdetails)){
	foreach ($param->rentdetails as $rentdetail){
		//削除
		
		if(isset($rentdetail->pid) && $rentdetail->pid > 0){
			$rentdetailSave = ORM::for_table(TBLPLANRENTROLLDETAIL)->find_one($rentdetail->pid);
			setUpdate($rentdetailSave, $param->updateUserId);			
		}
		else {
			$rentdetailSave = ORM::for_table(TBLPLANRENTROLLDETAIL)->create();
			setInsert($rentdetailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);			
		}		
		copyData($rentdetail, $rentdetailSave, array('pid','updateUserId', 'updateDate', 'createUserId', 'createDate','deleteUserId'));		
		$rentdetailSave->planPid = $plan->pid;
		if($plan->tempLandInfoPid > 0){
			$rentdetailSave->tempLandInfoPid = $plan->tempLandInfoPid;
		}
		$rentdetailSave->save();
	}
}


$plan = getPlanInfo($plan->pid);
echo json_encode($param );

?>