<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

ORM::get_db()->beginTransaction();
//更新
if(isset($param->pid) && $param->pid > 0){
	$loc = ORM::for_table(TBLLOCATIONINFO)->find_one($param->pid);
	setUpdate($loc, $param->updateUserId);
}
//登録
else {
	//000002
	$loc = ORM::for_table(TBLLOCATIONINFO)->create();	
	setInsert($loc, $param->createUserId);
}
// 20220614 S_Update
//copyData($param, $loc, array('pid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
copyData($param, $loc, array('pid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate', 'attachFiles', 'bottomLands', 'delBottomLands', 'contractDetail02', 'residents', 'delResidents'));
// 20220614 E_Update
$loc->save();

//所有者
if(isset($param->sharers)) {

    //所有者ループ
    $sharerPos = 1;
    foreach($param->sharers as $sharer){
        if(isset($sharer->pid) && $sharer->pid > 0) {
            $sharerSave = ORM::for_table(TBLSHARERINFO)->find_one($sharer->pid);
            setUpdate($sharerSave, $param->updateUserId);
        }
        else {
            $sharerSave = ORM::for_table(TBLSHARERINFO)->create();
            setInsert($sharerSave, $param->createUserId > 0 ? $param->createUserId : $param->updateUserId );
        }
        copyData($sharer, $sharerSave, array());	
        $sharerSave->registPosition = $sharerPos;
        $sharerSave->tempLandInfoPid = $loc->tempLandInfoPid;
        $sharerSave->locationInfoPid = $loc->pid;
        $sharerSave->save();
        $sharerPos++;
    }
}
// 削除
if(isset($param->delSharers)) {

    //20201007：tblContractRegistrant削除
    ORM::for_table(TBLCONTRACTREGISTRANT)->where_in('sharerInfoPid', $param->delSharers)->delete_many();

    ORM::for_table(TBLSHARERINFO)->where_in('pid', $param->delSharers)->delete_many();
}
// 20210614 S_Add
// 底地
if(isset($param->bottomLands)) {

    // 底地ループ
    $bottomLandPos = 1;
    foreach($param->bottomLands as $bottomLand){
        if(isset($bottomLand->pid) && $bottomLand->pid > 0) {
            $bottomLandSave = ORM::for_table(TBLBOTTOMLANDINFO)->find_one($bottomLand->pid);
            setUpdate($bottomLandSave, $param->updateUserId);
        }
        else {
            $bottomLandSave = ORM::for_table(TBLBOTTOMLANDINFO)->create();
            setInsert($bottomLandSave, $param->createUserId > 0 ? $param->createUserId : $param->updateUserId );
        }
        copyData($bottomLand, $bottomLandSave, array());	
        $bottomLandSave->registPosition = $bottomLandPos;
        $bottomLandSave->tempLandInfoPid = $loc->tempLandInfoPid;
        $bottomLandSave->locationInfoPid = $loc->pid;
        $bottomLandSave->save();
        $bottomLandPos++;
    }
}
// 削除
if(isset($param->delBottomLands)) {
    ORM::for_table(TBLBOTTOMLANDINFO)->where_in('pid', $param->delBottomLands)->delete_many();
}
// 20210614 E_Add
// 20220614 S_Add
// 入居者情報
if(isset($param->residents)) {

    // 入居者情報ループ
    $residentPos = 1;
    foreach($param->residents as $resident){
        $isChangeRentPrice = false;// 20231010 Add

        if(isset($resident->pid) && $resident->pid > 0) {
            $residentSave = ORM::for_table(TBLRESIDENTINFO)->find_one($resident->pid);
            setUpdate($residentSave, $param->updateUserId);

            // 20231010 S_Add
            $isChangeRentPrice = $residentSave->rentPrice != $param->rentPrice;
            // 20231010 E_Add
        }
        else {
            $residentSave = ORM::for_table(TBLRESIDENTINFO)->create();
            setInsert($residentSave, $param->createUserId > 0 ? $param->createUserId : $param->updateUserId );
        }
        copyData($resident, $residentSave, array());	
        $residentSave->registPosition = $residentPos;
        $residentSave->tempLandInfoPid = $loc->tempLandInfoPid;
        $residentSave->locationInfoPid = $loc->pid;
        $residentSave->save();
        $residentPos++;
        
        // 20231010 S_Add
        if($isChangeRentPrice){
            ORM::raw_execute("update " . TBLRENTALRECEIVE . " set updateUserId = " . $param->updateUserId . ",updateDate = now()" . ",receivePrice = " . $residentSave->rentPrice . " where receiveFlg = '0' and deleteDate is null and rentalContractPid in (select pid from " . TBLRENTALCONTRACT." where deleteDate is null and residentInfoPid = ". $residentSave->pid .")");
        }
        // 20231010 E_Add
    }
}
// 削除
if(isset($param->delResidents)) {
    ORM::for_table(TBLRESIDENTINFO)->where_in('pid', $param->delResidents)->delete_many();
}
// 20220614 E_Add
ORM::get_db()->commit();

$locationPid = $loc->pid;
$loc = ORM::for_table(TBLLOCATIONINFO)->findOne($locationPid)->asArray();
$sharers = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $locationPid)->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
$loc['sharers'] = $sharers;
// 20210311 S_Add
$attachFiles = ORM::for_table(TBLLOCATIONATTACH)->where('locationInfoPid', $locationPid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
$loc['attachFiles'] = $attachFiles;
// 20210311 E_Add
// 20210614 S_Add
$bottomLands = ORM::for_table(TBLBOTTOMLANDINFO)->where('locationInfoPid', $locationPid)->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
$loc['bottomLands'] = $bottomLands;
// 20210614 E_Add
// 20220614 S_Add
$residents = ORM::for_table(TBLRESIDENTINFO)->where('locationInfoPid', $locationPid)->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
$loc['residents'] = $residents;
// 20220614 E_Add
echo json_encode($loc);

?>