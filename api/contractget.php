<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$contract = getContractInfo($param->pid);

//親（物件）
$land = getLandInfo($contract['tempLandInfoPid']);
$contract['land'] = $land;

echo json_encode($contract);

?>