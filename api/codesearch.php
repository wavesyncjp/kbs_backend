<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLCODE)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.name', 'nameHeader');

$query = $query->inner_join(TBLCODENAMEMST, array('p1.code', '=', 'p2.code'), 'p2');

//$query = $query->where_null('p1.deleteDate');
$query = $query->where_null('p1.deleteDate')->where_not_like('p1.code', 'SYS%');

if(isset($param->code) && $param->code !== ''){
	$query = $query->where_like('p1.code', $param->code.'%');
}

if(isset($param->codeDetail) && $param->codeDetail !== ''){
	$query = $query->where_like('codeDetail', $param->codeDetail.'%');
}

if(isset($param->name) && $param->name !== ''){
	$query = $query->where_like('name', '%'.$param->name.'%');
}

$codes = $query->order_by_asc('code')->order_by_asc('codeDetail')->find_array();
echo json_encode($codes);

?>