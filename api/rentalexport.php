<?php
ini_set('memory_limit', '512M');// 20241004 Add

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

// 20241004 S_Add
// シートをスプレッドシートに追加する前に保持する配列を作成する
$sheetsToAdd = [];

// タイトルを追跡するための配列
$sheetTitles = []; 
// 20241004 E_Add

// 最終列数
$endColumn = 58;
// 最終行数
$endRow = 81;

$newline = "\n";

// 賃貸情報を取得
$ren = ORM::for_table(TBLRENTALINFO)->findOne($param->pid)->asArray();

// 20240404 S_Add
// 契約者名

// 20240501 S_Update
// $contractorName = getContractorName($ren['contractSellerInfoPid']);
$contractorName = getContractorNameCommon($ren['contractSellerInfoPid']);
// 20240501 E_Update
// 20240404 E_Add

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
    // 20231027 S_Update
    // $queryEvic = ORM::for_table(TBLEVICTIONINFO)
	// ->table_alias('p1')
	// ->select('p1.*')
	// ->where_null('p1.deleteDate');
	// $queryEvic = $queryEvic->where('p1.rentalInfoPid', $renCon['rentalInfoPid']);
	// $queryEvic = $queryEvic->where('p1.residentInfoPid', $renCon['residentInfoPid']);
	
    // $evic = $queryEvic->order_by_desc('p1.pid')->find_one();
    $evic = getEvic($renCon);
    // 20231027 E_Update
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

    // 20231019 S_Add
    $locObj = null;// 所在地情報
    $locationInfoPidTemp = getLocationPidByBuilding($renCon['locationInfoPid']);
    if (isset($locationInfoPidTemp) && $locationInfoPidTemp != null){
        $locObj = getLocationInfoForReport($locationInfoPidTemp);
    }
    else{
        $locObj = getLocationInfoForReport($renCon['locationInfoPid']);
    }
    // 20231019 E_Add
    
    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(0);
    // 20241004 S_Update
    // $sheet->setTitle($renCon['borrowerName'] . '様');
    // $spreadsheet->addSheet($sheet);
    $baseTitle = $renCon['borrowerName'] . '様';
    $sheetTitle = $baseTitle;

    // タイトルがすでに存在するかチェックし、存在する場合は番号を追加
    $index = 1;
    while (in_array($sheetTitle, $sheetTitles)) {
        $sheetTitle = $baseTitle . ' ' . $index;
        $index++;
    }

    // シートのユニークなタイトルを設定
    $sheet->setTitle($sheetTitle);

    // 重複を避けるためにタイトルを追跡
    $sheetTitles[] = $sheetTitle;
    // 20241004 E_Add

    // 列・行の位置を初期化
    $currentColumn = 1;
    $currentRow = 1;
    $cell = null;

    //出力日
    $sysDate = date('Ymd');

    // 20241028 S_Add
    $cell = setCell($cell, $sheet, 'jpEra', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($sysDate, 'name'));
    // 20241028 E_Add
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
    // 20240404 S_Add
    // 旧所有者
    $cell = setCell($cell, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $contractorName);
    // 20240404 E_Add
    //借主 氏  名				
    // 20240404 S_Update
    // $cell = setCell($cell, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerName']);
    $cell = setCell($cell, $sheet, 'borrowerName', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerName']);
    // 20240404 E_Update
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
    // 20231019 S_Update
    // $cell = setCell($cell, $sheet, 'structure', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['l_structureMap']);
    $cell = setCell($cell, $sheet, 'structure', $currentColumn, $endColumn, $currentRow, $endRow, $locObj['l_structureMap']);
    // 20231019 E_Update
    //物件の表示 面積		
    $cell = setCell($cell, $sheet, 'roomExtent', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomExtent']);
    //物件の表示 種　別					
    $cell = setCell($cell, $sheet, 'roomType', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($roomTypeList, $renCon['roomType']));
    //物件の表示 用途					
    $cell = setCell($cell, $sheet, 'usePurpose', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usePurposeList, $renCon['usePurpose']));

    // 20241028 S_Delete
    // //火災保険料				
    // $cell = setCell($cell, $sheet, 'InsuranceFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['InsuranceFee']);
    // 20241028 E_Delete
    
    // 20241028 S_Add
    //賃料タイプ
    $rentPriceTaxType = '月額（税込）';
    if(!isset($renCon['rentPriceTax']) || $renCon['rentPriceTax'] == 0){
        $rentPriceTaxType = '月額';
    }
    $cell = setCell($cell, $sheet, 'rentPriceTaxType', $currentColumn, $endColumn, $currentRow, $endRow, $rentPriceTaxType);
    
    // 敷金・保証金種別
    $depositType = '';
    if(isset($renCon['deposit']) && $renCon['deposit'] != 0){
        $depositType = '敷　金';
    }
    else if(isset($renCon['securityDeposit']) && $renCon['securityDeposit'] != 0){
        $depositType = '保証金';
    }
    $cell = setCell($cell, $sheet, 'depositType', $currentColumn, $endColumn, $currentRow, $endRow, $depositType );
    // 20241028 E_Add
    
    // 20241125 S_Delete
    // //賃  料					
    // $cell = setCell($cell, $sheet, 'rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice']);
    // //賃  料（税込）					
    // $cell = setCell($cell, $sheet, 'rentPriceInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice'] + $renCon['rentPriceTax']);

    // //敷  金					
    // $cell = setCell($cell, $sheet, 'deposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['deposit']);
    // //保証金						
    // $cell = setCell($cell, $sheet, 'securityDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['securityDeposit']);
    // 20241125 E_Delete

    // 20241028 S_Add
    $managementCondoType = '';
    if((isset($renCon['condoFee']) && $renCon['condoFee'] != 0) || (isset($renCon['condoFeeTax']) && $renCon['condoFeeTax'] != 0)){
        $managementCondoType = '共益費';
    }
    else if((isset($renCon['managementFee']) && $renCon['managementFee'] != 0) || (isset($renCon['managementFeeTax']) && $renCon['managementFeeTax'] != 0)){
        $managementCondoType = '管理費';
    }
    $cell = setCell($cell, $sheet, 'managementCondoType', $currentColumn, $endColumn, $currentRow, $endRow, $managementCondoType );

    //共益費・管理費タイプ
    $managementCondoTaxType = '月額（税込）';
    if((!isset($renCon['condoFeeTax']) || $renCon['condoFeeTax'] == 0) && (!isset($renCon['managementFeeTax']) || $renCon['managementFeeTax'] == 0)){
        $managementCondoTaxType = '月額';
    }
    $cell = setCell($cell, $sheet, 'managementCondoTaxType', $currentColumn, $endColumn, $currentRow, $endRow, $managementCondoTaxType);
    // 20241028 E_Add

    //償  却						
    $cell = setCell($cell, $sheet, 'amortization', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['amortization']);
    // 20241028 S_Delete
    // //鍵交換費用						
    // $cell = setCell($cell, $sheet, 'keyExchangeFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['keyExchangeFee']);
    // 20241028 E_Delete

    // 20241125 S_Add
    //賃  料					
    $cell = setCell($cell, $sheet, 'rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice']);
    //賃  料（税込）					
    $cell = setCell($cell, $sheet, 'rentPriceInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice'] + $renCon['rentPriceTax']);

    //敷  金					
    $cell = setCell($cell, $sheet, 'deposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['deposit']);
    //保証金						
    $cell = setCell($cell, $sheet, 'securityDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['securityDeposit']);
    // 20241125 E_Add
    
    //共益費 月額					
    $cell = setCell($cell, $sheet, 'condoFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFee']);
    //共益費 月額（税込）					
    $cell = setCell($cell, $sheet, 'condoFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFee'] + $renCon['condoFeeTax']);

    //管理費 月額					
    $cell = setCell($cell, $sheet, 'managementFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['managementFee']);
    //管理費 月額（税込）					
    $cell = setCell($cell, $sheet, 'managementFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['managementFee'] + $renCon['managementFeeTax']);
    
    // 20241028 S_Delete
    // //礼  金				
    // $cell = setCell($cell, $sheet, 'keyMoney', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['keyMoney']);
    // //その他費用				
    // $cell = setCell($cell, $sheet, 'otherExpenses', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['otherExpenses']);
    // 20241028 E_Delete
   
    // 20241125 S_Delete
    // //駐車場賃料（税込）				
    // $cell = setCell($cell, $sheet, 'parkingFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingFee']);
    // 20241125 E_Delete
    //駐車場敷金・礼金			
    $cell = setCell($cell, $sheet, 'parkingDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingDeposit']);

    // 20241028 S_Add
    //その他費用		
    $cell = setCell($cell, $sheet, 'otherExpenses', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['otherExpenses']);
    // 20241028 E_Add
    
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
    // 20231027 S_Delete
	// if (!isset($loanPeriodStartDate)) {
	// 	$loanPeriodStartDate = date('Ymd', strtotime($renCon['createDate']));
	// }
    // 20231027 E_Delete

	// 賃貸契約終了日
    $loanPeriodEndDate = $renCon['loanPeriodEndDate'];
    // 20231027 S_Delete
	// if (!isset($loanPeriodEndDate)) {
	// 	// 一年間
	// 	$loanPeriodEndDate = date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
	// }
    // 20231027 E_Delete

    // 日迄
    $diffDate = calDiffDate($loanPeriodStartDate, $loanPeriodEndDate);
    $loanPeriod = "";
    if($diffDate->y > 0){
        $loanPeriod = $loanPeriod . $diffDate->y . "年";
    }
    // 20241028 S_Update
    // if($diffDate->m > 0){
    //     $loanPeriod = $loanPeriod . $diffDate->m . "ヶ月";
    // }
    // if($diffDate->d > 0){
    //     $loanPeriod = $loanPeriod . $diffDate->d . "日";
    // }
    if($diffDate->m >= 0){
        $loanPeriod = $loanPeriod . $diffDate->m . "ヶ月";
    }
    if($diffDate->d >= 0){
        $loanPeriod = $loanPeriod . $diffDate->d . "日";
    }
    // 20241028 E_Update
    $cell = setCell($cell, $sheet, 'loanPeriod', $currentColumn, $endColumn, $currentRow, $endRow, $loanPeriod);

    // 20241125 S_Add
    //駐車場賃料タイプ
    $parkingFeeTaxType = '月額（税込）';
    if(!isset($renCon['parkingFeeTax']) || $renCon['parkingFeeTax'] == 0){
        $parkingFeeTaxType = '月額';
    }
    $cell = setCell($cell, $sheet, 'parkingFeeTaxType', $currentColumn, $endColumn, $currentRow, $endRow, $parkingFeeTaxType);
    // 20241125 E_Add

    // 20241125 S_Delete
    // //更新料				
    // $cell = setCell($cell, $sheet, 'updateFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['updateFee']);
    // 20241125 E_Delete
    
    //解約予告				
    $cell = setCell($cell, $sheet, 'contractEndNotification', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['contractEndNotification']);

    // 20241125 S_Add
    //駐車場賃料（税込）				
    $cell = setCell($cell, $sheet, 'parkingFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingFee']);
    //駐車場賃料（消費税）
    $cell = setCell($cell, $sheet, 'parkingFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['parkingFee'] + $renCon['parkingFeeTax']);
    // 20241125 E_Add

    //支払振込	
    $bankName = getBankName($ren['bankPid']);			
    $cell = setCell($cell, $sheet, 'bank_displayName', $currentColumn, $endColumn, $currentRow, $endRow, $bankName);

    //支払期限
    // 20241028 S_Update
    // $cell = setCell($cell, $sheet, 'usance', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usanceList, $renCon['usance']) . "分を毎月" .$renCon['paymentDay'] ."日までに支払う");
    $cell = setCell($cell, $sheet, 'usance', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usanceList, $renCon['usance']) . "分を毎月" .($renCon['paymentDay'] == 0 ? '末' : $renCon['paymentDay']) ."日までに支払う");
    // 20241028 E_Update
    
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
    // 20241125 S_Update
    // $cell = setCell($cell, $sheet, 'surrenderScheduledDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'Y'));
    // $cell = setCell($cell, $sheet, 'surrenderScheduledDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'm'));
    // $cell = setCell($cell, $sheet, 'surrenderScheduledDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderScheduledDate, 'd'));
    $isHasStart = isset($evic->roomRentExemptionStartDate) && $evic->roomRentExemptionStartDate != '';
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_YYYY', $currentColumn, $endColumn, $currentRow, $endRow, $isHasStart ? convert_dt($evic->surrenderScheduledDate, 'Y') : '');
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_MM', $currentColumn, $endColumn, $currentRow, $endRow, $isHasStart ? convert_dt($evic->surrenderScheduledDate, 'm') : '');
    $cell = setCell($cell, $sheet, 'surrenderScheduledDate_DD', $currentColumn, $endColumn, $currentRow, $endRow, $isHasStart ? convert_dt($evic->surrenderScheduledDate, 'd') : '');
    // 20241125 E_Update
    
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
    // 20241028 S_Update
    // 20241125 S_Update
    // // 　チェックボックスにチェックが入っているかつ、立退き.返還敷金(保証金) が1円以上の場合、立退き.返還敷金(保証金) - 賃貸契約.償却 の値を出す。
    // $successionDeposit = 0;
    // if($renCon['subtractionAmortizationFlg'] == '1' && $evic->returnDeposit >= 1){
    //     if(isset($renCon['amortization'])){
    //         $successionDeposit = $evic->returnDeposit - $renCon['amortization'];
    //     }
    //     else{
    //         $successionDeposit = $evic->returnDeposit;
    //     }
    //     if($successionDeposit < 1){
    //         $successionDeposit = 0;
    //     }
    // }
    // 償却差引きにチェックが入っていない場合は、敷金 OR 保証金で値が入っている方の金額をそのまま反映。
    // 償却差引きにチェックが入っている場合は、敷金、保証金から償却金額を引いた金額を反映させる。
    $successionDeposit = 0;
    $successionDeposit = $renCon['securityDeposit'] + $renCon['deposit'];
    if($renCon['subtractionAmortizationFlg'] == '1' && isset($renCon['amortization'])){
        $successionDeposit -= $renCon['amortization'];
    }

    if($successionDeposit < 1){
        $successionDeposit = 0;
    }
    // 20241125 E_Update
    $cell = setCell($cell, $sheet, 'successionDeposit', $currentColumn, $endColumn, $currentRow, $endRow, "【承継敷金、保証金】{$newline}"  . number_format($successionDeposit) . '円');
    // 20241028 E_Update
    
    $sheet->setSelectedCell('A1');// 初期選択セル設定
    $sheetsToAdd[] = $sheet;// 20241004 Add
}

// 20241004 S_Add
foreach ($sheetsToAdd as $sheet) {
    $spreadsheet->addSheet($sheet);
}
// 20241004 E_Add

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
