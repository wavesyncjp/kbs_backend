<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPAYCONTRACTDETAIL)
			->table_alias('p1')
			->select('p2.pid', 'pid')
			->select('p2.contractDay', 'payContractDay')
			->select('p2.contractFixDay', 'payContractFixDay')
			->select('p2.supplierName', 'supplierName')
			->select('p3.bukkenNo', 'bukkenNo')
			->select('p3.contractBukkenNo', 'contractBukkenNo')
			->select('p3.bukkenName', 'bukkenName')
			->select('p3.pid', 'tempLandInfoPid')
			->inner_join(TBLPAYCONTRACT, array('p1.payContractPid', '=', 'p2.pid'), 'p2')
			->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p3.pid'), 'p3')
			->distinct();

$query = $query->where_null('p1.deleteDate');

// 物件番号
if(isset($param->bukkenNo)  && $param->bukkenNo !== ''){
	$query = $query->where('p3.bukkenNo', $param->bukkenNo);
}
// 20200828 S_Add
// 契約物件番（前方一致）
if(isset($param->contractBukkenNo_Like) && $param->contractBukkenNo_Like !== ''){
	$query = $query->where_like('p3.contractBukkenNo', $param->contractBukkenNo_Like.'%');
}
// 20200913 E_Add
// 物件名
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p3.bukkenName', '%'.$param->bukkenName.'%');
}
// 支払種別
if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where('p1.paymentCode', $param->paymentCode);
}
// 取引先
if(isset($param->supplierName) && $param->supplierName !== ''){
	$query = $query->where_like('p2.supplierName', '%'.$param->supplierName.'%');
}
// 契約予定日(contractDay)
if(isset($param->contractDay_From) && $param->contractDay_From != ''){
	$query = $query->where_gte('p2.contractDay', $param->contractDay_From);
}
if(isset($param->contractDay_To) && $param->contractDay_To != ''){
	$query = $query->where_lte('p2.contractDay', $param->contractDay_To);
}
// 契約確定日(contractFixDay)
if(isset($param->contractFixDay_From) && $param->contractFixDay_From != ''){
	$query = $query->where_gte('p2.contractFixDay', $param->contractFixDay_From);
}
if(isset($param->contractFixDay_To) && $param->contractFixDay_To != ''){
	$query = $query->where_lte('p2.contractFixDay', $param->contractFixDay_To);
}
// 支払予定日(payDay)
if(isset($param->payDay_From) && $param->payDay_From != ''){
	$query = $query->where_gte('p1.contractDay', $param->payDay_From);
}
if(isset($param->payDay_To) && $param->payDay_To != ''){
	$query = $query->where_lte('p1.contractDay', $param->payDay_To);
}
// 20200913 S_Add
// 支払確定日(payFixDay)
if(isset($param->payFixDay_From) && $param->payFixDay_From != ''){
	$query = $query->where_gte('p1.contractFixDay', $param->payFixDay_From);
}
if(isset($param->payFixDay_To) && $param->payFixDay_To != ''){
	$query = $query->where_lte('p1.contractFixDay', $param->payFixDay_To);
}
// 20200913 E_Add

$contracts = $query->order_by_desc('p3.bukkenNo')->find_array();

// 20230314 S_Add
// 支払種別（明細単位指定）
$paymentCodeFilter = '';
if(isset($param->paymentCodeFilter) && $param->paymentCodeFilter !== ''){
	$paymentCodeFilter = $param->paymentCodeFilter;
}
// 20230314 E_Add
// 20230331 S_Add
// 支払予定日（明細単位指定）
$payDay_FromFilter = '';
if(isset($param->payDay_FromFilter) && $param->payDay_FromFilter !== ''){
	$payDay_FromFilter = $param->payDay_FromFilter;
}
$payDay_ToFilter = '';
if(isset($param->payDay_ToFilter) && $param->payDay_ToFilter !== ''){
	$payDay_ToFilter = $param->payDay_ToFilter;
}
// 支払確定日（明細単位指定）
$payFixDay_FromFilter = '';
if(isset($param->payFixDay_FromFilter) && $param->payFixDay_FromFilter !== ''){
	$payFixDay_FromFilter = $param->payFixDay_FromFilter;
}
$payFixDay_ToFilter = '';
if(isset($param->payFixDay_ToFilter) && $param->payFixDay_ToFilter !== ''){
	$payFixDay_ToFilter = $param->payFixDay_ToFilter;
}
// 20230331 E_Add

$ret = [];
foreach($contracts as $contract) {
	// 20230314 S_Update
	// $details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $contract['pid'])->where_null('deleteDate')->find_array();
	$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $contract['pid'])->where_null('deleteDate');
	if($paymentCodeFilter !== '') {
		$details = $details->where('paymentCode', $paymentCodeFilter);
	}
	// 20230331 S_Add
	if($payDay_FromFilter !== '') {
		$details = $details->where_gte('contractDay', $payDay_FromFilter);
	}
	if($payDay_ToFilter !== '') {
		$details = $details->where_lte('contractDay', $payDay_ToFilter);
	}
	if($payFixDay_FromFilter !== '') {
		$details = $details->where_gte('contractFixDay', $payFixDay_FromFilter);
	}
	if($payFixDay_ToFilter !== '') {
		$details = $details->where_lte('contractFixDay', $payFixDay_ToFilter);
	}
	// 20230331 E_Add
	$details = $details->find_array();
	// 20230314 E_Update
	$contract['details'] = $details;
	$ret[] = $contract;
}

echo json_encode($ret);

?>