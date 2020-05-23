<?php
require("../vendor/autoload.php");
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}


$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//$csvInfo = ORM::for_table(TBLCSVINFO)->where('csvCode', $param->csvCode)->asArray();
$csvInfo = ORM::for_table(TBLCSVINFO)->where('csvCode', $param->csvCode)->find_one();

$csvDetails = ORM::for_table(TBLCSVINFODETAIL)->where('csvCode', $param->csvCode)->order_by_asc('outputOrder')->findArray();

$columns = [];
foreach($csvDetails as $csvDetail) {
    $columns[] = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
}

// 対象テーブルが01:土地情報の場合
if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '01') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tbltemplandinfo
            WHERE tbltemplandinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid';
}
// 対象テーブルが02:共有者情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '02') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tblsharerinfo
            INNER JOIN tbllocationinfo ON tblsharerinfo.locationInfoPid = tbllocationinfo.pid
            INNER JOIN tbltemplandinfo ON tblsharerinfo.tempLandInfoPid = tbltemplandinfo.pid
                    AND tbllocationinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblsharerinfo.tempLandInfoPid IN (' . $param->ids . ')
            ORDER BY tblsharerinfo.tempLandInfoPid, tblsharerinfo.locationInfoPid';
}
// 対象テーブルが03:仕入契約情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '03') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tblcontractinfo
            INNER JOIN tbltemplandinfo ON tblcontractinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblsharerinfo.tempLandInfoPid IN (' . $param->ids . ')
            AND EXISTS (
                SELECT 1 FROM tblcontractdetailinfo
                WHERE tblcontractinfo.tempLandInfoPid = tblcontractdetailinfo.tempLandInfoPid
                AND tblcontractdetailinfo.contractDataType = \'01\'
            )
            ORDER BY tbltemplandinfo.pid, tblcontractinfo.contractNumber';
}
// 対象テーブルが04:支払契約詳細情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '04') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tblpaycontractdetail
            INNER JOIN tblpaycontract ON tblpaycontractdetail.payContractPid = tblpaycontract.pid
            INNER JOIN tbltemplandinfo ON tblpaycontractdetail.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblpaycontractdetail.pid IN (' . $param->ids . ')
            ORDER BY tblpaycontractdetail.tempLandInfoPid, tblpaycontractdetail.pid';
}
// 対象テーブルが05:物件プラン情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '05') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tbltemplandinfo
            WHERE tbltemplandinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid';
}

$res = ORM::raw_execute($query);
$statement = ORM::get_last_statement();
$rows = array();

$ret = [];

//ヘッダー
$header = [];
foreach($csvDetails as $csvDetail) {
    $header[] = $csvDetail['itemName'];
}
$ret[] = implode(',', $header);

//データ取得
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    
    $line = convertCsv($row, $csvDetails);
    $ret[] = implode(',', $line);
}

//行結合
$csv = array('data' => implode('\r\n', $ret));

echo json_encode($csv);
exit;

//データ変換
function convertCsv($row, $csvDetails) {
    $ret = [];
    foreach($csvDetails as $csvDetail) {
        $columnName = $csvDetail['itemColumn'];
        $ret[] = convertValue($row[$columnName], $csvDetail['conversionType'], $csvDetail['conversionCode']);
    }
    return $ret;
}

/**
 * 変換
 */
function convertValue($val, $conversionType, $conversionCode) {

    if($conversionType == 1) {
        return convertDate($val);
    }

    if($conversionType == 3) {
        return convertCode($val, $conversionCode);
    }
    return $val;
}

/**
 * 日付
 */
function convertDate($val) {
    if(!isset($val) || $val == '') return '';

    return date_format(date_create($val), 'Y/m/d');
}

/**
 * コード変換
 */
function convertCode($val, $conversionCode) {
    if(!isset($val)) return '';
    $lst = ORM::for_table(TBLCODE)->where(array(
        'code' => $conversionCode,
        'codeDetail' => $val
    ))->select('name')->findOne();

    if(isset($lst) && $lst) {
        return $lst->asArray()['name'];
    }
    return '';
}

?>