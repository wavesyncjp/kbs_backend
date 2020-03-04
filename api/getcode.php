<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table(TBLCODE);

if(isset($param->code)){
	$query = $query->where_in('code', $param->code);
}

$query = $query->find_array();
echo json_encode($query);

?>