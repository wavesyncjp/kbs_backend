<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = null;

//更新
if(isset($param->pid) && $param->pid > 0){
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->find_one($param->pid);
	setUpdate($receivecontract, $param->updateUserId);
	$userId = $param->updateUserId;
}
//登録
else {
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->create();
	setInsert($receivecontract, $param->createUserId);
	$userId = $param->createUserId;
}
ORM::get_db()->beginTransaction();

copyData($param, $receivecontract, array('pid', 'details', 'land', 'contractDayMap','contractFixDayMap','taxEffectiveDayMap', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$receivecontract->save();

//入金管理詳細
if(isset($param->details)){
	foreach ($param->details as $detail){
		//削除
		if(isset($detail->deleteUserId) && $detail->deleteUserId > 0) {
			$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->find_one($detail->pid);
			$detailSave->delete();
			setRentalReceiveFlgToFalse(
				isset($detail->rentalReceivePid) ? $detail->rentalReceivePid : null,
				$userId
			);
		}
		else {
			if(isset($detail->pid) && $detail->pid > 0){
				$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->find_one($detail->pid);
				setUpdate($detailSave, $param->updateUserId);
				// 20260116 S_Add
				synchronizeRentalReceives(
					isset($detail->rentalReceivePid) ? $detail->rentalReceivePid : null,
					isset($detail->contractFixDay) ? $detail->contractFixDay : null,
					isset($detail->receivePriceTax) ? $detail->receivePriceTax : null,
					$userId
				);
				// 20260116 E_Add
			}
			else {
				$detailSave = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->create();
				setInsert($detailSave, isset($param->updateUserId) && $param->updateUserId ? $param->updateUserId : $param->createUserId);
			}
			copyData($detail, $detailSave, array('pid', 'deleteUserId', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
			$detailSave->receiveContractPid = $receivecontract->pid;
			if($receivecontract->tempLandInfoPid > 0){
				$detailSave->tempLandInfoPid = $receivecontract->tempLandInfoPid;
			}
			$detailSave->save();
		}
	}
	ORM::get_db()->commit();
}
echo json_encode(getReceiveContractInfo($receivecontract->pid));

//setContractByReceive($receivecontract, $userId);
//setSaleByReceive($receivecontract, $userId);

// 20260116 S_Add
/**
 * 賃貸入金テーブルを更新する(入金管理と同期させる)
 *
 * @param integer|null $rentalReceivePid
 * @param string|null $receiveDay
 * @param integer|null $receivePrice
 * @param integer|null $userId
 * @return void
 */
function synchronizeRentalReceives(?int $rentalReceivePid, ?string $receiveDay, ?int $receivePrice, int $userId) {
	if(!$rentalReceivePid) {
		return;
	}
	$rentalReceive = ORM::for_table(TBLRENTALRECEIVE)
		->where_null('deleteDate')
		->find_one($rentalReceivePid);
	if(!$rentalReceive) return;

	$rentalReceive->receiveDay = $receiveDay;
	$rentalReceive->receivePrice = $receivePrice;
	setUpdate($rentalReceive, $userId);
	$rentalReceive->save();
	return;
}

/**
 * 賃貸入金の入金フラグをオフにする
 *
 * @param integer|null $rentalReceivePid
 * @param integer $userId
 * @return void
 */
function setRentalReceiveFlgToFalse(?int $rentalReceivePid, int $userId) {
	if(!$rentalReceivePid) {
		return;
	}
	$rentalReceive = ORM::for_table(TBLRENTALRECEIVE)
		->where_null('deleteDate')
		->find_one($rentalReceivePid);
	if(!$rentalReceive) return;

	$rentalReceive->receiveFlg = false;
	setUpdate($rentalReceive, $userId);
	$rentalReceive->save();
	return;
}
// 20260116 E_Add

?>