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
	$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($param->pid);
	setDelete($contract, $param->deleteUserId);
	$contract->save();

	// 計画地の公図の削除
	$files = ORM::for_table(TBLCONTRACTFILE)->where('contractInfoPid', $param->pid)->findArray();
	if(isset($files)){
		foreach($files as $file){
			$contractFile = ORM::for_table(TBLCONTRACTFILE)->find_one($file['pid']);
			setDelete($contractFile, $param->deleteUserId);
			$contractFile->save();
		}
	}
	else {
		echo "DELETE CONTRACTFILE ERROR";
	}

	// 仕入契約詳細情報の削除
	$details = ORM::for_table(TBLCONTRACTDETAILINFO)->where('contractInfoPid', $param->pid)->findArray();
	if(isset($details)){
		foreach($details as $detail){
			$contractDetail = ORM::for_table(TBLCONTRACTDETAILINFO)->find_one($detail['pid']);
			setDelete($contractDetail, $param->deleteUserId);
			$contractDetail->save();

			// 仕入契約登記人情報の削除
			$registrants = ORM::for_table(TBLCONTRACTREGISTRANT)->where('contractInfoPid', $param->pid)->where('contractDetailInfoPid', $detail['pid'])->findArray();
			if(isset($registrants)){
				foreach($registrants as $registrant){
					$contractRegistrant = ORM::for_table(TBLCONTRACTREGISTRANT)->find_one($registrant['pid']);
					setDelete($contractRegistrant, $param->deleteUserId);
					$contractRegistrant->save();
				}
			}
			else {
				echo "DELETE CONTRACTREGISTRANT ERROR";
			}
		}
	}
	else {
		echo "DELETE CONTRACTDETAIL ERROR";
	}

	// 仕入契約者情報の削除
	$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $param->pid)->findArray();
	if(isset($sellers)){
		foreach($sellers as $seller){
			$contractSeller = ORM::for_table(TBLCONTRACTSELLERINFO)->find_one($seller['pid']);
			setDelete($contractSeller, $param->deleteUserId);
			$contractSeller->save();
		}
	}
	else {
		echo "DELETE CONTRACTSELLER ERROR";
	}

	// 20240328 S_Add
	// 賃貸情報の削除
	$rentals = ORM::for_table(TBLRENTALINFO)->where('contractInfoPid', $param->pid)->where_null('deleteDate')->findArray();
	if(isset($rentals)){
		foreach($rentals as $ren){
			deleteRental($ren['pid'], $param->deleteUserId);
		}
	}
	// 20240328 E_Add
}
else
{
	echo "DELETE CONTRACT ERROR";
}

?>