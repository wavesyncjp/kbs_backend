<?php
require '../header.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->code)){
	$codes = ORM::for_table("tblcode")->where_in('code', $param->code)->find_array();
}
else {
	$codes = ORM::for_table("tblcode")->find_array();
}
echo json_encode($codes);

?>