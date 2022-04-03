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
// 20220331 S_Add
// 支払種別List
$paymentTypeList = ORM::for_table(TBLPAYMENTTYPE)->select('paymentCode', 'codeDetail')->select('paymentName', 'name')->where_null('deleteDate')->findArray();
$codeLists['paymentType'] = $paymentTypeList;
// 20220331 E_Add

// 決済代金の支払コードを取得
$paymentCodeByDecisionPrice = '';
// 20220331 S_Add
$paymentCodeByIntermediaryPrice = '';   // 仲介手数料
$paymentCodeByOutsourcingPrice = '';    // 業務委託料
// 20220331 E_Add
$codes = ORM::for_table(TBLCODE)->where_Like('code', 'SYS1%')->order_by_asc('code')->findArray();
if(sizeof($codes) > 0) {
    foreach($codes as $code) {
        if($code['codeDetail'] == 'decisionPrice') {
            $paymentCodeByDecisionPrice = $code['name'];
        }
        // 20220331 S_Add
        else if($code['codeDetail'] == 'intermediaryPrice') {
            $paymentCodeByIntermediaryPrice = $code['name'];
        }
        else if($code['codeDetail'] == 'outsourcingPrice') {
            $paymentCodeByOutsourcingPrice = $code['name'];
        }
        // 20220331 E_Add
    }
}

// 仕入契約情報を取得
//$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($param->pid)->asArray();
$contracts = ORM::for_table(TBLCONTRACTINFO)->where_in('pid', $param->ids)->where_null('deleteDate')->findArray();

foreach($contracts as $contract) {
    // 土地情報を取得
    $bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('seller')->select('residence')->findOne($contract['tempLandInfoPid'])->asArray();
    // 仕入契約者情報を取得
    $sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->select('contractorName')->select('contractorAdress')->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    $contractorName = '';// 契約者名
    foreach($sellers as $seller) {
        $contractorName = $seller['contractorName'];
        break;
    }
    // 20220118 S_Add
    $list_contractorName = getContractorName($sellers);// 複数契約者名
    // 20220118 E_Add

    // 支払契約情報を取得
    $payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $contract['tempLandInfoPid'])->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    // 支払契約詳細情報を取得
    $contractFixDay = '';   // 支払確定日
    $contractFixTime = '';  // 支払時間
    $payPriceTax = '';      // 支払金額（税込） 20220223 Add
    // 20220331 S_Add
    $payDetail_intermediary = null;// 支払契約詳細情報（仲介料）
    $payDetail_outsourcing = null; // 支払契約詳細情報（業務委託料）
    // 20220331 E_Add
    foreach($payContracts as $pay) {
        $details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
        if(sizeof($details) > 0) {
            foreach($details as $detail) {
                if($detail['paymentCode'] == $paymentCodeByDecisionPrice) {
                    $contractFixDay = $detail['contractFixDay'];
                    $contractFixTime = $detail['contractFixTime'];
                    $payPriceTax = $detail['payPriceTax'];// 20220223 Add
                }
                // 20220331 S_Add
                // 支払コードが仲介料の場合
                else if($detail['paymentCode'] == $paymentCodeByIntermediaryPrice) {
                    $payDetail_intermediary = $detail;
                    $pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_intermediary['payContractPid'])->asArray();
                    $payDetail_intermediary = array_merge($pay, $payDetail_intermediary);
                }
                // 支払コードが業務委託料の場合
                else if($detail['paymentCode'] == $paymentCodeByOutsourcingPrice) {
                    $payDetail_outsourcing = $detail;
                    $pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_outsourcing['payContractPid'])->asArray();
                    $payDetail_outsourcing = array_merge($pay, $payDetail_outsourcing);
                }
                // 20220331 E_Add
            }
        }
    }
    // 20220331 S_Add
    // 仕入契約者情報（物件単位）を取得
    $sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->table_alias('p1')
        ->select('p1.pid')->select('p1.contractorName')->select('p1.contractorAdress')
        ->inner_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p2.pid'), 'p2')
        ->where('p2.tempLandInfoPid', $contract['tempLandInfoPid'])
        ->where_null('p1.deleteDate')
        ->order_by_asc('p1.pid')->findArray();
    
    $list_contractorName_intermediary = getPayContractorName($sellers, $payDetail_intermediary); // 複数契約者名（仲介料）
    $list_contractorName_outsourcing = getPayContractorName($sellers, $payDetail_outsourcing);   // 複数契約者名（業務委託料）
    // 20220331 E_Add

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
        if($loc['locationType'] == '01') {
            $l_propertyTax += $loc['propertyTax'];
            $l_cityPlanningTax += $loc['cityPlanningTax'];
        }
        else {
            $b_propertyTax += $loc['propertyTax'];
            $b_cityPlanningTax += $loc['cityPlanningTax'];
        }
    }
    $blockOrBuildingNumber = $blockNumber;
    if(empty($blockOrBuildingNumber)) $blockOrBuildingNumber = $buildingNumber;
    // 20220118 S_Add
    $list_blockOrBuildingNumber = getBuildingNumber($locs);                 // 複数地番・複数家屋番号
    $addressAndBlockOrBuildingNumber = $address . $blockOrBuildingNumber;   // 所在地+地番/家屋番号
    if($cntlocs > 1) {
        $addressAndBlockOrBuildingNumber .= '　他';
    }
    // 20220118 E_Add

    $endColumn = 13;// 最終列数
    $endRow = 43;   // 最終行数

    // 20220331 S_Update
    // for($i = 0 ; $i < 4; $i++) {
    for($i = 0 ; $i < 6; $i++) {
    // 20220331 E_Update

        // シートをコピー
//        $sheet = $spreadsheet->getSheet($i);
        $sheet = clone $spreadsheet->getSheet($i);
        $title = $sheet->getTitle();
        $sheet->setTitle($title . '_' . $contract['contractNumber']);
        $spreadsheet->addSheet($sheet);

        // ・地権者振込一覧シート
        // 居住表示
        // 20220118 S_Update
        // $cell = setCell(null, $sheet, 'residence', 1, $endColumn, 1, $endRow, $bukken['residence']);
        $cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
        // 20220118 E_Update
        // 契約物件番号
        $cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
        // 支払確定日
        $cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'Y年m月d日'));
        // 買主氏名<-売主対象
        $sellerName = '';
        if($bukken['seller'] == '0') $sellerName = '（株）' . getCodeTitle($codeLists['seller'], $bukken['seller']);
        else if($bukken['seller'] == '1') $sellerName = getCodeTitle($codeLists['seller'], $bukken['seller']);
        $cell = setCell(null, $sheet, 'sellerName', 1, $endColumn, 1, $endRow, $sellerName);
        // 契約担当者
        $contractStaffName = getUserName($contract['contractStaff']);
        $cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);
        // 日時
        $contractFixDateTime = convert_dt($contractFixDay, 'Y/m/d');
        if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
        if(!empty($contractFixTime)) $contractFixDateTime .= $contractFixTime . '～';
        $cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
        // 20220331 S_Update
        // 契約書番号
        // $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
        for($j = 0 ; $j < 2; $j++) {
            // 契約書番号
            $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
        }
        // 20220331 E_Update
        // 地番/家屋番号
        $cell = setCell(null, $sheet, 'blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $blockOrBuildingNumber);
        // 地権者（売主）<-契約者名
        $cell = setCell(null, $sheet, 'contractorName', 1, $endColumn, 1, $endRow, $contractorName);
        // 20220118 S_Add
        // 20220331 S_Update
        // 複数地番/複数家屋番号
        // $cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
        for($j = 0 ; $j < 2; $j++) {
            // 契約書番号
            $cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
        }
        // 20220331 E_Update
        // 複数地権者（売主）<-契約者名
        $cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $list_contractorName);
        // 20220118 E_Add
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

        // 20220331 S_Add
        // ・支払依頼書帳票シート
        // 支払確定日
        $cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年m月d日'));
        // 仲介料
        // 日時
        $contractFixDateTime = convert_dt($payDetail_intermediary['contractFixDay'], 'Y/m/d');
        if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
        if(!empty($payDetail_intermediary['contractFixTime'])) $contractFixDateTime .= $payDetail_intermediary['contractFixTime'] . '～';
        $cell = setCell(null, $sheet, 'contractFixDateTime_intermediary', 1, $endColumn, 1, $endRow, $contractFixDateTime);
        // 支払先<-取引先名称
        $cell = setCell(null, $sheet, 'supplierName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['supplierName']);
        /*
        // 複数支払先<-契約者名
        $cell = setCell(null, $sheet, 'list_contractorName_intermediary', 1, $endColumn, 1, $endRow, $list_contractorName_intermediary);
        */
        // 振込口座名義<-名義
        $cell = setCell(null, $sheet, 'bankName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['bankName']);
        // 銀行・信用金庫等<-銀行名
        $cell = setCell(null, $sheet, 'bank_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['bank']);
        // 支店
        $cell = setCell(null, $sheet, 'branchName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['branchName']);
        // 口座種類<-口座種別
        $cell = setCell(null, $sheet, 'accountTypeName_intermediary', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $payDetail_intermediary['accountType']));
        // 口座番号<-口座
        $cell = setCell(null, $sheet, 'accountName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['accountName']);
        // 代金
        $cell = setCell(null, $sheet, 'payPriceTax_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['payPriceTax']);
        // 備考<-支払名称+備考
        $cell = setCell(null, $sheet, 'paymentName_intermediary', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail_intermediary['paymentCode']) . $payDetail_intermediary['detailRemarks']);
        // 業務委託料
        // 日時
        $contractFixDateTime = convert_dt($payDetail_outsourcing['contractFixDay'], 'Y/m/d');
        if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
        if(!empty($payDetail_outsourcing['contractFixTime'])) $contractFixDateTime .= $payDetail_outsourcing['contractFixTime'] . '～';
        $cell = setCell(null, $sheet, 'contractFixDateTime_outsourcing', 1, $endColumn, 1, $endRow, $contractFixDateTime);
        // 支払先<-取引先名称
        $cell = setCell(null, $sheet, 'supplierName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['supplierName']);
        /*
        // 複数支払先<-契約者名
        $cell = setCell(null, $sheet, 'list_contractorName_outsourcing', 1, $endColumn, 1, $endRow, $list_contractorName_outsourcing);
        */
        // 振込口座名義<-名義
        $cell = setCell(null, $sheet, 'bankName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['bankName']);
        // 銀行・信用金庫等<-銀行名
        $cell = setCell(null, $sheet, 'bank_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['bank']);
        // 支店
        $cell = setCell(null, $sheet, 'branchName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['branchName']);
        // 口座種類<-口座種別
        $cell = setCell(null, $sheet, 'accountTypeName_outsourcing', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $payDetail_outsourcing['accountType']));
        // 口座番号<-口座
        $cell = setCell(null, $sheet, 'accountName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['accountName']);
        // 代金
        $cell = setCell(null, $sheet, 'payPriceTax_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['payPriceTax']);
        // 備考<-支払名称+備考
        $cell = setCell(null, $sheet, 'paymentName_outsourcing', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail_outsourcing['paymentCode']) . $payDetail_outsourcing['detailRemarks']);
        // 20220331 E_Add

        // ・決済案内シート
        // 支払確定日
        $cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji_MM', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'm月'));
        $cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_MMdd', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'm月d日'));
        // 支払時間
        $cell = setCell(null, $sheet, 'contractFixTime', 1, $endColumn, 1, $endRow, $contractFixTime);
        // 売買代金
        // 20220223 S_Update
        // $cell = setCell(null, $sheet, 'tradingPrice', 1, $endColumn, 1, $endRow, $contract['tradingPrice']);
        $cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payPriceTax);
        // 20220223 E_Update

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
        if(!empty($sharingStartDayBuyer))
        {
            $sharingStartDayBuyer = date('Ymd', strtotime('+1 day', strtotime($sharingStartDayBuyer)));
        }
        $cell = setCell(null, $sheet, 'sharingStartDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingStartDayBuyer));
        // 分担期間終了日（買主）
        $sharingEndDayBuyer = $contract['sharingStartDay'];
        if(!empty($sharingEndDayBuyer))
        {
            $sharingEndDayBuyer = date('Ymd', strtotime('+1 year', strtotime($sharingEndDayBuyer)));
            $sharingEndDayBuyer = date('Ymd', strtotime('-1 day', strtotime($sharingEndDayBuyer)));
        }
        $cell = setCell(null, $sheet, 'sharingEndDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingEndDayBuyer));
        // 固都税清算金（土地）
        $cell = setCell(null, $sheet, 'fixedLandTax', 1, $endColumn, 1, $endRow, $contract['fixedLandTax']);
        // 固都税清算金（建物）
        $cell = setCell(null, $sheet, 'fixedBuildingTax', 1, $endColumn, 1, $endRow, $contract['fixedBuildingTax']);
        // 建物分消費税
        $cell = setCell(null, $sheet, 'fixedBuildingTaxOnlyTax', 1, $endColumn, 1, $endRow, $contract['fixedBuildingTaxOnlyTax']);
        // 20220118 S_Add
        // ・受領証シート
        // 物件所在地
        $cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
        // 20220118 E_Add
    }
}

// 20220331 S_Update
/*
// R-A　PK精算シートを保持
$sheet = clone $spreadsheet->getSheet(4);

// コピー元シート削除
for($i = 0 ; $i < 5; $i++) {
    $spreadsheet->removeSheetByIndex(0);
}
*/
// R-A　PK精算シートを保持
$sheet = clone $spreadsheet->getSheet(6);

// コピー元シート削除
for($i = 0 ; $i < 7; $i++) {
    $spreadsheet->removeSheetByIndex(0);
}
// 20220331 E_Update

// R-A　PK精算シートを一番右へ追加
$spreadsheet->addSheet($sheet);

// 保存
$filename = '買取決済_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath . '/' . $filename;
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
// 20220118 S_Add
/**
 * 地番or家屋番号取得（改行区切り）
 */
function getBuildingNumber($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if(!empty($data['blockNumber'])) $ret[] = mb_convert_kana($data['blockNumber'], 'kvrn');
            else if(!empty($data['buildingNumber'])) $ret[] = mb_convert_kana($data['buildingNumber'], 'kvrn');
        }
    }
    return implode(chr(10), $ret);
}
/**
 * 契約者取得（改行区切り）
 */
function getContractorName($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if(!empty($data['contractorName'])) $ret[] = mb_convert_kana($data['contractorName'], 'kvrn');
        }
    }
    return implode(chr(10), $ret);
}
// 20220118 E_Add
// 20220331 S_Add
/**
 * 契約者取得（支払契約詳細情報）（改行区切り）
 */
function getPayContractorName($sellers, $payDetail) {
    $ret = [];
    if(isset($sellers) && isset($payDetail['contractor'])) {
        $contractor = $payDetail['contractor'];
        $datas = [];
        // |で分割されている場合
        if(strpos($contractor, '|') !== false) {
            $explode1st = explode('|', $contractor);
        }
        else $explode1st[] = $contractor;
        foreach($explode1st as $explode1) {
            // ,で分割されている場合
            if(strpos($explode1, ',') !== false) {
                $explode2nd = explode(',', $explode1);
            }
            else $explode2nd[] = $explode1;
        }
        foreach($explode2nd as $explode2) {
            $datas[] = $explode2;
        }
        foreach($datas as $data) {
            foreach($sellers as $seller) {
                if(!empty($seller['contractorName']) && $seller['pid'] == $data) {
                    $ret[] = mb_convert_kana($seller['contractorName'], 'kvrn');
                }
            }
        }
    }
    return implode(chr(10), $ret);
}
// 20220331 E_Add
?>
