<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLTEMPLANDINFO)
			->table_alias('p1')
			->distinct()
            ->select('p1.*')
            ->left_outer_join(TBLPLAN, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');

$query = $query->where_null('p1.deleteDate');

if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where_like('p1.bukkenNo', $param->bukkenNo.'%');
}
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
if(isset($param->address) && $param->address !== ''){
	$query = $query->where_like('p1.address', '%'.$param->address.'%');
}
if(isset($param->planName) && $param->planName !== ''){
	$query = $query->where_like('p2.planName', '%'.$param->planName.'%');
}

$lands = $query->find_array();
$ret = array();
foreach($lands as $land){
	$plans = ORM::for_table(TBLPLAN)->where('tempLandInfoPid', $land['pid'])
			->where_null('deleteDate')
			->select('pid')
			->select('tempLandInfoPid')
			->select('planName')
			->select('createDate')
			->select('updateDate')->find_array();
	$obj = array('tempLandInfoPid' => $land['pid'], 'bukkenNo' => $land['bukkenNo'],'bukkenName' => $land['bukkenName'],'address' => '');
	$obj['plans'] = $plans;
	$ret[] = $obj;
}

echo json_encode($ret);

?>