<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLTEMPLANDINFO)
			->table_alias('p1')
			->distinct()
			->select('p1.*')
			->inner_join(TBLCONTRACTINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2')
			->where_null('p1.deleteDate')
			->where_null('p2.deleteDate');// 20210909 Add

// 物件番号
if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where('p1.bukkenNo', $param->bukkenNo);
}
// 契約物件番号
if(isset($param->contractBukkenNo) && $param->contractBukkenNo !== ''){
	$query = $query->where('p1.contractBukkenNo', $param->contractBukkenNo);
}
// 20220912 S_Add
// 契約物件番号_Like
if(isset($param->contractBukkenNo_Like) && $param->contractBukkenNo_Like !== ''){
	$query = $query->where_like('p1.contractBukkenNo', $param->contractBukkenNo_Like . '%');
}
// 20220912 E_Add
// 物件名
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
// 契約番号
if(isset($param->contractNumber) && $param->contractNumber !== ''){
	$raw = "(concat(p1.bukkenNo, '-', p2.contractNumber) LIKE  '" . $param->contractNumber . "%')";
	$query = $query->where_raw($raw);
}
// 明渡期日
if(isset($param->vacationDay) && $param->vacationDay != ''){
	$query = $query->where('p2.vacationDay', $param->vacationDay);
}
// 契約日
if(isset($param->contractDay) && $param->contractDay != ''){
	$query = $query->where('p2.contractDay', $param->contractDay);
}
// 契約日（開始）
if(isset($param->contractDay_From) && $param->contractDay_From != ''){
	$query = $query->where_gte('p2.contractDay', $param->contractDay_From);
}
// 契約日（終了）
if(isset($param->contractDay_To) && $param->contractDay_To != ''){
	$query = $query->where_lte('p2.contractDay', $param->contractDay_To);
}
// 20201222 S_Add
// 決済日
if(isset($param->decisionDay) && $param->decisionDay != ''){
	$query = $query->where('p2.decisionDay', $param->decisionDay);
}
// 決済日（開始）
if(isset($param->decisionDay_From) && $param->decisionDay_From != ''){
	$query = $query->where_gte('p2.decisionDay', $param->decisionDay_From);
}
// 決済日（終了）
if(isset($param->decisionDay_To) && $param->decisionDay_To != ''){
	$query = $query->where_lte('p2.decisionDay', $param->decisionDay_To);
}
// 20201222 E_Add
// 20220519 S_Add
// 物件担当部署
if(isset($param->department) && sizeof($param->department) > 0){
	$query = $query->where_in('p1.department', $param->department);
}
// 20220519 E_Add
// 20221122 S_Add
// 契約状況
if(isset($param->contractNow) && $param->contractNow != ''){
	$query = $query->where('p2.contractNow', $param->contractNow);
}
// 解約日（開始）
if(isset($param->canncellDay_From) && $param->canncellDay_From != ''){
	$query = $query->where_gte('p2.canncellDay', $param->canncellDay_From);
}
// 解約日（終了）
if(isset($param->canncellDay_To) && $param->canncellDay_To != ''){
	$query = $query->where_lte('p2.canncellDay', $param->canncellDay_To);
}
// 20221122 E_Add

$query = getQueryExpertTempland($param, $query, 'p1.pid');// 20250502 Add

$lands = $query->order_by_desc('pid')->find_array();
$ret = array();
foreach($lands as $land){

	// 所在地・地番
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('address')->where_not_equal('address', '')->where_null('deleteDate')->select('address')->select('blockNumber')->findOne();
	if(isset($address) ){
		$land['remark1'] = $address['address'];
		$land['remark2'] = $address['blockNumber'];
	}
	else {
		$land['remark1'] = '';
		$land['remark2'] = '';
	}
/*
	//地番
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('blockNumber')->where_not_equal('blockNumber', '')->where_null('deleteDate')->select('blockNumber')->findOne();
	if(isset($address)){
		$land['remark2'] = $address['blockNumber'];
	}
	else {
		$land['remark2'] = '';
	}
*/	
	//
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_null('deleteDate')->select_many('pid', 'locationType')->find_array();
	if(isset($locs) && sizeof($locs) > 0){
		$land['locations'] = $locs;
	}
	else {
		$land['locations'] = [];
	}

	//契約
	// 20200906 S_Update
//	$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $land['pid'])->where_null('deleteDate')->select('pid')->find_array();
	$contracts = ORM::for_table(TBLCONTRACTINFO)
					->table_alias('p2')
					->select('p2.*')
					->inner_join(TBLTEMPLANDINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p1')
					->where('p2.tempLandInfoPid', $land['pid'])
					->where_null('p2.deleteDate');
	// 契約番号
	if(isset($param->contractNumber) && $param->contractNumber !== ''){
		$raw = "(concat(p1.bukkenNo, '-', p2.contractNumber) LIKE  '" . $param->contractNumber . "%')";
		$contracts = $contracts->where_raw($raw);
	}
	// 明渡期日
	if(isset($param->vacationDay) && $param->vacationDay != ''){
		$contracts = $contracts->where('p2.vacationDay', $param->vacationDay);
	}
	// 契約日
	if(isset($param->contractDay) && $param->contractDay != ''){
		$contracts = $contracts->where('p2.contractDay', $param->contractDay);
	}
	// 契約日（開始）
	if(isset($param->contractDay_From) && $param->contractDay_From != ''){
		$contracts = $contracts->where_gte('p2.contractDay', $param->contractDay_From);
	}
	// 契約日（終了）
	if(isset($param->contractDay_To) && $param->contractDay_To != ''){
		$contracts = $contracts->where_lte('p2.contractDay', $param->contractDay_To);
	}
	// 20201222 S_Add
	// 決済日
	if(isset($param->decisionDay) && $param->decisionDay != ''){
		$contracts = $contracts->where('p2.decisionDay', $param->decisionDay);
	}
	// 決済日（開始）
	if(isset($param->decisionDay_From) && $param->decisionDay_From != ''){
		$contracts = $contracts->where_gte('p2.decisionDay', $param->decisionDay_From);
	}
	// 決済日（終了）
	if(isset($param->decisionDay_To) && $param->decisionDay_To != ''){
		$contracts = $contracts->where_lte('p2.decisionDay', $param->decisionDay_To);
	}
	// 20201222 E_Add
	// 20220519 S_Add
	// 物件担当部署
	if(isset($param->department) && sizeof($param->department) > 0){
		$contracts = $contracts->where_in('p1.department', $param->department);
	}
	// 20220519 E_Add
	// 20221122 S_Add
	// 契約状況
	if(isset($param->contractNow) && $param->contractNow != ''){
		$contracts = $contracts->where('p2.contractNow', $param->contractNow);
	}
	// 解約日（開始）
	if(isset($param->canncellDay_From) && $param->canncellDay_From != ''){
		$contracts = $contracts->where_gte('p2.canncellDay', $param->canncellDay_From);
	}
	// 解約日（終了）
	if(isset($param->canncellDay_To) && $param->canncellDay_To != ''){
		$contracts = $contracts->where_lte('p2.canncellDay', $param->canncellDay_To);
	}
	// 20221122 E_Add
	$contracts = $contracts->select('p2.pid')->find_array();
	// 20200906 E_Update
	if(isset($contracts) && sizeof($contracts) > 0){
		$arrs = array();
		foreach($contracts as $arr){
			$arrs[] = getContractInfo($arr['pid']);
		}
		$land['contracts'] = $arrs;
	}
	else {
		$land['contracts'] = [];
	}
	
	$ret[] = $land;
}
echo json_encode($ret);

?>