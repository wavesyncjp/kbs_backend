<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$deps = ORM::for_table(TBLKANJYO)->where_null('deleteDate')->find_array();
echo json_encode($deps);

?>