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

$locs = $query->find_array();
echo json_encode($locs);

?>