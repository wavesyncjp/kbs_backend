<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table(TBLUSER)->where_null('deleteDate');

if(isset($param->activeUser) && $param->activeUser === '1'){
	$query = $query->where_not_null('loginId')->where_not_equal('loginId','');
}

$emps = $query->where_not_equal('loginId', '0001')->where_not_equal('loginId', '0002')->order_by_asc('depCode')->order_by_asc('userId')->find_array();
echo json_encode($emps);

?>