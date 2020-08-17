<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLTEMPLANDINFO)
			->table_alias('p1')
			->distinct()
            ->select('p1.*')
			->left_outer_join(TBLPLAN, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');
//			->inner_join(TBLPLAN, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');

$query = $query->where_null('p1.deleteDate');

// 物件番号
if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where_like('p1.bukkenNo', $param->bukkenNo);
}
// 物件名
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
// 所在地
if(isset($param->address) && $param->address !== ''){
	$query = $query->where_like('p2.address', '%'.$param->address.'%');
}
// プラン名
if(isset($param->planName) && $param->planName !== ''){
	$query = $query->where_like('p2.planName', '%'.$param->planName.'%');
}
// 作成日
if(isset($param->createDay) && $param->createDay != ''){
	$query = $query->where('p2.createDay', $param->createDay);
}
// clct結果
if(isset($param->clctResult) && $param->clctResult != ''){
	$query = $query->where_in('p1.result', $param->clctResult);
}

$lands = $query->order_by_desc('pid')->find_array();
$ret = array();

foreach($lands as $land){
	$plans = ORM::for_table(TBLPLAN)->where('tempLandInfoPid', $land['pid'])
			->where_null('deleteDate')
			->select('pid')
			->select('tempLandInfoPid')
			->select('address')
			->select('planName')
			->select('createDay')
			->select('createDate')
			->select('updateDate')->find_array();
	$obj = array('tempLandInfoPid' => $land['pid'], 'bukkenNo' => $land['bukkenNo'],'bukkenName' => $land['bukkenName'],'address' => '');
	$obj['plans'] = $plans;
	$ret[] = $obj;
}

echo json_encode($ret);

?>