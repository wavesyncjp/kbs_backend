<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLRECEIVECONTRACTDETAIL)
	->table_alias('p1')
	->select('p2.pid', 'pid')
	->select('p2.contractDay', 'receiveContractDay')
	->select('p2.contractFixDay', 'receiveContractFixDay')
	->select('p2.supplierName', 'supplierName')
	->select('p3.bukkenNo', 'bukkenNo')
	->select('p3.contractBukkenNo', 'contractBukkenNo')
	->select('p3.bukkenName', 'bukkenName')
	->select('p3.pid', 'tempLandInfoPid')
	// 20230928 S_Add
	->select('p1.banktransferPid')
	->select('p1.banktransferNameKana')
	// 20230928 E_Add
	->inner_join(TBLRECEIVECONTRACT, array('p1.receiveContractPid', '=', 'p2.pid'), 'p2')
	->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p3.pid'), 'p3')
	->distinct();

$query = $query->where_null('p1.deleteDate');

// 物件番号
if (isset($param->bukkenNo)  && $param->bukkenNo !== '') {
	$query = $query->where('p3.bukkenNo', $param->bukkenNo);
}
// 契約物件番（前方一致）
if (isset($param->contractBukkenNo_Like) && $param->contractBukkenNo_Like !== '') {
	$query = $query->where_like('p3.contractBukkenNo', $param->contractBukkenNo_Like . '%');
}
// 物件名
if (isset($param->bukkenName) && $param->bukkenName !== '') {
	$query = $query->where_like('p3.bukkenName', '%' . $param->bukkenName . '%');
}
// 入金種別
if (isset($param->receiveCode) && $param->receiveCode !== '') {
	$query = $query->where('p1.receiveCode', $param->receiveCode);
}
// 取引先
if (isset($param->supplierName) && $param->supplierName !== '') {
	$query = $query->where_like('p2.supplierName', '%' . $param->supplierName . '%');
}
// 契約予定日(contractDay)
if (isset($param->contractDay_From) && $param->contractDay_From != '') {
	$query = $query->where_gte('p2.contractDay', $param->contractDay_From);
}
if (isset($param->contractDay_To) && $param->contractDay_To != '') {
	$query = $query->where_lte('p2.contractDay', $param->contractDay_To);
}
// 契約確定日(contractFixDay)
if (isset($param->contractFixDay_From) && $param->contractFixDay_From != '') {
	$query = $query->where_gte('p2.contractFixDay', $param->contractFixDay_From);
}
if (isset($param->contractFixDay_To) && $param->contractFixDay_To != '') {
	$query = $query->where_lte('p2.contractFixDay', $param->contractFixDay_To);
}
// 入金予定日(receiveDay)
if (isset($param->receiveDay_From) && $param->receiveDay_From != '') {
	$query = $query->where_gte('p1.contractDay', $param->receiveDay_From);
}
if (isset($param->receiveDay_To) && $param->receiveDay_To != '') {
	$query = $query->where_lte('p1.contractDay', $param->receiveDay_To);
}
// 入金確定日(receiveFixDay)
if (isset($param->receiveFixDay_From) && $param->receiveFixDay_From != '') {
	$query = $query->where_gte('p1.contractFixDay', $param->receiveFixDay_From);
}
if (isset($param->receiveFixDay_To) && $param->receiveFixDay_To != '') {
	$query = $query->where_lte('p1.contractFixDay', $param->receiveFixDay_To);
}

// 20230928 S_Add
if (isset($param->banktransferPid) && $param->banktransferPid != '') {
	$query = $query->where('p1.banktransferPid', $param->banktransferPid);
}

if (isset($param->banktransferNameKana) && $param->banktransferNameKana != '') {
	$query = $query->where_like('p1.banktransferNameKana', '%' . $param->banktransferNameKana . '%');
}
// 20230928 E_Add

$query = getQueryExpertTempland($param, $query, 'p3.pid');// 20250502 Add

$contracts = $query->order_by_desc('p3.bukkenNo')->find_array();

$ret = [];
foreach ($contracts as $contract) {
	$details = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where('receiveContractPid', $contract['pid'])->where_null('deleteDate')->find_array();
	$contract['details'] = $details;
	$ret[] = $contract;
}

echo json_encode($ret);

?>
