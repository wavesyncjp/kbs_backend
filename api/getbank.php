<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table(TBLBANK)->where_null('deleteDate');

if(isset($param->contractType) && $param->contractType !== ''){
	$query = $query->where('contractType', $param->contractType);
}

$banks = $query->order_by_asc('displayOrder')->find_array();
echo json_encode($banks);

?>