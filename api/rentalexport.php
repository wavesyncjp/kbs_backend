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
$filePath = $fullPath.'/取引成立台帳（賃貸）.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 買主シート
$sheet = $spreadsheet->getSheet(0);

// 最終列数
$endColumn = 58;
// 最終行数
$endRow = 81;

$newline = "\n";

// 賃貸情報を取得
$ren = ORM::for_table(TBLRENTALINFO)->findOne($param->pid)->asArray();

//契約方法
$contractMethodList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '043')->where_null('deleteDate')->findArray();
//種　別
$roomTypeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '046')->where_null('deleteDate')->findArray();
//用途
$usePurposeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '047')->where_null('deleteDate')->findArray();
//支払期限
$usanceList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '044')->where_null('deleteDate')->findArray();

$cntRenCons = 0;

// 賃貸契約を取得
$renCons = getRentalContractsForExport($param->pid,null);

foreach($renCons as $renCon) {
    $cntRenCons++;

    //立ち退き情報
    $queryEvic = ORM::for_table(TBLEVICTIONINFO)
	->table_alias('p1')
	->select('p1.*')
	->where_null('p1.deleteDate');
	$queryEvic = $queryEvic->where('p1.rentalInfoPid', $renCon['rentalInfoPid']);
	$queryEvic = $queryEvic->where('p1.residentInfoPid', $renCon['residentInfoPid']);
	
    $evic = $queryEvic->order_by_desc('p1.pid')->find_one();
    if(!isset($evic)){
        $evic = new stdClass();
        $evic->deposit1 = null;
        $evic->depositPayedDate1 = null;
        $evic->deposit2 = null;
        $evic->depositPayedDate2 = null;
        $evic->evictionFee = null;
        $evic->returnDeposit = null;
        $evic->returnDepositDate = null;
        $evic->successionDeposit = null;
    }

    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(0);
    $sheet->setTitle($renCon['borrowerName'] . '様');
    $spreadsheet->addSheet($sheet);

    // 列・行の位置を初期化
    $currentColumn = 1;
    $currentRow = 1;
    $cell = null;

    //出力日
    $sysDate = date('Ymd');

    $cell = setCell($cell, $sheet, 'outPutDate_YY', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt($sysDate, 'year'));
    $cell = setCell($cell, $sheet, 'outPutDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($sysDate, 'm'));
    $cell = setCell($cell, $sheet, 'outPutDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($sysDate, 'd'));


    //契約物件番号		

    $cell = setCell($cell, $sheet, 'contractBukkenNo', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['contractFormNumberMap']);
    
    //物 件 区 分					
    $cell = setCell($cell, $sheet, 'contractMethod', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($contractMethodList, $renCon['contractMethod']));
    //対象のセルのコメント
    $comment = $sheet -> getComment($cell->getColumn() . $cell->getRow());
    $comment ->getFillColor() -> setRGB('ffffe1');

    // 成立年月日
    $cell = setCell($cell, $sheet, 'agreementDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($renCon['agreementDate']));
    // 承継年月日
    $cell = setCell($cell, $sheet, 'ownershipRelocationDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($ren['ownershipRelocationDate']));
    //借主 氏  名				
    $cell = setCell($cell, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerName']);
    //借主 TEL				
    $cell = setCell($cell, $sheet, 'borrowerTel', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerTel']);
    //借主 住  所			
    $cell = setCell($cell, $sheet, 'borrowerAddress', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerAddress']);

    //入居者氏名			
    $cell = setCell($cell, $sheet, 'residentName', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['residentName']);
    //入居者 TEL				
    $cell = setCell($cell, $sheet, 'residentTel', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['residentTel']);

    //物件の表示 所在地				
    $cell = setCell($cell, $sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['l_addressMap']);
    //物件の表示 名  称				
    $cell = setCell($cell, $sheet, 'apartmentName', $currentColumn, $endColumn, $currentRow, $endRow, $ren['apartmentName']);
    //物件の表示 階				
    $cell = setCell($cell, $sheet, 'floorNumber', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['floorNumber']);
    //物件の表示 号室				
    $cell = setCell($cell, $sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomNo']);
    //物件の表示 構  造				
    $cell = setCell($cell, $sheet, 'structure', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['l_structureMap']);
    //物件の表示 面積		
    $cell = setCell($cell, $sheet, 'roomExtent', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomExtent']);
    //物件の表示 種　別					
    $cell = setCell($cell, $sheet, 'roomType', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($roomTypeList, $renCon['roomType']));
    //物件の表示 用途					
    $cell = setCell($cell, $sheet, 'usePurpose', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usePurposeList, $renCon['usePurpose']));

    //火災保険料				
    $cell = setCell($cell, $sheet, 'InsuranceFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['InsuranceFee']);
    
    //賃  料					
    $cell = setCell($cell, $sheet, 'rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice']);
    //賃  料（税込）					
    $cell = setCell($cell, $sheet, 'rentPriceInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice'] + $renCon['rentPriceTax']);

    //敷  金					
    $cell = setCell($cell, $sheet, 'deposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['deposit']);
    //保証金						
    $cell = setCell($cell, $sheet, 'securityDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['securityDeposit']);

    //償  却						
    $cell = setCell($cell, $sheet, 'amortization', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['amortization']);
    //鍵交換費用						
    $cell = setCell($cell, $sheet, 'keyExchangeFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['keyExchangeFee']);


    //共益費 月額					
    $cell = setCell($cell, $sheet, 'condoFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFee']);
    //共益費 月額（税込）					
    $cell = setCell($cell, $sheet, 'condoFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFee'] + $renCon['condoFeeTax']);

    //管理費 月額					
    $cell = setCell($cell, $sheet, 'managementFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['managementFee']);
    //管理費 月額（税込）					
    $cell = setCell($cell, $sheet, 'managementFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['managementFee'] + $renCon['managementFeeTax']);
    
    //礼  金				
    $cell = setCell($cell, $sheet, 'keyMoney', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['keyMoney']);
    //その他費用				
    $cell = setCell($cell, $sheet, 'otherExpenses', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['otherExpenses']);
   
    //駐車場賃料（税込）				
    $cell = setCell($cell, $sheet, 'parkingFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingFee']);
    //駐車場敷金・礼金			
    $cell = setCell($cell, $sheet, 'parkingDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingDeposit']);
            
    // 契約期間開始日
    $cell = setCell($cell, $sheet, 'loanPeriodStartDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodStartDate'], 'Y'));
    $cell = setCell($cell, $sheet, 'loanPeriodStartDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodStartDate'], 'm'));
    $cell = setCell($cell, $sheet, 'loanPeriodStartDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodStartDate'], 'd'));
            
    // 契約期間終了日
    $cell = setCell($cell, $sheet, 'loanPeriodEndDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodEndDate'], 'Y'));
    $cell = setCell($cell, $sheet, 'loanPeriodEndDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodEndDate'], 'm'));
    $cell = setCell($cell, $sheet, 'loanPeriodEndDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodEndDate'], 'd'));

    // 賃貸契約開始日
	// $loanPeriodStartDate = $ren['ownershipRelocationDate'];
	$loanPeriodStartDate = $renCon['loanPeriodStartDate'];
	if (!isset($loanPeriodStartDate)) {
		$loanPeriodStartDate = date('Ymd', strtotime($renCon['createDate']));
	}

    $loanPeriodEndDate = $renCon['loanPeriodEndDate'];
	// 賃貸契約終了日
	if (!isset($loanPeriodEndDate)) {
		// 一年間
		$loanPeriodEndDate = date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
	}

    // 日迄
    $diffDate = calDiffDate($loanPeriodStartDate, $loanPeriodEndDate);
    $loanPeriod = "";
    if($diffDate->y > 0){
        $loanPeriod = $loanPeriod . $diffDate->y . "年";
    }
    if($diffDate->m > 0){
        $loanPeriod = $loanPeriod . $diffDate->m . "ヶ月";
    }
    if($diffDate->d > 0){
        $loanPeriod = $loanPeriod . $diffDate->d . "日";
    }
    $cell = setCell($cell, $sheet, 'loanPeriod', $currentColumn, $endColumn, $currentRow, $endRow, $loanPeriod);

    //更新料				
    $cell = setCell($cell, $sheet, 'updateFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['updateFee']);
    
    //解約予告				
    $cell = setCell($cell, $sheet, 'contractEndNotification', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['contractEndNotification']);

    //支払振込	
    $bankName = getBankName($ren['bankPid']);			
    $cell = setCell($cell, $sheet, 'bank_displayName', $currentColumn, $endColumn, $currentRow, $endRow, $bankName);

    //支払期限
    $cell = setCell($cell, $sheet, 'usance', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usanceList, $renCon['usance']) . "分を毎月" .$renCon['paymentDay'] ."までに支払う");
    
    //管理の委託先 名称
    $cell = setCell($cell, $sheet, 'manageCompanyName', $currentColumn, $endColumn, $currentRow, $endRow, $ren['manageCompanyName']);
    //管理の委託先 連絡先
    $cell = setCell($cell, $sheet, 'manageCompanyTel', $currentColumn, $endColumn, $currentRow, $endRow, $ren['manageCompanyTel']);
    //管理の委託先 住所
    $cell = setCell($cell, $sheet, 'manageCompanyAddress', $currentColumn, $endColumn, $currentRow, $endRow, $ren['manageCompanyAddress']);

    // 立退料 情報
    // 解除日
    $cell = setCell($cell, $sheet, 'agreementCancellationDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->agreementCancellationDate, 'Y'));
    $cell = setCell($cell, $sheet, 'agreementCancellationDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->agreementCancellationDate, 'm'));
    $cell = setCell($cell, $sheet, 'agreementCancellationDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->agreementCancellationDate, 'd'));

    // 明渡日
    $cell = setCell($cell, $sheet, 'surrenderDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderDate, 'Y'));
    $cell = setCell($cell, $sheet, 'surrenderDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderDate, 'm'));
    $cell = setCell($cell, $sheet, 'surrenderDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderDate, 'd'));

    // 賃料免除
    $cell = setCell($cell, $sheet, 'roomRentExemptionStartDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->roomRentExemptionStartDate, 'Y'));
    $cell = setCell($cell, $sheet, 'roomRentExemptionStartDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->roomRentExemptionStartDate, 'm'));
    $cell = setCell($cell, $sheet, 'roomRentExemptionStartDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->roomRentExemptionStartDate, 'd'));
    
    // 賃料免除
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'Y'));
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'm'));
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'd'));
    
    // 立退料 金額①
    $cell = setCell($cell, $sheet, 'deposit1', $currentColumn, $endColumn, $currentRow, $endRow, $evic->deposit1);
    // 立退料 支払い日①
    $cell = setCell($cell, $sheet, 'depositPayedDate1_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate1, 'Y'));
    $cell = setCell($cell, $sheet, 'depositPayedDate1_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate1, 'm'));
    $cell = setCell($cell, $sheet, 'depositPayedDate1_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate1, 'd'));

    // 立退料 金額②
    $cell = setCell($cell, $sheet, 'deposit2', $currentColumn, $endColumn, $currentRow, $endRow, $evic->deposit2);
    // 立退料 支払い日②
    $cell = setCell($cell, $sheet, 'depositPayedDate2_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate2, 'Y'));
    $cell = setCell($cell, $sheet, 'depositPayedDate2_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate2, 'm'));
    $cell = setCell($cell, $sheet, 'depositPayedDate2_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->depositPayedDate2, 'd'));

    // 立退料 合計
    $cell = setCell($cell, $sheet, 'evictionFee', $currentColumn, $endColumn, $currentRow, $endRow, $evic->evictionFee);

    // 保証金返還金 金額
    $cell = setCell($cell, $sheet, 'returnDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $evic->returnDeposit);
    // 保証金返還金 支払い日
    $cell = setCell($cell, $sheet, 'returnDepositDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->returnDepositDate, 'Y'));
    $cell = setCell($cell, $sheet, 'returnDepositDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->returnDepositDate, 'm'));
    $cell = setCell($cell, $sheet, 'returnDepositDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->returnDepositDate, 'd'));

    // 備  考
    $cell = setCell($cell, $sheet, 'successionDeposit', $currentColumn, $endColumn, $currentRow, $endRow, "【承継敷金、保証金】{$newline}"  . number_format($evic->successionDeposit));

    $sheet->setSelectedCell('A1');// 初期選択セル設定
}
// コピー元買主シート削除
$spreadsheet->removeSheetByIndex(0);

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '取引成立台帳（賃貸）_' . date('YmdHis') . '.xlsx';
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
        // 値を設定する
        $setColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $setRow = $cell->getRow();
        $sheet->setCellValueByColumnAndRow($setColumn, $setRow, $value);
    }
    return $cell;
}
?>
