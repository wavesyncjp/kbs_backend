<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->pid) && $param->pid != ''){
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->findOne($param->pid);
	setDelete($receivecontract, $param->deleteUserId);
	$receivecontract->save();

	// 子レコードの削除
	$details = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where('receiveContractPid', $param->pid)->where_null('deleteDate')->findArray();
	if(isset($details)){
		foreach($details as $detail){
			$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->find_one($detail['pid']);
			setDelete($detailSave, $param->deleteUserId);
			$detailSave->save();
		}
	}
	else {
		echo "DELETE ERROR";
	}
}
else
{
	echo "DELETE ERROR";
}

?>