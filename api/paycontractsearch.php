<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLPAYCONTRACT)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.bukkenNo', 'bukkenNo')
			->select('p2.bukkenName', 'bukkenName');

$query = $query->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p2.pid'), 'p2');

$query = $query->where_null('p1.deleteDate');

if(isset($param->bukkenNo)  && $param->bukkenNo !== ''){
	$query = $query->where('p2.bukkenNo', $param->bukkenNo);
}
if(isset($param->bukkenName) && $param->bukkenName > 0){
	$query = $query->where('p2.bukkenName', $param->bukkenName);
}
if(isset($param->supplierName) && $param->supplierName > 0){
	$query = $query->where_like('p1.supplierName', $param->supplierName.'%');
}
if(isset($param->contractDay) && $param->contractDay > 0){
	$query = $query->where('p1.contractDay', $param->contractDay);
}
if(isset($param->contractFixDay) && $param->contractFixDay > 0){
	$query = $query->where('p1.contractFixDay', $param->contractFixDay);
}

$contracts = $query->find_array();
$ret = $contracts;
echo json_encode($ret);

?>