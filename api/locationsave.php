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
	$loc = ORM::for_table(TBLLOCATIONINFO)->find_one($param->pid);
	setUpdate($loc, $param->updateUserId);
}
//登録
else {
	//000002
	$loc = ORM::for_table(TBLLOCATIONINFO)->create();	
	setInsert($loc, $param->createUserId);
}

copyData($param, $loc, array('pid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers'));
$loc->save();

//所有者
if(isset($param->sharers)) {

    //所有者ループ
    $sharerPos = 1;
    foreach($param->sharers as $sharer){
        if($sharer->pid > 0) {
            $sharerSave = ORM::for_table(TBLSHARERINFO)->find_one($sharer->pid);
            setUpdate($sharerSave, $param->updateUserId);
        }
        else {
            $sharerSave = ORM::for_table(TBLSHARERINFO)->create();
            setInsert($sharerSave, $param->createUserId > 0 ? $param->createUserId : $param->updateUserId );
        }
        copyData($sharer, $sharerSave, null);	
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

//
$loc = ORM::for_table(TBLLOCATIONINFO)->findOne($param->pid)->asArray();
$sharers = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();			
$loc['sharers'] = $sharers;
echo json_encode($loc);

?>