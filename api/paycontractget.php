<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$payContract = getPayContractInfo($param->pid);

// 20200304 S_Add
//親（物件）
$land = getLandInfo($payContract['tempLandInfoPid']);
$payContract['land'] = $land;
// 20200304 E_Add

echo json_encode($payContract);

?>