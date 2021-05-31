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
$bukken = ORM::for_table(TBLTEMPLANDINFO)->findOne($param->pid)->asArray();

$codeLists = [];
// 売主対象コードList 0:メトロス開発 ,1:Royal House
$sellerCodeList = ORM::for_table(TBLCODE)->where('code', '027')->where_null('deleteDate')->findArray();
$codeLists['seller'] = $sellerCodeList;
// 地目コードList
$landCategoryCodeList = ORM::for_table(TBLCODE)->where('code', '002')->where_null('deleteDate')->findArray();
$codeLists['landCategory'] = $landCategoryCodeList;
// 権利形態コードList
$rightsFormCodeList = ORM::for_table(TBLCODE)->where('code', '011')->where_null('deleteDate')->findArray();
$codeLists['rightsForm'] = $rightsFormCodeList;
// 種類コードList
$dependTypeCodeList = ORM::for_table(TBLCODE)->where('code', '003')->where_null('deleteDate')->findArray();
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
    $sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    
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
    $details = ORM::for_table(TBLCONTRACTDETAILINFO)->where('contractInfoPid', $contract['pid'])->where('contractDataType', '01')->where_null('deleteDate')->order_by_asc('pid')->findArray();
    foreach($details as $detail) {
        $detailIds[] = $detail['locationInfoPid'];
    }

    // 謄本情報を設定する
    setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $detailIds, $codeLists);

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
    // 手付金日付
    // 手付金
    $earnestPriceDay = '';
    $earnestPrice = '';
    if($contract['earnestPriceDayChk'] === '1') {
        $earnestPriceDay = $contract['earnestPriceDay'];
        $earnestPrice = $contract['earnestPrice'];
    }
    $cell = setCell(null, $sheet, 'earnestPriceDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($earnestPriceDay));
    $cell = setCell(null, $sheet, 'earnestPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($earnestPrice, false));
    // 内金①日付
    // 内金①
    $deposit1Day = '';
    $deposit1 = '';
    if($contract['deposit1DayChk'] === '1') {
        $deposit1Day = $contract['deposit1Day'];
        $deposit1 = $contract['deposit1'];
    }
    $cell = setCell($cell, $sheet, 'deposit1Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit1Day));
    $cell = setCell($cell, $sheet, 'deposit1', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit1, false));
    // 内金②日付
    // 内金②
    $deposit2Day = '';
    $deposit2 = '';
    if($contract['deposit2DayChk'] === '1') {
        $deposit2Day = $contract['deposit2Day'];
        $deposit2 = $contract['deposit2'];
    }
    $cell = setCell($cell, $sheet, 'deposit2Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit2Day));
    $cell = setCell($cell, $sheet, 'deposit2', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit2, false));
    // 内金③日付
    // 内金③
    $deposit3Day = '';
    $deposit3 = '';
    if($contract['deposit3DayChk'] === '1') {
        $deposit3Day = $contract['deposit3Day'];
        $deposit3 = $contract['deposit3'];
    }
    $cell = setCell($cell, $sheet, 'deposit3Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit3Day));
    $cell = setCell($cell, $sheet, 'deposit3', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit3, false));
    // 内金④日付
    // 内金④
    $deposit4Day = '';
    $deposit4 = '';
    if($contract['deposit4DayChk'] === '1') {
        $deposit4Day = $contract['deposit4Day'];
        $deposit4 = $contract['deposit4'];
    }
    $cell = setCell($cell, $sheet, 'deposit4Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($deposit4Day));
    $cell = setCell($cell, $sheet, 'deposit4', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($deposit4, false));
    // 決済日
    // 決済代金
    $decisionDay = '';
    $decisionPrice = '';
    if($contract['decisionDayChk'] === '1') {
        $decisionDay = $contract['decisionDay'];
        $decisionPrice = $contract['decisionPrice'];
    }
    $cell = setCell($cell, $sheet, 'decisionDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($decisionDay));
    $cell = setCell($cell, $sheet, 'decisionPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($decisionPrice, false));
    // 留保金支払(明渡)日
    // 留保金
    $cell = setCell($cell, $sheet, 'retainageDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($contract['retainageDay']));
    $cell = setCell($cell, $sheet, 'retainage', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['retainage'], false));
    // 固都税清算金
    $cell = setCell($cell, $sheet, 'fixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['fixedTax'], false));
    // 仲介会社
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'intermediaryName', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['intermediaryName']);
    }
    // 仲介手数料
    $cell = setCell(null, $sheet, 'intermediaryPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['intermediaryPrice'], false));
    // 業務委託先
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'outsourcingName', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['outsourcingName']);
    }
    // 業務委託料
    $cell = setCell(null, $sheet, 'outsourcingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($contract['outsourcingPrice'], false));
    // 仲介会社住所
    $cell = setCell($cell, $sheet, 'intermediaryAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['intermediaryAddress']);
    // 業務委託先住所
    $cell = setCell($cell, $sheet, 'outsourcingAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $contract['outsourcingAddress']);
}
// コピー元買主シート削除
$spreadsheet->removeSheetByIndex(0);

// 売主シート
$sheet = $spreadsheet->getSheet(0);

$cntSales = 0;

// 物件売契約情報を取得
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
foreach($sales as $sale) {
    $cntSales++;

    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(0);
    $sheet->setTitle('売主' . $cntSales);
    if($sale['salesName'] !== '') $sheet->setTitle($sale['salesName'] . '様');
    $spreadsheet->addSheet($sheet);

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
    // 売買代金
    $cell = setCell($cell, $sheet, 'salesTradingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingPrice'], false));
    // 売買代金（消費税）
    $cell = setCell($cell, $sheet, 'salesTradingTaxPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesTradingTaxPrice'], false));
    // 手付金日付
    // 手付金
    $cell = setCell(null, $sheet, 'salesEarnestPriceDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesEarnestPriceDay']));
    $cell = setCell(null, $sheet, 'salesEarnestPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesEarnestPrice'], false));
    // 内金①日付
    // 内金①
    $cell = setCell($cell, $sheet, 'salesDeposit1Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDeposit1Day']));
    $cell = setCell($cell, $sheet, 'salesDeposit1', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDeposit1'], false));
    // 内金②日付
    // 内金②
    $cell = setCell($cell, $sheet, 'salesDeposit2Day', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDeposit2Day']));
    $cell = setCell($cell, $sheet, 'salesDeposit2', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDeposit2'], false));
    // 決済日
    // 決済代金
    $cell = setCell($cell, $sheet, 'salesDecisionDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesDecisionDay']));
    $cell = setCell($cell, $sheet, 'salesDecisionPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesDecisionPrice'], false));
    // 留保金支払日
    // 留保金
    $cell = setCell($cell, $sheet, 'salesRetainageDay', $tradingColumn, $endColumn, $tradingRow, $endRow, convert_jpdt_kanji($sale['salesRetainageDay']));
    $cell = setCell($cell, $sheet, 'salesRetainage', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesRetainage'], false));
    // 固都税清算金
    $cell = setCell($cell, $sheet, 'salesFixedTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesFixedTax'], false));
    // （内消費税相当額<-固都税清算金（消費税）
    $cell = setCell($cell, $sheet, 'salesFixedConsumptionTax', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesFixedConsumptionTax'], false));
    // 仲介会社
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'salesIntermediary', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesIntermediary']);
    }
    // 仲介手数料
    $cell = setCell(null, $sheet, 'salesBrokerageFee', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesBrokerageFee'], false));
    // 業務委託先
    for ($i = 1; $i <= 2; $i++) {
        $cell = setCell(null, $sheet, 'salesOutsourcingName', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesOutsourcingName']);
    }
    // 業務委託料
    $cell = setCell(null, $sheet, 'salesOutsourcingPrice', $tradingColumn, $endColumn, $tradingRow, $endRow, formatNumber($sale['salesOutsourcingPrice'], false));
    // 仲介会社住所
    $cell = setCell($cell, $sheet, 'salesIntermediaryAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesIntermediaryAddress']);
    // 業務委託先住所
    $cell = setCell($cell, $sheet, 'salesOutsourcingAddress', $tradingColumn, $endColumn, $tradingRow, $endRow, $sale['salesOutsourcingAddress']);
}
// コピー元売主シート削除
$spreadsheet->removeSheetByIndex(0);

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
function setLocationInfo($sheet, $currentColumn, $endColumn, $currentRow, $endRow, $pids, $codeLists) {
    $locsLand = [];     // 土地
    $locsBuilding = []; // 建物

    if(sizeof($pids) > 0) {
        // 所在地情報を取得
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $pids)->where_null('deleteDate')->order_by_asc('pid')->findArray();
        foreach($locs as $loc) {
            // 区分が01：土地の場合
            if($loc['locationType'] === '01') {
                $locsLand[] = $loc;
            }
            // 区分が02：建物の場合
            else if($loc['locationType'] === '02') {
                $locsBuilding[] = $loc;
                // 権利形態が01：借地権の場合
                if($loc['rightsForm'] === '01' && $loc['bottomLandPid'] !== '') {
                    // 底地を土地に追加
                    $bottomLand = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['bottomLandPid']);
                    if(isset($bottomLand)) {
                        $bottomLand['rightsForm'] = '01';// 01：借地権
                        $locsLand[] = $bottomLand;
                    }
                }
            }
            // 区分が04：区分所有（専有）の場合
            else if($loc['locationType'] === '04') {
                // 一棟の建物の所在地を設定
                if($loc['ridgePid'] !== '') {
                    $ridge = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['ridgePid']);
                    if(isset($ridge)) $loc['address'] = $ridge['address'];
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

    // 所在地情報（土地）が複数存在する場合
    if(sizeof($locsLand) > 1) {
        // セル結合を解除
        $sheet->unmergeCells('A' . $currentRow . ':A' . ($currentRow + 6));

        // 土地の行をコピー
        copyBlockWithVal($sheet, $currentRow, 3, sizeof($locsLand) - 1, $endColumn);

        // セルを再結合
        $mergeTo = $currentRow + (sizeof($locsLand) - 1) * 3 + 6;
        $sheet->mergeCells('A' . $currentRow . ':A' . $mergeTo);
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

    // 建物
    $cell = searchCell($sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    // 所在地情報（建物）が複数存在する場合
    if(sizeof($locsBuilding) > 1) {
        // セル結合を解除
        $sheet->unmergeCells('A' . $currentRow . ':A' . ($currentRow + 9));

        // 建物の行をコピー
        copyBlockWithVal($sheet, $currentRow, 6, sizeof($locsBuilding) - 1, $endColumn);

        // セルを再結合
        $mergeTo = $currentRow + (sizeof($locsBuilding) - 1) * 6 + 9;
        $sheet->mergeCells('A' . $currentRow . ':A' . $mergeTo);
    }
    foreach($locsBuilding as $loc) {
        // 所在
        $cell = setCell(null, $sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow, $loc['address']);
        // 家屋番号
        $cell = setCell($cell, $sheet, 'b_buildingNumber', $currentColumn, $endColumn, $currentRow, $endRow, $loc['buildingNumber']);
        // 構造
        $cell = setCell($cell, $sheet, 'b_structure', $currentColumn, $endColumn, $currentRow, $endRow, $loc['structure']);
        // 階建
        $cell = setCell($cell, $sheet, 'b_dependFloor', $currentColumn, $endColumn, $currentRow, $endRow, $loc['dependFloor']);
        // 種類
        $cell = setCell($cell, $sheet, 'b_dependType', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($codeLists['dependType'], $loc['dependType']));
        // 床面積
        $cell = setCell($cell, $sheet, 'b_grossFloorArea', $currentColumn, $endColumn, $currentRow, $endRow, $loc['grossFloorArea']);
    }
    // 所在地情報（建物）が存在しない場合、Emptyを設定
    for ($i = 1; $i <= 1 - sizeof($locsBuilding); $i++) {
        // 所在<-Empty
        $cell = setCell(null, $sheet, 'b_address', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 家屋番号<-Empty
        $cell = setCell($cell, $sheet, 'b_buildingNumber', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 構造<-Empty
        $cell = setCell($cell, $sheet, 'b_structure', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 階建<-Empty
        $cell = setCell($cell, $sheet, 'b_dependFloor', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 種類<-Empty
        $cell = setCell($cell, $sheet, 'b_dependType', $currentColumn, $endColumn, $currentRow, $endRow, '');
        // 床面積<-Empty
        $cell = setCell($cell, $sheet, 'b_grossFloorArea', $currentColumn, $endColumn, $currentRow, $endRow, '');
    }
}

?>
