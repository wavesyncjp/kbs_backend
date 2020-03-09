<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$taxes = ORM::for_table(TBLTAX)->where_null('deleteDate')->find_array();
echo json_encode($taxes);

?>