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
$paymentCodeByOutsourcingPrice = '';	// 業務委託料
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

// 20220529 S_Add
// 開始セル行
$pos = 4;
// 地権者振込一覧シート
$sheet = $spreadsheet->getSheet(0);
// データが複数ある場合、ブロックをコピー
if(sizeof($contracts) > 1) {
	// 20220712 S_Update
//	copyBlockWithVal($sheet, $pos, 1, sizeof($contracts) - 1, 14);
	copyBlockWithVal($sheet, $pos, 1, sizeof($contracts) - 1, 17);
	// 20220712 E_Update
}
// 合計の計算式
$sheet->setCellValue('J' . ($pos + sizeof($contracts)), '=SUM(J' . $pos . ':J' . ($pos + sizeof($contracts) - 1) . ')');
$sheet->setCellValue('K' . ($pos + sizeof($contracts)), '=SUM(K' . $pos . ':K' . ($pos + sizeof($contracts) - 1) . ')');
// 20220707 S_Update
// $sheet->setCellValue('L' . ($pos + sizeof($contracts)), '=SUM(L' . $pos . ':L' . ($pos + sizeof($contracts) - 1) . ')');
$sheet->setCellValue('M' . ($pos + sizeof($contracts)), '=SUM(M' . $pos . ':M' . ($pos + sizeof($contracts) - 1) . ')');
// 20220707 E_Update
// 20220529 E_Add

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
	// 20220529 S_Update
	// 20220118 S_Add
	// $list_contractorName = getContractorName($sellers);// 複数契約者名
	// 20220118 E_Add
	$list_contractorName = getContractorName($sellers, chr(10));// 複数契約者名（改行区切り）
	$list_contractorNameDot = getContractorName($sellers, '・');// 複数契約者名（・区切り）
	$cnt_contractorName = sizeof(explode(chr(10), $list_contractorName));
	// 20220529 E_Update

	// 支払契約情報を取得
	$payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $contract['tempLandInfoPid'])->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
	// 支払契約詳細情報を取得
	$contractFixDay = ''; // 支払確定日
	$contractFixTime = '';// 支払時間
	$payPriceTax = 0;     // 支払金額（税込） 20220223 Add
	// 20220331 S_Add
	$payDetail_intermediary = null;// 支払契約詳細情報（仲介料）
	$payDetail_outsourcing = null; // 支払契約詳細情報（業務委託料）
	// 20220331 E_Add
	$cntIntermediaryOrOutsourcing = 0;// 20220703 Add
	foreach($payContracts as $pay) {
		$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
		if(sizeof($details) > 0) {
			foreach($details as $detail) {
				if($detail['paymentCode'] == $paymentCodeByDecisionPrice) {
					$contractFixDay = $detail['contractFixDay'];
					$contractFixTime = $detail['contractFixTime'];
					$payPriceTax = intval($detail['payPriceTax']);// 20220223 Add
				}
				// 20220331 S_Add
				// 支払コードが仲介料の場合
				else if($detail['paymentCode'] == $paymentCodeByIntermediaryPrice) {
					$payDetail_intermediary = $detail;
					$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_intermediary['payContractPid'])->asArray();
					$payDetail_intermediary = array_merge($pay, $payDetail_intermediary);
					$cntIntermediaryOrOutsourcing++;// 20220703 Add
				}
				// 支払コードが業務委託料の場合
				else if($detail['paymentCode'] == $paymentCodeByOutsourcingPrice) {
					$payDetail_outsourcing = $detail;
					$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_outsourcing['payContractPid'])->asArray();
					$payDetail_outsourcing = array_merge($pay, $payDetail_outsourcing);
					$cntIntermediaryOrOutsourcing++;// 20220703 Add
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
	// 20220609 S_Add
	$cntLandlocs = 0;
	$cntNotLandlocs = 0;
	$bottomLands = [];
	// 20220609 E_Add
	// 20221024 S_Add
	$addressLand = '';
	$addressNotLand = '';
	// 20221024 E_Add

	foreach($locs as $loc) {
		$cntlocs++;

		// 20220609 S_Delete
		/*
		if($cntlocs == 1) {
			$address = $loc['address'];
			$blockNumber = $loc['blockNumber'];
			$buildingNumber = $loc['buildingNumber'];
		}
		*/
		// 20220609 E_Delete
		// 区分が01：土地の場合
		if($loc['locationType'] == '01') {
			$l_propertyTax += $loc['propertyTax'];
			$l_cityPlanningTax += $loc['cityPlanningTax'];
			$cntLandlocs++;// 20220609 Add
			// 20221024 S_Add
			if($cntLandlocs == 1) {
				$addressLand = $loc['address'];    // 所在地
				$blockNumber = $loc['blockNumber'];// 地番
			}
			// 20221024 E_Add
		}
		else {
			$b_propertyTax += $loc['propertyTax'];
			$b_cityPlanningTax += $loc['cityPlanningTax'];
			// 20220609 S_Add
			if(!empty($loc['bottomLandPid'])) $bottomLands[] = $loc;
			$cntNotLandlocs++;
			// 20220609 E_Add
			// 20221024 S_Add
			if($cntLandlocs == 0 && $cntNotLandlocs == 1) {
				$addressNotLand = $loc['address'];       // 所在地
				$buildingNumber = $loc['buildingNumber'];// 家屋番号
			}
			// 20221024 E_Add
		}
		// 20221024 S_Delete
		/*
		// 20220609 S_Add
		if($cntLandlocs == 1) {
			$address = $loc['address'];                 // 所在地
			$blockNumber = $loc['blockNumber'];         // 地番
		}
		if($cntLandlocs == 0 && $cntNotLandlocs == 1) {
			$address = $loc['address'];                 // 所在地
			$buildingNumber = $loc['buildingNumber'];   // 家屋番号
		}
		// 20220609 E_Add
		*/
		// 20221024 E_Delete
	}
	// 20221024 S_Add
	if($addressLand != '') $address = $addressLand;
	else $address = $addressNotLand;
	// 20221024 E_Add
	$blockOrBuildingNumber = $blockNumber;
	// 20220609 S_Update
	// if(empty($blockOrBuildingNumber)) $blockOrBuildingNumber = $buildingNumber;
	if(empty($blockOrBuildingNumber) && !empty($buildingNumber)) $blockOrBuildingNumber = '（' . $buildingNumber . '）';
	// 20220609 E_Update
	// 20220118 S_Add
	$list_blockOrBuildingNumber = getBuildingNumber($locs, chr(10));        // 複数地番・複数家屋番号
	$addressAndBlockOrBuildingNumber = $address . $blockOrBuildingNumber;   // 所在地+地番/家屋番号
	// 20220609 S_Update
	/*
	if($cntlocs > 1) {
		$addressAndBlockOrBuildingNumber .= '　他';
	}
	*/
	if($cntLandlocs > 1) {
		$addressAndBlockOrBuildingNumber .= '　外';
	}
	// 20220609 E_Update
	// 20220118 E_Add
	// 20220609 S_Add
	// 土地に指定がないかつ、底地に指定がある場合
	else if($cntLandlocs == 0 && sizeof($bottomLands) > 0) {
		foreach($bottomLands as $loc) {
			$bottomLand = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['bottomLandPid']);
			$addressAndBlockOrBuildingNumber = $bottomLand['address'] . $bottomLand['blockNumber'];
			if(!empty($loc['buildingNumber'])) $addressAndBlockOrBuildingNumber .= '（家屋番号：' . $loc['buildingNumber'] . '）';
			break;
		}
	}
	// 20220609 E_Add
	// 20220529 S_Add
	$payPriceTaxTitle = '売買代金';// 20220603 Add
	$description = '上記所在物件の不動産売買契約書第3条に基づく売買代金として';
	// 内金・手付金の日付チェックのいずれかにチェックがある場合
	if($contract['deposit1DayChk'] == '1' || $contract['deposit2DayChk'] == '1' || $contract['deposit3DayChk'] == '1'
		|| $contract['deposit4DayChk'] == '1' || $contract['earnestPriceDayChk'] == '1') {
			// $payPriceTax = intval($contract['tradingBalance']);// 売買代金<-売買残代金 20220603 Delete
			$payPriceTaxTitle = '売買残代金';// 20220603 Add
			$description = '上記所在物件の令和　年　月　日締結覚書に基づく売買代金（残代金）として';
	}
	$fixedTax = intval($contract['fixedLandTax']) + intval($contract['fixedBuildingTax']) + intval($contract['fixedBuildingTaxOnlyTax']);
	$payPriceTaxPlusFixedTax = $payPriceTax + $fixedTax;
	// 20220529 E_Add

	// 20220703 S_Update
	// $endColumn = 13;// 最終列数
	$endColumn = 16;// 最終列数
	// 20220703 E_Update
	$endRow = 43;   // 最終行数

	// 20220529 S_Add
	// ・地権者振込一覧シート
	$sheet = $spreadsheet->getSheet(0);

	// 居住表示
	$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
	// 契約物件番号
	$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
	// 支払確定日
	$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'Y年n月j日'));
	// 買主氏名<-売主対象
	$sellerName = '';
	if($bukken['seller'] == '0') $sellerName = '（株）' . getCodeTitle($codeLists['seller'], $bukken['seller']);
	else if($bukken['seller'] == '1') $sellerName = getCodeTitle($codeLists['seller'], $bukken['seller']);
	$cell = setCell(null, $sheet, 'sellerName', 1, $endColumn, 1, $endRow, $sellerName);
	// 契約担当者
	$contractStaffName = getUserName($contract['contractStaff']);
	$cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);
	// 日時
	$contractFixDateTime = convert_dt($contractFixDay, 'Y/n/j');
	if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
	if(!empty($contractFixTime)) $contractFixDateTime .= $contractFixTime . '～';
	$cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
	// 契約書番号
	$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
	// 地番/家屋番号
	$cell = setCell(null, $sheet, 'blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $blockOrBuildingNumber);
	// 地権者（売主）<-契約者名
//	$cell = setCell(null, $sheet, 'contractorName', 1, $endColumn, 1, $endRow, $contractorName);
	// 複数地番/複数家屋番号
	$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
	// 複数地権者（売主）<-契約者名
	$cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $list_contractorName);
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
	// 代金
	$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payPriceTax);
	// 固都税
	$cell = setCell(null, $sheet, 'fixedTax', 1, $endColumn, 1, $endRow, $fixedTax);
	// 送金金額
	$cell = setCell(null, $sheet, 'payPriceTaxPlusFixedTax', 1, $endColumn, 1, $endRow, $payPriceTaxPlusFixedTax);
	
	$sheet->setSelectedCell('A1');// 初期選択セル設定
	// 20220529 E_Add

	// 20220529 S_Update
	// for($i = 0 ; $i < 4; $i++) {
	for($i = 1 ; $i < 6; $i++) {
	// 20220529 E_Update

		// 20220703 S_Add
		// 仲介料と業務委託料が未登録の場合、支払依頼書帳票シートは作成しない
		if($i == 1 && $cntIntermediaryOrOutsourcing == 0) continue;
		// 20220703 E_Add

		// シートをコピー
		// $sheet = $spreadsheet->getSheet($i);
		$sheet = clone $spreadsheet->getSheet($i);
		$title = $sheet->getTitle();
		$sheet->setTitle($title . '_' . $contract['contractNumber']);
		$spreadsheet->addSheet($sheet);

		// ・支払依頼書帳票シート
		if($i == 1) {
			// 居住表示
			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			// 契約物件番号
			$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年n月j日'));
			// 契約担当者
			$cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);

			// 仲介料
			// 日時
			$contractFixDateTime = convert_dt($payDetail_intermediary['contractFixDay'], 'Y/n/j');
			if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
			if(!empty($payDetail_intermediary['contractFixTime'])) $contractFixDateTime .= $payDetail_intermediary['contractFixTime'] . '～';
			$cell = setCell(null, $sheet, 'contractFixDateTime_intermediary', 1, $endColumn, 1, $endRow, $contractFixDateTime);
			// 契約書番号
			$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
			// 複数地番/複数家屋番号
			$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
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
			// 契約書番号
			$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
			// 複数地番/複数家屋番号
			$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
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
		}

		// ・決済案内シート
		if($i == 2) {
			// 契約者名
//			$cell = setCell(null, $sheet, 'contractorName', 1, $endColumn, 1, $endRow, $contractorName);
			// 20220529 S_Add
			// 複数契約者名
			$cell = setCell(null, $sheet, 'list_contractorNameDot', 1, $endColumn, 1, $endRow, $list_contractorNameDot);
			// 20220529 E_Add
			// 契約担当者
			$cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);
			// 居住表示
			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji_MM', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'n月'));
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_MMdd', 1, $endColumn, 1, $endRow, convert_dt($contractFixDay, 'n月j日'));
			// 支払時間
			$cell = setCell(null, $sheet, 'contractFixTime', 1, $endColumn, 1, $endRow, $contractFixTime);
			// 20220603 S_Add
			// 売買代金タイトル
			$cell = setCell(null, $sheet, 'payPriceTaxTitle', 1, $endColumn, 1, $endRow, $payPriceTaxTitle);
			// 20220603 E_Add
			// 売買代金
			// 20220223 S_Update
			// $cell = setCell(null, $sheet, 'tradingPrice', 1, $endColumn, 1, $endRow, $contract['tradingPrice']);
			$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payPriceTax);
			// 固都税清算金（合計）
			$cell = setCell(null, $sheet, 'fixedTax', 1, $endColumn, 1, $endRow, $fixedTax);
			// 銀行・信用金庫等<-銀行名
			$cell = setCell(null, $sheet, 'bank', 1, $endColumn, 1, $endRow, $contract['bank']);
			// 支店
			$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $contract['branchName']);
			// 口座種類<-口座種別
			$cell = setCell(null, $sheet, 'accountTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $contract['accountType']));
			// 口座番号<-口座
			$cell = setCell(null, $sheet, 'accountName', 1, $endColumn, 1, $endRow, $contract['accountName']);
			// 振込口座名義<-名義
			$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $contract['bankName']);
			// 20220223 E_Update
		}

		// ・固都税精算シート
		if($i == 3) {
			// 物件所在地<-所在地
			// 20220707 S_Update
//			$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 20220707 E_Update
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'n月j日'));
			// 契約者名
//			$cell = setCell(null, $sheet, 'contractorName', 1, $endColumn, 1, $endRow, $contractorName);
			// 複数契約者名
			$cell = setCell(null, $sheet, 'list_contractorNameDot', 1, $endColumn, 1, $endRow, $list_contractorNameDot);
			// 買主氏名<-売主対象
			$cell = setCell(null, $sheet, 'sellerName', 1, $endColumn, 1, $endRow, $sellerName);
			// 固定資産税（土地）
			$cell = setCell(null, $sheet, 'l_propertyTax', 1, $endColumn, 1, $endRow, $l_propertyTax);
			// 都市計画税（土地）
			$cell = setCell(null, $sheet, 'l_cityPlanningTax', 1, $endColumn, 1, $endRow, $l_cityPlanningTax);
			// 固定資産税（建物）
			$cell = setCell(null, $sheet, 'b_propertyTax', 1, $endColumn, 1, $endRow, $b_propertyTax);
			// 都市計画税（建物）
			$cell = setCell(null, $sheet, 'b_cityPlanningTax', 1, $endColumn, 1, $endRow, $b_cityPlanningTax);
			// 分担期間開始日
			$cell = setCell(null, $sheet, 'sharingStartDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contract['sharingStartDay'], 'n月j日'));
			// 分担期間終了日
			$cell = setCell(null, $sheet, 'sharingEndDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contract['sharingEndDay'], 'n月j日'));
			// 分担期間開始日（買主）
			$sharingStartDayBuyer = $contract['sharingEndDay'];
			if(!empty($sharingStartDayBuyer))
			{
				$sharingStartDayBuyer = date('Ymd', strtotime('+1 day', strtotime($sharingStartDayBuyer)));
			}
			$cell = setCell(null, $sheet, 'sharingStartDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingStartDayBuyer, 'n月j日'));
			// 分担期間終了日（買主）
			$sharingEndDayBuyer = $contract['sharingStartDay'];
			if(!empty($sharingEndDayBuyer))
			{
				$sharingEndDayBuyer = date('Ymd', strtotime('+1 year', strtotime($sharingEndDayBuyer)));
				$sharingEndDayBuyer = date('Ymd', strtotime('-1 day', strtotime($sharingEndDayBuyer)));
			}
			$cell = setCell(null, $sheet, 'sharingEndDayBuyer_dt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($sharingEndDayBuyer, 'n月j日'));
			// 固都税清算金（土地）
			$cell = setCell(null, $sheet, 'fixedLandTax', 1, $endColumn, 1, $endRow, $contract['fixedLandTax']);
			// 固都税清算金（建物）
			$cell = setCell(null, $sheet, 'fixedBuildingTax', 1, $endColumn, 1, $endRow, $contract['fixedBuildingTax']);
			// 建物分消費税
			$cell = setCell(null, $sheet, 'fixedBuildingTaxOnlyTax', 1, $endColumn, 1, $endRow, $contract['fixedBuildingTaxOnlyTax']);
			// 固都税清算金（合計）
			$cell = setCell(null, $sheet, 'fixedTax', 1, $endColumn, 1, $endRow, $fixedTax);
		}

		// ・受領証シート
		if($i == 4) {
			// 20220118 S_Add
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 売買代金
			$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payPriceTax);
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'n月j日'));
			// 20220118 E_Add
			// 固都税清算金（合計）
			$cell = setCell(null, $sheet, 'fixedTax', 1, $endColumn, 1, $endRow, $fixedTax);
			// 20220529 S_Add
			// 摘要
			$cell = setCell(null, $sheet, 'description', 1, $endColumn, 1, $endRow, $description);

			$pos = 19;      // 開始セル行
			$copyEndRow = 4;// コピー行
			// データが複数ある場合、ブロックをコピー
			if($cnt_contractorName > 1) {
				copyBlockWithVal($sheet, $pos, $copyEndRow, $cnt_contractorName - 1, 11);
			}
			// 20220703 S_Delete
			/*
			// 収入印紙を設定
			$setRow = $pos + (($cnt_contractorName - 1) * $copyEndRow);// 設定位置
			copyMergeCellStyleWithVal($sheet, 3, $setRow, 3, $setRow + 1, 12, 5);
			*/
			// 20220703 E_Delete
			// 20220529 E_Add
		}

		// ・領収証シート
		if($i == 5) {
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 代金（業務委託料）
			$cell = setCell(null, $sheet, 'payPriceTax_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['payPriceTax']);
			// 支払確定日（業務委託料）
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_outsourcing', 1, $endColumn, 1, $endRow, convert_dt($payDetail_outsourcing['contractFixDay'], 'Y年n月j日'));
		}
		$sheet->setSelectedCell('A1');// 初期選択セル設定
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
// 20220529 S_Update
/*
for($i = 0 ; $i < 7; $i++) {
	$spreadsheet->removeSheetByIndex(0);
}
*/
for($i = 1 ; $i < 7; $i++) {
	$spreadsheet->removeSheetByIndex(1);
}
// 20220529 E_Update
// 20220331 E_Update

// R-A　PK精算シートを一番右へ追加
$spreadsheet->addSheet($sheet);

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '買取決済_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath . '/' . $filename;
// 20230314 S_Add
// Excel側の数式を再計算させる
$writer->setPreCalculateFormulas(false);
// 20230314 E_Add
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
	->select('p2.bottomLandPid', 'bottomLandPid')// 20220609 Add
	->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
	->where('p1.contractDataType', '01')
	->where('p1.contractInfoPid', $contractPid)
	->order_by_asc('p1.pid')->findArray();
	return $lst;
}
// 20220118 S_Add
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
				// 20220708 S_Update
//				$explode2nd = explode(',', $explode1);
				$temps = explode(',', $explode1);
				foreach($temps as $temp) {
					$explode2nd[] = $temp;
				}
				// 20220708 E_Update
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
// 20220529 S_Add
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
// 20220529 E_Add
?>
