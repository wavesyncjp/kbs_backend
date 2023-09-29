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

$userId = null;

ORM::get_db()->beginTransaction();

// 更新
if (isset($param->pid) && $param->pid > 0) {
	$rental = ORM::for_table(TBLRENTALINFO)->find_one($param->pid);
	setUpdate($rental, $param->updateUserId);
	$userId = $param->updateUserId;
	//所在地情報PID変更チェック
	if ($rental->locationInfoPid != $param->locationInfoPid) {
		$isChangedLocPid = true;
		$locationInfoPid = $rental->locationInfoPid;
		//賃貸契約
		$rentalContracts = ORM::for_table(TBLRENTALCONTRACT)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
		//賃貸入金
		$rentalReceives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $rental->pid)->where_null('deleteDate')->find_many();
	}
}
// 登録
else {
	$rental = ORM::for_table(TBLRENTALINFO)->create();
	setInsert($rental, $param->createUserId);
	$userId = $param->createUserId;
}

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
if (isset($rentalReceivesChanged)) {

	// 入金契約Pid
	$receiveContractPids = array();

	foreach ($rentalReceivesChanged as $rev) {
		$revDB = ORM::for_table(TBLRENTALRECEIVE)->find_one($rev->pid);
		if ($isChangedLocPid && $revDB->locationInfoPid == $locationInfoPid) {
			continue;
		}
		else {
			setUpdate($revDB, $userId);
			$revDB->receiveFlg = $rev->receiveFlg;
			$revDB->save();

			if ($rev->receiveFlg == '1') { //入金済
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

ORM::get_db()->commit();

echo json_encode(getRental($rental->pid));

?>
