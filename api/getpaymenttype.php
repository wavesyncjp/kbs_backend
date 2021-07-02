<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$paymenttypes = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('paymentCode')->find_array();
echo json_encode($paymenttypes);

?>
