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
	$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->findOne($sale['tempLandInfoPid'])->asArray();

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
	for($i = 1 ; $i < 5; $i++) {
	// 20240528 E_Update
		// シートをコピー
		$sheet = clone $spreadsheet->getSheet($i);
		$title = $sheet->getTitle();
		$sheet->setTitle($title . '_' . $sale['pid']);
		$spreadsheet->addSheet($sheet);

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

		// ・決済案内シート
		if($i == 1) {
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
		if($i == 2) {
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
		if($i == 3) {
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

		// 20240528 S_Add
		// 振替伝票シート
		if($i == 4) {
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
		$sheet->setSelectedCell('A1');// 初期選択セル設定
	}
}

// コピー元シート削除
// 20240528 S_Update
// for($i = 1 ; $i < 4; $i++) {
for($i = 1 ; $i < 5; $i++) {
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
?>
