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
	} else {
		$groups = $targets[$key];
		$groups[] = $payDetail;
		$targets[$key] = $groups;
	}
}

foreach ($targets as $key => $groups) {
	// 支払依頼書帳票シートをコピー
	$sheet = clone $spreadsheet->getSheet(0);
	$title = $sheet->getTitle();
	$sheet->setTitle($title . '_' . $key);
	$spreadsheet->addSheet($sheet);

	// 開始セル行
	$pos = 4;
	// データが複数ある場合、ブロックをコピー
	if(sizeof($groups) > 1) {
		copyBlockWithVal($sheet, $pos, 1, sizeof($groups) - 1, 14);
	}
	// 合計の計算式
	$sheet->setCellValue('J' . ($pos + sizeof($groups)), '=SUM(J' . $pos . ':J' . ($pos + sizeof($groups) - 1) . ')');
	$sheet->setCellValue('K' . ($pos + sizeof($groups)), '=SUM(K' . $pos . ':K' . ($pos + sizeof($groups) - 1) . ')');
	$sheet->setCellValue('L' . ($pos + sizeof($groups)), '=SUM(L' . $pos . ':L' . ($pos + sizeof($groups) - 1) . ')');

	$endColumn = 13;// 最終列数
	$endRow = 43;   // 最終行数

	foreach($groups as $payDetail) {
		// 土地情報を取得
		$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->findOne($payDetail['tempLandInfoPid'])->asArray();
		// 支払契約情報を取得
		$pay = ORM::for_table(TBLPAYCONTRACT)->findOne($payDetail['payContractPid'])->asArray();
		// 20220627 S_Update
		/*
		// 仕入契約情報を取得
		$contract = ORM::for_table(TBLCONTRACTINFO)->select('pid')->select('contractFormNumber')->findOne($pay['contractInfoPid'])->asArray();
		$contractFormNumber = '';// 契約書番号
		$locs = [];
		if(sizeof($contract) > 0) {
			$contractFormNumber = $contract['contractFormNumber'];
			// 所在地情報を取得
			$locs = getLocation($contract['pid']);
		}
		*/
		$contracts = [];
		if(!empty($pay['contractInfoPid'])) {
			// 仕入契約情報を取得
			$contracts[] = $contract = ORM::for_table(TBLCONTRACTINFO)->select('pid')->select('contractFormNumber')->findOne($pay['contractInfoPid'])->asArray();
		}
		else if(!empty($payDetail['contractor'])) {
			$contractor = $payDetail['contractor'];
			$contractSellerInfoPids = [];
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
			$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $payDetail['tempLandInfoPid'])->where_null('deleteDate')->findArray();
		}
		// 20220627 E_Update
		// 所在地
		$address = '';
		// 複数地番・複数家屋番号
		$list_blockOrBuildingNumber = '';
		if(sizeof($locs) > 0) {
			if(sizeof($contracts) > 0) $list_blockOrBuildingNumber = getBuildingNumber($locs, chr(10));
			$cntLandlocs = 0;
			$cntNotLandlocs = 0;
			foreach($locs as $loc) {
				// 区分が01：土地の場合
				if($loc['locationType'] == '01') $cntLandlocs++;
				else $cntNotLandlocs++;

				if($cntLandlocs == 1) $address = $loc['address'];
				if($cntLandlocs == 0 && $cntNotLandlocs == 1) $address = $loc['address'];
			}
		}
		// 居住表示
		// $cell = setCell(null, $sheet, 'supplierAddress', 1, $endColumn, 1, $endRow, $pay['supplierAddress']);
		$cell = setCell(null, $sheet, 'address', 1, $endColumn, 1, $endRow, $address);
		// 契約物件番号
		$cell = setCell(null, $sheet, 'contractBukkenNo', 1, $endColumn, 1, $endRow, $bukken['contractBukkenNo']);
		// 支払確定日
		$cell = setCell(null, $sheet, 'contractFixDay_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($payDetail['contractFixDay'], 'Y年n月j日'));
		// 契約担当者
		$cell = setCell(null, $sheet, 'payContractStaffName', 1, $endColumn, 1, $endRow, getUserName($pay['userId']));

		// 日時
		$contractFixDateTime = convert_dt($payDetail['contractFixDay'], 'Y/m/d');
		if(!empty($contractFixDateTime)) $contractFixDateTime .= '  ';
		if(!empty($payDetail['contractFixTime'])) $contractFixDateTime .= $payDetail['contractFixTime'] . '～';
		$cell = setCell(null, $sheet, 'contractFixDateTime', 1, $endColumn, 1, $endRow, $contractFixDateTime);
		// 契約書番号
		// $cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contractFormNumber);
		$cell = setCell(null, $sheet, 'list_contractFormNumber', 1, $endColumn, 1, $endRow, $list_contractFormNumber);
		// 複数地番/複数家屋番号
		$cell = setCell(null, $sheet, 'list_blockOrBuildingNumber', 1, $endColumn, 1, $endRow, $list_blockOrBuildingNumber);
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
		$cell = setCell(null, $sheet, 'payPriceTax', 1, $endColumn, 1, $endRow, $payDetail['payPriceTax']);
		// 支払名称
		$cell = setCell(null, $sheet, 'paymentName', 1, $endColumn, 1, $endRow, getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']));
		// 備考
		$cell = setCell(null, $sheet, 'detailRemarks', 1, $endColumn, 1, $endRow, $payDetail['detailRemarks']);

		// 領収証シートをコピー
		$subSheet = clone $spreadsheet->getSheet(1);
		$subTitle = $subSheet->getTitle();
		$subSheet->setTitle($subTitle . '_' . getCodeTitle($codeLists['paymentType'], $payDetail['paymentCode']) . '(' . $payDetail['pid'] . ')');
		$spreadsheet->addSheet($subSheet);

		// 物件所在地
		$cell = setCell(null, $subSheet, 'supplierAddress', 1, $endColumn, 1, $endRow, $pay['supplierAddress']);
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

for($i = 1 ; $i < 3; $i++) {
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
?>
