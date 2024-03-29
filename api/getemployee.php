<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//$query = ORM::for_table(TBLUSER)->where_null('deleteDate');
$query = ORM::for_table(TBLUSER)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.displayOrder')
			->left_outer_join(TBLDEPARTMENT, array('p1.depCode', '=', 'p2.depCode'), 'p2')
			->where_null('p1.deleteDate');

if(isset($param->activeUser) && $param->activeUser === '1') {
	$query = $query->where_not_null('loginId')->where_not_equal('loginId','');
}

// 20220517 S_Update
// $emps = $query->where_not_equal('loginId', '0001')->where_not_equal('loginId', '0002')->order_by_asc('displayOrder')->order_by_asc('depCode')->order_by_asc('userId')->find_array();
if(!isset($param->activeUser) || $param->activeUser !== '9') {
	$query = $query->where_not_in('loginId', ['0001', '0002', '0003', '0004', '0005']);
}
$emps = $query->order_by_asc('displayOrder')->order_by_asc('depCode')->order_by_asc('userId')->find_array();
// 20220517 E_Update

echo json_encode($emps);

?>