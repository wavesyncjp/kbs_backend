<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$plan = getPlanInfo($param->pid);

// 20200304 S_Add hirano 以下の項目を考えねば
//親（物件）
$land = getLandInfo($plan['tempLandInfoPid']);
$plan['land'] = $land;
// 20200304 E_Add

echo json_encode($plan);

?>