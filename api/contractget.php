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
// 20230917 S_Add
// 賃貸一覧
$rentals = getRentalsForContract($contract['pid']);
$contract['rentalsMap'] = $rentals;

// 立ち退き一覧
$evictions = getEvictionInfos($contract['pid'],null);
$contract['evictionsMap'] = $evictions;
// 20230917 E_Add

//親（物件）
$land = getLandInfo($contract['tempLandInfoPid']);
$contract['land'] = $land;

echo json_encode($contract);

?>
