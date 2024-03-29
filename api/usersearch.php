<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLUSER)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.depName');

//$query = $query->inner_join(TBLDEPARTMENT, array('p1.depCode', '=', 'p2.depCode'), 'p2');
$query = $query->left_outer_join(TBLDEPARTMENT, array('p1.depCode', '=', 'p2.depCode'), 'p2');

$query = $query->where_null('p1.deleteDate');

if(isset($param->userId) && $param->userId !== ''){
	$query = $query->where_like('userId', $param->userId.'%');
}
if(isset($param->userName) && $param->userName !== ''){
	$query = $query->where_like('userName', '%'.$param->userName.'%');
}
if(isset($param->depCode) && $param->depCode !== ''){
	$query = $query->where('depCode', $param->depCode);
}
if(isset($param->authority) && $param->authority !== ''){
	$query = $query->where('authority', $param->authority);
}

//$deps = $query->where_not_equal('loginId', '0001')->where_not_equal('loginId', '0002')->order_by_asc('userId')->find_array();
$deps = $query->where_not_in('loginId', ['0001', '0002', '0003', '0004', '0005'])->order_by_asc('userId')->find_array();
echo json_encode($deps);

?>