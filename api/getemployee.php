<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//$query = ORM::for_table(TBLUSER)->where_null('deleteDate');
$query = ORM::for_table(TBLUSER)
			->table_alias('p1')
			->select('p1.*')
			->distinct()
			->left_outer_join(TBLUSERDEPARTMENT, array('p1.userId', '=', 'ud.userId'), 'ud')
			->where_null('p1.deleteDate');

if(isset($param->activeUser) && $param->activeUser === '1') {
	$query = $query->where_not_null('loginId')->where_not_equal('loginId','');
}

// 20220517 S_Update
// $emps = $query->where_not_equal('loginId', '0001')->where_not_equal('loginId', '0002')->order_by_asc('displayOrder')->order_by_asc('depCode')->order_by_asc('userId')->find_array();
if(!isset($param->activeUser) || $param->activeUser !== '9') {
	$query = $query->where_not_in('loginId', ['0001', '0002', '0003', '0004', '0005']);
}

if (is_array($param->depCode) && count($param->depCode) > 0) {
	$query = $query->where('ud.depCode', $param->depCode)
					->where_null('ud.deleteDate');
}

// 権限の条件
if (is_array($param->authority) && count($param->authority) > 0) {
	$query = $query->where_in('p1.authority', $param->authority);
}

// 20250924 S_Update
$emps = $query->order_by_expr('CASE WHEN authority = 99 THEN 1 ELSE 0 END')
	->order_by_expr('CASE WHEN userNameKana IS NULL THEN 1 ELSE 0 END')
	->order_by_asc('userNameKana')
	->order_by_asc('userId')
	->find_array();
// 20250924 E_Update
// 20220517 E_Update

echo json_encode($emps);

?>