<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$emps = ORM::for_table(TBLUSER)->find_array();
echo json_encode($emps);


?>