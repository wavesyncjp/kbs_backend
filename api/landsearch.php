<?php
require '../header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

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

// 物件番号
if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where('p1.bukkenNo', $param->bukkenNo);
}
// 契約物件番号
if(isset($param->contractBukkenNo) && $param->contractBukkenNo !== ''){
	$query = $query->where('p1.contractBukkenNo', $param->contractBukkenNo);
}
// 物件名
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
// 物件担当部署
if(isset($param->department) && sizeof($param->department) > 0){
	$query = $query->where_in('p1.department', $param->department);
}
// 結果
if(isset($param->result) && sizeof($param->result) > 0){
	$query = $query->where_in('p1.result', $param->result);
}
// 居住表示
if(isset($param->residence) && $param->residence !== ''){
	$query = $query->where_like('p1.residence', '%'.$param->residence.'%');
}
// 所在地
if(isset($param->address) && $param->address != ''){
	$query = $query->where_like('p2.address', '%'.$param->address.'%');
}
//情報収集日(pickDate)
if(isset($param->pickDateSearch_From) && $param->pickDateSearch_From != ''){
	$query = $query->where_gte('p1.pickDate', $param->pickDateSearch_From);
}
if(isset($param->pickDateSearch_To) && $param->pickDateSearch_To != ''){
	$query = $query->where_lte('p1.pickDate', $param->pickDateSearch_To);
}
//測量依頼日(surveyRequestedDay)
if(isset($param->surveyRequestedDaySearch_From) && $param->surveyRequestedDaySearch_From != ''){
	$query = $query->where_gte('p1.surveyRequestedDay', $param->surveyRequestedDaySearch_From);
}
if(isset($param->surveyRequestedDaySearch_To) && $param->surveyRequestedDaySearch_To != ''){
	$query = $query->where_lte('p1.surveyRequestedDay', $param->surveyRequestedDaySearch_To);
}

$lands = $query->order_by_desc('pid')->find_array();
$ret = array();
foreach($lands as $land){

	// 所在地
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

	//地番
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('blockNumber')->where_not_equal('blockNumber', '')->where_null('deleteDate')->select('blockNumber')->find_array();
	if(isset($address) && sizeof($address) > 0){
		$arrs = array();
		foreach($address as $arr){
			$arrs[] = $arr['blockNumber'];
		}
		$land['remark2'] = implode(",", $arrs);		
	}
	else {
		$land['remark2'] = '';
	}

	//地図添付
	$mapFiles = ORM::for_table(TBLMAPATTACH)->where('tempLandInfoPid', $land['pid'])->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($mapFiles)){
		$land['mapFiles'] = $mapFiles;
	}
	
	$ret[] = $land;
}
echo json_encode($ret);


?>