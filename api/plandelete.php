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
	$plan = ORM::for_table(TBLPLAN)->findOne($param->pid);
	setDelete($plan, $param->deleteUserId);
	$plan->save();

	// 20200304 S_Add
	// 子レコードの削除
	$details = ORM::for_table(TBLPLANDETAIL)->where('planPid', $param->pid)->where_null('deleteDate')->findArray();
	if(isset($details)){
		foreach($details as $detail){
			$detailSave = ORM::for_table(TBLPLANDETAIL)->find_one($detail['pid']);
			setDelete($detailSave, $param->deleteUserId);
			$detailSave->save();
		}
	}
	else {
		echo "DELETE ERROR";
	}
	// 20200304 E_Add
}
else
{
	echo "DELETE ERROR";
}

?>