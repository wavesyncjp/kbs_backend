<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLRENTALINFO)
			->table_alias('p1')
			->distinct()
			->select('p1.*')
			->select('p3.bukkenNo')
			->select('p3.contractBukkenNo')
			// 20240201 S_Update
			// ->inner_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p2.pid'), 'p2')
			->left_outer_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p2.pid'), 'p2')
			// 20240201 E_Update
			->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p3.pid'), 'p3')
			->where_null('p1.deleteDate')
			->where_null('p2.deleteDate')
			->where_null('p3.deleteDate');

// 物件番号
if (isset($param->bukkenNo) && $param->bukkenNo !== '') {
	$query = $query->where('p3.bukkenNo', $param->bukkenNo);
}
// 契約物件番号
if (isset($param->contractBukkenNo) && $param->contractBukkenNo !== '') {
	$query = $query->where('p3.contractBukkenNo', $param->contractBukkenNo);
}
// 契約物件番号_Like
if (isset($param->contractBukkenNo_Like) && $param->contractBukkenNo_Like !== '') {
	$query = $query->where_like('p3.contractBukkenNo', $param->contractBukkenNo_Like . '%');
}

// 所有権移転日（開始）
if (isset($param->ownershipRelocationDate_From) && $param->ownershipRelocationDate_From != '') {
	$query = $query->where_gte('p1.ownershipRelocationDate', $param->ownershipRelocationDate_From);
}
// 所有権移転日（終了）
if (isset($param->ownershipRelocationDate_To) && $param->ownershipRelocationDate_To != '') {
	$query = $query->where_lte('p1.ownershipRelocationDate', $param->ownershipRelocationDate_To);
}
// 有効区分
if (isset($param->validType) && $param->validType !== '') {
	$query = $query->where('p1.validType', $param->validType);
}

// 入金口座
if(isset($param->bankPid) && $param->bankPid !== '') {
	$query = $query->where('p1.bankPid', $param->bankPid);
}

$query = getQueryExpertTempland($param, $query, 'p3.pid');// 20250502 Add

$results = $query->order_by_desc('pid')->find_array();

echo json_encode($results);
?>
