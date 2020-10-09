<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$plan = getPlanInfoHistory($param->pid);


$land = getLandInfo($plan['tempLandInfoPid']);
$plan['land'] = $land;


echo json_encode($plan);

?>