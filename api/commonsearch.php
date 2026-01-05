<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$searchFor = $param->searchFor;
// 入居者を取得
if ($searchFor == 'searchResident') {
	$datas = searchResident($param);
}
else if ($searchFor == 'searchResident2') {
	$datas = searchResident2($param);
}
// 地番　家屋番号を取得
else if ($searchFor == 'searchLocationNumber') {
	$datas = searchLocationNumber($param);
}
// 所有者名を取得
else if ($searchFor == 'searchSellerName') {
	// 20231010 S_Update
	// $datas = searchSellerName($param->contractInfoPid);
	$datas = searchSellerName($param->contractInfoPid, $param->isGetMore == 1);
	// 20231010 E_Update
}
// 賃貸入金を取得
else if ($searchFor == 'searchRentalReceive') {
	$datas = getRentalReceives($param->rentalInfoPid);
}
// 賃貸契約・入金を取得
else if ($searchFor == 'searchRentalContract_Receive') {
	$datas = getRentalContract_Receives($param->rentalInfoPid);
}
// 20240229 S_Add
// 賃貸契約・入金を取得・立ち退き登録
else if ($searchFor == 'searchRentalContract_Receive_Eviction') {
	$datas = getRentalContract_Receives($param->rentalInfoPid);
	$datas->evictions = getEvictionInfos(null, null,$param->rentalInfoPid);
}
// 20240229 E_Add
// 賃貸の建物名を取得
else if ($searchFor == 'searchRentalApartment') {
	$datas = getRentalApartments($param->contractInfoPid);
}
// 契約の立退き一覧を取得
else if ($searchFor == 'searchEvictionForContract') {
	$datas = getEvictionInfos($param->contractInfoPid, null);
}
// 20231027 S_Add
// 契約を取得
else if ($searchFor == 'searchContractSimple') {
	$datas = searchContractSimple($param);
}
// 20231027 E_Add
// 20240123 S_Add
// 賃貸契約取得(計算用)
else if ($searchFor == 'getRentalContractsForCalc') {
	$datas = getRentalContractsForCalc($param->contractInfoPid);
}
// 20240123 E_Add
echo json_encode($datas);

/**
 * 入居者検索
 */
function searchResident($param) {
	// 20231019 S_Add
	$locationInfoPid = $param->locationInfoPid;
	if (isset($locationInfoPid) && $locationInfoPid !== '') {
		$locationInfoPidTemps = getLocationPidByBuilding($locationInfoPid);
		$locationInfoPid = !empty($locationInfoPidTemps) ? $locationInfoPidTemps: [$locationInfoPid];
	}
	// 20231019 E_Add
	$query = ORM::for_table(TBLRESIDENTINFO)->where_null('deleteDate')->where_not_null('roomNo');

	// 20231019 S_Update
	// if (isset($param->locationInfoPid) && $param->locationInfoPid !== '') {
	// 	$query = $query->where('locationInfoPid', $param->locationInfoPid);
	// }
	if (!empty($locationInfoPid)) {
		$query = $query->where_in('locationInfoPid', $locationInfoPid);
	}
	// 20231019 E_Update

	if (isset($param->tempLandInfoPid) && $param->tempLandInfoPid !== '') {
		$query = $query->where('tempLandInfoPid', $param->tempLandInfoPid);
	}
	return $query->order_by_asc('registPosition')->findArray();
}

/**
 * 所在地情報の地番・家屋番号検索
 */
function searchLocationNumber($param) {
	$query = ORM::for_table(TBLLOCATIONINFO)
		->table_alias('p1')
		->distinct()
		->select('p1.pid', 'locationInfoPid')
		->select('p1.blockNumber')
		->select('p1.buildingNumber')
		->select('p1.displayOrder')// 20230930 Add
		// 20231019 S_Add 一棟の建物
		->select('p1.ridgePid')
		->select('p3.address')
		->left_outer_join(TBLLOCATIONINFO, array('p1.ridgePid', '=', 'p3.pid'), 'p3')
		// 20231019 E_Add 一棟の建物
		->inner_join(TBLCONTRACTDETAILINFO, array('p1.pid', '=', 'p2.locationInfoPid'), 'p2')
		->where_null('p1.deleteDate')
		->where_null('p2.deleteDate');

	$query = $query->where('p2.contractInfoPid', $param->contractInfoPid);
	$query = $query->where('p2.contractDataType', '01');// 01：売主選択

	return $query->order_by_desc('p1.displayOrder')->find_array();
}

/**
 * 賃貸の建物名を取得
 * @param contractInfoPid 契約情報PID
 */
function getRentalApartments($contractInfoPid) {
	$query = ORM::for_table(TBLRENTALINFO)
		->table_alias('p1')
		->select('p1.pid')
		->select('p1.apartmentName')
		->select('p1.locationInfoPid')// 20231019 Add
		->where_null('p1.deleteDate');

	$query = $query->where('p1.contractInfoPid', $contractInfoPid);
	return $query->order_by_desc('p1.pid')->findArray();
}
// 20231027 S_Add
/**
 * 契約情報を取得
 * @param tempLandInfoPid 所在地PID
 */
function searchContractSimple($param) {
	$query = ORM::for_table(TBLCONTRACTINFO)
	->table_alias('p1')
	->select('p1.pid')
	->select('p1.contractNumber')
	->select('p1.tempLandInfoPid')
	->select('p1.decisionDay')// 20250418 Add
	->select('p1.successionDeposit')// 20250418 Add
	->select('p1.successionSecurityDeposit')// 20250418 Add
	->select('p2.bukkenNo')
	->select('p2.bukkenName')
	->select('p2.contractBukkenNo')// 20250909 Add
	->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p2.pid'), 'p2')
	->where_null('p1.deleteDate');

	if(isset($param->tempLandInfoPid)){
		$query = $query->where('p1.tempLandInfoPid', $param->tempLandInfoPid);
	}
	if(isset($param->contractInfoPid)){
		$query = $query->where('p1.pid', $param->contractInfoPid);
	}
	return $query->order_by_desc('p1.pid')->find_array();
}
// 20231027 E_Add
?>
