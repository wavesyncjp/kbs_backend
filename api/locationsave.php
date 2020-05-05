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

copyData($param, $loc, array('pid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
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
    ORM::for_table(TBLSHARERINFO)->where_in('pid', $param->delSharers)->delete_many();
}
ORM::get_db()->commit();

$locationPid = $loc->pid;
$loc = ORM::for_table(TBLLOCATIONINFO)->findOne($locationPid)->asArray();
$sharers = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $locationPid)->where_null('deleteDate')->order_by_asc('registPosition')->findArray();			
$loc['sharers'] = $sharers;
echo json_encode($loc);

?>