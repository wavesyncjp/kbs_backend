<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate');

if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where_like('paymentCode', $param->paymentCode.'%');
}
if(isset($param->paymentName) && $param->paymentName !== ''){
	$query = $query->where_like('paymentName', '%'.$param->paymentName.'%');
}

$ret = $query->find_array();
echo json_encode($ret);

?>