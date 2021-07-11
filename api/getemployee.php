<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$query = ORM::for_table(TBLUSER)->where_null('deleteDate');

if(isset($param->activeUser) && $param->activeUser === '1'){
	$query = $query->where_not_null('loginId')->where_not_equal("loginId","");
}

$emps = $query->find_array();
echo json_encode($emps);

?>