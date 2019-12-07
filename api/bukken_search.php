<?php
require '../header.php';

$postdata = file_get_contents("php://input");

$bukkens = ORM::for_table("tblbukken")->find_array();
echo json_encode($bukkens);


?>