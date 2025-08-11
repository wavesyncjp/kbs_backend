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
			// 20250804 S_Add
			->select_expr(
				'(SELECT GROUP_CONCAT(DISTINCT p4.banktransferNameKana SEPARATOR "、")
				FROM ' . TBLRENTALCONTRACT . ' p4
				WHERE p4.rentalInfoPid = p1.pid AND p4.deleteDate IS NULL
				)',
				'banktransferNameKanaListMap'
			)
			// 20250804 E_Add
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

// 20250804 S_Add
// 建物名_Like
if (isset($param->apartmentName_Like) && $param->apartmentName_Like !== '') {
	$query = $query->where_like('p1.apartmentName', $param->apartmentName_Like . '%');
}

if (isset($param->banktransferNameKana_Like) && $param->banktransferNameKana_Like !== '') {
	$subquery = ORM::for_table(TBLRENTALCONTRACT)
    ->table_alias('p1')
    ->distinct()
    ->select('p1.rentalInfoPid')
    ->where_like('p1.banktransferNameKana', $param->banktransferNameKana_Like . '%')
    ->where_null('p1.deleteDate')
    ->find_array();

	$ids = array_column($subquery, 'rentalInfoPid');

	if (!empty($ids)) {
		$query = $query->where_in('p1.pid', $ids);
	} else {
		$query = $query->where_raw('1 = 0');
	}
}
// 20250804 E_Add

$query = getQueryExpertTempland($param, $query, 'p3.pid');// 20250502 Add

$results = $query->order_by_desc('pid')->find_array();

echo json_encode($results);
?>
