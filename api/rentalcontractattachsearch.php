<?php
require '../header.php';
require '../util.php';

$fullPath = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLRENTALCONTRACTATTACH)
			->table_alias('p1')
			->select('p1.*')
			->where_null('p1.deleteDate')
			;

// 賃貸契約PID
if(isset($param->rentalContractPid) && $param->rentalContractPid !== '') {
	$query = $query->where('p1.rentalContractPid', $param->rentalContractPid);
}

$results = $query->order_by_asc('pid')->find_array();

echo json_encode($results);
?>

