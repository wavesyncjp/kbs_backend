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
$filePath = $fullPath.'/売却決済案内.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 20250616 S_Add
$paymentCodeByIntermediaryPrice = '';   // 仲介手数料
$paymentCodeByOutsourcingPrice = '';	// 業務委託料

$isHasIntermediary = false;// 仲介手数料
$isHasOutsourcingPrice = false;// 業務委託料
$cntIntermediaryOrOutsourcing = 0;
$cntIntermediary = 0;
$cntOutsourcing = 0;
$sheetIndexInOut = 8;// 振替伝票 (仲介・業務委託)
$payDetailInOuts = array();
// 20250616 E_Add

$codeLists = [];
// 預金種目List
$depositTypeCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '034')->where_null('deleteDate')->findArray();
$codeLists['depositType'] = $depositTypeCodeList;

// 20240528 S_Add
// 支払種別List
// 20240930 S_Update
// $paymentTypeList = ORM::for_table(TBLPAYMENTTYPE)->select('paymentCode', 'codeDetail')->select('paymentName', 'name')->where_null('deleteDate')->findArray();
$paymentTypeList = ORM::for_table(TBLRECEIVETYPE)->select('receiveCode', 'codeDetail')->select('receiveName', 'name')->where_null('deleteDate')->findArray();
// 20240930 E_Update
$codeLists['paymentType'] = $paymentTypeList;
// 20240528 E_Add

// 20250616 S_Add
// 口座種別List
$accountTypeCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '026')->where_null('deleteDate')->findArray();
$codeLists['accountType'] = $accountTypeCodeList;

// 支払種別List(振替伝票用)
$paymentTypeList2 = ORM::for_table(TBLPAYMENTTYPE)->select('paymentCode', 'codeDetail')->select('paymentName', 'name')->where_null('deleteDate')->findArray();
$codeLists['paymentType2'] = $paymentTypeList2;

$codes = ORM::for_table(TBLCODE)->where_Like('code', 'SYS1%')->order_by_asc('code')->findArray();
if(sizeof($codes) > 0) {
	foreach($codes as $code) {
		if($code['codeDetail'] == 'tradingLandPrice' || $code['codeDetail'] == 'tradingBuildingPrice') {
			$paymentCodeByDecisionPriceList[] = $code['name'];
		}
		else if($code['codeDetail'] == 'intermediaryPrice') {
			$paymentCodeByIntermediaryPrice = $code['name'];
		}
		else if($code['codeDetail'] == 'outsourcingPrice') {
			$paymentCodeByOutsourcingPrice = $code['name'];
		}
	}
}
// 20250616 E_Add

// 物件売契約情報を取得
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where_in('pid', $param->ids)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();

foreach($sales as $sale) {

	// 固都税清算金=固都税清算金（土地）+固都税清算金（建物）+建物分消費税
	$salesFixedTax = intval($sale['salesFixedLandTax']) + intval($sale['salesFixedBuildingTax']) + intval($sale['salesFixedBuildingTaxOnlyTax']);

	// 銀行マスタを取得
	// 20221116 S_Update
	// $bank = ORM::for_table(TBLBANK)->findOne($sale['bankPid'])->asArray();
	$bank = [];
	if(!empty($sale['bankPid'])) {
		$bank = ORM::for_table(TBLBANK)->findOne($sale['bankPid'])->asArray();
	}
	// 20221116 E_Update
	// 土地情報を取得
	// 20250616 S_Update
	// $bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->findOne($sale['tempLandInfoPid'])->asArray();
	$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('bukkenNo')->select('infoStaff')->findOne($sale['tempLandInfoPid'])->asArray();
	// 20250616 E_Update

	$locs = [];
	if(!empty($sale['salesLocation'])) {
		$salesLocation = $sale['salesLocation'];
		$locationInfoPids = [];
		$explodes = [];
		// ,で分割されている場合
		if(strpos($salesLocation, ',') !== false) {
			$explodes = explode(',', $salesLocation);
		}
		else $explodes[] = $salesLocation;
		foreach($explodes as $explode) {
			$locationInfoPids[] = $explode;
		}
		// 所在地情報を取得
		$locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $locationInfoPids)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
	}

	// 20250616 S_Add
	$locSelectAddresses = [];

	foreach ($locs as $locAdress) {
		$codeDetail = $locAdress['pid'];
		$address = isset($locAdress['address']) ? $locAdress['address'] : '';
		$blockNumber = isset($locAdress['blockNumber']) ? $locAdress['blockNumber'] : '';
		$buildingNumber = isset($locAdress['buildingNumber']) ? $locAdress['buildingNumber'] : '';
		
		$name = $address . $blockNumber;
		if ($address !== '' || $blockNumber !== '') {
			$name .= '　'; 
		}
		$name .= $buildingNumber;

		$locSelectAddresses[] = [
			'codeDetail' => $codeDetail,
			'name' => $name,
		];
	}

	$addressAndBlockOrBuildingNumber = '';
	if (count($locSelectAddresses) > 0) {
		$addressAndBlockOrBuildingNumber = $locSelectAddresses[0]['name'] . (count($locSelectAddresses) > 1 ? '　外' : '');
	}
	$isHasIntermediary = $sale['salesBrokerageFee'] > 0;// 仲介手数料
	$isHasOutsourcingPrice = $sale['salesOutsourcingPrice'] > 0;// 業務委託料
	// 支払契約情報を取得
	if($isHasIntermediary || $isHasOutsourcingPrice){
		// 契約担当者
		$contractStaffName = getUserName($bukken['infoStaff']);

		$list_blockOrBuildingNumber = getBuildingNumber($locs, chr(10));        // 複数地番・複数家屋番号

		$payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $sale['tempLandInfoPid'])->where('bukkenSalesInfoPid', $sale['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
	
		foreach($payContracts as $pay) {
			$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
			if(sizeof($details) > 0) {
				foreach($details as $detail) {
					if(in_array($detail['paymentCode'], $paymentCodeByDecisionPriceList)) {
						if(!empty($detail['contractFixDay'])) $contractFixDay = $detail['contractFixDay'];
						if(!empty($detail['contractFixTime'])) $contractFixTime = $detail['contractFixTime'];
						if(!empty($detail['payPriceTax'])) $payPriceTax += intval($detail['payPriceTax']);
					}
					// 支払コードが仲介料の場合
					else if($detail['paymentCode'] == $paymentCodeByIntermediaryPrice) {
						$payDetail_intermediary = $detail;
						$payDetailInOuts[] = $detail + [
							'bukkenNo' => $bukken['bukkenNo'],
							'contractBukkenNo' => $bukken['contractBukkenNo']
						];
						$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_intermediary['payContractPid'])->asArray();
						$payDetail_intermediary = array_merge($pay, $payDetail_intermediary);
						$cntIntermediaryOrOutsourcing++;
						$cntIntermediary++;
					}
					// 支払コードが業務委託料の場合
					else if($detail['paymentCode'] == $paymentCodeByOutsourcingPrice) {
						$payDetail_outsourcing = $detail;
						$payDetailInOuts[] = $detail + [
							'bukkenNo' => $bukken['bukkenNo'],
							'contractBukkenNo' => $bukken['contractBukkenNo']
						];
						$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_outsourcing['payContractPid'])->asArray();
						$payDetail_outsourcing = array_merge($pay, $payDetail_outsourcing);
						$cntIntermediaryOrOutsourcing++;
						$cntOutsourcing++;
					}
				}
			}
		}
	}
	// 20250616 E_Add

	$address = '';          // 所在地
	$blockNumber = '';      // 地番
	$l_propertyTax = 0;     // 固定資産税（土地）
	$l_cityPlanningTax = 0; // 都市計画税（土地）
	$b_propertyTax = 0;     // 固定資産税（建物）
	$b_cityPlanningTax = 0; // 都市計画税（建物）
	$cntlocs = 0;
	$cntLandlocs = 0;
	foreach($locs as $loc) {
		$cntlocs++;
		// 区分が01：土地の場合
		if($loc['locationType'] == '01') {
			$l_propertyTax += $loc['propertyTax'];
			$l_cityPlanningTax += $loc['cityPlanningTax'];
			$cntLandlocs++;
		}
		else {
			$b_propertyTax += $loc['propertyTax'];
			$b_cityPlanningTax += $loc['cityPlanningTax'];
		}
		if($cntLandlocs == 1) {
			$address = $loc['address'];                 // 所在地
			$blockNumber = $loc['blockNumber'];         // 地番
		}
	}
	$addressAndBlockNumber = $address . $blockNumber;   // 所在地+地番
	if($cntLandlocs > 1) {
		$addressAndBlockNumber .= '　外';
	}

	// 20240528 S_Add
	// 振替伝票データ
	$bankName = getBankName($sale['bankPid']);
	$contractType = '1';// 入金
	$slipRemarks = '売決済';
	$contractBukkenNo = $bukken['contractBukkenNo'];
	$slipCodes = getCodesCommon('SYS602');
	$transferSlipDatas = array();

	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesTradingLandPrice', '土地売買決済代金', $bankName);
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesTradingBuildingPrice', '建物売買決済代金', $bankName);
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesTradingLeasePrice', '借地権売買決済代金', $bankName);
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesFixedLandTax', '固都税清算金（土地）', $bankName);
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesFixedBuildingTax', '固都税精算金（建物）', $bankName);
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesDeposit1', '受領済内金（手付金）を売買代金に充当');
	addSlipData($transferSlipDatas, $sale, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $sale['salesName'], 'salesRetainage', '留保金', $bankName);
	
	// 20250723 S_Add
	$transferSlipDatasExpert = getExpertDataExportInOut($sale, $payDetailInOuts, $slipCodes, $codeLists, $locs);
	foreach ($transferSlipDatasExpert as $data) {
		$transferSlipDatas[] = $data;
	}
	// 20250723 E_Add

	if(sizeof($transferSlipDatas) == 0) {
		$transferSlipDatas[] = new stdClass();
	}
	// 20240528 E_Add
		
	$endColumn = 12;// 最終列数
	$endRow = 34;   // 最終行数

	// 20220707 S_Add
	// ・支払明細書シート
	$sheet = $spreadsheet->getSheet(0);
	$title = $sheet->getTitle();
	$sheet->setTitle($title . '_' . $sale['pid']);

	// 買主<-売却先
	$cell = setCell(null, $sheet, 'salesName', 1, $endColumn, 1, $endRow, $sale['salesName']);
	// 物件名<-所在地
	$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
	// 契約物件番号
	$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
	// 支払日<-決済日
	$cell = setCell(null, $sheet, 'salesDecisionDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($sale['salesDecisionDay'], 'Y年n月j日'));
	// 売買代金
	$cell = setCell(null, $sheet, 'salesTradingPrice', 1, $endColumn, 1, $endRow, $sale['salesTradingPrice']);
	// 固定資産税清算金<-固都税清算金
	$cell = setCell(null, $sheet, 'salesFixedTax', 1, $endColumn, 1, $endRow, $salesFixedTax);
	// 銀行名
	$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $bank['bankName']);
	// 支店名
	$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $bank['branchName']);
	// 預金種目
	$cell = setCell(null, $sheet, 'depositTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['depositType'], $bank['depositType']));
	// 口座番号
	$cell = setCell(null, $sheet, 'accountNumber', 1, $endColumn, 1, $endRow, $bank['accountNumber']);
	// 口座名義
	$cell = setCell(null, $sheet, 'accountHolder', 1, $endColumn, 1, $endRow, $bank['accountHolder']);

	$sheet->setSelectedCell('A1');// 初期選択セル設定
	// 20220707 E_Add

	// 20240528 S_Update
	// for($i = 1 ; $i < 4; $i++) {
	// 20250616 S_Update
	// for($i = 1 ; $i < 5; $i++) {
	for($i = 1 ; $i < 9; $i++) {
		$endColumn = 12;// 最終列数
		$endRow = 34;   // 最終行数
		// ・支払依頼書帳票シート
		if($i == 1 && !($isHasIntermediary || $isHasOutsourcingPrice)) {
			continue;
		}
		// ・領収証 (仲介手数料)シート
		if($i == 5 && !$isHasIntermediary) {
			continue;
		}
		// ・領収証 (業務委託料)シート
		if($i == 6 && !$isHasOutsourcingPrice) {
			continue;
		}
		// ・振替伝票 (仲介・業務委託)シート
		if($i == $sheetIndexInOut && !($isHasIntermediary || $isHasOutsourcingPrice)) {
			continue;
		}
	// 20250616 E_Update
	// 20240528 E_Update
		// シートをコピー
		if($i != $sheetIndexInOut){// 20250616 Add
			$sheet = clone $spreadsheet->getSheet($i);
			$title = $sheet->getTitle();
			$sheet->setTitle($title . '_' . $sale['pid']);
			$spreadsheet->addSheet($sheet);
		}// 20250616 Add

		/*
		// ・支払明細書シート
		if($i == 0) {
			// 買主<-売却先
			$cell = setCell(null, $sheet, 'salesName', 1, $endColumn, 1, $endRow, $sale['salesName']);
			// 物件名<-所在地
			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			// 契約物件番号
			$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
			// 支払日<-決済日
			$cell = setCell(null, $sheet, 'salesDecisionDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($sale['salesDecisionDay'], 'Y年n月j日'));
			// 売買代金
			$cell = setCell(null, $sheet, 'salesTradingPrice', 1, $endColumn, 1, $endRow, $sale['salesTradingPrice']);
			// 固定資産税清算金<-固都税清算金
			$cell = setCell(null, $sheet, 'salesFixedTax', 1, $endColumn, 1, $endRow, $salesFixedTax);
			// 銀行名
			$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $bank['bankName']);
			// 支店名
			$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $bank['branchName']);
			// 預金種目
			$cell = setCell(null, $sheet, 'depositTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['depositType'], $bank['depositType']));
			// 口座番号
			$cell = setCell(null, $sheet, 'accountNumber', 1, $endColumn, 1, $endRow, $bank['accountNumber']);
			// 口座名義
			$cell = setCell(null, $sheet, 'accountHolder', 1, $endColumn, 1, $endRow, $bank['accountHolder']);
		}
		*/

		// 20250616 S_Add
		// ・支払依頼書帳票シート
		if($i == 1 && ($isHasIntermediary || $isHasOutsourcingPrice)) {
			$endColumn = 16;// 最終列数
			$endRow = 43;   // 最終行数
			// 居住表示
			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			// 契約物件番号
			$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年n月j日'));
			// 契約担当者
			$cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);

			if ($cntIntermediary == 0) {
				$payDetail_intermediary = $payDetail_outsourcing;
				$payDetail_outsourcing = []; // clear array
			}

			if ($cntOutsourcing == 0) {
				$payDetail_outsourcing = []; // clear array
			}

			// 仲介料
			// 日時
			$contractFixDateTime = convert_dt($payDetail_intermediary['contractFixDay'], 'Y/n/j');
			if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
			if(!empty($payDetail_intermediary['contractFixTime'])) $contractFixDateTime .= $payDetail_intermediary['contractFixTime'] . '～';
			$cell = setCell(null, $sheet, 'contractFixDateTime_intermediary', 1, $endColumn, 1, $endRow, $contractFixDateTime);
			// 契約書番号
			$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $sale['contractFormNumber']);
			// 複数地番/複数家屋番号
			// $cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockNumber);
			$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
			
			// 支払先<-取引先名称
			$cell = setCell(null, $sheet, 'supplierName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['supplierName']);
			
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
			// 契約書番号
			$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $sale['contractFormNumber']);
			// 複数地番/複数家屋番号
			$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $list_blockOrBuildingNumber);
			// 支払先<-取引先名称
			$cell = setCell(null, $sheet, 'supplierName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['supplierName']);
			
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
		
			// 20250616 S_Add
			if($cntIntermediary == 0 || $cntOutsourcing == 0){
				$sheet->removeRow(5,1);
				$sheet->getCell('J5')->setValue('=SUM(J4:J4)');
				$sheet->getCell('K5')->setValue('=SUM(K4:K4)');
				$sheet->getCell('M5')->setValue('=SUM(M4:M4)');
			}
			// 20250616 E_Add
		}
		// 20250616 E_Add

		// ・決済案内シート
		// 20250616 S_Update
		// if($i == 1) {
		if($i == 2) {
		// 20250616 E_Update
			// 決済日
			$cell = setCell(null, $sheet, 'salesDecisionDay_jpdt_kanji_MM', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sale['salesDecisionDay'], 'n月'));
			// 売却先
			$cell = setCell(null, $sheet, 'salesName', 1, $endColumn, 1, $endRow, $sale['salesName']);
			// 所在地
			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			// 決済日
			$cell = setCell(null, $sheet, 'salesDecisionDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($sale['salesDecisionDay'], 'Y年n月j日'));
			// 売買代金
			$cell = setCell(null, $sheet, 'salesTradingPrice', 1, $endColumn, 1, $endRow, $sale['salesTradingPrice']);
			// 固都税清算金
			$cell = setCell(null, $sheet, 'salesFixedTax', 1, $endColumn, 1, $endRow, $salesFixedTax);
			// 銀行名
			$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $bank['bankName']);
			// 支店名
			$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $bank['branchName']);
			// 預金種目
			$cell = setCell(null, $sheet, 'depositTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['depositType'], $bank['depositType']));
			// 口座番号
			$cell = setCell(null, $sheet, 'accountNumber', 1, $endColumn, 1, $endRow, $bank['accountNumber']);
			// 口座名義
			$cell = setCell(null, $sheet, 'accountHolder', 1, $endColumn, 1, $endRow, $bank['accountHolder']);
		}

		// ・固都税精算シート
		// 20250616 S_Update
		// if($i == 2) {
		if($i == 3) {
		// 20250616 E_Update
			// 所在地+地番
			$cell = setCell(null, $sheet, 'addressAndBlockNumber', 1, $endColumn, 1, $endRow, $addressAndBlockNumber);
			// 決済日
			$cell = setCell(null, $sheet, 'salesDecisionDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($sale['salesDecisionDay'], 'Y年n月j日'));
			// 売却先
			$cell = setCell(null, $sheet, 'salesName', 1, $endColumn, 1, $endRow, $sale['salesName']);
			// 固定資産税（土地）
			$cell = setCell(null, $sheet, 'l_propertyTax', 1, $endColumn, 1, $endRow, $l_propertyTax);
			// 都市計画税（土地）
			$cell = setCell(null, $sheet, 'l_cityPlanningTax', 1, $endColumn, 1, $endRow, $l_cityPlanningTax);
			// 固定資産税（建物）
			$cell = setCell(null, $sheet, 'b_propertyTax', 1, $endColumn, 1, $endRow, $b_propertyTax);
			// 都市計画税（建物）
			$cell = setCell(null, $sheet, 'b_cityPlanningTax', 1, $endColumn, 1, $endRow, $b_cityPlanningTax);
			// 分担期間開始日
			$cell = setCell(null, $sheet, 'sharingStartDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sale['sharingStartDay'], 'n月j日'));
			// 分担期間終了日
			$cell = setCell(null, $sheet, 'sharingEndDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sale['sharingEndDay'], 'n月j日'));
			// 分担期間開始日（買主）
			$sharingStartDayBuyer = $sale['sharingEndDay'];
			if(!empty($sharingStartDayBuyer))
			{
				$sharingStartDayBuyer = date('Ymd', strtotime('+1 day', strtotime($sharingStartDayBuyer)));
			}
			$cell = setCell(null, $sheet, 'sharingStartDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingStartDayBuyer, 'n月j日'));
			// 分担期間終了日（買主）
			$sharingEndDayBuyer = $sale['sharingStartDay'];
			if(!empty($sharingEndDayBuyer))
			{
				$sharingEndDayBuyer = date('Ymd', strtotime('+1 year', strtotime($sharingEndDayBuyer)));
				$sharingEndDayBuyer = date('Ymd', strtotime('-1 day', strtotime($sharingEndDayBuyer)));
			}
			$cell = setCell(null, $sheet, 'sharingEndDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingEndDayBuyer, 'n月j日'));
			// 固都税清算金（土地）
			$cell = setCell(null, $sheet, 'salesFixedLandTax', 1, $endColumn, 1, $endRow, $sale['salesFixedLandTax']);
			// 固都税清算金（建物）
			$cell = setCell(null, $sheet, 'salesFixedBuildingTax', 1, $endColumn, 1, $endRow, $sale['salesFixedBuildingTax']);
			// 建物分消費税
			$cell = setCell(null, $sheet, 'salesFixedBuildingTaxOnlyTax', 1, $endColumn, 1, $endRow, $sale['salesFixedBuildingTaxOnlyTax']);
			// 固都税清算金（合計）<-固都税清算金
			$cell = setCell(null, $sheet, 'salesFixedTax', 1, $endColumn, 1, $endRow, $salesFixedTax);
		}

		// ・領収証シート
		// 20250616 S_Update
		// if($i == 3) {
		if($i == 4) {
		// 20250616 E_Update
			// 売却先
			$cell = setCell(null, $sheet, 'salesName', 1, $endColumn, 1, $endRow, $sale['salesName']);
			// 所在地+地番
			$cell = setCell(null, $sheet, 'addressAndBlockNumber', 1, $endColumn, 1, $endRow, $addressAndBlockNumber);
			// 売買代金
			$cell = setCell(null, $sheet, 'salesTradingPrice', 1, $endColumn, 1, $endRow, $sale['salesTradingPrice']);
			// 固都税清算金
			$cell = setCell(null, $sheet, 'salesFixedTax', 1, $endColumn, 1, $endRow, $salesFixedTax);
			// 決済日
			$cell = setCell(null, $sheet, 'salesDecisionDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($sale['salesDecisionDay'], 'Y年n月j日'));

			/*
			// 収入印紙を設定
			$setRow = 19;// 設定位置
			copyMergeCellStyleWithVal($sheet, 3, $setRow, 3, $setRow + 1, 12, 5);
			*/
		}

		// 20250616 S_Add
		// ・領収証 (仲介手数料)シート
		if($i == 5 && $isHasIntermediary) {
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 代金（業務委託料）
			$cell = setCell(null, $sheet, 'payPriceTax_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['payPriceTax']);
			// 支払確定日（業務委託料）
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年n月j日'));
		}

		// ・領収証 (業務委託料)シート
		if($i == 6 && $isHasOutsourcingPrice) {
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 代金（業務委託料）
			$cell = setCell(null, $sheet, 'payPriceTax_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['payPriceTax']);
			// 支払確定日（業務委託料）
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_outsourcing', 1, $endColumn, 1, $endRow, convert_dt($payDetail_outsourcing['contractFixDay'], 'Y年n月j日'));
		}
		// 20250616 E_Add

		// 20240528 S_Add
		// 振替伝票シート
		// 20250616 S_Update
		// if($i == 4) {
		if($i == 7) {
		// 20250616 E_Update
			$endRow = 10;   // 最終行数
			$currentRow = 3;
			$currentColumn = 2;
			$cell = null;
			//決済日
			$cell = setCell($cell, $sheet, 'salesDecisionDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($sale['salesDecisionDay']));
			
			$cell = null;
			$cell = searchCell($sheet, 'debtorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow);
			if($cell != null) {
				$currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
				$currentRow = $cell->getRow();
				$pos = $currentRow;
			}

			if(sizeof($transferSlipDatas) > 1) {
				$endRow += sizeof($transferSlipDatas) * 2;
				copyBlockWithVal($sheet, $currentRow, 2, sizeof($transferSlipDatas) - 1, $endColumn);
			}
			$cell = null;

			foreach($transferSlipDatas as $slipData) {
			
				//借方勘定科目
				$cell = setCell($cell, $sheet, 'debtorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorKanjyoName);
				//借方金額
				$cell = setCell($cell, $sheet, 'debtorPayPrice', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorPayPrice);
				//貸方勘定科目
				$cell = setCell($cell, $sheet, 'creditorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorKanjyoName);
				//貸方金額
				$cell = setCell($cell, $sheet, 'creditorPayPrice', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorPayPrice);
				//摘要
				$cell = setCell($cell, $sheet, 'remark', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->remark);
				//備考
				$cell = setCell($cell, $sheet, 'note', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->note);
				//借方補助科目
				$cell = setCell($cell, $sheet, 'debtorKanjyoDetailName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorKanjyoDetailName);
				//借方消費税
				$cell = setCell($cell, $sheet, 'debtorPayTax', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorPayTax);
				//貸方補助科目
				$cell = setCell($cell, $sheet, 'creditorKanjyoDetailName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorKanjyoDetailName);
				//貸方消費税
				$cell = setCell($cell, $sheet, 'creditorPayTax', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorPayTax);
			
				$currentRow += 2;
			}

			$sheet->setCellValue('B' . $currentRow, '=SUM(B' . $pos . ':B' . ($currentRow - 1) . ')');
			$sheet->setCellValue('D' . $currentRow, '=SUM(D' . $pos . ':D' . ($currentRow - 1) . ')');
		}
		// 20240528 E_Add

		// 20250616 S_Add
		// ・振替伝票 (仲介・業務委託)シート
		if($i == $sheetIndexInOut && ($isHasIntermediary || $isHasOutsourcingPrice)) {
			createExportInOut($spreadsheet, $sale, $payDetailInOuts, $sheetIndexInOut, $slipCodes, $codeLists, $locs);
		}
		// 20250616 E_Add

		$sheet->setSelectedCell('A1');// 初期選択セル設定
	}
}

// コピー元シート削除
// 20240528 S_Update
// for($i = 1 ; $i < 4; $i++) {
// 20250616 S_Update
// for($i = 1 ; $i < 5; $i++) {
for($i = 1 ; $i < 9; $i++) {
// 20250616 E_Update
// 20240528 E_Update
	$spreadsheet->removeSheetByIndex(1);
}

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '売却決済案内_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath . '/' . $filename;

// 20230521 S_Add
// Excel側の数式を再計算させる
$writer->setPreCalculateFormulas(false);
// 20230521 E_Add

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
 * 結合セルと書式コピー
 */
function copyMergeCellStyleWithVal($sheet, $startColumn, $startRow, $endColumn, $endRow, $fromColumn, $fromRow) {
	// セルの位置（文字列）を取得 3,19 → C19
	$dstCellStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::stringFromColumnIndex($startColumn) . ($startRow);
	$dstCellEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::stringFromColumnIndex($endColumn) . ($endRow);
	$merge = $dstCellStart . ":" . $dstCellEnd;
	// セルを結合
	$sheet->mergeCells($merge);

	// コピー元の値を設定
	$setCell = $sheet->getCellByColumnAndRow($fromColumn, $fromRow);
	$sheet->setCellValueByColumnAndRow($startColumn, $startRow, $setCell);

	$i = 0;
	$j = 0;
	// 結合セルすべてに書式を反映する
	for ($col = $startColumn; $col <= $endColumn; $col++) {
		for ($row = $startRow; $row <= $endRow; $row++) {
			$setStyle = $sheet->getStyleByColumnAndRow($fromColumn + $i, $fromRow + $j);
			$dstCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::stringFromColumnIndex($col) . ($row);
			$sheet->duplicateStyle($setStyle, $dstCell);
			$j++;
		}
		$i++;
	}
}

// 20240528 S_Add
/**
 * 行と値コピー
 */
function copyBlockWithVal($sheet, $startPos, $blockRowCount, $copyCount, $colums) {
	$sheet->insertNewRowBefore($startPos, $blockRowCount * $copyCount);
	$lastPos = $startPos + ($blockRowCount * $copyCount);
	for($cursor = 0 ; $cursor < $copyCount ; $cursor++) {
		$copyPos = $startPos  + $blockRowCount * $cursor;
		copyRowsWithValue($sheet, $lastPos, $copyPos, $blockRowCount, $colums);
	}
}
// 20240528 E_Add

// 20250616 S_Add
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
 * 地番or家屋番号取得（指定文字区切り）
 */
function getBuildingNumber($lst, $split) {
	$ret = [];
	if(isset($lst)) {
		foreach($lst as $data) {
			if(!empty($data['blockNumber'])) $ret[] = mb_convert_kana($data['blockNumber'], 'kvrn');
			else if(!empty($data['buildingNumber'])) $ret[] = mb_convert_kana($data['buildingNumber'], 'kvrn');
		}
	}
	return implode($split, $ret);
}

function sanitizeSheetTitle($title) {
	$title = preg_replace('/[\\\\\\/\\:\\?\\*\\[\\]]/', '', $title);
	return mb_substr($title, 0, 31);
}
function createExportInOut($spreadsheet, $sale, $payDetails, $sheetIndex, $slipCodes, $codeLists, $locs){
	$isHasBrokerageDate = isset($sale['salesBrokerageFeePayDay']);// 20250723 Add

	usort($payDetails, function ($a, $b) {
		return strcmp($a["contractFixDay"], $b["contractFixDay"]);
	});
	
	$contractType = '0';
	$targets = [];

	foreach($payDetails as $payDetail) {
		
		// key=物件番号+支払確定日
		$key = $payDetail['bukkenNo'] . '-' . $payDetail['contractFixDay'];
		// グルーピングを行う
		if(!isset($targets[$key])) {
			$groups = [];
			$groups[] = $payDetail;
			$targets[$key] = $groups;
		} else {
			$groups = $targets[$key];
			$groups[] = $payDetail;
			$targets[$key] = $groups;
		}
	}

	foreach ($targets as $key => $groups) {
		// 20250723 S_Delete
		// // 振替伝票シートをコピー
		// $sheet = clone $spreadsheet->getSheet($sheetIndex);
		// $title = $sheet->getTitle();
		// // $sheet->setTitle($title . '_' . $contract['contractNumber'] . '_' . $key);
		// $sheet->setTitle(sanitizeSheetTitle($title . '_' . $sale['pid'] . '_' . $key));
		
		// $spreadsheet->addSheet($sheet);
		// 20250723 E_Delete
		
		// 開始セル行
		$endColumn = 11; // 最終列数 
		$endRow = 10;   // 最終行数
		$currentRow = 3;
		$currentColumn = 2;
		$cell = null;
		$pos = 4;
	
		$transferSlipDatas = array();
	
		foreach($groups as $payDetail) {
			// 支払契約情報を取得
			$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
			
			// 仕入契約情報を取得
			// $contracts = [$contract];
	
			if(!empty($payDetail['contractor'])) {
				$contractor = $payDetail['contractor'];
				$contractSellerInfoPids = [];
				$explode1st = [];
				$explode2nd = [];
				// |で分割されている場合
				if(strpos($contractor, '|') !== false) {
					$explode1st = explode('|', $contractor);
				}
				else $explode1st[] = $contractor;
	
				foreach($explode1st as $explode1) {
					// ,で分割されている場合
					if(strpos($explode1, ',') !== false) {
						$temps = explode(',', $explode1);
						foreach($temps as $temp) {
							$explode2nd[] = $temp;
						}
					}
					else $explode2nd[] = $explode1;
				}
				foreach($explode2nd as $explode2) {
					$contractSellerInfoPids[] = $explode2;
				}
	
				// 仕入契約者情報を取得
				$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where_in('pid', $contractSellerInfoPids)->where_null('deleteDate')->findArray();
				if(sizeof($sellers) > 0) {
					$contractInfoPids = [];
					foreach($sellers as $seller) {
						$contractInfoPids[] = $seller['contractInfoPid'];
					}
	
					$list_contractorNameComma = getContractorName($sellers, '、');// 複数契約者名（カンマ区切り）
				}
			}
	
			// $locs = [];
			// if(sizeof($contracts) > 0) {
			// 	$contractInfoPids = [];
			// 	foreach($contracts as $contract) {
			// 		$contractInfoPids[] = $contract['pid'];
			// 	}
			// 	// 所在地情報を取得
			// 	$locs = getLocations($contractInfoPids);
			// }
			// else if(!empty($payDetail['tempLandInfoPid'])) {
			// 	// 所在地情報を取得
			// 	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $payDetail['tempLandInfoPid'])->where_null('deleteDate')->order_by_asc('displayOrder')->findArray();
			// }
	
			// 所在地
			$address = '';
			if(sizeof($locs) > 0) {
				$cntLandlocs = 0;
				$cntNotLandlocs = 0;
				$addressLand = '';
				$addressNotLand = '';
				foreach($locs as $loc) {
					// 区分が01：土地の場合
					if($loc['locationType'] == '01') {
						$cntLandlocs++;
						if($cntLandlocs == 1)
						{
							$addressLand = $loc['address'];
						}
					}
					else {
						$cntNotLandlocs++;
						if($cntLandlocs == 0 && $cntNotLandlocs == 1)
						{
							$addressNotLand = $loc['address'];
						}
					}
				}
				if($addressLand != '') $address = $addressLand;
				else $address = $addressNotLand;
			}
			// addSlipData2($transferSlipDatas, $contracts, $payDetail, $pay, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $payDetail['contractBukkenNo'], $list_contractorNameComma, '');
			// 20250801 S_Update
			// $slipRemarks = isset($payDetail['contractFixDay']) ? '売決済' : '買掛計上';
			if($payDetail['paymentCode'] == '1003'){// 仲介手数料
				$slipRemarks = isset($payDetail['contractFixDay']) ? '売決済' : '買掛計上';
			}
			else{
				$slipRemarks = '売決済';
			}
			// 20250801 E_Update
			addSlipData2($transferSlipDatas, null, $payDetail, $pay, $contractType, $slipCodes, $codeLists['paymentType2'], $slipRemarks, $address, $payDetail['contractBukkenNo'], $list_contractorNameComma, '', true);
		}
	
		// 20250723 S_Add
		if(!$isHasBrokerageDate){
			$transferSlipDatasNew = array();
			foreach ($transferSlipDatas as $data) {
				if ($data->paymentCode != '1003') {// 仲介手数料
					$data->creditorKanjyoName = '買掛金';
					$transferSlipDatasNew[] = $data;
				}
			}
			$transferSlipDatas = $transferSlipDatasNew;
		}

		if(sizeof($transferSlipDatas) > 0) {
			// 振替伝票シートをコピー
			$sheet = clone $spreadsheet->getSheet($sheetIndex);
			$title = $sheet->getTitle();
			// $sheet->setTitle($title . '_' . $contract['contractNumber'] . '_' . $key);
			$sheet->setTitle(sanitizeSheetTitle($title . '_' . $sale['pid'] . '_' . $key));
			
			$spreadsheet->addSheet($sheet);
			// 20250723 E_Add

			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($payDetail['contractFixDay']));

			$cell = null;

			$cell = searchCell($sheet, 'debtorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow);
			if($cell != null) {
				$currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
				$currentRow = $cell->getRow();
				$pos = $currentRow;
			}
			$cell = null;
		
			if(sizeof($transferSlipDatas) == 0) {
				$transferSlipDatas[] = new stdClass();
			}
		
			if(sizeof($transferSlipDatas) > 1) {
				$endRow += sizeof($transferSlipDatas) * 2;
				copyBlockWithVal($sheet, $currentRow, 2, sizeof($transferSlipDatas) - 1, $endColumn);
			}
		
			foreach($transferSlipDatas as $slipData) {
				
				//借方勘定科目
				$cell = setCell($cell, $sheet, 'debtorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorKanjyoName);
				//借方金額
				$cell = setCell($cell, $sheet, 'debtorPayPrice', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorPayPrice);
				//貸方勘定科目
				$cell = setCell($cell, $sheet, 'creditorKanjyoName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorKanjyoName);
				//貸方金額
				$cell = setCell($cell, $sheet, 'creditorPayPrice', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorPayPrice);
				//摘要
				$cell = setCell($cell, $sheet, 'remark', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->remark);
				//備考
				$cell = setCell($cell, $sheet, 'note', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->note);
				//借方補助科目
				$cell = setCell($cell, $sheet, 'debtorKanjyoDetailName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorKanjyoDetailName);
				//借方消費税
				$cell = setCell($cell, $sheet, 'debtorPayTax', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->debtorPayTax);
				//貸方補助科目
				$cell = setCell($cell, $sheet, 'creditorKanjyoDetailName', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorKanjyoDetailName);
				//貸方消費税
				$cell = setCell($cell, $sheet, 'creditorPayTax', $currentColumn, $endColumn, $currentRow, $endRow, $slipData->creditorPayTax);
			
				$currentRow += 2;
			}
		
			$sheet->setCellValue('B' . $currentRow, '=SUM(B' . $pos . ':B' . ($currentRow - 1) . ')');
			$sheet->setCellValue('D' . $currentRow, '=SUM(D' . $pos . ':D' . ($currentRow - 1) . ')');
		
			$sheet->setSelectedCell('A1');// 初期選択セル設定
		}// 20250723 Add
	}
}

// 20250723 S_Add
function getExpertDataExportInOut($sale, $payDetails, $slipCodes, $codeLists, $locs){
	$transferSlipDatasExpert = array();
	if(!isset($sale['salesBrokerageFeePayDay'])){
		usort($payDetails, function ($a, $b) {
			return strcmp($a["contractFixDay"], $b["contractFixDay"]);
		});
		
		$contractType = '0';
		$targets = [];

		foreach($payDetails as $payDetail) {
			
			// key=物件番号+支払確定日
			$key = $payDetail['bukkenNo'] . '-' . $payDetail['contractFixDay'];
			// グルーピングを行う
			if(!isset($targets[$key])) {
				$groups = [];
				$groups[] = $payDetail;
				$targets[$key] = $groups;
			} else {
				$groups = $targets[$key];
				$groups[] = $payDetail;
				$targets[$key] = $groups;
			}
		}

		foreach ($targets as $key => $groups) {
		
			$transferSlipDatas = array();
		
			foreach($groups as $payDetail) {
				// 支払契約情報を取得
				$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
				
				// 仕入契約情報を取得
		
				if(!empty($payDetail['contractor'])) {
					$contractor = $payDetail['contractor'];
					$contractSellerInfoPids = [];
					$explode1st = [];
					$explode2nd = [];
					// |で分割されている場合
					if(strpos($contractor, '|') !== false) {
						$explode1st = explode('|', $contractor);
					}
					else $explode1st[] = $contractor;
		
					foreach($explode1st as $explode1) {
						// ,で分割されている場合
						if(strpos($explode1, ',') !== false) {
							$temps = explode(',', $explode1);
							foreach($temps as $temp) {
								$explode2nd[] = $temp;
							}
						}
						else $explode2nd[] = $explode1;
					}
					foreach($explode2nd as $explode2) {
						$contractSellerInfoPids[] = $explode2;
					}
		
					// 仕入契約者情報を取得
					$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where_in('pid', $contractSellerInfoPids)->where_null('deleteDate')->findArray();
					if(sizeof($sellers) > 0) {
						$contractInfoPids = [];
						foreach($sellers as $seller) {
							$contractInfoPids[] = $seller['contractInfoPid'];
						}
		
						$list_contractorNameComma = getContractorName($sellers, '、');// 複数契約者名（カンマ区切り）
					}
				}
		
				// 所在地
				$address = '';
				if(sizeof($locs) > 0) {
					$cntLandlocs = 0;
					$cntNotLandlocs = 0;
					$addressLand = '';
					$addressNotLand = '';
					foreach($locs as $loc) {
						// 区分が01：土地の場合
						if($loc['locationType'] == '01') {
							$cntLandlocs++;
							if($cntLandlocs == 1)
							{
								$addressLand = $loc['address'];
							}
						}
						else {
							$cntNotLandlocs++;
							if($cntLandlocs == 0 && $cntNotLandlocs == 1)
							{
								$addressNotLand = $loc['address'];
							}
						}
					}
					if($addressLand != '') $address = $addressLand;
					else $address = $addressNotLand;
				}
				// 20250801 S_Update
				// $slipRemarks = isset($payDetail['contractFixDay']) ? '売決済' : '買掛計上';
				if($payDetail['paymentCode'] == '1003'){// 仲介手数料
					$slipRemarks = isset($payDetail['contractFixDay']) ? '売決済' : '買掛計上';
				}
				else{
					$slipRemarks = '売決済';
				}
				// 20250801 E_Update
				addSlipData2($transferSlipDatas, null, $payDetail, $pay, $contractType, $slipCodes, $codeLists['paymentType2'], $slipRemarks, $address, $payDetail['contractBukkenNo'], $list_contractorNameComma, '', true);
			}

			foreach ($transferSlipDatas as $data) {
				if ($data->paymentCode == '1003') {
					$data->creditorKanjyoName = '買掛金';
					$transferSlipDatasExpert[] = $data;
				}
			}
		}
	}
	return $transferSlipDatasExpert;
}
// 20250723 E_Add

/**
 * 所在地情報取得
 */
function getLocations($contractInfoPids) {
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
	->where_in('p1.contractInfoPid', $contractInfoPids)
	->order_by_asc('p1.pid')->findArray();
	return $lst;
}

/**
 * 契約者取得（指定文字区切り）
 */
function getContractorName($lst, $split) {
	$ret = [];
	if(isset($lst)) {
		foreach($lst as $data) {
			if(!empty($data['contractorName'])) $ret[] = mb_convert_kana($data['contractorName'], 'kvrn');
		}
	}
	return implode($split, $ret);
}
// 20250616 E_Add
?>
