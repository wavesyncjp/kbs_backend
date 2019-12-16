<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//更新
if($param->pid > 0){
	$contract = ORM::for_table(TBLCONTRACTINFO)->find_one($param->pid);
	setUpdate($land, $param->updateUserId);
}
//登録
else {
	//000002
	$contract = ORM::for_table(TBLCONTRACTINFO)->create();	
	$maxNo = ORM::for_table(TBLCONTRACTINFO)->max('contractNumber');
	$maxNum = intval(ltrim($maxNo, "0")) + 1;
	$nextNo = str_pad($maxNum, 3, '0', STR_PAD_LEFT);
	$contract->contractNumber = $nextNo;
	setInsert($land, $param->createUserId);
}


copyData($param, $contract, array('pid', 'land', 'details', 'depends'));
$contract->save();

echo json_encode(getContractInfo($contract->pid));

?>