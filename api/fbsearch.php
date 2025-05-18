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
			->select('p1.*')
			->select('p2.supplierName', 'supplierName')
			->select('p3.bukkenNo', 'bukkenNo')
			->select('p3.contractBukkenNo', 'contractBukkenNo')
			->select('p3.bukkenName', 'bukkenName')
			->inner_join(TBLPAYCONTRACT, array('p1.payContractPid', '=', 'p2.pid'), 'p2')
			->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p3.pid'), 'p3');

$query = $query->where_null('p1.deleteDate');

// 支払予定日
if(isset($param->contractDay)  && $param->contractDay !== ''){
	$query = $query->where('p1.contractDay', $param->contractDay);
}

// 取引先名称(部分一致)
if(isset($param->supplierName_Like)  && $param->supplierName_Like !== ''){
	$query = $query->where_Like('p2.supplierName', '%'.$param->supplierName_Like.'%');
}

// 支払種別
if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where('p1.paymentCode', $param->paymentCode);
}

// 物件番号
if(isset($param->bukkenNo)  && $param->bukkenNo !== ''){
	$query = $query->where('p3.bukkenNo', $param->bukkenNo);
}

// 契約物件番号
if(isset($param->contractBukkenNo)  && $param->contractBukkenNo !== ''){
	$query = $query->where('p3.contractBukkenNo', $param->contractBukkenNo);
}

// 物件名称(部分一致)
if(isset($param->bukkenName_Like)  && $param->bukkenName_Like !== ''){
	$query = $query->where_Like('p3.bukkenName', '%'.$param->bukkenName_Like.'%');
}

// 出力済フラグ
if(isset($param->fbOutPutFlg)  && $param->fbOutPutFlg !== ''){
	$query = $query->where('p1.fbOutPutFlg', $param->fbOutPutFlg);
}

// FB承認フラグ
if(isset($param->fbApprovalFlg)  && $param->fbApprovalFlg !== ''){
	$query = $query->where('p1.fbApprovalFlg', $param->fbApprovalFlg);
}
$query = getQueryExpertTempland($param, $query, 'p3.pid');// 20250502 Add

$detail = $query->order_by_desc('p1.pid')->find_array();
echo json_encode($detail);

?>