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

// CSV管理を取得
//$csvInfo = ORM::for_table(TBLCSVINFO)->where('csvCode', $param->csvCode)->asArray();
$csvInfo = ORM::for_table(TBLCSVINFO)->where('csvCode', $param->csvCode)->find_one();

// CSV管理詳細を取得
$csvDetails = ORM::for_table(TBLCSVINFODETAIL)->where('csvCode', $param->csvCode)->order_by_asc('outputOrder')->findArray();

// 対象カラムを設定
$columns = [];
foreach($csvDetails as $csvDetail) {
    $columns[] = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
}

// 【ノート】
// 対象データを取得する

$selectContent = implode(',', $columns);

// 対象テーブルが01:土地情報の場合
if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '01') {
    $query = 'SELECT ' . $selectContent . ' FROM tbltemplandinfo
            WHERE tbltemplandinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid';
}
// 対象テーブルが02:共有者情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '02') {
    $query = 'SELECT ' . $selectContent . ' FROM tblsharerinfo
            INNER JOIN tbllocationinfo ON tblsharerinfo.locationInfoPid = tbllocationinfo.pid
            INNER JOIN tbltemplandinfo ON tblsharerinfo.tempLandInfoPid = tbltemplandinfo.pid
                    AND tbllocationinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblsharerinfo.tempLandInfoPid IN (' . $param->ids . ')
            ORDER BY tblsharerinfo.tempLandInfoPid, tblsharerinfo.locationInfoPid';
}
// 対象テーブルが03:仕入契約情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '03') {
    // 【ノート】
    // 1:Nの項目を設定

    // 所在地
    $selectContent = str_replace('tbllocationinfo.address', '(SELECT GROUP_CONCAT(address) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid) as address', $selectContent);
    // 地番
    $selectContent = str_replace('tbllocationinfo.blockNumber', '(SELECT GROUP_CONCAT(blockNumber) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid) as blockNumber', $selectContent);
    // 家屋番号
    $selectContent = str_replace('tbllocationinfo.buildingNumber', '(SELECT GROUP_CONCAT(buildingNumber) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid) as buildingNumber', $selectContent);
    // 契約者名
    $selectContent = str_replace('tblcontractsellerinfo.contractorName', '(SELECT GROUP_CONCAT(contractorName) FROM tblcontractsellerinfo WHERE contractInfoPid = tblcontractinfo.pid) as contractorName', $selectContent);

    $query = 'SELECT ' . $selectContent . ' FROM tblcontractinfo
            INNER JOIN tbltemplandinfo ON tblcontractinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblcontractinfo.tempLandInfoPid IN (' . $param->ids . ')
            AND EXISTS (
                SELECT 1 FROM tblcontractdetailinfo
                WHERE contractInfoPid = tblcontractinfo.pid
                AND contractDataType = \'01\'
            )
            ORDER BY tbltemplandinfo.pid, tblcontractinfo.contractNumber';
}
// 対象テーブルが04:支払契約詳細情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '04') {
    $query = 'SELECT ' . $selectContent . ' FROM tblpaycontractdetail
            INNER JOIN tblpaycontract ON tblpaycontractdetail.payContractPid = tblpaycontract.pid
            INNER JOIN tbltemplandinfo ON tblpaycontractdetail.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblpaycontract.pid IN (' . $param->ids . ')
            ORDER BY tblpaycontractdetail.tempLandInfoPid, tblpaycontractdetail.pid';
}
// 対象テーブルが05:物件プラン情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '05') {
    // 【ノート】
    // 1:Nの項目を設定

    // プラン依頼先
    $selectContent = str_replace('tblbukkenplaninfo.planRequest', '(SELECT GROUP_CONCAT(planRequest) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planRequest', $selectContent);
    // プラン依頼日
    $selectContent = str_replace('tblbukkenplaninfo.planRequestDay', '(SELECT GROUP_CONCAT(planRequestDay) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planRequestDay', $selectContent);
    // 取得予定日
    $selectContent = str_replace('tblbukkenplaninfo.planScheduledDay', '(SELECT GROUP_CONCAT(planScheduledDay) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planScheduledDay', $selectContent);
    // プラン価格
    $selectContent = str_replace('tblbukkenplaninfo.planPrice', '(SELECT GROUP_CONCAT(planPrice) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planPrice', $selectContent);

    $query = 'SELECT ' . $selectContent . ' FROM tbltemplandinfo
            WHERE tbltemplandinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid';
}

$res = ORM::raw_execute($query);
$statement = ORM::get_last_statement();
$rows = array();

$ret = [];// 返却データ

// ヘッダーを設定
$header = [];
foreach($csvDetails as $csvDetail) {
    $header[] = $csvDetail['itemName'];
}
$ret[] = implode(',', $header);// 配列をカンマ区切りの文字列に変換

// データを設定
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $line = convertCsv($row, $csvDetails);
    $ret[] = implode(',', $line);
}

// 行結合
$csv = array('data' => implode('\r\n', $ret));

echo json_encode($csv);
exit;

/**
 * カラムを設定
 */
function convertCsv($row, $csvDetails) {
    $ret = [];
    foreach($csvDetails as $csvDetail) {
//        $columnName = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
        $columnName = $csvDetail['itemColumn'];
        
        // 複数区分に指定がある場合
        if($csvDetail['multipleType'] != '') {
            $ret[] = convertValueMulti($row[$columnName], $csvDetail['multipleType'], $csvDetail['conversionType'], $csvDetail['conversionCode']);
        }
        // 複数区分に指定がない場合
        else {
            $ret[] = convertValue($row[$columnName], $csvDetail['conversionType'], $csvDetail['conversionCode']);
        }
    }
    return $ret;
}

/**
 * 値を変換（複数指定）
 */
function convertValueMulti($val, $multipleType, $conversionType, $conversionCode) {
    $lsts = explore(',', $val);// カンマ区切りの文字列を配列に変換

    $retVal = '';

    foreach($lsts as $lst) {
        // 複数区分が01:・区切りの場合
        if($multipleType === '01') {
            if(!empty($retVal)) $retVal .= '・';
        }
        // 複数区分が02:TOP1の場合
        else if($multipleType === '02') {
            if(!empty($retVal)) break;
        }
        $retVal .= convertValue($lst, $conversionType, $conversionCode);
    }
    return $retVal;
}

/**
 * 値を変換
 */
function convertValue($val, $conversionType, $conversionCode) {
    // 変換区分が1:日付の場合
    if($conversionType == 1) {
        return convertDate($val);
    }
    // 変換区分が2:数値の場合
    else if($conversionType == 2) {
        return convertNumber($val);
    }
    // 変換区分が3:マスタ参照の場合
    else if($conversionType == 3) {
        return convertMaster($val, $conversionCode);
    }
    // 変換区分が4:パーセントの場合
    else if($conversionType == 4) {
        return convertPercent($val, $conversionCode);
    }
    return $val;
}

/**
 * 日付変換
 */
function convertDate($val) {
    if(!isset($val) || $val == '') return '';
    return date_format(date_create($val), 'Y/m/d');
}

/**
 * 数値変換
 */
function convertNumber($val) {
    if(!isset($val) || $val == '') return '';
    return number_format($val);
}

/**
 * マスタ変換
 */
function convertMaster($val, $conversionCode) {
    if(!isset($val)) return '';
    
    // 変換コードがuserId:ユーザーの場合
    if($conversionCode === 'userId') {
        $lst = ORM::for_table(TBLUSER)->where(array(
            'userId' => $val
        ))->select('userName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['userName'];
        }
    }
    // 変換コードがdepCode:部署の場合
    else if($conversionCode === 'depCode') {
        $lst = ORM::for_table(TBLDEPARTMENT)->where(array(
            'depCode' => $val
        ))->select('depName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['depName'];
        }
    }
    // 変換コードがdepCode:支払種別の場合
    else if($conversionCode === 'paymentCode') {
        $lst = ORM::for_table(TBLPAYMENTTYPE)->where(array(
            'paymentCode' => $val
        ))->select('paymentName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['paymentName'];
        }
    }
    // 上記以外の場合
    else {
        $lst = ORM::for_table(TBLCODE)->where(array(
            'code' => $conversionCode,
            'codeDetail' => $val
        ))->select('name')->findOne();

        if(isset($lst) && $lst) {
            return $lst->asArray()['name'];
        }
    }
    return '';
}

/**
 * パーセント変換
 */
function convertPercent($val) {
    if(!isset($val) || $val == '') return '';
    return $val . '%';
}

?>