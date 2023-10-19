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
// 賃貸の建物名を取得
else if ($searchFor == 'searchRentalApartment') {
	$datas = getRentalApartments($param->contractInfoPid);
}
// 契約の立退き一覧を取得
else if ($searchFor == 'searchEvictionForContract') {
	$datas = getEvictionInfos($param->contractInfoPid, null);
}
echo json_encode($datas);

/**
 * 入居者検索
 */
function searchResident($param) {
	$query = ORM::for_table(TBLRESIDENTINFO)->where_null('deleteDate')->where_not_null('roomNo');

	if (isset($param->locationInfoPid) && $param->locationInfoPid !== '') {
		$query = $query->where('locationInfoPid', $param->locationInfoPid);
	}

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
		->where_null('p1.deleteDate');

	$query = $query->where('p1.contractInfoPid', $contractInfoPid);
	return $query->order_by_desc('p1.pid')->findArray();
}
?>
