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
$filePath = $fullPath.'/契約精算申請書.xlsx';
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 仕入契約情報を取得
$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($param->pid)->asArray();

// 契約・不可分一体精算申請書シート
$sheet = $spreadsheet->getSheet(0);

// 土地情報を取得
// 20230518 S_Update
// $bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('bukkenName')->select('startDate')->findOne($contract['tempLandInfoPid'])->asArray();
$bukken = ORM::for_table(TBLTEMPLANDINFO)->select('contractBukkenNo')->select('bukkenName')->select('startDate')->select('pid')->findOne($contract['tempLandInfoPid'])->asArray();
// 20230518 E_Update
// 仕入契約者情報を取得
$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->select('contractorName')->select('contractorAdress')->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();

// 複数契約者名（・区切り）
$list_contractorNameDot = getContractorName($sellers, '・');

// 所在地情報（物件単位）を取得
$locsByTempLand = ORM::for_table(TBLLOCATIONINFO)->select('locationType')->select('address')->select('bottomLandPid')->where('tempLandInfoPid', $bukken['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();

$addressByTempLand = '';// 所在地（物件単位）

$cntlocsByTempLand = 0;
$cntLandlocsByTempLand = 0;
$cntNotLandlocsByTempLand = 0;
$bottomLandsByTempLand = [];

$addressLandByTempLand = '';
$addressNotLandByTempLand = '';

foreach($locsByTempLand as $loc) {
	$cntlocsByTempLand++;

	// 区分が01：土地の場合
	if($loc['locationType'] == '01') {
		$cntLandlocsByTempLand++;
		if($cntLandlocsByTempLand == 1) {
			$addressLandByTempLand = $loc['address'];   // 所在地
		}
	}
	else {
		if(!empty($loc['bottomLandPid'])) $bottomLandsByTempLand[] = $loc;
		$cntNotLandlocsByTempLand++;
		if($cntLandlocsByTempLand == 0 && $cntNotLandlocsByTempLand == 1) {
			$addressNotLandByTempLand = $loc['address'];// 所在地
		}
	}
}

if($addressLandByTempLand != '') $addressByTempLand = $addressLandByTempLand;
else $addressByTempLand = $addressNotLandByTempLand;

// 土地に指定がないかつ、底地に指定がある場合
if($cntLandlocsByTempLand == 0 && sizeof($bottomLandsByTempLand) > 0) {
	foreach($bottomLandsByTempLand as $loc) {
		$bottomLand = ORM::for_table(TBLLOCATIONINFO)->find_one($loc['bottomLandPid']);
		$addressByTempLand = $bottomLand['address'];
		break;
	}
}

// 所在地情報を取得
$locs = getLocation($contract['pid']);
$address = '';          // 所在地
$blockNumber = '';      // 地番
$buildingNumber = '';   // 家屋番号

$cntlocs = 0;
$cntLandlocs = 0;
$cntNotLandlocs = 0;
$bottomLands = [];

$addressLand = '';
$addressNotLand = '';

foreach($locs as $loc) {
	$cntlocs++;

	// 区分が01：土地の場合
	if($loc['locationType'] == '01') {
		$cntLandlocs++;
		if($cntLandlocs == 1) {
			$addressLand = $loc['address'];    // 所在地
			$blockNumber = $loc['blockNumber'];// 地番
		}
	}
	else {
		if(!empty($loc['bottomLandPid'])) $bottomLands[] = $loc;
		$cntNotLandlocs++;
		if($cntLandlocs == 0 && $cntNotLandlocs == 1) {
			$addressNotLand = $loc['address'];       // 所在地
			$buildingNumber = $loc['buildingNumber'];// 家屋番号
		}
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

$endColumn = 18;// 最終列数
$endRow = 25;   // 最終行数

// 20230509 S_Add
$options = array('1部', '2部', '3部', '4部', '5部', '6部', '8部', '大阪1部', '大阪2部', '大阪3部', '名古屋1部', '名古屋2部', '名古屋3部');
createCombobox($sheet,'A3',$options);

$options = array('2ヶ月', '3ヶ月', '5ヶ月');
createCombobox($sheet,'D13',$options);

//対象のセルのコメント
$comment = $sheet -> getComment('D13');
$comment ->getFillColor() -> setRGB('ffffe1');
// 20230509 E_Add

// 20230518 S_Add
$bukkenName = $bukken['bukkenName'];
if($bukkenName != '') $bukkenName = '（' . $bukkenName . '）';
// 20230518 E_Add

// 物件住所（物件フォルダ名）契約物件番号
// 20230518 S_Update
// $cell = setCell(null, $sheet, 'addressAndBukkenNameAndContractBukkenNo', 1, $endColumn, 1, $endRow, $addressByTempLand . $bukken['bukkenName'] . $bukken['contractBukkenNo']);
$cell = setCell(null, $sheet, 'addressAndBukkenNameAndContractBukkenNo', 1, $endColumn, 1, $endRow, $addressByTempLand . $bukkenName . $bukken['contractBukkenNo']);
// 20230518 E_Update
// 所在地（地番）
$cell = setCell(null, $sheet, 'addressAndBlockOrBuildingNumber', 1, $endColumn, 1, $endRow, $addressAndBlockOrBuildingNumber);
// 契約物件（名義人）
$cell = setCell(null, $sheet, 'list_contractorNameDot', 1, $endColumn, 1, $endRow, $list_contractorNameDot);
// 契約書番号
$cell = setCell(null, $sheet, 'contractFormNumber', 1, $endColumn, 1, $endRow, $contract['contractFormNumber']);

$sheet->setSelectedCell('A1');// 初期選択セル設定

// 20230619 S_Add
//「契約・不可分一体精算申請書」シート
// エクセルの条件付き書式で行に色を付ける
createConditionExcel($sheet,'A3','=$A3<>""');
createConditionExcel($sheet,'E3','=$E3<>""');
createConditionExcel($sheet,'G6','=$G6<>""');
createConditionExcel($sheet,'M10','=$M10<>""');
createConditionExcel($sheet,'P10','=$P10<>""');
createConditionExcel($sheet,'G11','=$G11<>""');
createConditionExcel($sheet,'J11','=$J11<>""');
createConditionExcel($sheet,'G12','=$G12<>""');
createConditionExcel($sheet,'J12','=$J12<>""');
// 20230619 E_Add

// 不可分一体契約特別歩合申請書シート
$sheet = $spreadsheet->getSheet(1);

// 契約物件（名義人）
$cell = setCell(null, $sheet, 'startDate_dt_kanji', 1, $endColumn, 1, $endRow, convert_dt($bukken['startDate'], 'Y年n月j日'));

$sheet->setSelectedCell('A1');// 初期選択セル設定

// 20230619 S_Add
// 「不可分一体契約特別歩合申請書」シート
// エクセルの条件付き書式で行に色を付ける
createConditionExcel($sheet,'G10','=$G10<>""');
createConditionExcel($sheet,'J10','=$J10<>""');
// 20230619 E_Add

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '契約精算申請書_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath . '/' . $filename;

// Excel側の数式を再計算させる
$writer->setPreCalculateFormulas(false);

$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

// 20230619 S_Add
// エクセルの条件付き書式で行に色を付ける
function createConditionExcel($sheet,$range,$condition){
	// Create conditional formatting rule
	$conditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
	$conditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION)
		->addCondition($condition);
	$conditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
	$conditional->getStyle()->getFill()->getEndColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
	
	// Apply conditional formatting to the range
	$sheet->getStyle($range)->setConditionalStyles([$conditional]);	
}
// 20230619 E_Add

// 20230509 S_Add
function createCombobox($sheet, $cellName,$options){
	$dataValidation = $sheet->getCell($cellName)->getDataValidation();
	$dataValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
    ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION)
    ->setAllowBlank(false)
    ->setShowDropDown(true)
    ->setFormula1('"'.implode(',', $options).'"');
}
// 20230509 E_Add

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
	->select('p2.bottomLandPid', 'bottomLandPid')
	->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
	->where('p1.contractDataType', '01')
	->where('p1.contractInfoPid', $contractPid)
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
