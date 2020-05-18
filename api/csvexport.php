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

$csvInfo = ORM::for_table(TBLCSVINFO)->where('csvCode', $param->csvCode)->asArray();

$csvDetails = ORM::for_table(TBLCSVINFODETAIL)->where('csvCode', $param->csvCode)->order_by_asc('outputOrder')->findArray();

$columns = [];
foreach($csvDetails as $csvDetail) {
    $columns[] = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
}

//地権者一覧
if($param->csvCode == '0102') {
    $query = 'SELECT ' . implode(',', $columns) . ' FROM tblsharerinfo
            INNER JOIN tbllocationinfo ON tblsharerinfo.locationInfoPid = tbllocationinfo.pid
            INNER JOIN tbltemplandinfo ON tblsharerinfo.tempLandInfoPid = tbltemplandinfo.pid 
                    AND tbllocationinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblsharerinfo.tempLandInfoPid = 19';
}

$res = ORM::raw_execute($query);
$statement = ORM::get_last_statement();
$rows = array();

$ret = [];

while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    
    $line = convertCsv($row, $csvDetails);
    $ret[] = implode(',', $line);
}

$csv = array('data' => implode('\r\n', $ret));

echo json_encode($csv);
exit;

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