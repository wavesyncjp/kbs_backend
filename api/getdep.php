<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$deps = ORM::for_table(TBLDEPARTMENT)->where_null('deleteDate')->order_by_asc('displayOrder')->find_array();
echo json_encode($deps);

?>