<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$paymenttypes = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate')->find_array();
echo json_encode($paymenttypes);

?>