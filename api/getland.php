<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$lands = ORM::for_table("tbltemplandinfo")->where_null('deleteDate')->find_array();
echo json_encode($lands);

?>