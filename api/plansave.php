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
if($param->updateUserId > 0){
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
copyData($param, $plan, array('cratedDayMap','startDayMap','upperWingDayMap','completionDayMap',
'scheduledDayMap','details','updateUserId', 'updateDate', 'createUserId', 'createDate'));
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
			copyData($detail, $detailSave, array('updateUserId', 'updateDate', 'createUserId', 'createDate'));		
			$detailSave->planPid = $plan->pid;
			if($plan->tempLandInfoPid > 0){
				$detailSave->tempLandInfoPid = $plan->tempLandInfoPid;
			}
			$detailSave->save();
		}
	}
}*/

echo json_encode(getPlanInfo($plan->pid));

?>