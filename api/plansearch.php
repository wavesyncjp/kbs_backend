<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPLAN)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.bukkenNo', 'bukkenNo')
			->select('p2.bukkenName', 'bukkenName');

$query = $query->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p2.pid'), 'p2');

$query = $query->where_null('p1.deleteDate');

if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where_like('p2.bukkenNo', $param->bukkenNo.'%');
}
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p2.bukkenName', '%'.$param->bukkenName.'%');
}
if(isset($param->address) && $param->address !== ''){
	$query = $query->where_like('p2.address', '%'.$param->address.'%');
}
if(isset($param->planName) && $param->planName !== ''){
	$query = $query->where_like('p1.planName', '%'.$param->planName.'%');
}

$ret = $query->find_array();
echo json_encode($ret);

?>