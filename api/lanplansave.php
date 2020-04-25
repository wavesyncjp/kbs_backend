<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//物件情報
$land = $param->land;
$landDB = ORM::for_table(TBLTEMPLANDINFO)->find_one($land->pid);
setUpdate($landDB, $land->updateUserId);

copyData($land, $landDB, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles', 'pickDateMap','startDateMap','finishDateMap','surveyRequestedDayMap','surveyDeliveryDayMap','infoStaffMap','infoOfferMap'));
$landDB->save();


//物件プラン
$plans = $param->plans;
foreach($plans as $plan) {
    //削除
    if(isset($plan->deleteUserId) && $plan->deleteUserId > 0) {
        ORM::for_table(TBLBUKKENPLANINFO)->find_one($plan->pid)->delete();			
    }
    else {
        if(isset($plan->pid) && $plan->pid > 0){
            $planSave = ORM::for_table(TBLBUKKENPLANINFO)->find_one($plan->pid);
            setUpdate($planSave, $land->updateUserId);			
        }
        else {
            $planSave = ORM::for_table(TBLBUKKENPLANINFO)->create();
            setInsert($planSave, isset($land->updateUserId) && $land->updateUserId ? $land->updateUserId : $land->createUserId);			
        }		
        copyData($plan, $planSave, array('pid', 'planRequestDayMap', 'planScheduledDayMap', 'delete'));		
        $planSave->tempLandInfoPid = $land->pid;
        $planSave->save();
    }
}

$data = getLandPlan($land->pid);

echo json_encode($data);

?>