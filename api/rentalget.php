<?php
require '../header.php';
require '../util.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$getIts = '0';// 0:子データを取得しない
if(isset($param->getIts)) {
	$getIts = $param->getIts;
}

$ren = getRental($param->pid, $getIts == '1');
echo json_encode($ren);
?>
