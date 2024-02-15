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

// // 新規登録フラグ
// $isNew = false;

// // 賃貸入金再作成フラグ
// $isChangedReceive = false;

// //20231010 S_Add
// $rentPrice = getRentPrice($param->residentInfoPid);
// $ownershipRelocationDate = getOwnershipRelocationDate($param->rentalInfoPid);
// //20231010 E_Add

// ORM::get_db()->beginTransaction();

// // 賃貸契約処理
// // 更新
// if (isset($param->pid) && $param->pid > 0) {
// 	$rentalCT = ORM::for_table(TBLRENTALCONTRACT)->find_one($param->pid);
// 	setUpdate($rentalCT, $param->updateUserId);
// 	$userId = $param->updateUserId;

// 	// 20231010 S_Update
// 	//賃料 OR 賃貸契約期間 OR 支払期限　が変更の場合
// 	// if ($rentalCT->rentPrice != $param->rentPrice
// 	// 	|| $rentalCT->loanPeriodStartDate != $param->loanPeriodStartDate
// 	//  || $rentalCT->loanPeriodEndDate != $param->loanPeriodEndDate
// 	if ($rentalCT->loanPeriodEndDate != $param->loanPeriodEndDate
// 	// 20231010 E_Update
// 		|| $rentalCT->usance != $param->usance
// 		|| $rentalCT->paymentDay != $param->paymentDay
// 		|| $rentalCT->locationInfoPid != $param->locationInfoPid
// 		|| $rentalCT->receiveCode != $param->receiveCode
// 	) {
// 		$isChangedReceive = true;
// 		$receives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalContractPid', $param->pid)->where_null('deleteDate')->find_many();
// 	}
// }
// // 登録
// else {
// 	$isNew = true;
// 	$rentalCT = ORM::for_table(TBLRENTALCONTRACT)->create();
// 	setInsert($rentalCT, $param->createUserId);
// 	$userId = $param->createUserId;
// }

// copyData($param, $rentalCT, array('pid', 'roomNo', 'borrowerName', 'locationInfoPidForSearch', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));

// // 賃貸入金処理
// // 新規登録　OR 賃貸入金未登録　の場合
// if ($isNew || ($isChangedReceive && ($receives == null || count($receives) == 0))) {
// 	//賃貸契約を登録
// 	$rentalCT->save();

// 	//賃貸入金を準備
// 	// 20231027 S_Update
// 	// $objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $param->loanPeriodEndDate);
// 	$evic = getEvic($rentalCT);
// 	$objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $evic);
// 	// 20231027 E_Update

// 	foreach ($objs as $obj) {
// 		$receiveSave = ORM::for_table(TBLRENTALRECEIVE)->create();
// 		setInsert($receiveSave, $userId);

// 		copyData($obj, $receiveSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
// 		$receiveSave->save();
// 	}
// }
// else if ($isChangedReceive) {
// 	// 既存賃貸入金PID
// 	$existedRePids = array();

// 	// 賃貸入金を準備
// 	// 20231027 S_Update
// 	// $objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $param->loanPeriodEndDate);
// 	$evic = getEvic($rentalCT);
// 	$objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $evic);
// 	// 20231027 E_Update

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

// 	//賃貸契約を更新
// 	$rentalCT->save();

// 	foreach ($objs as $obj) {
// 		$hasRev = false;

// 		// 既存賃貸入金をチェック
// 		foreach ($receives as $rev) {
// 			// 入金月日同じ
// 			if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
// 				$hasRev = true;
// 				// $existedRePids[] = $rev->pid;

// 				// 20231010 S_Update
// 				// 入金未済,賃料変更の場合
// 				// if ($rev->receiveFlg != '1' && ($rev->receivePrice != $rentalCT->rentPrice || $rev->receiveCode != $rentalCT->receiveCode)) {
// 				// 	$rev->receivePrice = $rentalCT->rentPrice;
// 				// 入金未済,入金コード変更の場合
// 				if ($rev->receiveFlg != '1' || $rev->receiveCode != $rentalCT->receiveCode) {
// 				// 20231010 E_Update
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
// 	//賃貸契約を更新
// 	$rentalCT->save();
// }
$rentalCT = saveRentalContract($param);
// 20240123 E_Update

ORM::get_db()->commit();

echo json_encode(getRentalContracts($rentalCT->rentalInfoPid, $rentalCT->pid));

// 20231010 S_Delete
// /**
//  * 入金月取得
//  */
// function getReceiveMonths($dateStrFrom, $dateStrTo) {
// 	$arr = array();

// 	$dateFrom = new DateTime($dateStrFrom);
// 	$dateCheck = $dateFrom->format('Ym');
// 	$arr[] = $dateCheck;
// 	$dateTo = new DateTime($dateStrTo);

// 	// $interval = $dateFrom->diff($dateTo);
// 	$limit = $dateTo->format('Ym');

// 	while ($dateCheck < $limit) {
// 		$dateCheck = date('Ym', strtotime("+1 months", strtotime($dateCheck . '01')));
// 		$arr[] = $dateCheck;
// 	};
// 	return $arr;
// }

// function createRentalReceives($param, $rentalCT) {
// 	$objs = array();

// 	// 登録日
// 	$createDate = $rentalCT->createDate;

// 	// 支払いサイト
// 	$usance = $rentalCT->usance;
// 	if (!isset($usance) || $usance == '') {
// 		$usance = '1';// 1:翌月、2:翌々月
// 	}

// 	// 支払日
// 	$paymentDay = $rentalCT->paymentDay;
// 	if (!isset($paymentDay) || $paymentDay == '' || $paymentDay == '0') {
// 		$paymentDay = '31';
// 	}

// 	if (strlen($paymentDay) == 1) {
// 		$paymentDay = '0' . $paymentDay;
// 	}

// 	// 賃貸契約開始日
// 	$loanPeriodStartDate = $param->loanPeriodStartDate;
// 	if (!isset($loanPeriodStartDate)) {
// 		$loanPeriodStartDate = date('Ymd', strtotime($createDate));
// 	}

// 	// 賃貸契約終了日
// 	$loanPeriodEndDate = $param->loanPeriodEndDate;
// 	if (!isset($loanPeriodEndDate)) {
// 		// 一年間
// 		$loanPeriodEndDate = date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
// 	}

// 	// 入金月
// 	$receiveMonths = getReceiveMonths($loanPeriodStartDate, $loanPeriodEndDate);

// 	// 賃貸入金作成
// 	foreach ($receiveMonths as $receiveMonth) {
// 		$obj = new stdClass();
// 		$obj->rentalInfoPid = $rentalCT->rentalInfoPid;
// 		$obj->rentalContractPid = $rentalCT->pid;
// 		$obj->contractInfoPid = $rentalCT->contractInfoPid;
// 		$obj->locationInfoPid = $rentalCT->locationInfoPid;
// 		$obj->tempLandInfoPid = $rentalCT->tempLandInfoPid;
// 		$obj->receivePrice = $rentalCT->rentPrice;
// 		$obj->receiveCode = $rentalCT->receiveCode;
// 		$obj->receiveFlg = '0';
// 		$obj->receiveMonth = $receiveMonth;

// 		// 仮入金日
// 		$dateTemp = date('Ymd', strtotime("+" . $usance . " months", strtotime($receiveMonth . '01')));

// 		// 各月の日数
// 		$dayMaxInMonth = getDayInMonth($dateTemp);

// 		// まず、日まで設定
// 		$dayTemp = $paymentDay;

// 		// 各月の日数は日までより小さい場合
// 		if (intval($dayMaxInMonth) < intval($paymentDay)) {
// 			$dayTemp = $dayMaxInMonth;
// 		}

// 		if (strlen($dayTemp) == 1) {
// 			$dayTemp = '0' . $dayTemp;
// 		}

// 		// 入金日
// 		$obj->receiveDay = substr($dateTemp, 0, 6) . $dayTemp;

// 		$objs[] = $obj;
// 	}
// 	return $objs;
// }
// 20231010 E_Delete
?>
