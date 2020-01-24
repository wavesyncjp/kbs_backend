<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);
                     
$query = ORM::for_table(TBLLOCATIONINFO)->where_null('deleteDate');


$deps = $query->find_array();
echo json_encode($deps);

?>