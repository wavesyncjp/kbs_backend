<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

// 20240123 S_Update
// $userId = null;

// // 20231027 S_Add
// // 賃貸入金再作成フラグ
// $isChangedReceive = false;
// $rentalCT = getRentalContract($param->rentalInfoPid, $param->residentInfoPid, $param->surrenderScheduledDate);

// // 20231027 E_Add

// // 更新
// if (isset($param->pid) && $param->pid > 0) {
// 	$evi = ORM::for_table(TBLEVICTIONINFO)->find_one($param->pid);
// 	setUpdate($evi, $param->updateUserId);
// 	$userId = $param->updateUserId;

// 	// 20231027 S_Add
// 	if(isset($rentalCT)){
// 		if ($evi->surrenderDate != $param->surrenderDate
// 			|| $evi->surrenderScheduledDate != $param->surrenderScheduledDate
// 		) {
// 			$isChangedReceive = true;
// 			$receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalContractPid', $rentalCT->pid)->where_null('deleteDate')->find_many();
// 		}
// 	}
// 	// 20231027 E_Add
// }
// // 登録
// else {
// 	$evi = ORM::for_table(TBLEVICTIONINFO)->create();
// 	setInsert($evi, $param->createUserId);
// 	$userId = $param->createUserId;
// 	// 20231027 S_Add
// 	if(isset($rentalCT)){
// 		if (isset($param->surrenderDate)
// 			|| isset($param->surrenderScheduledDate)
// 		) {
// 			$isChangedReceive = true;
// 			$receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalContractPid', $rentalCT->pid)->where_null('deleteDate')->find_many();
// 		}
// 	}
// 	// 20231027 E_Add	
// }

// copyData($param, $evi, array('pid', 'roomNo', 'borrowerName', 'apartmentName', 'evictionFiles', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
// // if(isset($rentalCT)){
// // 	echo json_encode(array('statusMap' => 'NG', 'msgMap' => '契約期間に指定されている範囲外に、既に入金済の賃料があります。（' . $rentalCT->pid . '、開始日:'. $rentalCT->loanPeriodStartDate. '、変更:'. $isChangedReceive . '）'));
// // 	exit;
// // }
// // 20231027 S_Add
// if ($isChangedReceive) {
// 	$rentPrice = getRentPrice($param->residentInfoPid);
// 	$ownershipRelocationDate = getOwnershipRelocationDate($param->rentalInfoPid);

// 	// 既存賃貸入金PID
// 	$existedRePids = array();

// 	// 賃貸入金を準備
// 	$objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $param);

// 	foreach ($objs as $obj) {
// 		// 既存賃貸入金をチェック
// 		foreach ($receives as $rev) {
// 			// 入金月日同じ
// 			if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
// 				$existedRePids[] = $rev->pid;
// 			}
// 		}
// 	}

// 	//入金済をチェック
// 	$paids = array();
// 	foreach ($receives as $rev) {
// 		// 存在しないデータを削除
// 		if (in_array($rev->pid, $existedRePids) == false) {
// 			if ($rev->receiveFlg == '1') { //入金済み
// 				$paids[] = substr($rev->receiveMonth, 0, 4) . '年' . substr($rev->receiveMonth, 4, 2) . '月';
// 			}
// 		}
// 	}

// 	//入金済の場合、何もしない
// 	if (count($paids) > 0) {
// 		echo json_encode(array('statusMap' => 'NG', 'msgMap' => '契約期間に指定されている範囲外に、既に入金済の賃料があります。（' . join(',', $paids) . '）'));
// 		exit;
// 	}

// 	//立ち退きを更新
// 	$evi->save();

// 	foreach ($objs as $obj) {
// 		$hasRev = false;

// 		// 既存賃貸入金をチェック
// 		foreach ($receives as $rev) {
// 			// 入金月日同じ
// 			if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
// 				$hasRev = true;

// 				// 入金未済,賃料変更の場合
// 				// 入金未済,入金コード変更の場合
// 				if ($rev->receiveFlg != '1' || $rev->receiveCode != $rentalCT->receiveCode) {
// 					$rev->receiveCode = $rentalCT->receiveCode;
// 					setUpdate($rev, $userId);
// 					$rev->save();
// 					break;
// 				}
// 			}
// 		}

// 		// 賃貸入金存在しない場合、新規登録
// 		if ($hasRev == false) {
// 			$receiveSave = ORM::for_table(TBLRENTALRECEIVE)->create();
// 			setInsert($receiveSave, $userId);

// 			copyData($obj, $receiveSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
// 			$receiveSave->save();
// 		}
// 	}
// 	// 賃貸入金再作成対象外の場合、削除
// 	foreach ($receives as $rev) {
// 		// 存在しないデータを削除
// 		if (in_array($rev->pid, $existedRePids) == false) {
// 			setDelete($rev, $userId);
// 			$rev->save();
// 		}
// 	}
// }
// else{
// // 20231027 E_Add
// 	$evi->save();
// }// 20231027 Add
// 20240221 S_Update
// $evi = saveEviction();
$evi = saveEviction($param);
// 20240221 E_Update
// 20240123 E_Update
echo json_encode(getEvictionInfos($evi->contractInfoPid,$evi->pid));
?>
