<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$land = getLandInfo($param->pid);
echo json_encode($land);

?>