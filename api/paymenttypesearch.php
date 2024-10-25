<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

// 20241022 S_Update
// $query = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate');
$query = ORM::for_table(TBLPAYMENTTYPE);

if(!isset($param->isAllData)){
	$query = $query->where_null('deleteDate');
}
// 20241022 E_Update

// 支払コード
if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where_like('paymentCode', $param->paymentCode.'%');
}
// 支払名称
if(isset($param->paymentName) && $param->paymentName !== ''){
	$query = $query->where_like('paymentName', '%'.$param->paymentName.'%');
}
// 原価フラグ
if(isset($param->costFlg)  && $param->costFlg !== ''){
	$query = $query->where('costFlg', $param->costFlg);
}
// 追加フラグ
if(isset($param->addFlg)  && $param->addFlg !== ''){
	$query = $query->where('addFlg', $param->addFlg);
}
// 課税フラグ
if(isset($param->taxFlg)  && $param->taxFlg !== ''){
	$query = $query->where('taxFlg', $param->taxFlg);
}
// 光熱費フラグ
if(isset($param->utilityChargesFlg)  && $param->utilityChargesFlg !== ''){
	$query = $query->where('utilityChargesFlg', $param->utilityChargesFlg);
}
// 大分類フラグ
if(isset($param->categoryFlg)  && $param->categoryFlg !== ''){
	$query = $query->where('categoryFlg', $param->categoryFlg);
}
// 支払登録対象
if(isset($param->payContractEntryFlg)  && $param->payContractEntryFlg !== ''){
	$query = $query->where('payContractEntryFlg', $param->payContractEntryFlg)->order_by_asc('displayOrder');
}

// 20241024 S_Update
// $ret = $query->order_by_asc('paymentCode')->find_array();
if(!isset($param->payContractEntryFlg) && isset($param->isAllData)){
	$ret = $query->order_by_asc('displayOrder')->order_by_asc('paymentCode')->find_array();
}
else{
	$ret = $query->order_by_asc('paymentCode')->find_array();
}
// 20241024 E_Update
echo json_encode($ret);

?>