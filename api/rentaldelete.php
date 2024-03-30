<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = $param->userPid;
$pid = $param->pid;

ORM::get_db()->beginTransaction();

// 20243028 S_Update
// // 賃貸削除
// $obj = ORM::for_table(TBLRENTALINFO)->find_one($pid);
// if (isset($obj)) {
// 	setDelete($obj, $userId);
// 	$obj->save();
// }

// // 賃貸契約削除
// $renCons = ORM::for_table(TBLRENTALCONTRACT)->where('rentalInfoPid', $pid)->where_null('deleteDate')->find_many();
// if ($renCons != null) {
// 	foreach ($renCons as $renCon) {
// 		setDelete($renCon, $userId);
// 		$renCon->save();
// 	}
// }

// //賃貸入金削除
// $receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $pid)->where_null('deleteDate')->find_many();
// if ($receives != null) {
// 	foreach ($receives as $rev) {
// 		setDelete($rev, $userId);
// 		$rev->save();
// 	}
// }

// //立退き削除
// $evics = ORM::for_table(TBLEVICTIONINFO)->where('rentalInfoPid', $pid)->where_null('deleteDate')->find_many();
// if ($evics != null) {
// 	foreach ($evics as $item) {
// 		setDelete($item, $userId);
// 		$item->save();
// 	}
// }
deleteRental($pid, $userId);
// 20243028 E_Update
ORM::get_db()->commit();

echo json_encode(array('status' => 'OK'));
?>
