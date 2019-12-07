<?php
require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLTEMPLANDINFO)
			->table_alias('p1')
			->distinct()
			->select('p1.*');
			//->select("GROUP_CONCAT(address SEPARATOR ', ') as locationAddress");
			//->left_outer_join(TBLLOCATIONINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2')->where_null('p1.deleteDate');

if(isset($param->address) && $param->address != ''){
	$query = $query->inner_join(TBLLOCATIONINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');
}						
$query = $query->where_null('p1.deleteDate');

if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where('p1.bukkenNo', $param->bukkenNo);
}
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
if(isset($param->department) && sizeof($param->department) > 0){
	$query = $query->where_in('p1.department', $param->department);
}
if(isset($param->result) && sizeof($param->result) > 0){
	$query = $query->where_in('p1.result', $param->result);
}
if(isset($param->address) && $param->address != ''){
	$query = $query->where_like('p2.address', '%'.$param->address.'%');
}

if(isset($param->pickDate) && $param->pickDate != ''){
	$query = $query->where_raw(" TIMESTAMPDIFF(day, p1.pickDate, '" . $param->pickDate . "') <= 0");
}


$lands = $query->find_array();
$ret = array();
foreach($lands as $land){
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('address')->where_not_equal('address', '')->where_null('deleteDate')->select('address')->find_array();
	if(isset($address) && sizeof($address) > 0){
		$arrs = array();
		foreach($address as $arr){
			$arrs[] = $arr['address'];
		}
		$land['remark1'] = implode(",", $arrs);		
	}
	else {
		$land['remark1'] = '';
	}
	
	$ret[] = $land;
}
echo json_encode($ret);


?>