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
	$land = ORM::for_table(TBLTEMPLANDINFO)->find_one($param->pid);
	setUpdate($land, $param->updateUserId);
}
//登録
else {
	//000002
	$land = ORM::for_table(TBLTEMPLANDINFO)->create();	
	$maxNo = ORM::for_table(TBLTEMPLANDINFO)->max('bukkenNo');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 6, '0', STR_PAD_LEFT);
	$land->bukkenNo = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $land, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles'));
$land->save();

//所有地
if(isset($param->locations)){
	foreach ($param->locations as $loc){
		if($loc->pid > 0){
			$locSave = ORM::for_table(TBLLOCATIONINFO)->find_one($loc->pid);
			setUpdate($locSave, $param->updateUserId);			
		}
		else {
			$locSave = ORM::for_table(TBLLOCATIONINFO)->create();
			setInsert($locSave, $param->createUserId);			
		}		
		copyData($loc, $locSave, array('pid', 'isContract', 'isDepend', 'contractData', 'sharers', 'delSharers'));		
		$locSave->tempLandInfoPid = $land->pid;
		$locSave->save();	
		
		//所有者
		if(isset($loc->sharers)) {
			//所有者ループ
			$sharerPos = 1;
			foreach($loc->sharers as $sharer){
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
				$sharerSave->tempLandInfoPid = $land->pid;
				$sharerSave->locationInfoPid = $loc->pid;
				$sharerSave->save();
				$sharerPos++;
			}
		}
		// 削除
		if(isset($loc->delSharers)) {
			ORM::for_table(TBLSHARERINFO)->where_in('pid', $loc->delSharers)->delete_many();
		}
	}
}

echo json_encode(getLandInfo($land->pid));


?>