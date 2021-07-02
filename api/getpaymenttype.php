<?php
require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate');

if(isset($param->paymentCode)){
	$query = $query->where_in('paymentCode', $param->paymentCode);
}
if(isset($param->payContractEntryFlg)){
	$query = $query->where_in('payContractEntryFlg', $param->payContractEntryFlg);
}
$paymenttypes = $query->order_by_asc('displayOrder')->order_by_asc('paymentCode')->find_array();

echo json_encode($paymenttypes);

?>
