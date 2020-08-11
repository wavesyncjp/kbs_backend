<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPLANHISTORY)->where_null('deleteDate');

// 土地情報PID
if(isset($param->planPid) && $param->planPid !== ''){
	$query = $query->where('planPid', $param->planPid);
}

$planhistorys = $query->order_by_desc('pid')->find_array();
$ret = array();

foreach($planHistorys as $planHistory){
	$details = ORM::for_table(TBLPLANDETAILHISTORY)->where('planHistoryPid', $planHistory['pid'])
			->where_null('deleteDate')->select('price')->order_by_asc('backNumber')->find_array();
	$planHistory['details'] = $details;
	$ret[] = $planHistory;
}

echo json_encode($ret);

?>