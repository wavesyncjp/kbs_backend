<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = $param->userId;
$pid = $param->pid;

// 20231101 S_Update
// $obj = ORM::for_table(TBLEVICTIONINFO)->find_one($pid);
// if (isset($obj)) {
// 	setDelete($obj, $userId);
// 	$obj->save();
// }		
$objEvic = ORM::for_table(TBLEVICTIONINFO)->find_one($pid);
if (isset($objEvic)) {
	$rentalCT = getRentalContract($objEvic->rentalInfoPid, $objEvic->residentInfoPid, $objEvic->surrenderScheduledDate);
	if(isset($rentalCT)){

		$receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalContractPid', $rentalCT->pid)->where_null('deleteDate')->find_many();
	
		$rentPrice = getRentPrice($objEvic->residentInfoPid);
		$ownershipRelocationDate = getOwnershipRelocationDate($objEvic->rentalInfoPid);
	
		// 既存賃貸入金PID
		$existedRePids = array();
	
		// 賃貸入金を準備
		$objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $obj);
	
		// 20240311 S_Add
		$minReceiveMonth = getMinReceiveMonth($objs);
		$maxReceiveMonth = getMaxReceiveMonth($objEvic);
		// 20240311 E_Add
		
		foreach ($objs as $obj) {
			// 既存賃貸入金をチェック
			foreach ($receives as $rev) {
				// 入金月日同じ
				// 20240311 S_Update
				// if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
				if ($rev->receiveMonth == $obj->receiveMonth) {
				// 20240311 E_Update
					$existedRePids[] = $rev->pid;
				}
			}
		}
	
		//入金済をチェック
		$paids = array();
		foreach ($receives as $rev) {
			// 存在しないデータを削除
			if (in_array($rev->pid, $existedRePids) == false) {
				// 20240311 S_Update
				// if ($rev->receiveFlg == '1') { //入金済み
				if ($rev->receiveFlg == '1' && isOutOfRangeReceiveMonth($rev->receiveMonth, $minReceiveMonth, $maxReceiveMonth)) { //入金済み　及び　範囲外
				// 20240311 E_Update
					$paids[] = substr($rev->receiveMonth, 0, 4) . '年' . substr($rev->receiveMonth, 4, 2) . '月';
				}
			}
		}
	
		//入金済の場合、何もしない
		if (count($paids) > 0) {
			echo json_encode(array('statusMap' => 'NG', 'msgMap' => '契約期間に指定されている範囲外に、既に入金済の賃料があります。（' . join(',', $paids) . '）'));
			exit;
		}
	
		//立ち退きを削除
		setDelete($objEvic, $userId);
		$objEvic->save();
	
		foreach ($objs as $obj) {

			$hasRev = false;
	
			// 既存賃貸入金をチェック
			foreach ($receives as $rev) {
				// 20240311 S_Update
				// // 入金月日同じ
				// if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
				// 	$hasRev = true;
	
				// 	// 入金未済,賃料変更の場合
				// 	// 入金未済,入金コード変更の場合
				// 	if ($rev->receiveFlg != '1' || $rev->receiveCode != $rentalCT->receiveCode) {
				// 		$rev->receiveCode = $rentalCT->receiveCode;
				// 		setUpdate($rev, $userId);
				// 		$rev->save();
				// 		break;
				// 	}
				// }
				// 入金月日同じ
				if ($rev->receiveMonth == $obj->receiveMonth) {
					$hasRev = true;
	
					// 入金未済
					if ($rev->receiveFlg != '1') {
						$rev->receiveCode = $rentalCT->receiveCode;
						$rev->receiveDay = $obj->receiveDay;
						setUpdate($rev, $userId);
						$rev->save();
						break;
					}
				}
				// 20240311 E_Update
			}
	
			// 賃貸入金存在しない場合、新規登録
			if ($hasRev == false) {
				$receiveSave = ORM::for_table(TBLRENTALRECEIVE)->create();
				setInsert($receiveSave, $userId);
	
				copyData($obj, $receiveSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
				$receiveSave->save();
			}
		}
		// 賃貸入金再作成対象外の場合、削除
		foreach ($receives as $rev) {
			// 存在しないデータを削除
			// 20240311 S_Update
			// if (in_array($rev->pid, $existedRePids) == false) {
			if (in_array($rev->pid, $existedRePids) == false && isOutOfRangeReceiveMonth($rev->receiveMonth, $minReceiveMonth, $maxReceiveMonth)) {
			// 20240311 E_Update
				setDelete($rev, $userId);
				$rev->save();
			}
		}
	}
	else{
		setDelete($objEvic, $userId);
		$objEvic->save();
	}
}
// 20231101 E_Update
echo json_encode(array('status' => 'OK'));
?>
