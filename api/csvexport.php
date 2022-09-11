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
    // 20210103 S_Update
    /*$columns[] = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];*/
    if($csvDetail['itemColumn'] != '-') {
        $columns[] = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
    }
    // 20210103 E_Update
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
            ORDER BY tblsharerinfo.tempLandInfoPid, tbllocationinfo.displayOrder, tblsharerinfo.locationInfoPid, tblsharerinfo.pid';
}
// 対象テーブルが03:仕入契約情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '03') {
    // 【ノート】
    // 1:Nの項目を設定

    // 所在地
    $selectContent = str_replace('tbllocationinfo.address', '(SELECT GROUP_CONCAT(address) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as address', $selectContent);
    // 地番
    $selectContent = str_replace('tbllocationinfo.blockNumber', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(blockNumber, \'\'))) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as blockNumber', $selectContent);
    // 家屋番号
    $selectContent = str_replace('tbllocationinfo.buildingNumber', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(buildingNumber, \'\'))) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as buildingNumber', $selectContent);
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
    // 20220526 S_Add
    // 支払予定日（支払契約情報）
    $selectContent = str_replace('tblpaycontract.contractDayHeader', 'tblpaycontract.contractDay as contractDayHeader', $selectContent);
    // 支払確定日（支払契約情報）
    $selectContent = str_replace('tblpaycontract.contractFixDayHeader', 'tblpaycontract.contractFixDay as contractFixDayHeader', $selectContent);
    // 20220526 E_Add

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

    // プラン依頼日
    $selectContent = str_replace('tblbukkenplaninfo.planRequestDay', '(SELECT GROUP_CONCAT(planRequestDay) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planRequestDay', $selectContent);
    // プラン依頼先
    $selectContent = str_replace('tblbukkenplaninfo.planRequest', '(SELECT GROUP_CONCAT(planRequest) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planRequest', $selectContent);
    // 取得予定日
    $selectContent = str_replace('tblbukkenplaninfo.planScheduledDay', '(SELECT GROUP_CONCAT(planScheduledDay) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planScheduledDay', $selectContent);
    // プラン価格
    $selectContent = str_replace('tblbukkenplaninfo.planPrice', '(SELECT GROUP_CONCAT(planPrice) FROM tblbukkenplaninfo WHERE tbltemplandinfo.pid = tempLandInfoPid) as planPrice', $selectContent);

    $query = 'SELECT ' . $selectContent . ' FROM tbltemplandinfo
            WHERE tbltemplandinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid';
}
// 20210103 S_Add
// 対象テーブルが06:売り契約情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '06') {
    // 【ノート】
    // 1:Nの項目を設定

    // 所在地
    $selectContent = str_replace('tbllocationinfo.address', '(SELECT GROUP_CONCAT(address) FROM tbllocationinfo WHERE tempLandInfoPid = tblbukkensalesinfo.tempLandInfoPid AND locationType IN (\'01\',\'02\') ORDER BY locationType) as address', $selectContent);
    // 地番
    $selectContent = str_replace('tbllocationinfo.blockNumber', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(blockNumber, \'\'))) FROM tbllocationinfo WHERE FIND_IN_SET(pid, tblbukkensalesinfo.salesLocation) > 0) as blockNumber', $selectContent);
    // 地積
    /*$selectContent = str_replace('tbllocationinfo.area', '(SELECT SUM(area) FROM tbllocationinfo WHERE FIND_IN_SET(pid, tblbukkensalesinfo.salesLocation) > 0) as area', $selectContent);*/ // 20210107 Delete
    
    // 20220329 S_Update
    /*
    $query = 'SELECT ' . $selectContent . ' FROM tblbukkensalesinfo
            INNER JOIN tbltemplandinfo ON tblbukkensalesinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblbukkensalesinfo.tempLandInfoPid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid, tblbukkensalesinfo.pid';
    */
    $query = 'SELECT ' . $selectContent . ' FROM tblbukkensalesinfo
            INNER JOIN tbltemplandinfo ON tblbukkensalesinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblbukkensalesinfo.tempLandInfoPid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid, tblbukkensalesinfo.displayOrder, tblbukkensalesinfo.pid';
    // 20220329 S_Update
}
// 対象テーブルが07:支払調書情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '07') {
    // 【ノート】
    // 1:Nの項目を設定

    // 所在地
    $selectContent = str_replace('tbllocationinfo.address', '(SELECT GROUP_CONCAT(address) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType IN (\'01\',\'02\') ORDER BY locationType) as address', $selectContent);
    // 地番
    $selectContent = str_replace('tbllocationinfo.blockNumber', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(blockNumber, \'\'))) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType = \'01\' AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType IN (\'01\',\'03\'))) as blockNumber', $selectContent);
    // 地積
    $selectContent = str_replace('tbllocationinfo.area', '(SELECT SUM(area) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType = \'01\' AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as area', $selectContent);
    // 権利形態
    $selectContent = str_replace('tbllocationinfo.rightsForm', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(rightsForm, \'\'))) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType IN (\'01\',\'02\') AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as rightsForm', $selectContent);
    // 家屋番号
    $selectContent = str_replace('tbllocationinfo.buildingNumber', '(SELECT GROUP_CONCAT(CONCAT(IFNULL(buildingNumber, \'\'))) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType IN (\'02\',\'04\') AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as buildingNumber', $selectContent);
    // 延床面積
    $selectContent = str_replace('tbllocationinfo.grossFloorArea', '(SELECT SUM(grossFloorArea) FROM tbllocationinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid AND locationType IN (\'02\',\'04\') AND EXISTS (SELECT 1 FROM tblcontractdetailinfo WHERE locationInfoPid = tbllocationinfo.pid AND contractInfoPid = tblcontractinfo.pid AND contractDataType = \'01\')) as grossFloorArea', $selectContent);
    // 契約者名
    $selectContent = str_replace('tblcontractsellerinfo.contractorName', '(SELECT GROUP_CONCAT(contractorName) FROM tblcontractsellerinfo WHERE contractInfoPid = tblcontractinfo.pid) as contractorName', $selectContent);
    // 契約者住所
    $selectContent = str_replace('tblcontractsellerinfo.contractorAdress', '(SELECT GROUP_CONCAT(contractorAdress) FROM tblcontractsellerinfo WHERE contractInfoPid = tblcontractinfo.pid) as contractorAdress', $selectContent);
    // 売却先
    $selectContent = str_replace('tblbukkensalesinfo.salesName', '(SELECT GROUP_CONCAT(salesName) FROM tblbukkensalesinfo WHERE tempLandInfoPid = tblcontractinfo.tempLandInfoPid) as salesName', $selectContent);

    $query = 'SELECT ' . $selectContent . ' FROM tblcontractinfo
            INNER JOIN tbltemplandinfo ON tblcontractinfo.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblcontractinfo.pid IN (' . $param->ids . ')
            ORDER BY tbltemplandinfo.pid, tblcontractinfo.contractNumber';
}
// 20210103 E_Add
// 20210804 S_Add
// 対象テーブルが08:支払契約詳細情報（明細単位）の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '08') {
    $query = 'SELECT ' . $selectContent . ' FROM tblpaycontractdetail
            INNER JOIN tblpaycontract ON tblpaycontractdetail.payContractPid = tblpaycontract.pid
            INNER JOIN tbltemplandinfo ON tblpaycontractdetail.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblpaycontractdetail.pid IN (' . $param->ids . ')
            ORDER BY tblpaycontractdetail.tempLandInfoPid, tblpaycontractdetail.pid';
}
// 対象テーブルが09:仕訳情報の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '09') {
    $query = 'SELECT ' . $selectContent . ' FROM tblsorting
            INNER JOIN tbltemplandinfo ON tblsorting.tempLandInfoPid = tbltemplandinfo.pid
            WHERE tblsorting.pid IN (' . $param->ids . ')
            ORDER BY tblsorting.tempLandInfoPid';
}
// 20210804 E_Add
// 20220912 S_Add
// 対象テーブルが10:仕入契約情報（月間契約件数取得）の場合
else if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '10') {
    $query = 'SELECT ' . $selectContent . ', substring(tblcontractinfo.contractDay, 1, 6) AS month, count(*) AS qty FROM tblcontractinfo
            WHERE tblcontractinfo.pid IN (' . $param->ids . ')
            GROUP BY substring(tblcontractinfo.contractDay, 1, 6), ' . $selectContent . '
            ORDER BY substring(tblcontractinfo.contractDay, 1, 6), ' . $selectContent;
}
// 20220912 E_Add

$res = ORM::raw_execute($query);
$statement = ORM::get_last_statement();
$rows = array();

$ret = [];// 返却データ

// ヘッダーを設定
$header = [];
foreach($csvDetails as $csvDetail) {
    // $header[] = '"' . $csvDetail['itemName'] . '"';
    $header[$csvDetail['itemColumn']] = '"' . $csvDetail['itemName'] . '"';
}
// 20220912 S_Update
/*
$ret[] = implode(',', $header);// 配列をカンマ区切りの文字列に変換

// データを設定
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $line = convertCsv($row, $csvDetails, $csvInfo);
    $ret[] = implode(',', $line);
}
*/
// 対象テーブルが10:仕入契約情報（月間契約件数取得）の場合
if(isset($csvInfo['targetTableCode']) && $csvInfo['targetTableCode'] === '10') {
    $header['depCode'] = '部署名';// タイトルに部署名追加
    
    $targets = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        if(empty($row['contractStaff']) || empty($row['month'])) continue;

        // ヘッダー追加
        $month = '"' . $row['month'] . '"';
        if(!isset($header[$month]))
        {
            $header[$month] = '"' . convert_dt($row['month'] . '01', 'n月') . '"';
        }

        // 契約担当者を分割
        $staff = $row['contractStaff'];
        if(strpos($staff, ',') !== false) {
            $staffs = explode(',', $staff);
        }
        else $staffs[] = $staff;

        foreach($staffs as $contractStaff) {
            // key=契約担当者
            $key = $contractStaff;
            // グルーピングを行う
            if(!isset($targets[$key])) {
                $groups = [];
                $groups = $row;
                $groups['contractStaff'] = $contractStaff;
                // 部署コード
                $user = ORM::for_table(TBLUSER)->find_one($contractStaff);
                $groups['depCode'] = $user['depCode'];
                // 権限が03:営業ではない場合、対象外
                if($user['authority'] !== '03') continue;
                $groups[$month] = $row['qty'];
                $targets[$key] = $groups;
            } else {
                $groups = $targets[$key];
                // 件数を加算する
                if(!isset($groups[$month])) {
                    $groups[$month] = $row['qty'];
                }
                else {
                    $qty = $groups[$month];
                    $groups[$month] = $qty + $row['qty'];
                }
                $targets[$key] = $groups;
            }
        }
    }
    $ret[] = implode(',', $header);
    foreach ($targets as $groups) {
        $line = convertCsv($groups, $csvDetails, $csvInfo);

        // 部署名追加
        $line[] = '"' . convertMaster($groups['depCode'], 'depCode') . '"';

        // 契約月を追加
        foreach ($header as $key => $value) {
            $chk = str_replace('"', '', $key);
            if(!is_numeric($chk)) continue;

            if(isset($groups[$key])) {
                $line[] = '"' . $groups[$key] . '"';
            }
            else $line[] = '"0"';
        }
        $ret[] = implode(',', $line);
    }
}
else {
    $ret[] = implode(',', $header);// 配列をカンマ区切りの文字列に変換
    
    // データを設定
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $line = convertCsv($row, $csvDetails, $csvInfo);
        $ret[] = implode(',', $line);
    }
}
// 20220912 E_Update

// 行結合
$csv = array('data' => implode('\r\n', $ret));

echo json_encode($csv);
exit;

/**
 * カラムを設定
 */
function convertCsv($row, $csvDetails, $csvInfo) {
    $ret = [];
    foreach($csvDetails as $csvDetail) {
//        $columnName = strtolower($csvDetail['itemTable']).'.'.$csvDetail['itemColumn'];
        $columnName = $csvDetail['itemColumn'];

        // 20210105 S_Add
        $columnVal = $row[$columnName];
        if($csvInfo['csvCode'] === '0301' && $columnName === 'rightsForm') {
            if(strpos($columnVal, '01') !== false) {
                $columnVal = '01';
            }
            else if(strpos($columnVal, '03') !== false) {
                $columnVal = '03';
            }
            else if(strpos($columnVal, '02') !== false) {
                $columnVal = '02';
            }
            else {
                $columnVal = '';
            }
        }
        // 20210105 E_Add
        
        // 複数区分に指定がある場合
        if($csvDetail['multipleType'] != '') {
            // 20200105 S_Update
            /*$ret[] = '"' . convertValueMulti($row[$columnName], $csvDetail['multipleType'], $csvDetail['conversionType'], $csvDetail['conversionCode']) . '"';*/
            $ret[] = '"' . convertValueMulti($columnVal, $csvDetail['multipleType'], $csvDetail['conversionType'], $csvDetail['conversionCode']) . '"';
            // 20200105 E_Update
        }
        // 複数区分に指定がない場合
        else {
            // 20200105 S_Update
            /*$ret[] = '"' . convertValue($row[$columnName], $csvDetail['conversionType'], $csvDetail['conversionCode']) . '"';*/
            $ret[] = '"' . convertValue($columnVal, $csvDetail['conversionType'], $csvDetail['conversionCode']) . '"';
            // 20200105 E_Update
        }
    }
    return $ret;
}

/**
 * 値を変換（複数指定）
 */
function convertValueMulti($val, $multipleType, $conversionType, $conversionCode) {
    $lsts = explode(',', $val);// カンマ区切りの文字列を配列に変換
//    $lsts = array_filter(explode(',', $val), 'strlen');

    $retVal = '';
    $index = 1;

    foreach($lsts as $lst) {
        // 複数区分が01:・区切りの場合
        if($multipleType === '01') {
            if($index > 1) $retVal .= '・';
        }
        // 複数区分が02:TOP1の場合
        else if($multipleType === '02') {
            if($index > 1) break;
        }
        // 20210103 S_Add
        // 複数区分が03:，区切りの場合
        else if($multipleType === '03') {
            if($index > 1) $retVal .= '，';
        }
        // 複数区分が04:，区切り（２件まで）の場合
        else if($multipleType === '04') {
            if($index > 1 && $index < 3) $retVal .= '，';
            if($index > 2) {
                $retVal .= '他';
                break;
            }
        }
        // 20210103 E_Add

        $retVal .= convertValue($lst, $conversionType, $conversionCode);
        $index++;
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
    // 20210804 S_Add
    // 変換区分が5:固定値の場合
    else if($conversionType == 5) {
        return $val = $conversionCode;
    }
    // 20210804 E_Add
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
    // 変換コードがpaymentCode:支払種別の場合
    else if($conversionCode === 'paymentCode') {
        $lst = ORM::for_table(TBLPAYMENTTYPE)->where(array(
            'paymentCode' => $val
        ))->select('paymentName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['paymentName'];
        }
    }
    // 変換コードがcontractSellerInfoPid:仕入契約者の場合
    else if($conversionCode === 'contractSellerInfoPid') {
        $lst = ORM::for_table(TBLCONTRACTSELLERINFO)->where(array(
            'pid' => $val
        ))->select('contractorName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['contractorName'];
        }
    }
    // 20210804 S_Add
    else if($conversionCode === 'kanjyoCode') {
        $lst = ORM::for_table(TBLKANJYO)->where(array(
            'kanjyoCode' => $val
        ))->select('kanjyoName')->findOne();
    
        if(isset($lst) && $lst) {
            return $lst->asArray()['kanjyoName'];
        }
    }
    // 20210804 E_Add
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