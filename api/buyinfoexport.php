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

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/買取決済.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

$codeLists = [];
// 売主対象コードList 0:メトロス開発 ,1:Royal House
$sellerCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '027')->where_null('deleteDate')->findArray();
$codeLists['seller'] = $sellerCodeList;
// 口座種別List
$accountTypeCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '026')->where_null('deleteDate')->findArray();
$codeLists['accountType'] = $accountTypeCodeList;

// 留保金の支払コードを取得
$paymentCodeByretainage = '';
$codes = ORM::for_table(TBLCODE)->where_Like('code', 'SYS1%')->order_by_asc('code')->findArray();
if(sizeof($codes) > 0) {
    foreach($codes as $code) {
        if($code['codeDetail'] === 'retainage') {
            $paymentCodeByretainage = $code['name'];
        }
    }
}

// 仕入契約情報を取得
$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($param->pid)->asArray();
// 土地情報を取得
$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('seller')->select('residence')->findOne($contract['tempLandInfoPid'])->asArray();
// 仕入契約者情報を取得
$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->select('contractorName')->select('contractorAdress')->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
$contractorName = '';// 契約者名
foreach($sellers as $seller) {
    $contractorName = $seller['contractorName'];
    break;
}

// 支払契約情報を取得
$payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $contract['tempLandInfoPid'])->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
// 支払契約詳細情報を取得
$contractFixDay = '';   // 支払確定日
$contractFixTime = '';  // 支払時間
foreach($payContracts as $pay) {
    $details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    if(sizeof($details) > 0) {
        foreach($details as $detail) {
            if($detail['paymentCode'] === $paymentCodeByretainage) {
                $contractFixDay = $detail['contractFixDay'];
                $contractFixTime = $detail['contractFixTime'];
            }
        }
    }
}

// 所在地情報を取得
$locs = getLocation($contract['pid']);
$address = '';          // 所在地
$blockNumber = '';      // 地番
$buildingNumber = '';   // 家屋番号
$l_propertyTax = 0;     // 固定資産税（土地）
$l_cityPlanningTax = 0; // 都市計画税（土地）
$b_propertyTax = 0;     // 固定資産税（建物）
$b_cityPlanningTax = 0; // 都市計画税（建物）
$cntlocs = 0;
foreach($locs as $loc) {
    $cntlocs++;

    if($cntlocs == 1) {
        $address = $loc['address'];
        $blockNumber = $loc['blockNumber'];
        $buildingNumber = $loc['buildingNumber'];
    }
    // 区分が01：土地の場合
    if($loc['locationType'] === '01') {
        $l_propertyTax += $loc['propertyTax'];
        $l_cityPlanningTax += $loc['cityPlanningTax'];
    }
    else {
        $b_propertyTax += $loc['propertyTax'];
        $b_cityPlanningTax += $loc['cityPlanningTax'];
    }
}
$blockOrBuildingNumber = $blockNumber;
if($blockOrBuildingNumber !== '') $blockOrBuildingNumber = $buildingNumber;

$endColumn = 13;// 最終列数
$endRow = 43;   // 最終行数

for($i = 0 ; $i < 4; $i++) {

    // シート取得
    $sheet = $spreadsheet->getSheet($i);

    // ・地権者振込一覧シート
    // 居住表示
    $cell = setCell(null, $sheet, 'residence', 1, $endColumn, 1, $endRow, $bukken['residence']);
    // 契約物件番号
    $cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
    // 支払確定日
    $cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'Y年m月d日'));
    // 買主氏名<-売主対象
    $sellerName = '';
    if($bukken['seller'] === '0') $sellerName = '（株）' . getCodeTitle($codeLists['seller'], $bukken['seller']);
    else if($bukken['seller'] === '1') $sellerName = getCodeTitle($codeLists['seller'], $bukken['seller']);
    $cell = setCell(null, $sheet, 'sellerName', 1, $endColumn, 1, $endRow, $sellerName);
    // 契約担当者
    $contractStaffName = getUserName($contract['contractStaff']);
    $cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);
    // 日時
    $contractFixDateTime = convert_dt($contractFixDay, 'Y/m/d');
    if($contractFixDateTime !== '') $contractFixDateTime .= '  ';
    if($contractFixTime !== '') $contractFixDateTime .= $contractFixTime. '～';
    $cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
    // 契約書番号
    $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
    // 地番/家屋番号
    $cell = setCell(null, $sheet, 'blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $blockOrBuildingNumber);
    // 地権者（売主）<-契約者名
    $cell = setCell(null, $sheet, 'contractorName', 1, $endColumn, 1, $endRow, $contractorName);
    // 振込口座名義<-名義
    $cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $contract['bankName']);
    // 銀行・信用金庫等<-銀行名
    $cell = setCell(null, $sheet, 'bank', 1, $endColumn, 1, $endRow, $contract['bank']);
    // 支店
    $cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $contract['branchName']);
    // 口座種類<-口座種別
    $cell = setCell(null, $sheet, 'accountTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $contract['accountType']));
    // 口座番号<-口座
    $cell = setCell(null, $sheet, 'accountName', 1, $endColumn, 1, $endRow, $contract['accountName']);
    
    // ・決済案内シート
    // 支払確定日
    $cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji_MM', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'm月'));
    $cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_MMdd', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'm月d日'));
    // 支払時間
    $cell = setCell(null, $sheet, 'contractFixTime', 1, $endColumn, 1, $endRow, $contractFixTime);
    // 売買代金
    $cell = setCell(null, $sheet, 'tradingPrice', 1, $endColumn, 1, $endRow, formatNumber($contract['tradingPrice'], false));
    
    // ・固都税精算シート
    // 物件所在地<-所在地
    $cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
    // 支払確定日
    $cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay));
    // 固定資産税（土地）
    $cell = setCell(null, $sheet, 'l_propertyTax', 1, $endColumn, 1, $endRow, $l_propertyTax);
    // 都市計画税（土地）
    $cell = setCell(null, $sheet, 'l_cityPlanningTax', 1, $endColumn, 1, $endRow, $l_cityPlanningTax);
    // 固定資産税（建物）
    $cell = setCell(null, $sheet, 'b_propertyTax', 1, $endColumn, 1, $endRow, $b_propertyTax);
    // 都市計画税（建物）
    $cell = setCell(null, $sheet, 'b_cityPlanningTax', 1, $endColumn, 1, $endRow, $b_cityPlanningTax);
    // 分担期間開始日
    $cell = setCell(null, $sheet, 'sharingStartDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contract['sharingStartDay']));
    // 分担期間終了日
    $cell = setCell(null, $sheet, 'sharingEndDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contract['sharingEndDay']));
    // 分担期間開始日（買主）
    $sharingStartDayBuyer = $contract['sharingEndDay'];
    if($sharingStartDayBuyer !== '')
    {
        $sharingStartDayBuyer = date('Ymd', strtotime('+1 day', strtotime($sharingStartDayBuyer)));
    }
    $cell = setCell(null, $sheet, 'sharingStartDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingStartDayBuyer));
    // 分担期間終了日（買主）
    $sharingEndDayBuyer = $contract['sharingStartDay'];
    if($sharingEndDayBuyer !== '')
    {
        $sharingEndDayBuyer = date('Ymd', strtotime('+1 year', strtotime($sharingEndDayBuyer)));
        $sharingEndDayBuyer = date('Ymd', strtotime('-1 day', strtotime($sharingEndDayBuyer)));
    }
    $cell = setCell(null, $sheet, 'sharingEndDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingEndDayBuyer));
    // 固都税清算金（土地）
    $cell = setCell(null, $sheet, 'fixedLandTax', 1, $endColumn, 1, $endRow, formatNumber($contract['fixedLandTax'], false));
    // 固都税清算金（建物）
    $cell = setCell(null, $sheet, 'fixedBuildingTax', 1, $endColumn, 1, $endRow, formatNumber($contract['fixedBuildingTax'], false));
    // 建物分消費税
    $cell = setCell(null, $sheet, 'fixedBuildingTaxOnlyTax', 1, $endColumn, 1, $endRow, formatNumber($contract['fixedBuildingTaxOnlyTax'], false));
}

// 保存
$filename = '買取決済_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

/**
 * セルに値設定
 */
function setCell($cell, $sheet, $keyWord, $startColumn, $endColumn, $startRow, $endRow, $value) {
    // セルに指定がある場合
    if($cell != null) {
        // 対象のセルを開始位置とする
        $startColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $startRow = $cell->getRow();
    }
    // キーワードのセルを探す
    $cell = searchCell($sheet, $keyWord, $startColumn, $endColumn, $startRow, $endRow);
    // 対象のセルが存在する場合
    if($cell != null) {
        // キーワードを置換する
        $setValue = str_replace('$' . $keyWord . '$', $value, $cell->getValue());
        // 値を設定する
        $setColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $setRow = $cell->getRow();
        $sheet->setCellValueByColumnAndRow($setColumn, $setRow, $setValue);
    }
    return $cell;
}

/**
 * ユーザー名称取得
 */
function getUserName($val) {
    $ret = '';

    $list = explode(',', $val);
    foreach ($list as $userId)
    {
        $lst = ORM::for_table(TBLUSER)->where('userId', $userId)->findArray();
        if(sizeof($lst) > 0)
        {
            if(strlen($ret) > 0) $ret .= ',';
            $ret .= $lst[0]['userName'];
        }
    }
    return $ret;
}

/**
 * 所在地情報取得
 */
function getLocation($contractPid) {
    $lst = ORM::for_table(TBLCONTRACTDETAILINFO)
    ->table_alias('p1')
    ->select('p2.locationType', 'locationType')
    ->select('p2.address', 'address')
    ->select('p2.blockNumber', 'blockNumber')
    ->select('p2.buildingNumber', 'buildingNumber')
    ->select('p2.propertyTax', 'propertyTax')
    ->select('p2.cityPlanningTax', 'cityPlanningTax')
    ->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
    ->where('p1.contractDataType', '01')
    ->where('p1.contractInfoPid', $contractPid)
    ->order_by_asc('p1.pid')->findArray();
    return $lst;
}

?>
