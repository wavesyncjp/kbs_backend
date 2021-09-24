<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$receiveContract = getReceiveContractInfo($param->pid);

//親（物件）
$land = getLandInfo($receiveContract['tempLandInfoPid']);
$receiveContract['land'] = $land;

echo json_encode($receiveContract);

?>