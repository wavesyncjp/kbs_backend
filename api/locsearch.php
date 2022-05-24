<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLLOCATIONINFO)->where_null('deleteDate');

if(isset($param->tempLandInfoPid) && $param->tempLandInfoPid !== ''){
	$query = $query->where('tempLandInfoPid', $param->tempLandInfoPid);
}
if(isset($param->locationType) && $param->locationType !== ''){
	$query = $query->where('locationType', $param->locationType);
}
// 20201221 S_Add
// clct結果
if(isset($param->clctLocationType) && $param->clctLocationType != ''){
	$query = $query->where_in('locationType', $param->clctLocationType);
}
// 20201221 E_Add
// 20220522 S_Add
// clctPID
if(isset($param->clctPid) && $param->clctPid != ''){
	$query = $query->where_in('pid', $param->clctPid);
}
// 20220522 E_Add

// 20220329 S_Update
// $locs = $query->find_array();
$locs = $query->order_by_asc('displayOrder')->order_by_asc('pid')->find_array();
// 20220329 E_Update
echo json_encode($locs);

?>