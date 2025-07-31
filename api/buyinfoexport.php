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

// 20240930 S_Add 
// 契約方法
$contractMethodCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '043')->where_null('deleteDate')->findArray();
// 契約方法選択しない対応のため、空白データも追加
$contractMethodCodeList[] = [
    'codeDetail' => '', 
    'name' => ''
];;
// 20240930 E_Add 

// 20220331 S_Add
// 支払種別List
$paymentTypeList = ORM::for_table(TBLPAYMENTTYPE)->select('paymentCode', 'codeDetail')->select('paymentName', 'name')->where_null('deleteDate')->findArray();
$codeLists['paymentType'] = $paymentTypeList;
// 20220331 E_Add

// 決済代金の支払コードを取得
// 20231218 S_Update
// $paymentCodeByDecisionPrice = '';
$paymentCodeByDecisionPriceList = [];
// 20231218 E_Update
// 20220331 S_Add
$paymentCodeByIntermediaryPrice = '';   // 仲介手数料
$paymentCodeByOutsourcingPrice = '';	// 業務委託料
// 20220331 E_Add
$codes = ORM::for_table(TBLCODE)->where_Like('code', 'SYS1%')->order_by_asc('code')->findArray();
if(sizeof($codes) > 0) {
	foreach($codes as $code) {
		// 20231218 S_Update
		/*
		if($code['codeDetail'] == 'decisionPrice') {
			$paymentCodeByDecisionPrice = $code['name'];
		}
		*/
		if($code['codeDetail'] == 'tradingLandPrice' || $code['codeDetail'] == 'tradingBuildingPrice') {
			$paymentCodeByDecisionPriceList[] = $code['name'];
		}
		// 20231218 E_Update
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

// 20250723 S_Add
$haveIntermediaryOrOutsourcing = false;
$posMergeBegin = 4; 
$posMerge = $posMergeBegin; 
$totalRows = 0;
// 20250723 E_Add

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

	// 20250723 S_Add
	$sheet->mergeCells('A' . $posMergeBegin . ':A' . ($posMergeBegin + sizeof($contracts) - 1));

	$sheet2 = $spreadsheet->getSheet(1);
	copyBlockWithVal($sheet2, $pos, 2, sizeof($contracts) - 1, 17);
	// 20250723 E_Add
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
	// 20240528 S_Add
	$list_contractorNameComma = getContractorName($sellers, '、');// 複数契約者名（カンマ区切り）
	// 20240528 E_Add

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
	// 20250402 S_Add
	$cntIntermediary = 0;
	$cntOutsourcing = 0;
	$payDetailInOuts = array();
	// 20250402 E_Add

	foreach($payContracts as $pay) {
		$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
		if(sizeof($details) > 0) {
			foreach($details as $detail) {
				// 20231218 S_Update
				/*
				if($detail['paymentCode'] == $paymentCodeByDecisionPrice) {
					$contractFixDay = $detail['contractFixDay'];
					$contractFixTime = $detail['contractFixTime'];
					$payPriceTax = intval($detail['payPriceTax']);// 20220223 Add
				}
				*/
				if(in_array($detail['paymentCode'], $paymentCodeByDecisionPriceList)) {
					if(!empty($detail['contractFixDay'])) $contractFixDay = $detail['contractFixDay'];
					if(!empty($detail['contractFixTime'])) $contractFixTime = $detail['contractFixTime'];
					if(!empty($detail['payPriceTax'])) $payPriceTax += intval($detail['payPriceTax']);
				}
				// 20231218 E_Update
				// 20220331 S_Add
				// 支払コードが仲介料の場合
				else if($detail['paymentCode'] == $paymentCodeByIntermediaryPrice) {
					$payDetail_intermediary = $detail;
					// 20250402 S_Add
					$payDetailInOuts[] = $detail + [
						'bukkenNo' => $bukken['bukkenNo'],
						'contractBukkenNo' => $bukken['contractBukkenNo']
					];
					// 20250402 E_Add
					$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_intermediary['payContractPid'])->asArray();
					$payDetail_intermediary = array_merge($pay, $payDetail_intermediary);
					$cntIntermediaryOrOutsourcing++;// 20220703 Add
					$cntIntermediary++;// 20250402 Add
				}
				// 支払コードが業務委託料の場合
				else if($detail['paymentCode'] == $paymentCodeByOutsourcingPrice) {
					$payDetail_outsourcing = $detail;
					// 20250402 S_Add
					$payDetailInOuts[] = $detail + [
						'bukkenNo' => $bukken['bukkenNo'],
						'contractBukkenNo' => $bukken['contractBukkenNo']
					];
					// 20250402 E_Add
					$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail_outsourcing['payContractPid'])->asArray();
					$payDetail_outsourcing = array_merge($pay, $payDetail_outsourcing);
					$cntIntermediaryOrOutsourcing++;// 20220703 Add
					$cntOutsourcing++;// 20250402 Add
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

	// 20240207 S_Add
	$renCons = getRentalContractsForCalc($contract['pid']);
	// 20240207 E_Add

	$rens = getRentalsForContract($contract['pid']);// 20240930 Add

	// 20240221 S_Add
	if(!empty($renCons)){
		$renConsNew = array();

		foreach ($renCons as $rentalCT) {
			$evic = getEvic($rentalCT);
			// 立退き情報の明渡日が決済日以前ではない
			if(!(isset($contract['decisionDay']) && isset($evic['surrenderDate']) && $evic['surrenderDate'] < $contract['decisionDay'])){
				$renConsNew[] = $rentalCT;
			}
		}
		$renCons = $renConsNew;
	}
	// 20240221 E_Add

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

	// 20240528 S_Add
	// 振替伝票データ
	$contractType = '0';// 支払
	$slipRemarks = '買決済';
	$contractBukkenNo = $bukken['contractBukkenNo'];
	$slipCodes = getCodesCommon('SYS601');
	$transferSlipDatas = array();

	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'tradingLandPrice', '売買代金（土地）');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'tradingBuildingPrice', '');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'tradingLeasePrice', '');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'fixedLandTax', '');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'fixedBuildingTax', '');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'deposit1NoCheck', '支払済内金（手付金）を売買代金に充当');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'deposit1Checked', '支払済内金を売買代金に充当');
	addSlipData($transferSlipDatas, $contract, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $contractBukkenNo, $list_contractorNameComma, 'retainage', '');
	
	// 20240930 S_Add
	$contractTypeRen = '1';// 入金
	$slipCodesRen = getCodesCommon('SYS611');
	if(!empty($rens)){


		$buyerRevenueMonth = substr($contract['buyerRevenueStartDay'], 4, 2); // の月
		if($buyerRevenueMonth != ''){
			$buyerRevenueMonth = $buyerRevenueMonth . '月日割り賃料';
		}

		foreach ($rens as $ren) {
			// 各contractMethodと$renの組み合わせに基づいて$renConsをフィルタリング
			foreach ($contractMethodCodeList as $contractMethodCode) {
				$contractMethod = $contractMethodCode['codeDetail'];
				$contractName = $contractMethodCode['name'];
				$roomNos = '';

				// rentalInfoPidとcontractMethodの条件でフィルタリング
				$filteredRenConsForMethod = array_filter($renCons, function($renCon) use ($ren, $contractMethod) {
					return $renCon['rentalInfoPid'] == $ren['pid'] && $renCon['contractMethod'] == $contractMethod;
				});
		
				$objRentPrice = calculateRentPrices($contract, $filteredRenConsForMethod);
				
				foreach ($slipCodesRen as $codeRen) {

					$codeDetail = $codeRen['codeDetail'];

					$debtorPayPrice = null;// 借方金額
					$debtorPayTax = null;// 借方消費税
					$creditorPayPrice = null;// 貸方金額
					$creditorPayTax = null;// 貸方消費税

					$remarks = [];// 摘要

					// 賃料精算金（非課税）
					if($codeDetail == 'rentalSettlementNoPayTax'){
						$creditorPayPrice = $objRentPrice->rentPriceNoPayTaxMap;
						$debtorPayPrice = $creditorPayPrice;

						// 賃料清算金（非課税）の部屋番号
						$filteredRoomNos = array_filter($filteredRenConsForMethod, function($renConSub) {
							return (int) $renConSub['rentPriceTax'] + (int) $renConSub['managementFeeTax'] + (int) $renConSub['condoFeeTax'] == 0;
						});
						$roomNos = implode(',', array_column($filteredRoomNos, 'roomNo'));
						
						$remarks = [$address, $contractBukkenNo, $list_contractorNameComma, $contractName, $buyerRevenueMonth];
					}
					// 賃料精算金（課税）
					else if($codeDetail == 'rentalSettlementPayTax'){
						$creditorPayPrice = $objRentPrice->rentPricePayTaxMap;
						$creditorPayTax = $objRentPrice->rentPriceTaxMap;
						$debtorPayPrice = $creditorPayPrice + $creditorPayTax;

						// 賃料清算金（課税）の部屋番号
						$filteredRoomNos = array_filter($filteredRenConsForMethod, function($renConSub) {
							return (int) $renConSub['rentPriceTax'] + (int) $renConSub['managementFeeTax'] + (int) $renConSub['condoFeeTax'] > 0;
						});
						$roomNos = implode(',', array_column($filteredRoomNos, 'roomNo'));

						$remarks = [$address, $contractBukkenNo, $list_contractorNameComma, $contractName, $buyerRevenueMonth];
					}
					// 継承敷金
					else if($codeDetail == 'successionDeposit'){
						$creditorPayPrice = array_reduce($filteredRenConsForMethod, function($carry, $renCon) {
							return $carry + (int) $renCon['deposit'];
						}, 0);
						$debtorPayPrice = $creditorPayPrice;
						
						// 継承敷金の部屋番号
						$filteredRoomNos = array_filter($filteredRenConsForMethod, function($renConSub) {
							return $renConSub['deposit'] > 0;
						});
						$roomNos = implode(',', array_column($filteredRoomNos, 'roomNo'));
					
						$remarks = [$address, $contractBukkenNo, $contractName, $ren['apartmentName'], '敷金'];
					}
					// 保証金
					else if($codeDetail == 'successionSecurityDeposit'){
						$creditorPayPrice = array_reduce($filteredRenConsForMethod, function($carry, $renCon) {
							return $carry + (int) $renCon['securityDeposit'];
						}, 0);
						$debtorPayPrice = $creditorPayPrice;

						// 承継保証金の部屋番号
						$filteredRoomNos = array_filter($filteredRenConsForMethod, function($renConSub) {
							return $renConSub['securityDeposit'] > 0;
						});
						$roomNos = implode(',', array_column($filteredRoomNos, 'roomNo'));
					
						$remarks = [$address, $contractBukkenNo, $contractName, $ren['apartmentName'], '保証金'];
					}
					// 償却
					else if($codeDetail == 'amortization'){
						$creditorPayPrice = array_reduce($filteredRenConsForMethod, function($carry, $renCon) {
							return $carry + $renCon['amortization'];
						}, 0);
						$debtorPayPrice = $creditorPayPrice;

						// 償却の部屋番号
						$filteredRoomNos = array_filter($filteredRenConsForMethod, function($renConSub) {
							return (int) $renConSub['amortization'] > 0;
						});
						$roomNos = implode(',', array_column($filteredRoomNos, 'roomNo'));
					
						$remarks = [$address, $contractBukkenNo, $contractName, $ren['apartmentName'], '保証金償却相当額'];
					}
					
					if($roomNos != ''){
						$roomNos = $roomNos . '号室';
					}
					array_push($remarks, $roomNos);

					$filteredRemarks = array_filter($remarks, function($value) {
						return !empty($value);
					});
				
					$remark = implode('　', $filteredRemarks);
				
					addSlipDataValue($transferSlipDatas, $debtorPayPrice, $debtorPayTax, $creditorPayPrice, $creditorPayTax, $contractTypeRen, $slipCodesRen, $codeRen['codeDetail'], $remark, $slipRemarks);
				}
			}
		}
	}
	// 20240930 E_Add
	if(sizeof($transferSlipDatas) == 0) {
		$transferSlipDatas[] = new stdClass();
	}
	// 20240528 E_Add

	// 20240912 S_Add
	// 貸方借方決定マスタの借方勘定科目が空の時に、借方金額を空にする
	// 貸方金額を 決済案内_振替伝票の売買代金（土地）貸方金額から引き算する。
	$debtReduction = 0;

	foreach ($transferSlipDatas as $data) {
		// 20250219 S_Update
		// if (isset($data->executionType) && strpos($data->executionType, '3') === 0 && empty($data->debtorKanjyoName) && isset($data->debtorPayPrice) && $data->debtorPayPrice > 0) {
		if (isset($data->executionType) && isBeginWith($data->executionType, '3') && empty($data->debtorKanjyoName) && isset($data->debtorPayPrice) && $data->debtorPayPrice > 0) {
		// 20250219 E_Update
			$debtReduction += $data->debtorPayPrice;
			$data->debtorPayPrice = null;
		}
	}

	if($debtReduction > 0){
		foreach ($transferSlipDatas as &$landData) {
			if ($landData->priceType === 'tradingLandPrice') {
				if(isset($landData->creditorPayPrice)){
					$landData->creditorPayPrice -= $debtReduction;
				}
				break; 
			}
		}
		unset($landData); 
	}
	// 20240912 E_Add

	// 20241125 S_Add
	// 契約情報詳細にて売買代金（土地）に金額が入っていない場合に売買代金（土地）の行を出力しない
	$creditorPayPrice = 0;
	foreach ($transferSlipDatas as &$landData) {
		if ($landData->priceType === 'tradingLandPrice') {
			if(!isset($landData->debtorPayPrice) || $landData->debtorPayPrice == 0){
				$creditorPayPrice = $landData->creditorPayPrice;
				$landData->creditorPayPrice = null;
			}
			break; 
		}
	}
	unset($landData); 

	if(isset($creditorPayPrice) && $creditorPayPrice > 0){
	
		$priceType = '';
		foreach ($transferSlipDatas as $landData) {
			if(isset($landData->debtorPayPrice) && $landData->debtorPayPrice > 0){
				$priceType = $landData->priceType;
				break; 
			}
		}

		if(isset($priceType)){
			foreach ($transferSlipDatas as &$landData) {
				if ($landData->priceType === $priceType) {
					if(isset($landData->creditorPayPrice)){
						$landData->creditorPayPrice += $creditorPayPrice;
					}
					else{
						$landData->creditorPayPrice = $creditorPayPrice;
					}
					break; 
				}
			}
			unset($landData);
		}
		
		 $transferSlipDatasNew = array_filter($transferSlipDatas, function($slipData) {
			return ($slipData->debtorPayPrice != null && $slipData->debtorPayPrice != 0) || 
				   ($slipData->creditorPayPrice != null && $slipData->creditorPayPrice != 0);
		});
	
		$transferSlipDatasNew = array_values($transferSlipDatasNew);
	
		$transferSlipDatas = $transferSlipDatasNew;
	}
	// 20241125 E_Add

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

	// 20250723 S_Add
	// 仲介料と業務委託料が未登録の場合、支払依頼書帳票シートは作成しない
	if($cntIntermediaryOrOutsourcing != 0){
		$haveIntermediaryOrOutsourcing = true;
		$sheet = $spreadsheet->getSheet(1);

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
		$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
		// 複数地番/複数家屋番号
		$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
		
		// 複数地権者（売主）<-契約者名
		$cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $list_contractorName);

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
		$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $contract['contractFormNumber']);
		
		// 複数地番/複数家屋番号
		$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $list_blockOrBuildingNumber);

		// 複数地権者（売主）<-契約者名
		$cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $list_contractorName);

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
		// // 20250616 S_Add
		if($cntIntermediary == 0 || $cntOutsourcing == 0){
			$totalRows += 1;
			$sheet->removeRow($totalRows + $posMergeBegin,1);
			// $sheet->getCell('J5')->setValue('=SUM(J4:J4)');
			// $sheet->getCell('K5')->setValue('=SUM(K4:K4)');
			// $sheet->getCell('M5')->setValue('=SUM(M4:M4)');
			$posMerge += 1;
		}
		else{
			$totalRows += 2;
				
			$sheet->mergeCells('A' . $posMerge . ':A' . ($posMerge + 1));
			$sheet->mergeCells('B' . $posMerge . ':B' . ($posMerge + 1));
			$sheet->mergeCells('C' . $posMerge . ':C' . ($posMerge + 1));
			$sheet->mergeCells('D' . $posMerge . ':D' . ($posMerge + 1));
			$posMerge += 2;
		}
	}
	else {
		$sheet = $spreadsheet->getSheet(1);
		$sheet->removeRow($totalRows + $posMergeBegin,2);
	}
	// 20250723 E_Add

	// 20250402 S_Update
	// for($i = 1 ; $i <= 7; $i++) {
	$numberOfSheet = 10;
	$sheetIndexInOut = 9;// 振替伝票 (仲介・業務委託)
	$sheetIndexReceiptOutsourcing = 5;// 領収証 (業務委託料)
	$sheetIndexReceiptIntermediary = 6;// 領収証 (仲介手数料)
	// 20250723 S_Update
	// for($i = 1 ; $i < $numberOfSheet; $i++) {
	for($i = 2 ; $i < $numberOfSheet; $i++) {
	// 20250723 E_Update
	// 20250402 E_Update		

		// 20250723 S_Delete
		// // 20220703 S_Add
		// // 仲介料と業務委託料が未登録の場合、支払依頼書帳票シートは作成しない
		// if($i == 1 && $cntIntermediaryOrOutsourcing == 0) continue;
		// // 20220703 E_Add
		// 20250723 E_Delete
		
		// 20250402 S_Add
		// 仲介料と業務委託料が未登録の場合、振替伝票 (仲介・業務委託)シートは作成しない
		if($i == $sheetIndexInOut && $cntIntermediaryOrOutsourcing == 0) continue;
		if($i == $sheetIndexReceiptOutsourcing && $cntOutsourcing == 0) continue;
		if($i == $sheetIndexReceiptIntermediary && $cntIntermediary == 0) continue;
		// 20250402 E_Add

		// シートをコピー
		// $sheet = $spreadsheet->getSheet($i);
		if($i != $sheetIndexInOut){// 20250402 Add
			$sheet = clone $spreadsheet->getSheet($i);
			$title = $sheet->getTitle();
			$sheet->setTitle($title . '_' . $contract['contractNumber']);
			$spreadsheet->addSheet($sheet);
		}// 20250402 Add

		// 20250723 S_Delete
		// // ・支払依頼書帳票シート
		// if($i == 1) {
		// 	// 居住表示
		// 	$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
		// 	// 契約物件番号
		// 	$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
		// 	// 支払確定日
		// 	$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年n月j日'));
		// 	// 契約担当者
		// 	$cell = setCell(null, $sheet, 'contractStaffName', 1, $endColumn, 1, $endRow, $contractStaffName);

		// 	// 20250402 S_Add
		// 	if ($cntIntermediary == 0) {
		// 		$payDetail_intermediary = $payDetail_outsourcing;
		// 		$payDetail_outsourcing = []; // clear array
		// 	}

		// 	if ($cntOutsourcing == 0) {
		// 		$payDetail_outsourcing = []; // clear array
		// 	}
		// 	// 20250402 E_Add

		// 	// 仲介料
		// 	// 日時
		// 	$contractFixDateTime = convert_dt($payDetail_intermediary['contractFixDay'], 'Y/n/j');
		// 	if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
		// 	if(!empty($payDetail_intermediary['contractFixTime'])) $contractFixDateTime .= $payDetail_intermediary['contractFixTime'] . '～';
		// 	$cell = setCell(null, $sheet, 'contractFixDateTime_intermediary', 1, $endColumn, 1, $endRow, $contractFixDateTime);
		// 	// 契約書番号
		// 	$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
		// 	// 複数地番/複数家屋番号
		// 	$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
			
		// 	// 20250616 S_Add
		// 	// 複数地権者（売主）<-契約者名
		// 	$cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $list_contractorName);
		// 	// 20250616 E_Add

		// 	// 支払先<-取引先名称
		// 	$cell = setCell(null, $sheet, 'supplierName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['supplierName']);
		// 	/*
		// 	// 複数支払先<-契約者名
		// 	$cell = setCell(null, $sheet, 'list_contractorName_intermediary', 1, $endColumn, 1, $endRow, $list_contractorName_intermediary);
		// 	*/
		// 	// 振込口座名義<-名義
		// 	$cell = setCell(null, $sheet, 'bankName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['bankName']);
		// 	// 銀行・信用金庫等<-銀行名
		// 	$cell = setCell(null, $sheet, 'bank_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['bank']);
		// 	// 支店
		// 	$cell = setCell(null, $sheet, 'branchName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['branchName']);
		// 	// 口座種類<-口座種別
		// 	$cell = setCell(null, $sheet, 'accountTypeName_intermediary', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $payDetail_intermediary['accountType']));
		// 	// 口座番号<-口座
		// 	$cell = setCell(null, $sheet, 'accountName_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['accountName']);
		// 	// 代金
		// 	$cell = setCell(null, $sheet, 'payPriceTax_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['payPriceTax']);
		// 	// 備考<-支払名称+備考
		// 	$cell = setCell(null, $sheet, 'paymentName_intermediary', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail_intermediary['paymentCode']) . $payDetail_intermediary['detailRemarks']);

		// 	// 業務委託料
		// 	// 日時
		// 	$contractFixDateTime = convert_dt($payDetail_outsourcing['contractFixDay'], 'Y/m/d');
		// 	if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
		// 	if(!empty($payDetail_outsourcing['contractFixTime'])) $contractFixDateTime .= $payDetail_outsourcing['contractFixTime'] . '～';
		// 	$cell = setCell(null, $sheet, 'contractFixDateTime_outsourcing', 1, $endColumn, 1, $endRow, $contractFixDateTime);
		// 	// 契約書番号
		// 	// 20250402 S_Update
		// 	// $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);
		// 	$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $contract['contractFormNumber']);
		// 	// 20250402 E_Update
		// 	// 複数地番/複数家屋番号
		// 	// 20250402 S_Update
		// 	// $cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
		// 	$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $list_blockOrBuildingNumber);
		// 	// 20250402 E_Update

		// 	// 20250616 S_Add
		// 	// 複数地権者（売主）<-契約者名
		// 	$cell = setCell(null, $sheet, 'list_contractorName', 1, $endColumn, 1, $endRow, $cntIntermediary == 0 || $cntOutsourcing == 0 ? null : $list_contractorName);
		// 	// 20250616 E_Add

		// 	// 支払先<-取引先名称
		// 	$cell = setCell(null, $sheet, 'supplierName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['supplierName']);
		// 	/*
		// 	// 複数支払先<-契約者名
		// 	$cell = setCell(null, $sheet, 'list_contractorName_outsourcing', 1, $endColumn, 1, $endRow, $list_contractorName_outsourcing);
		// 	*/
		// 	// 振込口座名義<-名義
		// 	$cell = setCell(null, $sheet, 'bankName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['bankName']);
		// 	// 銀行・信用金庫等<-銀行名
		// 	$cell = setCell(null, $sheet, 'bank_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['bank']);
		// 	// 支店
		// 	$cell = setCell(null, $sheet, 'branchName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['branchName']);
		// 	// 口座種類<-口座種別
		// 	$cell = setCell(null, $sheet, 'accountTypeName_outsourcing', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $payDetail_outsourcing['accountType']));
		// 	// 口座番号<-口座
		// 	$cell = setCell(null, $sheet, 'accountName_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['accountName']);
		// 	// 代金
		// 	$cell = setCell(null, $sheet, 'payPriceTax_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['payPriceTax']);
		// 	// 備考<-支払名称+備考
		// 	$cell = setCell(null, $sheet, 'paymentName_outsourcing', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail_outsourcing['paymentCode']) . $payDetail_outsourcing['detailRemarks']);
		// 	// 20250616 S_Add
		// 	if($cntIntermediary == 0 || $cntOutsourcing == 0){
		// 		$sheet->removeRow(5,1);
		// 		$sheet->getCell('J5')->setValue('=SUM(J4:J4)');
		// 		$sheet->getCell('K5')->setValue('=SUM(K4:K4)');
		// 		$sheet->getCell('M5')->setValue('=SUM(M4:M4)');
		// 	}
		// 	// 20250616 E_Add
		// }
		// 20250723 E_Delete

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
		// 20250402 S_Update
		// if($i == 5) {
		if($i == $sheetIndexReceiptOutsourcing) {
			if ($cntIntermediary == 0) {
				$payDetail_outsourcing = $payDetail_intermediary;
			}
		// 20250402 E_Update
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 代金（業務委託料）
			$cell = setCell(null, $sheet, 'payPriceTax_outsourcing', 1, $endColumn, 1, $endRow, $payDetail_outsourcing['payPriceTax']);
			// 支払確定日（業務委託料）
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_outsourcing', 1, $endColumn, 1, $endRow, convert_dt($payDetail_outsourcing['contractFixDay'], 'Y年n月j日'));
		}

		// 20250402 S_Update
		if($i == $sheetIndexReceiptIntermediary) {
			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 代金（業務委託料）
			$cell = setCell(null, $sheet, 'payPriceTax_intermediary', 1, $endColumn, 1, $endRow, $payDetail_intermediary['payPriceTax']);
			// 支払確定日（業務委託料）
			$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji_intermediary', 1, $endColumn, 1, $endRow, convert_dt($payDetail_intermediary['contractFixDay'], 'Y年n月j日'));
		}
		// 20250402 E_Add

		//20240207 S_Add
		// ・R-A　PK精算シート
		// 20250402 S_Update
		// if($i == 6) {
		if($i == $sheetIndexReceiptIntermediary + 1) {
		// 20250402 E_Update
			if (empty($renCons)) {
				$renCons[] = []; 
			}

			$endRow = 46;   // 最終行数
			$currentRow = 1;

			// 物件所在地
			$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
			// 支払確定日
			$cell = setCell(null, $sheet, 'contractFixDay_jpdt_kanji', 1, $endColumn, 1, $endRow, convert_jpdt_kanji($contractFixDay, 'n月j日'));
			$cell = setCell(null, $sheet, 'list_contractorNameDot', 1, $endColumn, 1, $endRow, $list_contractorNameDot);
		
			//敷金・保証金 START
			$pos = 0;
			$currentColumn = 2;
			$cell = searchCell($sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow);
			if($cell != null) {
				$currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
				$currentRow = $cell->getRow();
				$pos = $currentRow;
			}

			if(sizeof($renCons) > 1) {
				$endRow += sizeof($renCons) - 1;
				copyBlockWithVal($sheet, $currentRow, 1, sizeof($renCons) - 1, $endColumn);
			}
			$cell = null;

			foreach($renCons as $renCon) {
			
				//部屋番号
				$cell = setCell($cell, $sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomNo']);
			
				//敷金・保証金		
				$cell = setCell($cell, $sheet, 'SuccessionDepositAndSecurity', $currentColumn, $endColumn, $currentRow, $endRow, intval($renCon['deposit']) + intval($renCon['securityDeposit']));
			
				$currentRow++;
			}
			//敷金・保証金	合計
			$cell = setCell($cell, $sheet, 'sumSuccessionDepositAndSecurity', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(C' . $pos . ':C' . ($currentRow - 1) . ')');
			//敷金・保証金 END

			$currentRow += 2;
			//月額賃料等 END
			$cell = null;
			//決済日
			$decisionDay = $contract['decisionDay'];
			if (!empty($decisionDay) && strlen($decisionDay) === 8) {
				$year = substr($decisionDay, 0, 4);
				$month = substr($decisionDay, 4, 2);
				$day = substr($decisionDay, 6, 2);
			
				$decisionDay = $year . "/" . $month . "/" . $day;
			}
			$cell = setCell($cell, $sheet, 'decisionDay', $currentColumn, $endColumn, $currentRow, $endRow, $decisionDay);
			
			//月額賃料等 START
			$cell = null;
			$cell = searchCell($sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow);
			if($cell != null) {
				$currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
				$currentRow = $cell->getRow();
				$pos = $currentRow;
			}

			if(sizeof($renCons) > 1) {
				$endRow += sizeof($renCons) - 1;
				copyBlockWithVal($sheet, $currentRow, 1, sizeof($renCons) - 1, $endColumn);
			}
			$cell = null;

			foreach($renCons as $renCon) {
			
				//部屋番号
				$cell = setCell($cell, $sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomNo']);

				//賃料（消費税込）	
				$cell = setCell($cell, $sheet, 'rentPriceInTax', $currentColumn, $endColumn, $currentRow, $endRow, intval($renCon['rentPrice']) + intval($renCon['rentPriceTax']));

				//管理費等（消費税込）			
				$cell = setCell($cell, $sheet, 'managementFeeAndCondoFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, intval($renCon['condoFee']) + intval($renCon['managementFee']) + intval($renCon['condoFeeTax']) + intval($renCon['managementFeeTax']));
			
				//賃料（消費税込） + 管理費等（消費税込）		
				$cell = setCell($cell, $sheet, 'sumPriceAndFeeRow', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(C' . $currentRow . ':E' . $currentRow. ')');

				$currentRow++;
			}
			//賃料（消費税込）合計
			$cell = setCell($cell, $sheet, 'sumRentPriceInTax', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(C' . $pos . ':C' . ($currentRow - 1) . ')');
			//管理費等（消費税込）合計
			$cell = setCell($cell, $sheet, 'sumManagementFeeAndCondoFeeInTax', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(E' . $pos . ':E' . ($currentRow - 1) . ')');
			//賃料（消費税込）合計 + 管理費等（消費税込）合計
			$cell = setCell($cell, $sheet, 'sumPriceAndFeeAll', $currentColumn, $endColumn, $currentRow, $endRow, '=SUM(C' . $currentRow . ':E' . $currentRow . ')');

		}
		//20240207 E_Add

		// 20240528 S_Add
		// 振替伝票シート
		// 20250402 S_Update
		// if($i == 7) {
		if($i == $sheetIndexReceiptIntermediary + 2) {
		// 20250402 E_Update
			$endRow = 10;   // 最終行数
			$currentRow = 3;
			$currentColumn = 2;
			$cell = null;
			//決済日
			$cell = setCell($cell, $sheet, 'decisionDay', $currentColumn, $endColumn, $currentRow, $endRow, convert_jpdt_kanji($contract['decisionDay']));
			
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

		// 20250402 S_Update
		// $sheet->setSelectedCell('A1');// 初期選択セル設定
		// 振替伝票 (仲介・業務委託)シート
		if($i == $sheetIndexInOut) {
			createExportInOut($spreadsheet, $contract, $payDetailInOuts, $sheetIndexInOut, $slipCodes, $codeLists);
		}
		else{
			$sheet->setSelectedCell('A1');// 初期選択セル設定
		}
		// 20250402 E_Update
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
// 20240207 S_Delete
// // R-A　PK精算シートを保持
// $sheet = clone $spreadsheet->getSheet(6);
// 20240207 E_Delete

// コピー元シート削除
// 20220529 S_Update
/*
for($i = 0 ; $i < 7; $i++) {
	$spreadsheet->removeSheetByIndex(0);
}
*/
// 20250402 S_Update
// for($i = 1 ; $i < 8; $i++) {
// 20250723 S_Update
// for($i = 1 ; $i < $numberOfSheet; $i++) {
// // 20250402 E_Update
// 	$spreadsheet->removeSheetByIndex(1);
// }
// 支払依頼書帳票シート計算式を設定 START
$sheet = $spreadsheet->getSheet(1);
$rowSum = $totalRows + $posMergeBegin;
for($i = $posMergeBegin ; $i < $rowSum; $i++) {
	$sheet->setCellValue('N' . $i, '==IF(OR(K'. $i .'<>"", L'. $i .'<>""), K'. $i .'+L'. $i .', "")');
}

$sheet->setCellValue('K' . $rowSum, '=SUM(K'. $posMergeBegin .':K'. ($rowSum - 1) .')');
$sheet->setCellValue('L' . $rowSum, '=SUM(L'. $posMergeBegin .':L'. ($rowSum - 1) .')');
$sheet->setCellValue('N' . $rowSum, '=SUM(N'. $posMergeBegin .':N'. ($rowSum - 1) .')');

$sheet->setSelectedCell('A1');// 初期選択セル設定
// 支払依頼書帳票シート計算式を設定 END

$iBegin = $haveIntermediaryOrOutsourcing ? 2 : 1;
for($i = $iBegin ; $i < $numberOfSheet; $i++) {
	$spreadsheet->removeSheetByIndex($iBegin);
}
// 20250723 E_Update
// 20220529 E_Update
// 20220331 E_Update

// 20240207 S_Delete
// // R-A　PK精算シートを一番右へ追加
// $spreadsheet->addSheet($sheet);
// 20240207 E_Delete

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

// 20250402 S_Add
function createExportInOut($spreadsheet, $contract, $payDetails, $sheetIndex, $slipCodes, $codeLists){
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
		// 振替伝票シートをコピー
		$sheet = clone $spreadsheet->getSheet($sheetIndex);
		$title = $sheet->getTitle();
		$sheet->setTitle($title . '_' . $contract['contractNumber'] . '_' . $key);
		$spreadsheet->addSheet($sheet);
	
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
			$contracts = [$contract];
	
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
	
			$locs = [];
			if(sizeof($contracts) > 0) {
				$contractInfoPids = [];
				foreach($contracts as $contract) {
					$contractInfoPids[] = $contract['pid'];
				}
				// 所在地情報を取得
				$locs = getLocations($contractInfoPids);
			}
			else if(!empty($payDetail['tempLandInfoPid'])) {
				// 所在地情報を取得
				$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $payDetail['tempLandInfoPid'])->where_null('deleteDate')->order_by_asc('displayOrder')->findArray();
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
			addSlipData2($transferSlipDatas, $contracts, $payDetail, $pay, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $payDetail['contractBukkenNo'], $list_contractorNameComma, '');
		}
	
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
	}
}


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
// 20250402 E_Add
?>
