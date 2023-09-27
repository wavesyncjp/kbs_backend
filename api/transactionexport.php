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
$filePath = $fullPath.'/取引成立台帳.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 買主シート
$sheet = $spreadsheet->getSheet(0);

// 最終列数
$endColumn = 25;
// 最終行数
$endRow = 200;

// 土地情報を取得
$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('seller')->select('residence')->findOne($param->pid)->asArray();

$codeLists = [];
// 売主対象コードList 0:メトロス開発 ,1:Royal House
$sellerCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '027')->where_null('deleteDate')->findArray();
$codeLists['seller'] = $sellerCodeList;
// 地目コードList
$landCategoryCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '002')->where_null('deleteDate')->findArray();
$codeLists['landCategory'] = $landCategoryCodeList;
// 権利形態コードList
$rightsFormCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '011')->where_null('deleteDate')->findArray();
$codeLists['rightsForm'] = $rightsFormCodeList;
// 種類コードList
$dependTypeCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '003')->where_null('deleteDate')->findArray();
$codeLists['dependType'] = $dependTypeCodeList;

$cntContracts = 0;

// 仕入契約情報を取得
$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $param->pid)->where_not_null('contractNow')->where_not_equal('contractNow', '')->where_null('deleteDate')->order_by_asc('pid')->findArray();
foreach($contracts as $contract) {
    $cntContracts++;

    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(0);
    $sheet->setTitle('買主' . $cntContracts);
    $spreadsheet->addSheet($sheet);

    // 列・行の位置を初期化
    $currentColumn = 1;
    $currentRow = 1;
    $cell = null;

    // 契約物件番号
    $cell = setCell($cell, $sheet, 'contractBukkenNo', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['contractBukkenNo']);
    // 契約書番号
    $cell = setCell($cell, $sheet, 'contractFormNumber', $currentColumn, $endColumn, $currentRow, $endRow, $contract['contractFormNumber']);
    // 成立年月日<-契約日
    $cell = setCell($cell, $sheet, 'contractDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($contract['contractDay']));
    // 引渡年月日<-決済日
    $cell = setCell($cell, $sheet, 'decisionDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($contract['decisionDay']));

    $cntSellers = 0;

    // 仕入契約者情報を取得
    $sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->select('contractorName')->select('contractorAdress')->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    
    $cell = searchCell($sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 仕入契約者情報が３件を超える場合
    if(sizeof($sellers) > 3) {
        // 売主氏名・売主住所の行をコピー
        copyBlockWithVal($sheet, $currentRow + 2, 1, sizeof($sellers) - 3, $endColumn);
    }
    foreach($sellers as $seller) {
        $cntSellers++;
        if($cntSellers == 1 && $seller['contractorName'] !== '') $sheet->setTitle($seller['contractorName'] . '様');

        // 売主氏名<-契約者名
        $cell = setCell(null, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $seller['contractorName']);
        // 売主住所<-契約者住所
        $cell = setCell($cell, $sheet, 'contractorAdress', $currentColumn, $endColumn, $currentRow, $endRow, $seller['contractorAdress']);
    }
    // 仕入契約者情報が３件未満の場合、Emptyを設定
    for ($i = 1; $i <= 3 - sizeof($sellers); $i++) {
        // 売主氏名<-Empty
        $cell = setCell(null, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 売主住所<-Empty
        $cell = setCell($cell, $sheet, 'contractorAdress', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }

    // 買主氏名<-売主対象
    $sellerName = '';
    if($bukken['seller'] === '0') $sellerName = '株式会社' . getCodeTitle($codeLists['seller'], $bukken['seller']);
    else if($bukken['seller'] === '1') $sellerName = getCodeTitle($codeLists['seller'], $bukken['seller']) . '株式会社';
    $cell = setCell($cell, $sheet, 'sellerName', $currentColumn, $endColumn, $currentRow, $endRow, $sellerName);
    // 物件所在地（住居表示）<-居住表示
    $cell = setCell($cell, $sheet, 'residence', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['residence']);

    $detailIds = [];
    // 仕入契約詳細情報を取得
    $details = ORM::for_table(TBLCONTRACTDETAILINFO)->select('locationInfoPid')->where('contractInfoPid', $contract['pid'])->where('contractDataType', '01')->where_null('deleteDate')->order_by_asc('pid')->findArray();
    foreach($details as $detail) {
        $detailIds[] = $detail['locationInfoPid'];
    }

    // 謄本情報を設定する
    // 20220615 S_Update
    // setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $detailIds, $codeLists);
    setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $detailIds, $codeLists, true);
    // 20220615 E_Update

    // 【ノート】
    // 売買代金以降は、毎回初期位置以降のセルを確認する

    $tradingColumn = 1; // 売買代金初期列番号
    $tradingRow = 1;    // 売買代金初期行番号
    $cell = searchCell($sheet, 'tradingLandPrice', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $tradingColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $tradingRow = $cell->getRow();
    }

    // 売買代金（土地）
    $cell = setCell(null, $sheet, 'tradingLandPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['tradingLandPrice'], false));
    // 売買代金（建物）
    $cell = setCell($cell, $sheet, 'tradingBuildingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['tradingBuildingPrice'], false));
    // 売買代金（借地権）
    $cell = setCell($cell, $sheet, 'tradingLeasePrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['tradingLeasePrice'], false));
    // 売買代金（消費税）
    $cell = setCell($cell, $sheet, 'tradingTaxPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['tradingTaxPrice'], false));
    // 売買代金
    $cell = setCell($cell, $sheet, 'tradingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['tradingPrice'], false));
    
    $arrayDayChk = array();// 20230508 Add

    // 手付金日付
    // 手付金
    $earnestPriceDay = '';
    $earnestPrice = '';
    // 20230508 S_Update
    // if($contract['earnestPriceDayChk'] === '1') {
    //     $earnestPriceDay = $contract['earnestPriceDay'];
    //     $earnestPrice = $contract['earnestPrice'];
    // }
    $earnestPriceDay = $contract['earnestPriceDay'];
    $earnestPrice = $contract['earnestPrice'];
    if($contract['earnestPriceDayChk'] === '1') {
        if(($earnestPriceDay != null && $earnestPriceDay != '') || ($earnestPrice != null && $earnestPrice != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($earnestPriceDay), '手付金', $earnestPrice));
        }
    }
    // 20230508 E_Update

    $cell = setCell(null, $sheet, 'earnestPriceDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($earnestPriceDay));
    $cell = setCell(null, $sheet, 'earnestPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($earnestPrice, false));
    // 内金①日付
    // 内金①
    $deposit1Day = '';
    $deposit1 = '';
    // 20230508 S_Update
    // if($contract['deposit1DayChk'] === '1') {
    //     $deposit1Day = $contract['deposit1Day'];
    //     $deposit1 = $contract['deposit1'];
    // }
    $deposit1Day = $contract['deposit1Day'];
    $deposit1 = $contract['deposit1'];
    if($contract['deposit1DayChk'] === '1') {
        if(($deposit1Day != null && $deposit1Day != '') || ($deposit1 != null && $deposit1 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($deposit1Day), '中間金', $deposit1));
        }
    }    
    // 20230508 E_Update
    $cell = setCell($cell, $sheet, 'deposit1Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit1Day));
    $cell = setCell($cell, $sheet, 'deposit1', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit1, false));
    // 内金②日付
    // 内金②
    $deposit2Day = '';
    $deposit2 = '';
    // 20230508 S_Update 
    // if($contract['deposit2DayChk'] === '1') {
    //     $deposit2Day = $contract['deposit2Day'];
    //     $deposit2 = $contract['deposit2'];
    // }
    $deposit2Day = $contract['deposit2Day'];
    $deposit2 = $contract['deposit2'];
    if($contract['deposit2DayChk'] === '1') {
        if(($deposit2Day != null && $deposit2Day != '') || ($deposit2 != null && $deposit2 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($deposit2Day), '中間金', $deposit2));
        }
    }    
    // 20230508 E_Update      
    $cell = setCell($cell, $sheet, 'deposit2Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit2Day));
    $cell = setCell($cell, $sheet, 'deposit2', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit2, false));
    // 内金③日付
    // 内金③
    $deposit3Day = '';
    $deposit3 = '';
    // 20230508 S_Update 
    // if($contract['deposit3DayChk'] === '1') {
    //     $deposit3Day = $contract['deposit3Day'];
    //     $deposit3 = $contract['deposit3'];
    // }
    $deposit3Day = $contract['deposit3Day'];
    $deposit3 = $contract['deposit3'];
    if($contract['deposit3DayChk'] === '1') {
        if(($deposit3Day != null && $deposit3Day != '') || ($deposit3 != null && $deposit3 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($deposit3Day), '中間金', $deposit3));
        }
    }    
    // 20230508 E_Update     
    $cell = setCell($cell, $sheet, 'deposit3Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit3Day));
    $cell = setCell($cell, $sheet, 'deposit3', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit3, false));
    // 内金④日付
    // 内金④
    $deposit4Day = '';
    $deposit4 = '';
    // 20230508 S_Update    
    // if($contract['deposit4DayChk'] === '1') {
    //     $deposit4Day = $contract['deposit4Day'];
    //     $deposit4 = $contract['deposit4'];
    // }
    $deposit4Day = $contract['deposit4Day'];
    $deposit4 = $contract['deposit4'];
    if($contract['deposit4DayChk'] === '1') {
        if(($deposit4Day != null && $deposit4Day != '') || ($deposit4 != null && $deposit4 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($deposit4Day), '中間金', $deposit4));
        }
    }
    // 20230508 E_Update  
    $cell = setCell($cell, $sheet, 'deposit4Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit4Day));
    $cell = setCell($cell, $sheet, 'deposit4', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit4, false));
    // 決済日
    // 決済代金
    $decisionDay = '';
    $decisionPrice = '';
    // 20230508 S_Update 
    // if($contract['decisionDayChk'] === '1') {
    //     $decisionDay = $contract['decisionDay'];
    //     $decisionPrice = $contract['decisionPrice'];
    // }
    $decisionDay = $contract['decisionDay'];
    $decisionPrice = $contract['decisionPrice'];
    if($contract['decisionDayChk'] === '1') {
        if(($decisionDay != null && $decisionDay != '') || ($decisionPrice != null && $decisionPrice != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($decisionDay), '決済代金', $decisionPrice));
        }
    }
    // 20230508 E_Update 
    $cell = setCell($cell, $sheet, 'decisionDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($decisionDay));
    $cell = setCell($cell, $sheet, 'decisionPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($decisionPrice, false));
    // 留保金支払(明渡)日
    // 留保金
    $cell = setCell($cell, $sheet, 'retainageDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($contract['retainageDay']));
    $cell = setCell($cell, $sheet, 'retainage', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['retainage'], false));
    // 20230508 S_Update 
    // // 固都税清算金
    // $cell = setCell($cell, $sheet, 'fixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['fixedTax'], false));
    // // 20211128 S_Add
    // // （内消費税相当額<-建物分消費税
    // $cell = setCell($cell, $sheet, 'fixedBuildingTaxOnlyTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['fixedBuildingTaxOnlyTax'], false));
    // // 20211128 E_Add
    // 固都税清算金
    $fixedTax = $contract['fixedTax'];
    $fixedTaxDay = $contract['fixedTaxDay'];
    // （内消費税相当額<-建物分消費税
    $fixedBuildingTaxOnlyTax = $contract['fixedBuildingTaxOnlyTax'];

    // 20230628 S_Add
    // 固都税清算金日付
    $cell = setCell($cell, $sheet, 'fixedTaxDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($fixedTaxDay));
    // 20230628 E_Add
    $cell = setCell($cell, $sheet, 'fixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($fixedTax, false));
    $cell = setCell($cell, $sheet, 'fixedBuildingTaxOnlyTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($fixedBuildingTaxOnlyTax, false));
    if($contract['fixedTaxDayChk'] === '1') {
        if(($fixedTaxDay != null && $fixedTaxDay != '') || ($fixedTax != null && $fixedTax != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($fixedTaxDay), '固都税精算金', $fixedTax));
        }
    }
    // 20230508 E_Update 
    // 仲介会社
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'intermediaryName', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['intermediaryName']);
    }
    // 仲介手数料
    $cell = setCell(null, $sheet, 'intermediaryPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['intermediaryPrice'], false));
    // 20230628 S_Add
    // 仲介手数料支払日
    $cell = setCell($cell, $sheet, 'intermediaryPricePayDay_YY', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt($contract['intermediaryPricePayDay'], 'year'));
    $cell = setCell($cell, $sheet, 'intermediaryPricePayDay_MM', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($contract['intermediaryPricePayDay'], 'm'));
    $cell = setCell($cell, $sheet, 'intermediaryPricePayDay_DD', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($contract['intermediaryPricePayDay'], 'd'));
    // 20230628 E_Add
    // 業務委託先
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'outsourcingName', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['outsourcingName']);
    }
    // 業務委託料
    $cell = setCell(null, $sheet, 'outsourcingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['outsourcingPrice'], false));
    // 20230628 S_Add
    // 業務委託料支払日
    $cell = setCell($cell, $sheet, 'outsourcingPricePayDay_YY', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt($contract['outsourcingPricePayDay'], 'year'));
    $cell = setCell($cell, $sheet, 'outsourcingPricePayDay_MM', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($contract['outsourcingPricePayDay'], 'm'));
    $cell = setCell($cell, $sheet, 'outsourcingPricePayDay_DD', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($contract['outsourcingPricePayDay'], 'd'));
    // 20230628 E_Add
    // 仲介会社住所
    $cell = setCell($cell, $sheet, 'intermediaryAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['intermediaryAddress']);
    // 業務委託先住所
    $cell = setCell($cell, $sheet, 'outsourcingAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['outsourcingAddress']);
    // 20220615 S_Add
    // 摘要<-備考
    $cell = setCell($cell, $sheet, 'remarks', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['remarks']);
    // 20220615 E_Add

    // 20230511 S_Add
    // 契約書等
    $maxRowAttach = 2;//最大件数
    $attaches = ORM::for_table(TBLCONTRACTATTACH)->select('attachFileDay')->select('attachFileDisplayName')->where('contractInfoPid', $contract['pid'])->where('attachFileChk', '1')->where_null('deleteDate')->where_raw('(attachFileDay is not null OR attachFileDisplayName is not null)')->order_by_desc('updateDate')->findArray();
    
    $cell = searchCell($sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 契約書等が最大件数件を超える場合
    if(sizeof($attaches) > $maxRowAttach) {
        // 添付ファイル日付 及び　表示名の行をコピー
        copyBlockWithVal($sheet, $currentRow, 1, sizeof($attaches) - $maxRowAttach, $endColumn);
    }
    foreach($attaches as $attach) {
        // 添付ファイル日付 及び　表示名
        $attachFileDay = $attach['attachFileDay'];
        $attachFileDisplayName = $attach['attachFileDisplayName'];
        if($attachFileDay != null && $attachFileDay != ''){
            $attachFileDay = convert_jpdt_kanji($attachFileDay) . "締結";
        }
        $cell = setCell(null, $sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow, $attachFileDisplayName . $attachFileDay);
    }
    // 契約書等が最大件数未満の場合、Emptyを設定
    for ($i = 1; $i <= $maxRowAttach - sizeof($attaches); $i++) {
        // 売主氏名<-Empty
        $cell = setCell(null, $sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }
    // 20230511 E_Add

    // 20230508 S_Add
    // 入出金履歴が複数ある場合、ブロックをコピー
    $lengthArrayDayChk = count($arrayDayChk);

    $cell = searchCell($sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }  
    $cellPrice = searchCell($sheet, 'historyPrice', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cellPrice != null) {
        $currentColumnPrice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cellPrice->getColumn());
    }   
    if($lengthArrayDayChk > 1) {
        copyBlockWithVal($sheet, $currentRow, 1, $lengthArrayDayChk - 1, $endColumn);
    }
    if($lengthArrayDayChk == 0){
        array_push($arrayDayChk, array('', '', ''));
        $lengthArrayDayChk = 1;
    }
    for($i = 0; $i < $lengthArrayDayChk; $i++) {
        $cell = setCell(null, $sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][0]);
        $cell = setCell($cell, $sheet, 'historyName', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][1]);
        $cell = setCell($cell, $sheet, 'historyPrice', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][2]);
    }
    $cell = searchCell($sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    } 
      
    $letter = getAlphabetFromIndex($currentColumnPrice);

    $cell = setCell($cell, $sheet, 'historyPriceSum', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(' .$letter . $currentRow . ':' . $letter . ($currentRow + $lengthArrayDayChk - 1) . ')');
    // 20230508 E_Add
    $sheet->setSelectedCell('A1');// 初期選択セル設定 20220608 Add
}
// コピー元買主シート削除
$spreadsheet->removeSheetByIndex(0);

// 売主シート
$sheet = $spreadsheet->getSheet(0);

$cntSales = 0;

// 物件売契約情報を取得
// 20220329 S_Update
// $sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
// 20220329 E_Update
foreach($sales as $sale) {
    $cntSales++;

    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(0);
    $sheet->setTitle('売主' . $cntSales);
    if($sale['salesName'] !== '') $sheet->setTitle($sale['salesName'] . '様');
    $spreadsheet->addSheet($sheet);

    $arrayDayChk = array();// 20230509 Add
    // 列・行の位置を初期化
    $currentColumn = 1;
    $currentRow = 1;
    $cell = null;

    // 契約物件番号
    $cell = setCell($cell, $sheet, 'contractBukkenNo', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['contractBukkenNo']);
    // 契約書番号
    $cell = setCell($cell, $sheet, 'contractFormNumber', $currentColumn, $endColumn, $currentRow, $endRow, $sale['contractFormNumber']);
    // 成立年月日<-契約日
    $cell = setCell($cell, $sheet, 'salesContractDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($sale['salesContractDay']));
    // 引渡年月日<-決済日
    $cell = setCell($cell, $sheet, 'salesDecisionDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($sale['salesDecisionDay']));
    // 売主氏名<-売主対象
    $sellerName = '';
    if($bukken['seller'] === '0') $sellerName = '株式会社' . getCodeTitle($codeLists['seller'], $bukken['seller']);
    else if($bukken['seller'] === '1') $sellerName = getCodeTitle($codeLists['seller'], $bukken['seller']) . '株式会社';
    $cell = setCell($cell, $sheet, 'sellerName', $currentColumn, $endColumn, $currentRow, $endRow, $sellerName);
    // 買主氏名<-売却先
    $cell = setCell($cell, $sheet, 'salesName', $currentColumn, $endColumn, $currentRow, $endRow, $sale['salesName']);
    // 買主住所<-売却先住所
    $cell = setCell($cell, $sheet, 'salesAddress', $currentColumn, $endColumn, $currentRow, $endRow, $sale['salesAddress']);
    // 物件所在地（住居表示）<-居住表示
    $cell = setCell($cell, $sheet, 'residence', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['residence']);

    // 売却先所在地をカンマで分割する
    $detailIds = explode(",", $sale['salesLocation']);

    // 謄本情報を設定する
    setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $detailIds, $codeLists);

    // 【ノート】
    // 売買代金以降は、毎回初期位置以降のセルを確認する

    $tradingColumn = 1; // 売買代金初期列番号
    $tradingRow = 1;    // 売買代金初期行番号
    $cell = searchCell($sheet, 'salesTradingLandPrice', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $tradingColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $tradingRow = $cell->getRow();
    }

    // 売買代金（土地）
    $cell = setCell(null, $sheet, 'salesTradingLandPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingLandPrice'], false));
    // 売買代金（建物）
    $cell = setCell($cell, $sheet, 'salesTradingBuildingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingBuildingPrice'], false));
    // 売買代金（借地権）
    $cell = setCell($cell, $sheet, 'salesTradingLeasePrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingLeasePrice'], false));
    // 売買代金（消費税）
    $cell = setCell($cell, $sheet, 'salesTradingTaxPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingTaxPrice'], false));
    // 売買代金
    $cell = setCell($cell, $sheet, 'salesTradingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingPrice'], false));
    // 20230510 S_Update
    // // 手付金日付
    // // 手付金
    // $cell = setCell(null, $sheet, 'salesEarnestPriceDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesEarnestPriceDay']));
    // $cell = setCell(null, $sheet, 'salesEarnestPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesEarnestPrice'], false));
    // // 内金①日付
    // // 内金①
    // $cell = setCell($cell, $sheet, 'salesDeposit1Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDeposit1Day']));
    // $cell = setCell($cell, $sheet, 'salesDeposit1', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDeposit1'], false));
    // // 内金②日付
    // // 内金②
    // $cell = setCell($cell, $sheet, 'salesDeposit2Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDeposit2Day']));
    // $cell = setCell($cell, $sheet, 'salesDeposit2', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDeposit2'], false));
    // // 決済日
    // // 決済代金
    // $cell = setCell($cell, $sheet, 'salesDecisionDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDecisionDay']));
    // $cell = setCell($cell, $sheet, 'salesDecisionPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDecisionPrice'], false));
    // // 留保金支払日
    // // 留保金
    // $cell = setCell($cell, $sheet, 'salesRetainageDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesRetainageDay']));
    // $cell = setCell($cell, $sheet, 'salesRetainage', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesRetainage'], false));
    // // 固都税清算金
    // $cell = setCell($cell, $sheet, 'salesFixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesFixedTax'], false));

    // 手付金日付
    // 手付金
    $salesEarnestPriceDay = $sale['salesEarnestPriceDay'];
    $salesEarnestPrice = $sale['salesEarnestPrice'];
    $cell = setCell(null, $sheet, 'salesEarnestPriceDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($salesEarnestPriceDay));
    $cell = setCell(null, $sheet, 'salesEarnestPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($salesEarnestPrice, false));
    if($sale['salesEarnestPriceDayChk'] === '1') {
        if(($salesEarnestPriceDay != null && $salesEarnestPriceDay != '') || ($salesEarnestPrice != null && $salesEarnestPrice != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($salesEarnestPriceDay), '手付金', $salesEarnestPrice));
        }
    }
    // 内金①日付
    // 内金①
    $salesDeposit1Day = $sale['salesDeposit1Day'];
    $salesDeposit1 = $sale['salesDeposit1'];
    $cell = setCell($cell, $sheet, 'salesDeposit1Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($salesDeposit1Day));
    $cell = setCell($cell, $sheet, 'salesDeposit1', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($salesDeposit1, false));
    if($sale['salesDeposit1DayChk'] === '1') {
        if(($salesDeposit1Day != null && $salesDeposit1Day != '') || ($salesDeposit1 != null && $salesDeposit1 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($salesDeposit1Day), '中間金', $salesDeposit1));
        }
    }
    // 内金②日付
    // 内金②
    $salesDeposit2Day = $sale['salesDeposit2Day'];
    $salesDeposit2 = $sale['salesDeposit2'];
    $cell = setCell($cell, $sheet, 'salesDeposit2Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($salesDeposit2Day));
    $cell = setCell($cell, $sheet, 'salesDeposit2', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($salesDeposit2, false));
    if($sale['salesDeposit2DayChk'] === '1') {
        if(($salesDeposit2Day != null && $salesDeposit2Day != '') || ($salesDeposit2 != null && $salesDeposit2 != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($salesDeposit2Day), '中間金', $salesDeposit2));
        }
    }
    // 決済日
    // 決済代金
    $salesDecisionDay = $sale['salesDecisionDay'];
    $salesDecisionPrice = $sale['salesDecisionPrice'];
    $cell = setCell($cell, $sheet, 'salesDecisionDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($salesDecisionDay));
    $cell = setCell($cell, $sheet, 'salesDecisionPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($salesDecisionPrice, false));
    if($sale['salesDecisionDayChk'] === '1') {
        if(($salesDecisionDay != null && $salesDecisionDay != '') || ($salesDecisionPrice != null && $salesDecisionPrice != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($salesDecisionDay), '決済代金', $salesDecisionPrice));
        }
    }
    // 留保金支払日
    // 留保金
    $cell = setCell($cell, $sheet, 'salesRetainageDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesRetainageDay']));
    $cell = setCell($cell, $sheet, 'salesRetainage', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesRetainage'], false));
    // 固都税清算金
    $salesFixedTaxDay = $sale['salesFixedTaxDay'];
    $salesFixedTax = $sale['salesFixedTax'];
    // 20230723 S_Add
    // 固都税清算金日付
    $cell = setCell($cell, $sheet, 'salesFixedTaxDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($salesFixedTaxDay));
    // 20230723 E_Add
    $cell = setCell($cell, $sheet, 'salesFixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($salesFixedTax, false));
    if($sale['salesFixedTaxDayChk'] === '1') {
        if(($salesFixedTaxDay != null && $salesFixedTaxDay != '') || ($salesFixedTax != null && $salesFixedTax != '')){
            array_push($arrayDayChk, array(convert_jpdt_kanji($salesFixedTaxDay), '固都税精算金', $salesFixedTax));
        }
    }
    // 20230510 E_Update

    // （内消費税相当額<-固都税清算金（消費税）
    // 20220608 S_Update
    // $cell = setCell($cell, $sheet, 'salesFixedConsumptionTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesFixedConsumptionTax'], false));
    $cell = setCell($cell, $sheet, 'salesFixedBuildingTaxOnlyTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesFixedBuildingTaxOnlyTax'], false));// 建物分消費税
    // 20220608 E_Update
    // 仲介会社
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'salesIntermediary', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesIntermediary']);
    }
    // 仲介手数料
    $cell = setCell(null, $sheet, 'salesBrokerageFee', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesBrokerageFee'], false));
    // 20230723 S_Add
    // 仲介手数料支払日
    $cell = setCell(null, $sheet, 'salesBrokerageFeePayDay_YY', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt($sale['salesBrokerageFeePayDay'], 'year'));
    $cell = setCell(null, $sheet, 'salesBrokerageFeePayDay_MM', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($sale['salesBrokerageFeePayDay'], 'm'));
    $cell = setCell(null, $sheet, 'salesBrokerageFeePayDay_DD', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($sale['salesBrokerageFeePayDay'], 'd'));
    // 20230723 E_Add
    // 業務委託先
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'salesOutsourcingName', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesOutsourcingName']);
    }
    // 業務委託料
    $cell = setCell(null, $sheet, 'salesOutsourcingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesOutsourcingPrice'], false));
    // 20230723 S_Add
    // 業務委託料支払日
    $cell = setCell(null, $sheet, 'salesOutsourcingPricePayDay_YY', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt($sale['salesOutsourcingPricePayDay'], 'year'));
    $cell = setCell(null, $sheet, 'salesOutsourcingPricePayDay_MM', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($sale['salesOutsourcingPricePayDay'], 'm'));
    $cell = setCell(null, $sheet, 'salesOutsourcingPricePayDay_DD', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_dt($sale['salesOutsourcingPricePayDay'], 'd'));
    // 20230723 E_Add
    // 仲介会社住所
    $cell = setCell($cell, $sheet, 'salesIntermediaryAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesIntermediaryAddress']);
    // 業務委託先住所
    $cell = setCell($cell, $sheet, 'salesOutsourcingAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesOutsourcingAddress']);
    // 20220615 S_Add
    // 摘要<-備考
    $cell = setCell($cell, $sheet, 'salesRemark', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesRemark']);
    // 20220615 E_Add

    // 20230511 S_Add
    // 契約書等
    $maxRowAttach = 2;//最大件数
    $attaches = ORM::for_table(TBLBUKKENSALESATTACH)->select('attachFileDay')->select('attachFileDisplayName')->where('bukkenSalesInfoPid', $sale['pid'])->where('attachFileChk', '1')->where_null('deleteDate')->where_raw('(attachFileDay is not null OR attachFileDisplayName is not null)')->order_by_desc('updateDate')->findArray();
    
    $cell = searchCell($sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 契約書等が最大件数件を超える場合
    if(sizeof($attaches) > $maxRowAttach) {
        // 添付ファイル日付 及び　表示名の行をコピー
        copyBlockWithVal($sheet, $currentRow, 1, sizeof($attaches) - $maxRowAttach, $endColumn);
    }
    foreach($attaches as $attach) {
        // 添付ファイル日付 及び　表示名
        $attachFileDay = $attach['attachFileDay'];
        $attachFileDisplayName = $attach['attachFileDisplayName'];
        if($attachFileDay != null && $attachFileDay != ''){
            $attachFileDay = convert_jpdt_kanji($attachFileDay) . "締結";
        }
        $cell = setCell(null, $sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow, $attachFileDisplayName . $attachFileDay);
    }
    // 契約書等が最大件数未満の場合、Emptyを設定
    for ($i = 1; $i <= $maxRowAttach - sizeof($attaches); $i++) {
        // 売主氏名<-Empty
        $cell = setCell(null, $sheet, 'attach', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }
    // 20230511 E_Add

    // 20230509 S_Add
    // 入出金履歴が複数ある場合、ブロックをコピー
    $lengthArrayDayChk = count($arrayDayChk);

    $cell = searchCell($sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }  
    $cellPrice = searchCell($sheet, 'historyPrice', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cellPrice != null) {
        $currentColumnPrice = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cellPrice->getColumn());
    }   
    if($lengthArrayDayChk > 1) {
        copyBlockWithVal($sheet, $currentRow, 1, $lengthArrayDayChk - 1, $endColumn);
    }
    if($lengthArrayDayChk == 0){
        array_push($arrayDayChk, array('', '', ''));
        $lengthArrayDayChk = 1;
    }
    for($i = 0; $i < $lengthArrayDayChk; $i++) {
        $cell = setCell(null, $sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][0]);
        $cell = setCell($cell, $sheet, 'historyName', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][1]);
        $cell = setCell($cell, $sheet, 'historyPrice', $currentColumn, $endColumn, $currentRow, $endRow, $arrayDayChk[$i][2]);
    }
    $cell = searchCell($sheet, 'historyDay', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    } 
      
    $letter = getAlphabetFromIndex($currentColumnPrice);

    $cell = setCell($cell, $sheet, 'historyPriceSum', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(' .$letter . $currentRow . ':' . $letter . ($currentRow + $lengthArrayDayChk - 1) . ')');
    // 20230509 E_Add
    $sheet->setSelectedCell('A1');// 初期選択セル設定 20220608 Add
}
// コピー元売主シート削除
$spreadsheet->removeSheetByIndex(0);

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定 20220608 Add

// 保存
$filename = '取引成立台帳_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

/**
 * 行と値コピー
 */
function copyBlockWithVal($sheet, $startPos, $blockRowCount, $copyCount, $colums) {
    $sheet->insertNewRowBefore($startPos, $blockRowCount * $copyCount);
    $lastPos = $startPos + ($blockRowCount * $copyCount);
    for($cursor = 0 ; $cursor < $copyCount ; $cursor++) {
        $copyPos = $startPos  + $blockRowCount * $cursor;
        copyRowsWithValue($sheet, $lastPos, $copyPos, $blockRowCount, $colums + 1);
    }
}

// 20230508 S_Add
function getAlphabetFromIndex($index) {
    $alphabet = range('A', 'Z');
    $alphabetLength = count($alphabet);
    
    if ($index < 1 || $index > $alphabetLength) {
      return null; 
    }
    
    return $alphabet[$index - 1];
}
// 20230508 E_Add
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
        // 値を設定する
        $setColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $setRow = $cell->getRow();
        $sheet->setCellValueByColumnAndRow($setColumn, $setRow, $value);
    }
    return $cell;
}

/**
 * 謄本情報を設定する
 */
// 20220615 S_Update
// function setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $pids, $codeLists) {
function setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $pids, $codeLists, $getBottom = false) {
// 20220615 E_Update
    $locsLand = [];     // 土地
    $locsBuilding = []; // 建物
    $locsBottom = [];   // 底地 20220615 Add

    if(sizeof($pids) > 0) {
        // 所在地情報を取得
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $pids)->where_null('deleteDate')->order_by_asc('pid')->findArray();
        foreach($locs as $loc) {
            // 区分が01：土地の場合
            if($loc['locationType'] === '01') {
                // 20220620 S_Add
                if($getBottom) {
                    $leasedArea = 0;// 借地対象面積

                    // 対象の土地を底地選択している建物を取得
                    // 20230922 S_Update
                    // $buildings = ORM::for_table(TBLLOCATIONINFO)->where('bottomLandPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
                    $buildings = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $loc['tempLandInfoPid'])->where('bottomLandPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
                    // 20230922 E_Update
                    if(sizeof($buildings) > 0) {
                        foreach($buildings as $building) {
                            // 底地情報を取得
                            // 20230922 S_Update
                            // $bottomLandInfos = ORM::for_table(TBLBOTTOMLANDINFO)->where('locationInfoPid', $building['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
                            $bottomLandInfos = ORM::for_table(TBLBOTTOMLANDINFO)->where('tempLandInfoPid', $building['tempLandInfoPid'])->where('locationInfoPid', $building['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
                            // 20230922 E_Update
                            if(sizeof($bottomLandInfos) > 0) {
                                foreach($bottomLandInfos as $bottomLandInfo) {
                                    $leasedArea += $bottomLandInfo['leasedArea'];
                                    $bottomLandInfo['bottomLandPid'] = $building['pid'];    // 底地PID<-建物PID
                                    $bottomLandInfo['lenderBorrower'] = '借主名';           // 貸主名/借主名
                                    $bottomLandInfo['leasedAreaTitle'] = '借地契約面積：';  // 借地対象面積タイトル
                                    $bottomLandInfo['leasedArea'] = $bottomLandInfo['leasedArea'] . '㎡';
                                    // 20230922 S_Add
                                    $bottomLandInfo['landRentTitle'] = '地代';
                                    // 20230922 E_Add
                                    $locsBottom[] = $bottomLandInfo;
                                }
                            }
                        }
                    }
                    $loc['leasedArea'] = $leasedArea;

                    // 20230922 S_Add
                    if(isset($loc['borrowerName']) && $loc['borrowerName'] !== '') {
                        // 入居者情報（駐車場）を取得
                        $residents = ORM::for_table(TBLRESIDENTINFO)->where('tempLandInfoPid', $loc['tempLandInfoPid'])->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
                        if(sizeof($residents) > 0) {
                            foreach($residents as $resident) {
                                $resident['bottomLandPid'] = '';                            // 底地PID
                                $resident['lenderBorrower'] = '借主名';                     // 貸主名/借主名
                                $resident['lenderBorrowerName'] = $resident['borrowerName'];// 借主氏名
                                $resident['leasedAreaTitle'] = '';                          // 借地対象面積タイトル
                                $resident['leasedArea'] = '';
                                $resident['landRentTitle'] = '賃料';
                                $resident['landRent'] = $resident['rentPrice'];             // 賃料
                                $locsBottom[] = $resident;
                            }
                        }
                    }
                    // 20230922 E_Add
                }
                // 20220620 E_Add
                $locsLand[] = $loc;
            }
            // 区分が02：建物の場合
            else if($loc['locationType'] === '02') {
                $locsBuilding[] = $loc;
                // 権利形態が01：借地権の場合
                // 20220615 S_Update
                // if($loc['rightsForm'] === '01' && $loc['bottomLandPid'] !== '') {
                if($getBottom && $loc['rightsForm'] === '01' && $loc['bottomLandPid'] !== '') {
                // 20220615 E_Update
                    // 20220611 S_Update
                    /*
                    // 底地を土地に追加
                    $bottomLand = ORM::for_table(TBLLOCATIONINFO)
                        ->select('address')
                        ->select('blockNumber')
                        ->select('landCategory')
                        ->select('leasedArea')
                        ->select('area')
                        ->find_one($loc['bottomLandPid']);
                    if(isset($bottomLand)) {
                        $bottomLand['rightsForm'] = '01';// 01：借地権
                        $locsLand[] = $bottomLand;

                        // 20210614 S_Add
                        // 底地情報が２行以上存在する場合、土地に追加する
                        $bottomLandInfos = ORM::for_table(TBLBOTTOMLANDINFO)
                            ->table_alias('p1')
                            ->inner_join(TBLLOCATIONINFO, array('p1.bottomLandPid', '=', 'p2.pid'), 'p2')
                            ->select('p2.address', 'address')
                            ->select('p2.blockNumber', 'blockNumber')
                            ->select('p2.landCategory', 'landCategory')
                            ->select('p1.leasedArea', 'leasedArea')
                            ->select('p2.area', 'area')
                            ->where('p1.locationInfoPid', $loc['pid'])
                            ->where_null('p1.deleteDate')
                            ->order_by_asc('p1.registPosition')
                            ->findArray();
                        if(isset($bottomLandInfos) && sizeof($bottomLandInfos) > 1) {
                            $cntBottomLandInfos = 0;
                            foreach($bottomLandInfos as $bottomLandInfo) {
                                $cntBottomLandInfos++;
                                if($cntBottomLandInfos > 1) {
                                    $bottomLandInfo['rightsForm'] = '01';// 01：借地権
                                    $locsLand[] = $bottomLandInfo;
                                }
                            }
                        }
                        // 20210614 E_Add
                    }
                    */
                    $bottomLandInfos = ORM::for_table(TBLBOTTOMLANDINFO)
                        ->table_alias('p1')
                        ->inner_join(TBLLOCATIONINFO, array('p1.bottomLandPid', '=', 'p2.pid'), 'p2')
                        ->select('p2.address', 'address')
                        ->select('p2.blockNumber', 'blockNumber')
                        ->select('p2.landCategory', 'landCategory')
                        ->select('p1.leasedArea', 'leasedArea')
                        ->select('p2.area', 'area')
                        // 20220615 S_Add
                        ->select('p1.tempLandInfoPid', 'tempLandInfoPid')
                        ->select('p1.locationInfoPid', 'locationInfoPid')
                        ->select('p1.bottomLandPid', 'bottomLandPid')
                        ->select('p1.landRent', 'landRent')
                        // 20220615 S_Add
                        ->where('p1.locationInfoPid', $loc['pid'])
                        ->where_null('p1.deleteDate')
                        ->order_by_asc('p1.registPosition')
                        ->findArray();
                    if(sizeof($bottomLandInfos) > 0) {
                        foreach($bottomLandInfos as $bottomLandInfo) {
                            $bottomLandInfo['rightsForm'] = '01';// 01：借地権
                            $locsLand[] = $bottomLandInfo;
                            // 20220615 S_Add
                            $bottomLandInfo['lenderBorrower'] = '貸主名';   // 貸主名/借主名
                            $bottomLandInfo['leasedAreaTitle'] = '';        // 借地対象面積タイトル
                            $bottomLandInfo['leasedArea'] = '';             // 借地対象面積
                            // 20230922 S_Add
                            $bottomLandInfo['landRentTitle'] = '地代';
                            // 20230922 E_Add
                            $locsBottom[] = $bottomLandInfo;
                            // 20220615 E_Add
                        }
                    }
                    // 20220611 E_Update
                }
            }
            // 区分が04：区分所有（専有）の場合
            else if($loc['locationType'] === '04') {
                // 一棟の建物の所在地を設定
                if($loc['ridgePid'] !== '') {
                    // 20220930 S_Update
                    /*
                    $ridge = ORM::for_table(TBLLOCATIONINFO)->select('address')->find_one($loc['ridgePid']);
                    if(isset($ridge)) $loc['address'] = $ridge['address'];
                    */
                    $ridge = ORM::for_table(TBLLOCATIONINFO)->select('address')->select('rightsForm')->select('bottomLandPid')->find_one($loc['ridgePid']);
                    if(isset($ridge)) {
                        $loc['address'] = $ridge['address'];

                        if($getBottom && $loc['rightsForm'] === '01' && $loc['bottomLandPid'] !== '') {
                            $bottomLandInfos = ORM::for_table(TBLBOTTOMLANDINFO)
                                ->table_alias('p1')
                                ->inner_join(TBLLOCATIONINFO, array('p1.bottomLandPid', '=', 'p2.pid'), 'p2')
                                ->select('p2.address', 'address')
                                ->select('p2.blockNumber', 'blockNumber')
                                ->select('p2.landCategory', 'landCategory')
                                ->select('p1.leasedArea', 'leasedArea')
                                ->select('p2.area', 'area')
                                // 20220615 S_Add
                                ->select('p1.tempLandInfoPid', 'tempLandInfoPid')
                                ->select('p1.locationInfoPid', 'locationInfoPid')
                                ->select('p1.bottomLandPid', 'bottomLandPid')
                                ->select('p1.landRent', 'landRent')
                                // 20220615 S_Add
                                ->where('p1.locationInfoPid', $loc['pid'])
                                ->where_null('p1.deleteDate')
                                ->order_by_asc('p1.registPosition')
                                ->findArray();
                            if(sizeof($bottomLandInfos) > 0) {
                                foreach($bottomLandInfos as $bottomLandInfo) {
                                    $bottomLandInfo['rightsForm'] = '01';// 01：借地権
                                    $locsLand[] = $bottomLandInfo;
                                    // 20220615 S_Add
                                    $bottomLandInfo['lenderBorrower'] = '貸主名';   // 貸主名/借主名
                                    $bottomLandInfo['leasedAreaTitle'] = '';        // 借地対象面積タイトル
                                    $bottomLandInfo['leasedArea'] = '';             // 借地対象面積
                                    // 20230922 S_Add
                                    $bottomLandInfo['landRentTitle'] = '地代';
                                    // 20230922 E_Add
                                    $locsBottom[] = $bottomLandInfo;
                                    // 20220615 E_Add
                                }
                            }
                        }
                    }
                    // 20220930 E_Update
                }
                $locsBuilding[] = $loc;
            }
        }
    }

    // 土地
    $cell = searchCell($sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }
    // 20220615 S_Add
    $mergeFrom = $currentRow;
    $mergeTo = $currentRow + 6;
    // セル結合を解除
    $sheet->unmergeCells('A' . $mergeFrom . ':A' . $mergeTo);
    // 20220615 E_Add

    // 所在地情報（土地）が複数存在する場合
    if(sizeof($locsLand) > 1) {
        // 20220615 S_Delete
        // セル結合を解除
        /*$sheet->unmergeCells('A' . $currentRow . ':A' . ($currentRow + 6));*/
        // 20220615 S_Delete

        // 土地の行をコピー
        copyBlockWithVal($sheet, $currentRow, 3, sizeof($locsLand) - 1, $endColumn);
        $mergeTo += (sizeof($locsLand) - 1) * 3;// 20220615 Add

        // 20220615 S_Delete
        // セルを再結合
        /*$mergeTo = $currentRow + (sizeof($locsLand) - 1) * 3 + 6;
        $sheet->mergeCells('A' . $currentRow . ':A' . $mergeTo);*/
        // 20220615 S_Delete
    }
    foreach($locsLand as $loc) {
        // 所 在（地番）
        $cell = setCell(null, $sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow, $loc['address'] . $loc['blockNumber']);
        // 地目
        $cell = setCell($cell, $sheet, 'l_landCategory', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($codeLists['landCategory'], $loc['landCategory']));
        // 借地対象面積
        $cell = setCell($cell, $sheet, 'l_leasedArea', $currentColumn, $endColumn, $currentRow, $endRow, $loc['leasedArea']);
        // 地積
        $cell = setCell($cell, $sheet, 'l_area', $currentColumn, $endColumn, $currentRow, $endRow, $loc['area']);
        // 権利の種類
        $rightsForm = getCodeTitle($codeLists['rightsForm'], $loc['rightsForm']);
        if($rightsForm === '') $rightsForm = '所有権';
        $cell = setCell($cell, $sheet, 'l_rightsForm', $currentColumn, $endColumn, $currentRow, $endRow, $rightsForm);
    }
    // 所在地情報（土地）が存在しない場合、Emptyを設定
    for ($i = 1; $i <= 1 - sizeof($locsLand); $i++) {
        // 所 在（地番）<-Empty
        $cell = setCell(null, $sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 地目<-Empty
        $cell = setCell($cell, $sheet, 'l_landCategory', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 借地対象面積<-Empty
        $cell = setCell($cell, $sheet, 'l_leasedArea', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 地積<-Empty
        $cell = setCell($cell, $sheet, 'l_area', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 権利の種類<-Empty
        $cell = setCell($cell, $sheet, 'l_rightsForm', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }

    // 20220615 S_Add
    // 底地
    $cell = searchCell($sheet, 'l2_lenderBorrower', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 底地が複数存在する場合
    if(sizeof($locsBottom) > 1) {
        // 底地の行をコピー
        copyBlockWithVal($sheet, $currentRow, 2, sizeof($locsBottom) - 1, $endColumn);
        $mergeTo += (sizeof($locsBottom) - 1) * 2;
    }

    // セルを再結合
    $sheet->mergeCells('A' . $mergeFrom . ':A' . $mergeTo);

    foreach($locsBottom as $loc) {
        // 貸主名/借主名
        $cell = setCell(null, $sheet, 'l2_lenderBorrower', $currentColumn, $endColumn, $currentRow, $endRow, $loc['lenderBorrower']);
        // 借地対象面積タイトル
        $cell = setCell($cell, $sheet, 'l2_leasedAreaTitle', $currentColumn, $endColumn, $currentRow, $endRow, $loc['leasedAreaTitle']);
        // 借地対象面積
        $cell = setCell($cell, $sheet, 'l2_leasedArea', $currentColumn, $endColumn, $currentRow, $endRow, $loc['leasedArea']);
        // 20230922 S_Add
        // 地代タイトル
        $cell = setCell($cell, $sheet, 'l2_landRentTitle', $currentColumn, $endColumn, $currentRow, $endRow, $loc['landRentTitle']);
        // 20230922 E_Add
        // 所有者
        // 20230922 S_Update
        // $cell = setCell($cell, $sheet, 'l2_sharers', $currentColumn, $endColumn, $currentRow, $endRow, getSharerName($loc, '、'));
        if(isset($loc['bottomLandPid']) && $loc['bottomLandPid'] !== '') {
            $cell = setCell($cell, $sheet, 'l2_sharers', $currentColumn, $endColumn, $currentRow, $endRow, getSharerName($loc, '、'));
        }
        else $cell = setCell($cell, $sheet, 'l2_sharers', $currentColumn, $endColumn, $currentRow, $endRow, $loc['lenderBorrowerName']);
        // 20230922 E_Update
        // 地代
        $cell = setCell($cell, $sheet, 'l2_landRent', $currentColumn, $endColumn, $currentRow, $endRow, $loc['landRent']);
    }
    // 底地が存在しない場合、Emptyを設定
    for ($i = 1; $i <= 1 - sizeof($locsBottom); $i++) {
        // 貸主名/借主名<-Empty
        $cell = setCell(null, $sheet, 'l2_lenderBorrower', $currentColumn, $endColumn, $currentRow, $endRow, ' 貸主名 ・ 借主名');
        // 借地対象面積タイトル<-Empty
        $cell = setCell($cell, $sheet, 'l2_leasedAreaTitle', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 借地対象面積<-Empty
        $cell = setCell($cell, $sheet, 'l2_leasedArea', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 20230922 S_Add
        // 地代タイトル
        $cell = setCell($cell, $sheet, 'l2_landRentTitle', $currentColumn, $endColumn, $currentRow, $endRow, '地代');
        // 20230922 E_Add
        // 所有者<-Empty
        $cell = setCell($cell, $sheet, 'l2_sharers', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 地代<-Empty
        $cell = setCell($cell, $sheet, 'l2_landRent', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }
    // 20220615 E_Add

    // 建物
    $cell = searchCell($sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 20220615 S_Add
    $locsResident = [];// 入居者

    $mergeFrom = $currentRow;
    $mergeTo = $currentRow + 9;
    // セル結合を解除
    $sheet->unmergeCells('A' . $mergeFrom . ':A' . $mergeTo);
    // 20220615 E_Add

    // 所在地情報（建物）が複数存在する場合
    if(sizeof($locsBuilding) > 1) {
        // 20220615 S_Delete
        // セル結合を解除
        /*$sheet->unmergeCells('A' . $currentRow . ':A' . ($currentRow + 9));*/
        // 20220615 E_Delete

        // 建物の行をコピー
        copyBlockWithVal($sheet, $currentRow, 6, sizeof($locsBuilding) - 1, $endColumn);
        $mergeTo += (sizeof($locsBuilding) - 1) * 6;// 20220615 Add

        // 20220615 S_Delete
        // セルを再結合
        /*$mergeTo = $currentRow + (sizeof($locsBuilding) - 1) * 6 + 9;
        $sheet->mergeCells('A' . $currentRow . ':A' . $mergeTo);*/
        // 20220615 E_Delete
    }
    foreach($locsBuilding as $loc) {
        // 所在
        $cell = setCell(null, $sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow, $loc['address']);
        // 家屋番号
        $cell = setCell($cell, $sheet, 'b_buildingNumber', $currentColumn, $endColumn, $currentRow, $endRow, $loc['buildingNumber']);
        // 20220914 S_Update
        // 権利の種類
        /*
        $rightsForm = getCodeTitle($codeLists['rightsForm'], $loc['rightsForm']);
        if($rightsForm === '') $rightsForm = '所有権';
        $cell = setCell($cell, $sheet, 'b_rightsForm', $currentColumn, $endColumn, $currentRow, $endRow, $rightsForm);
        */
        $cell = setCell($cell, $sheet, 'b_rightsForm', $currentColumn, $endColumn, $currentRow, $endRow, '所有権');
        // 20220914 E_Update
        // 構造
        $cell = setCell($cell, $sheet, 'b_structure', $currentColumn, $endColumn, $currentRow, $endRow, $loc['structure']);
        // 階建
        $cell = setCell($cell, $sheet, 'b_dependFloor', $currentColumn, $endColumn, $currentRow, $endRow, $loc['dependFloor']);
        // 種類
        $cell = setCell($cell, $sheet, 'b_dependType', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($codeLists['dependType'], $loc['dependType']));
        // 床面積
        $cell = setCell($cell, $sheet, 'b_grossFloorArea', $currentColumn, $endColumn, $currentRow, $endRow, $loc['grossFloorArea']);
        // 20211025 S_Add
        // 建築時期 （竣工日）
        $cell = setCell($cell, $sheet, 'b_completionDay_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($loc['completionDay'], 'Y'));
        $cell = setCell($cell, $sheet, 'b_completionDay_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($loc['completionDay'], 'm'));
        $cell = setCell($cell, $sheet, 'b_completionDay_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($loc['completionDay'], 'd'));
        // 20211025 E_Add

        // 20220615 S_Add
        // 入居者情報を取得
        $residents = ORM::for_table(TBLRESIDENTINFO)->where('tempLandInfoPid', $loc['tempLandInfoPid'])->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
        if(sizeof($residents) > 0) {
            foreach($residents as $resident) {
                $locsResident[] = $resident;
            }
        }
        // 20220615 E_Add
    }
    // 所在地情報（建物）が存在しない場合、Emptyを設定
    for ($i = 1; $i <= 1 - sizeof($locsBuilding); $i++) {
        // 所在<-Empty
        $cell = setCell(null, $sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 家屋番号<-Empty
        $cell = setCell($cell, $sheet, 'b_buildingNumber', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 20220912 S_Add
        // 権利の種類<-Empty
        $cell = setCell($cell, $sheet, 'b_rightsForm', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 20220912 E_Add
        // 構造<-Empty
        $cell = setCell($cell, $sheet, 'b_structure', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 階建<-Empty
        $cell = setCell($cell, $sheet, 'b_dependFloor', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 種類<-Empty
        $cell = setCell($cell, $sheet, 'b_dependType', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 床面積<-Empty
        $cell = setCell($cell, $sheet, 'b_grossFloorArea', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 20211025 S_Add
        // 建築時期 （竣工日）<-Empty
        $cell = setCell($cell, $sheet, 'b_completionDay_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, '');
        $cell = setCell($cell, $sheet, 'b_completionDay_MM', $currentColumn, $endColumn, $currentRow, $endRow, '');
        $cell = setCell($cell, $sheet, 'b_completionDay_DD', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 20211025 E_Add
    }

    // 20220615 S_Add
    // 入居者
    $cell = searchCell($sheet, 'b2_roomNo', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 入居者が複数存在する場合
    if(sizeof($locsResident) > 1) {
        // 入居者の行をコピー
        copyBlockWithVal($sheet, $currentRow, 2, sizeof($locsResident) - 1, $endColumn);
        $mergeTo += (sizeof($locsResident) - 1) * 2;
    }

    // セルを再結合
    $sheet->mergeCells('A' . $mergeFrom . ':A' . $mergeTo);

    foreach($locsResident as $loc) {
        // 部屋番号
        $cell = setCell(null, $sheet, 'b2_roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $loc['roomNo']);
        // 借主氏名
        $cell = setCell($cell, $sheet, 'b2_borrowerName', $currentColumn, $endColumn, $currentRow, $endRow, $loc['borrowerName']);
        // 賃料
        $cell = setCell($cell, $sheet, 'b2_rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $loc['rentPrice']);
        // 契約期間満了日
        $cell = setCell($cell, $sheet, 'b2_expirationDate_wareki', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt($loc['expirationDate'], 'name'));
        $cell = setCell($cell, $sheet, 'b2_expirationDate_YY', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt($loc['expirationDate'], 'year'));
        $cell = setCell($cell, $sheet, 'b2_expirationDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($loc['expirationDate'], 'm'));
        $cell = setCell($cell, $sheet, 'b2_expirationDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($loc['expirationDate'], 'd'));
    }
    // 入居者が存在しない場合、Emptyを設定
    for ($i = 1; $i <= 1 - sizeof($locsResident); $i++) {
        // 部屋番号<-Empty
        $cell = setCell(null, $sheet, 'b2_roomNo', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 借主氏名<-Empty
        $cell = setCell($cell, $sheet, 'b2_borrowerName', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 賃料<-Empty
        $cell = setCell($cell, $sheet, 'b2_rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 契約期間満了日<-Empty
        $cell = setCell($cell, $sheet, 'b2_expirationDate_wareki', $currentColumn, $endColumn, $currentRow, $endRow, '');
        $cell = setCell($cell, $sheet, 'b2_expirationDate_YY', $currentColumn, $endColumn, $currentRow, $endRow, '');
        $cell = setCell($cell, $sheet, 'b2_expirationDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, '');
        $cell = setCell($cell, $sheet, 'b2_expirationDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }
    // 20220615 E_Add
}
// 20220615 S_Add
/**
 * 所有者名取得（指定文字区切り）
 */
function getSharerName($loc, $split) {
    $ret = [];

    // 共有者情報を取得
    $shares = ORM::for_table(TBLSHARERINFO)->where('tempLandInfoPid', $loc['tempLandInfoPid'])->where('locationInfoPid', $loc['bottomLandPid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();

    if(isset($shares)) {
        foreach($shares as $share) {
            if(!empty($share['sharer'])) $ret[] = mb_convert_kana($share['sharer'], 'kvrn');
        }
    }
    return implode($split, $ret);
}
// 20220615 E_Add

?>
