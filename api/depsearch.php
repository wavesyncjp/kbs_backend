<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLDEPARTMENT)->where_null('deleteDate');

if(isset($param->depCode) && $param->depCode !== ''){
	$query = $query->where_like('depCode', $param->depCode.'%');
}
if(isset($param->depName) && $param->depName !== ''){
	$query = $query->where_like('depName', '%'.$param->depName.'%');
}

$deps = $query->find_array();
echo json_encode($deps);

?>