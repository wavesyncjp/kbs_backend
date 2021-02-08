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
//			->select('p1.*');
			->select('p1.pid')
			->select('p1.bukkenNo')
			->select('p1.contractBukkenNo')
			->select('p1.bukkenName')
			->select('p1.residence')
			->select('p1.result')
			->select('p1.infoStaff')
			->select('p1.pickDate')
			->select('p1.surveyRequestedDay')
			->select('p1.latitude')
			->select('p1.longitude')
			->select('p1.department');// 20210208 Add
			//->select("GROUP_CONCAT(address SEPARATOR ', ') as locationAddress");
			//->left_outer_join(TBLLOCATIONINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2')->where_null('p1.deleteDate');

if(isset($param->address) && $param->address != ''){
	$query = $query->inner_join(TBLLOCATIONINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');
}
// 2021012 S_Add
if((isset($param->salesDecisionDaySearch_From) && $param->salesDecisionDaySearch_From != '')
	|| (isset($param->salesDecisionDaySearch_From) && $param->salesDecisionDaySearch_From != '')){
	$query = $query->inner_join(TBLBUKKENSALESINFO, array('p1.pid', '=', 'p3.tempLandInfoPid'), 'p3');
}
// 20210112 E_Add
$query = $query->where_null('p1.deleteDate');

// 物件番号
if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
	$query = $query->where('p1.bukkenNo', $param->bukkenNo);
}
// 契約物件番号
if(isset($param->contractBukkenNo) && $param->contractBukkenNo !== ''){
	$query = $query->where('p1.contractBukkenNo', $param->contractBukkenNo);
}
// 20200828 S_Add
// 契約物件番（前方一致）
if(isset($param->contractBukkenNo_Like) && $param->contractBukkenNo_Like !== ''){
	$query = $query->where_like('p1.contractBukkenNo', $param->contractBukkenNo_Like.'%');
}
// 物件担当者（複数指定）
if(isset($param->clctInfoStaff) && $param->clctInfoStaff !== '' && sizeof($param->clctInfoStaff) > 0){

	$str = [];
	foreach($param->clctInfoStaff as $userId) {
		$str[] = " CONCAT(',',p1.infoStaff, ',') like '%," . $userId . ",%' ";
	}

	$whereRaw = '( ' . implode ('OR', $str) . ' )';
	$query = $query->where_raw($whereRaw);
}
// 20200828 E_Add
// 20210208 S_Add
// 物件担当者（担当なし）
if(isset($param->notStaffChk) && $param->notStaffChk === '1'){
	$query = $query->where('p1.infoStaff', '');
}
// 20210208 E_Add
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
// 20200913 S_Add
//終了日(finishDate)
if(isset($param->finishDateSearch_From) && $param->finishDateSearch_From != ''){
	$query = $query->where_gte('p1.finishDate', $param->finishDateSearch_From);
}
if(isset($param->finishDateSearch_To) && $param->finishDateSearch_To != ''){
	$query = $query->where_lte('p1.finishDate', $param->finishDateSearch_To);
}
// 20200913 E_Add
// 20210112 S_Add
//決済日(salesDecisionDay)
if(isset($param->salesDecisionDaySearch_From) && $param->salesDecisionDaySearch_From != ''){
	$query = $query->where_gte('p3.salesDecisionDay', $param->salesDecisionDaySearch_From);
}
if(isset($param->salesDecisionDaySearch_To) && $param->salesDecisionDaySearch_To != ''){
	$query = $query->where_lte('p3.salesDecisionDay', $param->salesDecisionDaySearch_To);
}
// 物件管理表
if(isset($param->bukkenListChk) && $param->bukkenListChk !== ''){
	$query = $query->where('p1.bukkenListChk', $param->bukkenListChk);
}
// 測量依頼日チェック
if(isset($param->surveyRequestedDayChk) && $param->surveyRequestedDayChk !== ''){
	$query = $query->where('p1.surveyRequestedDayChk', $param->surveyRequestedDayChk);
}
// 重要度
if(isset($param->importance) && sizeof($param->importance) > 0){
	$query = $query->where_in('p1.importance', $param->importance);
}
// 20210112 E_Add
// 20210207 S_Add
// 情報提供者 MetProは未使用
if(isset($param->infoOffer) && $param->infoOffer !== ''){
	$query = $query->where_like('p1.infoOffer', '%'.$param->infoOffer.'%');
}
// 20210207 E_Add

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