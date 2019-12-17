<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table("tblcode");

if(isset($param->code)){
	$codes = $codes->where_in('code', $param->code);
}

if(isset($param->notCode)){
	$codes = $codes->where_not_in('code', $param->notCode);
}

$codes = $codes->find_array();
echo json_encode($codes);

?>