<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$rentalReceivesChanged = $param->rentalReceivesChanged;

// 20231010 S_Add
$isChangedReceive = isset($rentalReceivesChanged);
$isChangedOwnerDate = false;// 所有権移転日変更フラグ
$rentalReceiveAllDeleteObj = array();// 全部賃貸入金削除データ
$rentalReceiveAllDeletePid = array();// 全部賃貸入金削除Pid
$rentalReceiveAllNewObj = array();// 全部賃貸入金（新規）
// 20231010 E_Add

$userId = null;

ORM::get_db()->beginTransaction();

// 更新
if (isset($param->pid) && $param->pid > 0) {
	$rental = ORM::for_table(TBLRENTALINFO)->find_one($param->pid);
	setUpdate($rental, $param->updateUserId);
	$userId = $param->updateUserId;

	// 20231010 S_Add
	$isChangedOwnerDate = $param->ownershipRelocationDate != $rental->ownershipRelocationDate;
	// 20231010 E_Add

	//所在地情報PID変更チェック
	if ($rental->locationInfoPid != $param->locationInfoPid) {
		$isChangedLocPid = true;
		$locationInfoPid = $rental->locationInfoPid;
		//賃貸契約
		$rentalContracts = ORM::for_table(TBLRENTALCONTRACT)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
		//賃貸入金
		$rentalReceives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
	}
	// 20231010 S_Add
	else if($isChangedOwnerDate){
		//賃貸契約
		$rentalContracts = ORM::for_table(TBLRENTALCONTRACT)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
		//賃貸入金
		$rentalReceives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
		
		// 賃貸契約未登録の場合、所有権移転日変更処理をスキップ
		if($rentalContracts == null || count($rentalContracts) == 0){
			$isChangedOwnerDate = false;
		}
	}
	// 20231010 E_Add
}
// 登録
else {
	$rental = ORM::for_table(TBLRENTALINFO)->create();
	setInsert($rental, $param->createUserId);
	$userId = $param->createUserId;
}


// 20231010 S_Add
// 所有権移転日変更した場合、既存賃貸入金をチェック
if($isChangedOwnerDate){

	$ownershipRelocationDate = $param->ownershipRelocationDate;
	$paids = array();

	foreach ($rentalContracts as $rentalCT) {
		$rentPrice = getRentPrice($rentalCT->residentInfoPid);
		$objs = createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $rentalCT->loanPeriodEndDate);
	
		$receives = array();
		$existedRePids = array();

		foreach ($rentalReceives as $rev) {
			if($rev->rentalContractPid == $rentalCT->pid){
				$receives[] = $rev;
			}
		}

		foreach ($objs as $obj) {
			$isExists = false;

			// 既存賃貸入金をチェック
			foreach ($receives as $rev) {
				// 入金月日同じ
				if (in_array($rev->pid, $existedRePids) == false) {
					if ($rev->receiveMonth == $obj->receiveMonth && $rev->receiveDay == $obj->receiveDay) {
						$existedRePids[] = $rev->pid;
						$isExists = true;
					}
				}
			}

			if(!$isExists){
				$rentalReceiveAllNewObj[] =$obj;
			}
		}

		//入金済をチェック
		foreach ($receives as $rev) {
			// 存在しないデータを削除
			if (in_array($rev->pid, $existedRePids) == false) {
				if ($rev->receiveFlg == '1') { //入金済み
					$isSkip = false;

					//画面で賃貸入金更新ある
					if($isChangedReceive){
						foreach ($rentalReceivesChanged as $revChanged) {
							if($revChanged->pid == $rev->pid){
								//画面で入金済を未入金に変更
								$isSkip = $revChanged->receiveFlg == '0';
								break;
							}
						}
					}

					if(!$isSkip){
						$tmp = substr($rev->receiveMonth, 0, 4) . '年' . substr($rev->receiveMonth, 4, 2) . '月';
						if (in_array($tmp, $paids) == false) {
							$paids[] = $tmp;
						}
					}
				}
				else{
					$rentalReceiveAllDeleteObj[] = $rev;
					$rentalReceiveAllDeletePid[] = $rev->pid;
				}
			}
		}
	}
	//入金済の場合、何もしない
	if (count($paids) > 0) {
		echo json_encode(array('statusMap' => 'NG', 'msgMap' => '契約期間に指定されている範囲外に、既に入金済の賃料があります。（' . join(',', $paids) . '）'));
		exit;
	}
}
// 20231010 E_Add

copyData($param, $rental, array('pid', 'rentalContracts', 'rentalReceives', 'rentalReceivesChanged', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$rental->save();

// 所在地情報PIDを変更の場合、賃貸契約の入居者情報PID、所在地情報PIDをクリア
if ($isChangedLocPid) {
	// 賃貸契約の部屋番号をクリア
	if ($rentalContracts != null) {
		foreach ($rentalContracts as $con) {
			$con->locationInfoPid = null;
			$con->residentInfoPid = null;
			setUpdate($con, $userId);
			$con->save();
		}
	}

	// 賃貸入金を削除
	if ($rentalReceives != null) {
		// 入金契約Pid
		$receiveContractPids = array();

		foreach ($rentalReceives as $rev) {
			setDelete($rev, $userId);
			$rev->save();

			//入金契約詳細情報を削除
			$revConDetail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where_null('deleteDate')->where('rentalReceivePid', $rev->pid)->find_one();
			if ($revConDetail != null) {
				if (!in_array($revConDetail->receiveContractPid, $receiveContractPids)) {
					$receiveContractPids[] = $revConDetail->receiveContractPid;
				}

				$revConDetail->delete();
			}
		}

		// 入金契約詳細情報の件数をチェック、０件の場合、入金契約情報を削除
		foreach ($receiveContractPids as $receiveContractPid) {
			//入金契約詳細情報の件数
			$number_of_detail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where_null('deleteDate')->where('receiveContractPid', $receiveContractPid)->count();
			if ($number_of_detail == 0) {
				//入金契約を削除
				$revCon = ORM::for_table(TBLRECEIVECONTRACT)->where_null('deleteDate')->find_one($receiveContractPid);
				if ($revCon != null) {
					$revCon->delete();
				}
			}
		}
	}
}

// 賃貸入金を更新
// 20231010 S_Update
// if (isset($rentalReceivesChanged)) {
if (!$isChangedLocPid && $isChangedReceive) {
// 20231010 S_Update

	// 入金契約Pid
	$receiveContractPids = array();

	foreach ($rentalReceivesChanged as $rev) {
		if (in_array($rev->pid, $rentalReceiveAllDeletePid) == false) {
			$revDB = ORM::for_table(TBLRENTALRECEIVE)->find_one($rev->pid);
			$receiveFlgDB = $revDB->receiveFlg;// 20231010 Add

			setUpdate($revDB, $userId);
			$revDB->receiveFlg = $rev->receiveFlg;
			$revDB->receiveDay = $rev->receiveDay;// 20231010 Add
			$revDB->save();

			if ($rev->receiveFlg == '1') { //入金済
				// 20231010 S_Add
				//入金済フラグ変更しない場合
				if($receiveFlgDB == '1'){
					//入金契約詳細情報を更新
					$revConDetail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where_null('deleteDate')->where('rentalReceivePid', $revDB->pid)->find_one();
					if ($revConDetail != null) {
						setUpdate($revConDetail, $userId);
						$revConDetail->contractFixDay = $rev->receiveDay; //入金確定日
						$revConDetail->save();
					}
				}
				else{
				// 20231010 E_Add
					//賃貸契約
					$renCon = ORM::for_table(TBLRENTALCONTRACT)->find_one($revDB->rentalContractPid);

					//入金契約
					$revCon = ORM::for_table(TBLRECEIVECONTRACT)->where_null('deleteDate')->where('rentalContractPid', $renCon->pid)->find_one();

					//入金契約情報
					if ($revCon == null) {
						$revConTemp = createReceiveContract($renCon);
						$revCon = ORM::for_table(TBLRECEIVECONTRACT)->create();

						setInsert($revCon, $userId);

						copyData($revConTemp, $revCon, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
						$revCon->save();
					}

					//入金契約詳細情報
					$revConDetailTemp = createReceiveContractDetail($rental, $revCon, $renCon, $revDB);
					$revConDetail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->create();

					setInsert($revConDetail, $userId);
					copyData($revConDetailTemp, $revConDetail, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
					$revConDetail->save();
				}// 20231010 Add
			}
			else {
				//入金契約詳細情報を削除
				$revConDetail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where_null('deleteDate')->where('rentalReceivePid', $revDB->pid)->find_one();
				if ($revConDetail != null) {
					if (!in_array($revConDetail->receiveContractPid, $receiveContractPids)) {
						$receiveContractPids[] = $revConDetail->receiveContractPid;
					}

					$revConDetail->delete();
				}
			}
		}
	}

	// 入金契約詳細情報の件数をチェック、０件の場合、入金契約情報を削除
	foreach ($receiveContractPids as $receiveContractPid) {
		//入金契約詳細情報の件数
		$number_of_detail = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where_null('deleteDate')->where('receiveContractPid', $receiveContractPid)->count();
		if ($number_of_detail == 0) {
			//入金契約を削除
			$revCon = ORM::for_table(TBLRECEIVECONTRACT)->where_null('deleteDate')->find_one($receiveContractPid);
			if ($revCon != null) {
				$revCon->delete();
			}
		}
	}
}

// 20231010 S_Add
// 所有権移転日変更の処理
if (!$isChangedLocPid && $isChangedOwnerDate) {

	// 範囲外データを削除
	foreach ($rentalReceiveAllDeleteObj as $rev) {
		setDelete($rev, $userId);
		$rev->save();
	}

	// 範囲内データを登録
	foreach ($rentalReceiveAllNewObj as $obj) {
		$receiveSave = ORM::for_table(TBLRENTALRECEIVE)->create();
		setInsert($receiveSave, $userId);

		copyData($obj, $receiveSave, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
		$receiveSave->save();
	}
}
// 20231010 E_Add

ORM::get_db()->commit();

echo json_encode(getRental($rental->pid));

?>
