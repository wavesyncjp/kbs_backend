<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPLAN)->where_null('deleteDate');

// 土地情報PID
if(isset($param->tempLandInfoPid) && $param->tempLandInfoPid !== ''){
	$query = $query->where('tempLandInfoPid', $param->tempLandInfoPid);
}
// 除外PID
if(isset($param->notPlanPid) && $param->notPlanPid !== ''){
	$query = $query->where_not_equal('pid', $param->notPlanPid);
}

$plans = $query->order_by_desc('pid')->find_array();
$ret = array();

foreach($plans as $plan){
	$details = ORM::for_table(TBLPLANDETAIL)->where('planPid', $plan['pid'])
			->where_null('deleteDate')->order_by_asc('backNumber')->find_array();
	$plan['details'] = $details;
	$ret[] = $plan;
}

echo json_encode($ret);

?>