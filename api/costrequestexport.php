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
$filePath = $fullPath.'/諸経費等.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

$codeLists = [];
// 口座種別List
$accountTypeCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '026')->where_null('deleteDate')->findArray();
$codeLists['accountType'] = $accountTypeCodeList;
// 支払種別List
$paymentTypeList = ORM::for_table(TBLPAYMENTTYPE)->select('paymentCode', 'codeDetail')->select('paymentName', 'name')->where_null('deleteDate')->findArray();
$codeLists['paymentType'] = $paymentTypeList;

// 20240213 S_Add
// 支払依頼書出力パターン
$patternCodeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->select('displayOrder')->where('code', 'SYS501')->where_null('deleteDate')->order_by_asc('displayOrder')->findArray();
// 20240213 E_Add

$addedSum = false;// 20240220 Add
$targetsSum = [];// 20240213 Add
$targets = [];

// 支払契約詳細情報を取得
$payDetails = ORM::for_table(TBLPAYCONTRACTDETAIL)->where_in('pid', $param->ids)->where_null('deleteDate')->order_by_asc('tempLandInfoPid')->order_by_asc('contractFixDay')->findArray();
foreach($payDetails as $payDetail) {
	// 土地情報を取得
	$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('bukkenNo')->findOne($payDetail['tempLandInfoPid'])->asArray();
	/*
	// 支払契約情報を取得
	$pay = ORM::for_table(TBLPAYCONTRACT)->select('contractInfoPid')->findOne($payDetail['payContractPid'])->asArray();
	// 仕入契約情報を取得
	$contract = ORM::for_table(TBLCONTRACTINFO)->select('contractNumber')->findOne($pay['contractInfoPid'])->asArray();
	*/
	// key=物件番号+支払確定日
	$key = $bukken['bukkenNo'] . '-' . $payDetail['contractFixDay'];
	// グルーピングを行う
	if(!isset($targets[$key])) {
		$groups = [];
		$groups[] = $payDetail;
		$targets[$key] = $groups;
		$targetsSum[$key] = $groups;// 20240213 Add

		// 20240221 S_Add
		foreach ($patternCodeList as $row) {
			if ($row['codeDetail'] === $payDetail['paymentCode']) {
				$addedSum = true;
				break; 
			}
		}
		// 20240221 E_Add
	} else {
		$groupsSum = $targetsSum[$key];// 20240213 Add

		$groups = $targets[$key];
		$groups[] = $payDetail;
		$targets[$key] = $groups;

		// 20240213 S_Add
		$existsCode = false;

		foreach ($patternCodeList as $row) {
			if ($row['codeDetail'] === $payDetail['paymentCode']) {
				$existsCode = true;
				break; 
			}
		}

		// 20240220 S_Update
		// if($existsCode){
		if($existsCode && $addedSum){
		// 20240220 E_Update
			$displayOrder = null;
			foreach ($patternCodeList as $pattern) {
				if ($pattern['codeDetail'] == $payDetail['paymentCode']) {
					$displayOrder = $pattern['displayOrder'];
					break;
				}
			}

			foreach ($groupsSum as $keySub => $payDetailSub) {
				foreach ($patternCodeList as $row) {
					if ($row['codeDetail'] === $payDetailSub['paymentCode']) {
						$groupsSum[$keySub]['payPriceTax'] = intval($groupsSum[$keySub]['payPriceTax']) + intval($payDetail['payPriceTax']);
						$groupsSum[$keySub]['withholdingTax'] = intval($groupsSum[$keySub]['withholdingTax']) + intval($payDetail['withholdingTax']);
						
						if($displayOrder < $row['displayOrder']){
							$groupsSum[$keySub]['paymentCode'] = $payDetail['paymentCode'];
							$groupsSum[$keySub]['detailRemarks'] = $payDetail['detailRemarks'];
						}
						$addedSum = true;// 20240220 Add
						break; 
					}
				}
			}
		}
		else{
			$groupsSum[] = $payDetail;
			// 20240220 S_Add
			if($existsCode){
				$addedSum = true;
			}
			// 20240220 E_Add
		}
		$targetsSum[$key] = $groupsSum;
		// 20240213 E_Add
	}
}

// 20240213 S_Add
foreach ($targetsSum as $key => $groups) {
	// 支払依頼書帳票シートをコピー
	$sheet = clone $spreadsheet->getSheet(0);
	$title = $sheet->getTitle();
	$sheet->setTitle($title . '_' . $key);
	$spreadsheet->addSheet($sheet);

	// 開始セル行
	$pos = 4;
	// データが複数ある場合、ブロックをコピー
	if(sizeof($groups) > 1) {
		copyBlockWithVal($sheet, $pos, 1, sizeof($groups) - 1, 17);
	}
	// 合計の計算式
	// 20240905 S_Update
	// $sheet->setCellValue('J' . ($pos + sizeof($groups)), '=SUM(J' . $pos . ':J' . ($pos + sizeof($groups) - 1) . ')');
	// $sheet->setCellValue('K' . ($pos + sizeof($groups)), '=SUM(K' . $pos . ':K' . ($pos + sizeof($groups) - 1) . ')');
	// $sheet->setCellValue('M' . ($pos + sizeof($groups)), '=SUM(M' . $pos . ':M' . ($pos + sizeof($groups) - 1) . ')');
	$sheet->setCellValue('K' . ($pos + sizeof($groups)), '=SUM(K' . $pos . ':K' . ($pos + sizeof($groups) - 1) . ')');
	$sheet->setCellValue('L' . ($pos + sizeof($groups)), '=SUM(L' . $pos . ':L' . ($pos + sizeof($groups) - 1) . ')');
	// 20240905 E_Update

	// 20240801 S_Update
	// $endColumn = 16;// 最終列数
	$endColumn = 17;// 最終列数
	// 20240801 E_Update
	$endRow = 43;   // 最終行数

	foreach($groups as $payDetail) {
		// 土地情報を取得
		$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('infoStaff')->findOne($payDetail['tempLandInfoPid'])->asArray();
		// 支払契約情報を取得
		$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
		
		$contracts = [];
		if(!empty($pay['contractInfoPid'])) {
			// 仕入契約情報を取得
			$contracts[] = ORM::for_table(TBLCONTRACTINFO)->select('pid')->select('contractFormNumber')->findOne($pay['contractInfoPid'])->asArray();
			// 20240801 S_Add
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
			// 20240801 E_Add
		}
		else if(!empty($payDetail['contractor'])) {
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
				// 仕入契約情報を取得
				$contracts = ORM::for_table(TBLCONTRACTINFO)->where_in('pid', $contractInfoPids)->where_null('deleteDate')->findArray();
			}
		}
		// 複数契約書番号
		$list_contractFormNumber = '';
		$locs = [];
		if(sizeof($contracts) > 0) {
			$list_contractFormNumber = getContractFormNumber($contracts, chr(10));
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
		// 複数地番・複数家屋番号
		$list_blockOrBuildingNumber = '';
		$blockNumber = '';      // 地番
		$buildingNumber = '';   // 家屋番号
		$bottomLands = [];
		if(sizeof($locs) > 0) {
			if(sizeof($contracts) > 0) $list_blockOrBuildingNumber = getBuildingNumber($locs, chr(10));
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
						$blockNumber = $loc['blockNumber'];// 地番 20220824 Add
					}
				}
				else {
					$cntNotLandlocs++;
					if($cntLandlocs == 0 && $cntNotLandlocs == 1)
					{
						$addressNotLand = $loc['address'];
						$buildingNumber = $loc['buildingNumber'];// 家屋番号 20220824 Add
					}
					if(!empty($loc['bottomLandPid'])) $bottomLands[] = $loc;
				}
			}
			if($addressLand != '') $address = $addressLand;
			else $address = $addressNotLand;
			$blockOrBuildingNumber = $blockNumber;
			if(empty($blockOrBuildingNumber) && !empty($buildingNumber)) $blockOrBuildingNumber = '（' . $buildingNumber . '）';
			$addressAndBlockOrBuildingNumber = $address . $blockOrBuildingNumber;// 所在地+地番/家屋番号
			if($cntLandlocs > 1) {
				$addressAndBlockOrBuildingNumber .= '　外';
			}
			// 土地に指定がないかつ、底地に指定がある場合
			else if($cntLandlocs == 0 && sizeof($bottomLands) > 0) {
				foreach($bottomLands as $loc) {
					$bottomLand = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['bottomLandPid']);
					$addressAndBlockOrBuildingNumber = $bottomLand['address'] . $bottomLand['blockNumber'];
					if(!empty($loc['buildingNumber'])) $addressAndBlockOrBuildingNumber .= '（家屋番号：' . $loc['buildingNumber'] . '）';
					break;
				}
			}
		}
		// 居住表示
		// $cell = setCell(null, $sheet, 'supplierAddress', 1, $endColumn, 1, $endRow, $pay['supplierAddress']);
		$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
		// 契約物件番号
		$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
		// 支払確定日
		$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($payDetail['contractFixDay'], 'Y年n月j日'));
		// 担当<-物件担当者
		$cell = setCell(null, $sheet, 'infoStaffName', 1, $endColumn, 1, $endRow, getInfoStaff($bukken['infoStaff']));

		// 日時
		$contractFixDateTime = convert_dt($payDetail['contractFixDay'], 'Y/m/d');
		if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
		if(!empty($payDetail['contractFixTime'])) $contractFixDateTime .= $payDetail['contractFixTime'] . '～';
		$cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
		// 契約書番号
		$cell = setCell(null, $sheet, 'list_contractFormNumber', 1, $endColumn, 1, $endRow, $list_contractFormNumber);
		// 複数地番/複数家屋番号
		$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
		// 20240801 S_Add
		// 地権者（売主）
		$cell = setCell(null, $sheet, 'contractor', 1, $endColumn, 1, $endRow, !empty($payDetail['contractor']) ? getContractorName($sellers, '、') : '');
		// 20240801 E_Add
		// 支払先<-取引先名称
		$cell = setCell(null, $sheet, 'supplierName', 1, $endColumn, 1, $endRow, $pay['supplierName']);
		// 振込口座名義<-名義
		$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $pay['bankName']);
		// 銀行・信用金庫等<-銀行名
		$cell = setCell(null, $sheet, 'bank', 1, $endColumn, 1, $endRow, $pay['bank']);
		// 支店
		$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $pay['branchName']);
		// 口座種類<-口座種別
		$cell = setCell(null, $sheet, 'accountTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $pay['accountType']));
		// 口座番号<-口座
		$cell = setCell(null, $sheet, 'accountName', 1, $endColumn, 1, $endRow, $pay['accountName']);
		// 代金
		$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, intval($payDetail['payPriceTax']) - intval($payDetail['withholdingTax']));
		// 送金金額
		$cell = setCell(null, $sheet, 'payPriceTaxPlusFixedTax', 1, $endColumn, 1, $endRow, intval($payDetail['payPriceTax']) - intval($payDetail['withholdingTax']));
		// 支払名称
		$cell = setCell(null, $sheet, 'paymentName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']));
		// 備考
		$cell = setCell(null, $sheet, 'detailRemarks', 1, $endColumn, 1, $endRow, $payDetail['detailRemarks']);
	}
	$sheet->setSelectedCell('A1');// 初期選択セル設定
}
// 20240213 E_Add


// 20240905 S_Add
$slipRemarks = '';
$list_contractorNameComma = '';
$contractType = '0';
$slipCodes = getCodesCommon('SYS601');

foreach ($targets as $key => $groups) {
	// 振替伝票シートをコピー
	$sheet = clone $spreadsheet->getSheet(1);
	$title = $sheet->getTitle();
	$sheet->setTitle($title . '_' . $key);
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
		// 土地情報を取得
		$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('infoStaff')->findOne($payDetail['tempLandInfoPid'])->asArray();
		// 支払契約情報を取得
		$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
		
		$contracts = [];
		$isSetContracts = false;
		if(!empty($pay['contractInfoPid'])) {
			// 仕入契約情報を取得
			$contracts[] = ORM::for_table(TBLCONTRACTINFO)->findOne($pay['contractInfoPid'])->asArray();
			$isSetContracts = true;
		}

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

				// 仕入契約情報を取得
				if(!$isSetContracts){
					$contracts = ORM::for_table(TBLCONTRACTINFO)->where_in('pid', $contractInfoPids)->where_null('deleteDate')->findArray();
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
		addSlipData2($transferSlipDatas, $contracts, $payDetail, $pay, $contractType, $slipCodes, $codeLists['paymentType'], $slipRemarks, $address, $bukken['contractBukkenNo'], $list_contractorNameComma, '');
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

	// 20250402 S_Update
	// // 20240806 S_Add
	// // 初期化: カウンタとプレースホルダー
	// $paymentCounts = [
	// 	'5002' => 0,// 弁護士報酬
	// 	'5005' => 0,// 司法書士報酬
	// 	'3015' => 0// 登録免許税及び印紙代
	// ];

	// $creditorPayPrice3015 = null;
	// $paymentCodeProcess = null;

	// // transferSlipDatas を反復処理してカウントとフィルタリング
	// foreach ($transferSlipDatas as $slipData) {
	// 	if (isset($slipData->paymentCode)) {
	// 		switch ($slipData->paymentCode) {
	// 			case '5002':
	// 			case '5005':
	// 				// 支払コード '5002' または '5005' のカウント
	// 				$paymentCounts[$slipData->paymentCode]++;
	// 				break;
	// 			case '3015':
	// 				// 支払コード '3015' のカウントと価格の保存
	// 				$paymentCounts['3015']++;
	// 				$creditorPayPrice3015 = $slipData->creditorPayPrice;
	// 				break;
	// 		}
	// 	}
	// }

	// // カウントに基づいて処理する支払コードを決定
	// if ($paymentCounts['3015'] == 1) {
	// 	if ($paymentCounts['5002'] == 1 && $paymentCounts['5005'] == 0) {
	// 		$paymentCodeProcess = '5002';
	// 	} elseif ($paymentCounts['5002'] == 0 && $paymentCounts['5005'] == 1) {
	// 		$paymentCodeProcess = '5005';
	// 	}

	// 	// 一致する支払コードの処理
	// 	if ($paymentCodeProcess !== null && $creditorPayPrice3015 !== null) {
	// 		foreach ($transferSlipDatas as $slipData) {
	// 			if ($slipData->paymentCode == $paymentCodeProcess) {
	// 				// 支払コードが一致する場合、価格を加算
	// 				$slipData->creditorPayPrice += $creditorPayPrice3015;
	// 			} elseif ($slipData->paymentCode == '3015') {
	// 				// 支払コードが '3015' の場合、価格を null に設定
	// 				$slipData->creditorPayPrice = null;
	// 				$slipData->creditorKanjyoName = null;
	// 			}
	// 		}
	// 	}
	// }
	// // 20240806 E_Add

	$totalCreditorPayPrice = 0;

	// $codeMstから'codeDetail'の値を取り出し、$codeDetailsに格納
	$codeDetails = array_column($patternCodeList, 'codeDetail');

	foreach ($transferSlipDatas as &$data) {
		// paymentCodeが$codeDetails配列の中にあるかを確認
		if (in_array($data->paymentCode, $codeDetails) && isset($data->creditorPayPrice)) {
			$totalCreditorPayPrice += $data->creditorPayPrice;
			$data->creditorPayPrice = null;
		} 
	}
	foreach ($transferSlipDatas as &$data) {
		if (in_array($data->paymentCode, $codeDetails)) {
			if(isset($totalCreditorPayPrice)){
				$data->creditorPayPrice = $totalCreditorPayPrice;
			}
			break;
		} 
	}
	// 20250402 E_Update

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
// 20240905 E_Add

foreach ($targets as $key => $groups) {
	// 20240213 S_Delete
// 	// 支払依頼書帳票シートをコピー
// 	$sheet = clone $spreadsheet->getSheet(0);
// 	$title = $sheet->getTitle();
// 	$sheet->setTitle($title . '_' . $key);
// 	$spreadsheet->addSheet($sheet);

// 	// 開始セル行
// 	$pos = 4;
// 	// データが複数ある場合、ブロックをコピー
// 	if(sizeof($groups) > 1) {
// 		// 20220712 S_Update
// //		copyBlockWithVal($sheet, $pos, 1, sizeof($groups) - 1, 14);
// 		copyBlockWithVal($sheet, $pos, 1, sizeof($groups) - 1, 17);
// 		// 20220712 E_Update
// 	}
// 	// 合計の計算式
// 	$sheet->setCellValue('J' . ($pos + sizeof($groups)), '=SUM(J' . $pos . ':J' . ($pos + sizeof($groups) - 1) . ')');
// 	$sheet->setCellValue('K' . ($pos + sizeof($groups)), '=SUM(K' . $pos . ':K' . ($pos + sizeof($groups) - 1) . ')');
// 	// 20220707 S_Update
// 	// $sheet->setCellValue('L' . ($pos + sizeof($groups)), '=SUM(L' . $pos . ':L' . ($pos + sizeof($groups) - 1) . ')');
// 	$sheet->setCellValue('M' . ($pos + sizeof($groups)), '=SUM(M' . $pos . ':M' . ($pos + sizeof($groups) - 1) . ')');
// 	// 20220707 E_Update
	// 20240213 E_Delete

	// 20220703 S_Update
	// $endColumn = 13;// 最終列数
	$endColumn = 16;// 最終列数
	// 20220703 E_Update
	$endRow = 43;   // 最終行数

	foreach($groups as $payDetail) {
		// 20240213 S_Delete
// 		// 土地情報を取得
// 		$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('infoStaff')->findOne($payDetail['tempLandInfoPid'])->asArray();
// 		// 支払契約情報を取得
// 		$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
// 		// 20220627 S_Update
// 		/*
// 		// 仕入契約情報を取得
// 		$contract = ORM::for_table(TBLCONTRACTINFO)->select('pid')->select('contractFormNumber')->findOne($pay['contractInfoPid'])->asArray();
// 		$contractFormNumber = '';// 契約書番号
// 		$locs = [];
// 		if(sizeof($contract) > 0) {
// 			$contractFormNumber = $contract['contractFormNumber'];
// 			// 所在地情報を取得
// 			$locs = getLocation($contract['pid']);
// 		}
// 		*/
// 		$contracts = [];
// 		if(!empty($pay['contractInfoPid'])) {
// 			// 仕入契約情報を取得
// 			$contracts[] = ORM::for_table(TBLCONTRACTINFO)->select('pid')->select('contractFormNumber')->findOne($pay['contractInfoPid'])->asArray();
// 		}
// 		else if(!empty($payDetail['contractor'])) {
// 			$contractor = $payDetail['contractor'];
// 			$contractSellerInfoPids = [];
// 			// 20221110 S_Add
// 			$explode1st = [];
// 			$explode2nd = [];
// 			// 20221110 E_Add
// 			// |で分割されている場合
// 			if(strpos($contractor, '|') !== false) {
// 				$explode1st = explode('|', $contractor);
// 			}
// 			else $explode1st[] = $contractor;
// 			foreach($explode1st as $explode1) {
// 				// ,で分割されている場合
// 				if(strpos($explode1, ',') !== false) {
// 					// 20220708 S_Update
// //					$explode2nd = explode(',', $explode1);
// 					$temps = explode(',', $explode1);
// 					foreach($temps as $temp) {
// 						$explode2nd[] = $temp;
// 					}
// 					// 20220708 S_Update
// 				}
// 				else $explode2nd[] = $explode1;
// 			}
// 			foreach($explode2nd as $explode2) {
// 				$contractSellerInfoPids[] = $explode2;
// 			}
// 			// 仕入契約者情報を取得
// 			$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where_in('pid', $contractSellerInfoPids)->where_null('deleteDate')->findArray();
// 			if(sizeof($sellers) > 0) {
// 				$contractInfoPids = [];
// 				foreach($sellers as $seller) {
// 					$contractInfoPids[] = $seller['contractInfoPid'];
// 				}
// 				// 仕入契約情報を取得
// 				$contracts = ORM::for_table(TBLCONTRACTINFO)->where_in('pid', $contractInfoPids)->where_null('deleteDate')->findArray();
// 			}
// 		}
// 		// 複数契約書番号
// 		$list_contractFormNumber = '';
// 		$locs = [];
// 		if(sizeof($contracts) > 0) {
// 			$list_contractFormNumber = getContractFormNumber($contracts, chr(10));
// 			$contractInfoPids = [];
// 			foreach($contracts as $contract) {
// 				$contractInfoPids[] = $contract['pid'];
// 			}
// 			// 所在地情報を取得
// 			$locs = getLocations($contractInfoPids);
// 		}
// 		else if(!empty($payDetail['tempLandInfoPid'])) {
// 			// 所在地情報を取得
// 			$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $payDetail['tempLandInfoPid'])->where_null('deleteDate')->order_by_asc('displayOrder')->findArray();
// 		}
// 		// 20220627 E_Update
// 		// 所在地
// 		$address = '';
// 		// 複数地番・複数家屋番号
// 		$list_blockOrBuildingNumber = '';
// 		// 20220824 S_Add
// 		$blockNumber = '';      // 地番
// 		$buildingNumber = '';   // 家屋番号
// 		$bottomLands = [];
// 		// 20220824 E_Add
// 		if(sizeof($locs) > 0) {
// 			if(sizeof($contracts) > 0) $list_blockOrBuildingNumber = getBuildingNumber($locs, chr(10));
// 			$cntLandlocs = 0;
// 			$cntNotLandlocs = 0;
// 			// 20220707 S_Add
// 			$addressLand = '';
// 			$addressNotLand = '';
// 			// 20220707 E_Add
// 			foreach($locs as $loc) {
// 				// 区分が01：土地の場合
// 				// 20220708 S_Update
// 				/*
// 				if($loc['locationType'] == '01') $cntLandlocs++;
// 				else $cntNotLandlocs++;
// 				*/
// 				if($loc['locationType'] == '01') {
// 					$cntLandlocs++;
// 					if($cntLandlocs == 1)
// 					{
// 						$addressLand = $loc['address'];
// 						$blockNumber = $loc['blockNumber'];// 地番 20220824 Add
// 					}
// 				}
// 				else {
// 					$cntNotLandlocs++;
// 					// 20220824 S_Update
// 					// if($cntNotLandlocs == 1) $addressNotLand = $loc['address'];
// 					if($cntLandlocs == 0 && $cntNotLandlocs == 1)
// 					{
// 						$addressNotLand = $loc['address'];
// 						$buildingNumber = $loc['buildingNumber'];// 家屋番号 20220824 Add
// 					}
// 					// 20220824 E_Update
// 					// 20220824 S_Add
// 					if(!empty($loc['bottomLandPid'])) $bottomLands[] = $loc;
// 					// 20220824 E_Add
// 				}
// 				// 20220708 E_Update
// 				// 20220707 S_Delete
// 				/*
// 				if($cntLandlocs == 1) $address = $loc['address'];
// 				if($cntLandlocs == 0 && $cntNotLandlocs == 1) $address = $loc['address'];
// 				*/
// 				// 20220707 E_Delete
// 			}
// 			// 20220707 S_Add
// 			if($addressLand != '') $address = $addressLand;
// 			else $address = $addressNotLand;
// 			// 20220707 E_Add
// 			// 20220824 S_Add
// 			$blockOrBuildingNumber = $blockNumber;
// 			if(empty($blockOrBuildingNumber) && !empty($buildingNumber)) $blockOrBuildingNumber = '（' . $buildingNumber . '）';
// 			$addressAndBlockOrBuildingNumber = $address . $blockOrBuildingNumber;// 所在地+地番/家屋番号
// 			if($cntLandlocs > 1) {
// 				$addressAndBlockOrBuildingNumber .= '　外';
// 			}
// 			// 土地に指定がないかつ、底地に指定がある場合
// 			else if($cntLandlocs == 0 && sizeof($bottomLands) > 0) {
// 				foreach($bottomLands as $loc) {
// 					$bottomLand = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['bottomLandPid']);
// 					$addressAndBlockOrBuildingNumber = $bottomLand['address'] . $bottomLand['blockNumber'];
// 					if(!empty($loc['buildingNumber'])) $addressAndBlockOrBuildingNumber .= '（家屋番号：' . $loc['buildingNumber'] . '）';
// 					break;
// 				}
// 			}
// 			// 20220824 E_Add
// 		}
// 		// 居住表示
// 		// $cell = setCell(null, $sheet, 'supplierAddress', 1, $endColumn, 1, $endRow, $pay['supplierAddress']);
// 		$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
// 		// 契約物件番号
// 		$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
// 		// 支払確定日
// 		$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($payDetail['contractFixDay'], 'Y年n月j日'));
// 		// 担当<-物件担当者
// 		// 20220703 S_Update
// 		// $cell = setCell(null, $sheet, 'payContractStaffName', 1, $endColumn, 1, $endRow, getUserName($pay['userId']));
// 		$cell = setCell(null, $sheet, 'infoStaffName', 1, $endColumn, 1, $endRow, getInfoStaff($bukken['infoStaff']));
// 		// 20220703 E_Update

// 		// 日時
// 		$contractFixDateTime = convert_dt($payDetail['contractFixDay'], 'Y/m/d');
// 		if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
// 		if(!empty($payDetail['contractFixTime'])) $contractFixDateTime .= $payDetail['contractFixTime'] . '～';
// 		$cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
// 		// 契約書番号
// 		// $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contractFormNumber);
// 		$cell = setCell(null, $sheet, 'list_contractFormNumber', 1, $endColumn, 1, $endRow, $list_contractFormNumber);
// 		// 複数地番/複数家屋番号
// 		$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
// 		// 支払先<-取引先名称
// 		$cell = setCell(null, $sheet, 'supplierName', 1, $endColumn, 1, $endRow, $pay['supplierName']);
// 		// 振込口座名義<-名義
// 		$cell = setCell(null, $sheet, 'bankName', 1, $endColumn, 1, $endRow, $pay['bankName']);
// 		// 銀行・信用金庫等<-銀行名
// 		$cell = setCell(null, $sheet, 'bank', 1, $endColumn, 1, $endRow, $pay['bank']);
// 		// 支店
// 		$cell = setCell(null, $sheet, 'branchName', 1, $endColumn, 1, $endRow, $pay['branchName']);
// 		// 口座種類<-口座種別
// 		$cell = setCell(null, $sheet, 'accountTypeName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['accountType'], $pay['accountType']));
// 		// 口座番号<-口座
// 		$cell = setCell(null, $sheet, 'accountName', 1, $endColumn, 1, $endRow, $pay['accountName']);
// 		// 代金
// 		// 20240201 S_Update
// 		// $cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payDetail['payPriceTax']);
// 		$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, intval($payDetail['payPriceTax']) - intval($payDetail['withholdingTax']));
// 		// 20240201 E_Update
// 		// 送金金額
// 		// 20240201 S_Update
// 		// $cell = setCell(null, $sheet, 'payPriceTaxPlusFixedTax', 1, $endColumn, 1, $endRow, $payDetail['payPriceTax']);
// 		$cell = setCell(null, $sheet, 'payPriceTaxPlusFixedTax', 1, $endColumn, 1, $endRow, intval($payDetail['payPriceTax']) - intval($payDetail['withholdingTax']));
// 		// 20240201 S_Update
// 		// 支払名称
// 		$cell = setCell(null, $sheet, 'paymentName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']));
// 		// 備考
// 		$cell = setCell(null, $sheet, 'detailRemarks', 1, $endColumn, 1, $endRow, $payDetail['detailRemarks']);

		//20240213 E_Delete
		// 領収証シートをコピー
		// 20240905 S_Update
		// $subSheet = clone $spreadsheet->getSheet(1);
		$subSheet = clone $spreadsheet->getSheet(2);
		// 20240905 E_Update
		$subTitle = $subSheet->getTitle();
		$subSheet->setTitle($subTitle . '_' . getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']) . '(' . $payDetail['pid'] . ')');
		$spreadsheet->addSheet($subSheet);

		// 物件所在地
		// 20220824 S_Update
		// $cell = setCell(null, $subSheet, 'supplierAddress', 1, $endColumn, 1, $endRow, $pay['supplierAddress']);
		$cell = setCell(null, $subSheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
		// 20220824 E_Update
		// 摘要
		$cell = setCell(null, $subSheet, 'paymentName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']));
		// 金額
		$cell = setCell(null, $subSheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payDetail['payPriceTax']);
		// 支払確定日
		$cell = setCell(null, $subSheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($payDetail['contractFixDay'], 'Y年n月j日'));

		$subSheet->setSelectedCell('A1');// 初期選択セル設定
	}
	$sheet->setSelectedCell('A1');// 初期選択セル設定
}

// 20240528 E_Add
// for($i = 1 ; $i < 3; $i++) {
for($i = 1 ; $i < 4; $i++) {
// 20240528 E_Add
	$spreadsheet->removeSheetByIndex(0);
}

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '諸経費等_' . date('YmdHis') . '.xlsx';
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
/*
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
*/

/**
 * 物件担当者取得
 */
function getInfoStaff($staff) {
	if(!isset($staff) || $staff == '') return '';
	$ids = explode(',', $staff);
	$users = ORM::for_table(TBLUSER)->where_in('userId', $ids)->where_null('deleteDate')->select('userName')->findArray();
	$names = [];
	foreach($users as $user) {
		$names[] = $user['userName'];
	}
	return implode('、', $names);
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

/**
 * 契約書番号取得（指定文字区切り）
 */
function getContractFormNumber($lst, $split) {
	$ret = [];
	if(isset($lst)) {
		foreach($lst as $data) {
			if(!empty($data['contractFormNumber'])) $ret[] = mb_convert_kana($data['contractFormNumber'], 'kvrn');
		}
	}
	return implode($split, $ret);
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

// 20240528 S_Add
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
// 20240528 E_Add
?>
