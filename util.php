<?php
function endsWith($needle, $haystack) {
	return preg_match('/' . preg_quote($needle, '/') . '$/', $haystack);
}

function getGUID(){
	if (function_exists('com_create_guid')){
		return com_create_guid();
	}else{
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = chr(123)// "{"
		.substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12)
		.chr(125);// "}"
		return $uuid;
	}
}

function deleteDirectory($dirPath) {
	if (is_dir($dirPath)) {
		$objects = scandir($dirPath);
		foreach ($objects as $object) {
			if ($object != "." && $object !="..") {
				if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
					deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
				} else {
					unlink($dirPath . DIRECTORY_SEPARATOR . $object);
				}
			}
		}
		reset($objects);
		rmdir($dirPath);
	}
}

/**
 * 土地情報取得
 * @param unknown $pid
 */
function getLandInfo($pid){
	$land = ORM::for_table(TBLTEMPLANDINFO)->findOne($pid)->asArray();
	
	//地図添付
	$mapFiles = ORM::for_table(TBLMAPATTACH)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($mapFiles)){
		$land['mapFiles'] = $mapFiles;
	}
	
	//ファイル添付
	$attachFiles = ORM::for_table(TBLFILEATTACH)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($attachFiles)){
		$land['attachFiles'] = $attachFiles;
	}

	// 20231020 S_Add
	//物件写真添付
	$photoFiles = ORM::for_table(TBLBUKKENPHOTOATTACH)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($photoFiles)){
		$land['photoFilesMap'] = $photoFiles;
	}
	// 20231020 E_Add

	$locList = [];
	// 20220329 S_Update
	// $locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->findArray();
	// $locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('blockNumber')->order_by_asc('buildingNumber')->order_by_asc('address')->findArray();
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
	// 20220329 E_Update
	if(isset($locs)){

		foreach($locs as $loc){
			$sharers = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
			$loc['sharers'] = $sharers;
			// 20210311 S_Add
			$attachFiles = ORM::for_table(TBLLOCATIONATTACH)->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
			$loc['attachFiles'] = $attachFiles;
			// 20210311 E_Add
			// 20210614 S_Add
			$bottomLands = ORM::for_table(TBLBOTTOMLANDINFO)->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
			$loc['bottomLands'] = $bottomLands;
			// 20210614 E_Add
			// 20220614 S_Add
			$residents = ORM::for_table(TBLRESIDENTINFO)->where('locationInfoPid', $loc['pid'])->where_null('deleteDate')->order_by_asc('registPosition')->findArray();
			$loc['residents'] = $residents;
			// 20220614 E_Add
			$locList[] = $loc;
		}

		$land['locations'] = $locList;
	}
	return $land;
}

/**
 * 契約情報取得
 * @param unknown $pid
 */
function getContractInfo($pid){
	$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($pid)->asArray();

	$detailList = [];
	$details = ORM::for_table(TBLCONTRACTDETAILINFO)->where('contractInfoPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($details)){

		foreach($details as $detail){
			$registrants = ORM::for_table(TBLCONTRACTREGISTRANT)->where('contractDetailInfoPid', $detail['pid'])->where_null('deleteDate')->findArray();
			$detail['registrants'] = $registrants;
			$detailList[] = $detail;
		}

		$contract['details'] = $detailList;
	}
	else {
		$contract['details'] = [];
	}

	// 契約者
	$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
	if(isset($sellers)){
		$contract['sellers'] = $sellers;
	}
	else {
		$contract['sellers'] = [];
	}

	// 地図添付
	$contractFiles = ORM::for_table(TBLCONTRACTFILE)->where('contractInfoPid', $pid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($contractFiles)){
		$contract['contractFiles'] = $contractFiles;
	}

	// 20230227 S_Add
	// 仕入契約添付ファイル
	$contractAttaches = ORM::for_table(TBLCONTRACTATTACH)->where('contractInfoPid', $pid)->where('attachFileType', '0')->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($contractAttaches)){
		$contract['contractAttaches'] = $contractAttaches;
	}
	// 20230227 E_Add

	return $contract;
}

/**
 * 削除情報設定
 * @param unknown $data
 */
function setDelete($data, $userId){
	$data->deleteUserId = $userId;
	$data->deleteDate = date('Y-m-d H:i:s');
}
/**
 * 登録情報設定
 * @param unknown $data
 */
function setInsert($data, $userId){
	$data->createUserId = $userId;
	$data->createDate = date('Y-m-d H:i:s');
	//$data->updateUserId = $userId;
	//$data->updateDate = date('Y-m-d H:i:s');
}
/**
 * 更新情報設定
 * @param unknown $data
 */
function setUpdate($data, $userId){
	$data->updateDate = date('Y-m-d H:i:s');
	$data->updateUserId = $userId;
}

function copyData($source, $dest, $excludes){
	foreach (get_object_vars($source) as $key => $value) {
		if(!in_array($key, $excludes) && !endsWith('Map', $key)){
			$dest->$key = $value;
		}
	}
}

function formatNumber($number, $is2Byte) {
	if(!isset($number) || $number == '') {
		return '';
	}
	$ret = number_format($number);

	if(isset($is2Byte) && $is2Byte) {
		return mb_convert_kana($ret, 'KVRN');
	}
	return $ret;
}

//-----------------------出力-----------------------------

/**
 * セルに値設定
 */
function bindCell($cellName, $sheet, $keywords, $vals) {
	$str = $sheet->getCell($cellName)->getValue();

	if(is_array($keywords)) {
		for($i = 0; $i < sizeof($keywords); $i++) {
			$str = str_replace('$'. $keywords[$i] . '$', isset($vals[$i])? $vals[$i] : '' , $str);
		}
	}
	else {
		$str = str_replace('$'. $keywords . '$', $vals, $str);
	}
	$sheet->setCellValue($cellName, $str);
}
// 20211115 S_Add
/**
 * セルに値設定（指定文字で改行）
 */
function bindCellAndNewLine($cellName, $sheet, $keywords, $vals, $prefix, $length) {
	$str = $sheet->getCell($cellName)->getValue();

	if(is_array($keywords)) {
		for($i = 0; $i < sizeof($keywords); $i++) {
			$str = str_replace('$'. $keywords[$i] . '$', isset($vals[$i])? $vals[$i] : '', $str);
		}
	}
	else {
		$str = str_replace('$'. $keywords . '$', $vals, $str);
	}
	// 接頭辞を除外する
	$str = str_replace($prefix, '', $str);
	// 指定文字数で分割する
	/*
	$arrays = array();
	for($offset = 0; $offset < mb_strlen($str, 'SJIS'); $offset += $length) {
		$arrays[] = mb_substr($str, $offset, $length);
	}
	*/
	$arrays = array();
	$strLength = mb_strlen($str, 'SJIS');
	for($offset = 0; $offset < $strLength; $offset += $length) {
		// 指定文字数分の文字列を取得
		$part = mb_substr($str, $offset, $length);
		$charCount = 0;

		// ２行目以降の場合
		if(count($arrays) > 0 && $strLength > 0) {
			// １行前の文字列
			$abovePath = $arrays[count($arrays) - 1];
			// 先頭の文字
			$firstChar = mb_substr($part, 0, 1);

			while($firstChar === '）' || $firstChar === '、' || $firstChar === '。' || $firstChar === '」') {
				// １行前の文字列の末尾に先頭の文字を連結
				$abovePath .= $firstChar;
				// 先頭の文字を除外
				$part = mb_substr($part, 1);
				// 除外後の文字列の先頭の文字を設定
				$firstChar = mb_substr($part, 0, 1);
				$charCount++;
			}
			// １行前の文字列を更新
			$arrays[count($arrays) - 1] = $abovePath;
		}
		// １行前の文字列への移動があった場合
		if($charCount > 0) {
			// 移動した文字数分位置を加算
			$offset += $charCount;
			// 移動した文字を除外した位置から、指定文字数分の文字列を取得
			$part = mb_substr($str, $offset, $length);
		}
		$arrays[] = $part;
	}
	// セルに値を設定する
	$index = 1;
	$pos = $sheet->getCell($cellName)->getRow();
	foreach($arrays as $val) {
		if($val !== '') {
			if($index > 1) {
				$pos += 1;
				// 行の挿入
				$sheet->insertNewRowBefore($pos, 1);
			}
			// 接頭辞を先頭に連結して、値を設定
			$sheet->setCellValue('A' . $pos, $prefix . $val);
			$index++;
		}
	}
}
// 20211115 E_Add

/**
 * ブロックコピー
 */
function copyBlock($sheet, $startPos, $blockRowCount, $copyCount, $hasSingleRow = false) {

	$styleArray = [
		'font' => [
			'size' => 10.5,
			'name' => 'ＭＳ 明朝'
		],
		'alignment' => [
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_JUSTIFY,
			'wrapText' => true
		]
	];

	if(isset($hasSingleRow) && $hasSingleRow == true) {
		$single = $sheet->getCell('A' . ($startPos + $blockRowCount - 1 ))->getValue();
	}

	$str = $sheet->getCell('A' . $startPos)->getValue();
	$sheet->insertNewRowBefore($startPos, $blockRowCount * $copyCount);
	$lastPos = $startPos + ($blockRowCount * $copyCount);
	for($cursor = 0 ; $cursor < $copyCount ; $cursor++) {

		$copyPos = $startPos  + $blockRowCount * $cursor;
		copyRows($sheet, $lastPos, $copyPos, $blockRowCount, 1);

		$sheet->setCellValue('A'. $copyPos, $str);

		$range = 'A'. $copyPos . ':A' . ($copyPos + $blockRowCount - 1);
		$sheet->getStyle($range)->applyFromArray($styleArray);

		if(isset($hasSingleRow) && $hasSingleRow == true) {
			$sheet->setCellValue('A'. ($copyPos + $blockRowCount -1), $single);
		}
	}
}

/**
 * cellコピー
 */
function copyRows($sheet,$srcRow,$dstRow,$height,$width, $setStyle = false, $hasmerge = true) {

	$styleArray = [
		'font' => [
			'size' => 10.5,
			'name' => 'ＭＳ 明朝'
		],
		'alignment' => [
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'wrapText' => true
		]
	];

	for ($row = 0; $row < $height; $row++) {
		for ($col = 0; $col <= $width; $col++) {
			$cell = $sheet->getCellByColumnAndRow($col, $srcRow + $row);
			$style = $sheet->getStyleByColumnAndRow($col, $srcRow + $row);
			$dstCell = 'A' . (string)($dstRow + $row);
			$sheet->setCellValue($dstCell, $cell->getValue());
			$sheet->duplicateStyle($style, $dstCell);

			if($setStyle) {
				$sheet->getStyle($dstCell)->applyFromArray($styleArray);
			}
		}

		$h = $sheet->getRowDimension($srcRow + $row)->getRowHeight();
		$sheet->getRowDimension($dstRow + $row)->setRowHeight($h);
	}

	if($hasmerge) {
		foreach ($sheet->getMergeCells() as $mergeCell) {
			$mc = explode(":", $mergeCell);
			$col_s = preg_replace("/[0-9]*/", "", $mc[0]);
			$col_e = preg_replace("/[0-9]*/", "", $mc[1]);
			$row_s = ((int)preg_replace("/[A-Z]*/", "", $mc[0])) - $srcRow;
			$row_e = ((int)preg_replace("/[A-Z]*/", "", $mc[1])) - $srcRow;

			if (0 <= $row_s && $row_s < $height) {
				$merge = $col_s . (string)($dstRow + $row_s) . ":" . $col_e . (string)($dstRow + $row_e);
				$sheet->mergeCells($merge);
			} 
		}
	}
}

/**
 * cellコピー
 */
function copyRowsReverse($sheet,$srcRow,$dstRow,$height,$width, $setStyle = false, $hasmerge = true) {

	$styleArray = [
		'font' => [
			'size' => 10.5,
			'name' => 'ＭＳ 明朝'
		],
		'alignment' => [
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'wrapText' => true
		]
	];

	for ($row = 0; $row < $height; $row++) {
		for ($col = 0; $col <= $width; $col++) {
			$cell = $sheet->getCellByColumnAndRow($col, $srcRow);
			$style = $sheet->getStyleByColumnAndRow($col, $srcRow);
			$dstCell = 'A' . (string)($dstRow + $row);
			$sheet->setCellValue($dstCell, $cell->getValue());
			$sheet->duplicateStyle($style, $dstCell);

			if($setStyle) {
				$sheet->getStyle($dstCell)->applyFromArray($styleArray);
			}
		}

		$h = $sheet->getRowDimension($srcRow + $row)->getRowHeight();
		$sheet->getRowDimension($dstRow + $row)->setRowHeight($h);
	}

	if($hasmerge) {
		foreach ($sheet->getMergeCells() as $mergeCell) {
			$mc = explode(":", $mergeCell);
			$col_s = preg_replace("/[0-9]*/", "", $mc[0]);
			$col_e = preg_replace("/[0-9]*/", "", $mc[1]);
			$row_s = ((int)preg_replace("/[A-Z]*/", "", $mc[0])) - $srcRow;
			$row_e = ((int)preg_replace("/[A-Z]*/", "", $mc[1])) - $srcRow;

			if (0 <= $row_s && $row_s < $height) {
				$merge = $col_s . (string)($dstRow + $row_s) . ":" . $col_e . (string)($dstRow + $row_e);
				$sheet->mergeCells($merge);
			} 
		}
	}
}

/**
 * 行と値コピー
 */
function copyBlockWithValue($sheet, $startPos, $blockRowCount, $copyCount, $colums) {

	$styleArray = [
		'font' => [
			'size' => 10.5,
			'name' => 'ＭＳ 明朝'
		],
		'alignment' => [
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_JUSTIFY,
			'wrapText' => true
		]
	];

	$sheet->insertNewRowBefore($startPos, $blockRowCount * $copyCount);
	$lastPos = $startPos + ($blockRowCount * $copyCount);
	for($cursor = 0 ; $cursor < $copyCount ; $cursor++) {

		$copyPos = $startPos  + $blockRowCount * $cursor;
		copyRowsWithValue($sheet, $lastPos, $copyPos, $blockRowCount, $colums);

		$range = 'A'. $copyPos . ':A' . ($copyPos + $blockRowCount - 1);
		$sheet->getStyle($range)->applyFromArray($styleArray);
	}
}

function copyRowsWithValue($sheet,$srcRow,$dstRow,$height,$width, $setStyle = false) {

	$styleArray = [
		'font' => [
			'size' => 10.5,
			'name' => 'ＭＳ 明朝'
		],
		'alignment' => [
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'wrapText' => true
		]
	];

	for ($row = 0; $row < $height; $row++) {
		for ($col = 0; $col < $width; $col++) {
		$cell = $sheet->getCellByColumnAndRow($col, $srcRow + $row);
			$style = $sheet->getStyleByColumnAndRow($col, $srcRow + $row);
			$dstCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::stringFromColumnIndex($col) . ($dstRow + $row);//'A' . (string)($dstRow + $row);
			$sheet->setCellValue($dstCell, $cell->getValue());
			$sheet->duplicateStyle($style, $dstCell);

			if($setStyle) {
				$sheet->getStyle($dstCell)->applyFromArray($styleArray);
			}
		}

		$h = $sheet->getRowDimension($srcRow + $row)->getRowHeight();
		$sheet->getRowDimension($dstRow + $row)->setRowHeight($h);
	}

	foreach ($sheet->getMergeCells() as $mergeCell) {
		$mc = explode(":", $mergeCell);
		$col_s = preg_replace("/[0-9]*/", "", $mc[0]);
		$col_e = preg_replace("/[0-9]*/", "", $mc[1]);
		$row_s = ((int)preg_replace("/[A-Z]*/", "", $mc[0])) - $srcRow;
		$row_e = ((int)preg_replace("/[A-Z]*/", "", $mc[1])) - $srcRow;

		if (0 <= $row_s && $row_s < $height) {
			$merge = $col_s . (string)($dstRow + $row_s) . ":" . $col_e . (string)($dstRow + $row_e);
			$sheet->mergeCells($merge);
		} 
	}
}

// 20201021 S_Update
/*
function getCodeTitle($lst, $codeDetail) {
	$ret = [];
	$strs = explode(',', $codeDetail);
	foreach($lst as $code) {
		if($code['codeDetail'] === $codeDetail || in_array($code['codeDetail'], $strs)){
			$ret[] = $code['name'];
		}
	}
	return implode(',', $ret);
}
*/
function getCodeTitle($lst, $codeDetail, $delimiter = ',') {
	$ret = [];
	$strs = explode(',', $codeDetail);
	foreach($lst as $code) {
		if($code['codeDetail'] === $codeDetail || in_array($code['codeDetail'], $strs)){
			$ret[] = $code['name'];
		}
	}
	return implode($delimiter, $ret);
}
// 20201021 E_Update

// 20210204 S_Update
//function getRegistrants($details, $loc) {
function getRegistrants($details, $loc, $setShareRatio = false) {
// 20210204 E_Update
	$ctDetail = null;
	$ret = [];
	foreach($details as $detail) {
		if($detail['locationInfoPid'] === $loc['pid']) {
			$ctDetail = $detail;
			break;
		}
	}
	if(!isset($ctDetail)) {
		return $ret;
	}

	$ids = [];
	foreach($ctDetail['registrants'] as $regist) {
		$ids[] = $regist['sharerInfoPid'];
	}

	if(sizeof($ids) > 0) {
		// 20210204 S_Update
		/*
		$lst = ORM::for_table(TBLSHARERINFO)->where_in('pid', $ids)->order_by_asc('pid')->select('sharer')->findArray();
		foreach($lst as $item) {
			$ret[] = $item['sharer'];
		}
		*/
		if($setShareRatio) {
			$lst = ORM::for_table(TBLSHARERINFO)->where_in('pid', $ids)->order_by_asc('pid')->select('sharer')->select('shareRatio')->findArray();
			foreach($lst as $item) {
				$shareRatio = $item['shareRatio'];
				// 持ち分に指定がある場合
				if(isset($shareRatio) && $shareRatio !== '') {
					$ret[] = $item['sharer'].'（持分'.$shareRatio.'）';
				}
				else {
					$ret[] = $item['sharer'];
				}
			}
		}
		else {
			$lst = ORM::for_table(TBLSHARERINFO)->where_in('pid', $ids)->order_by_asc('pid')->select('sharer')->findArray();
			foreach($lst as $item) {
				$ret[] = $item['sharer'];
			}
		}
		// 20210204 E_Update
	}
	return $ret;
}

/**
 * ロケーションのSharer
 */
// 20210204 S_Update
//function getSharers($loc) {
function getSharers($loc, $setShareRatio = false) {
// 20210204 E_Update
	$ret = [];

	// 20210204 S_Update
	/*
	$lst = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $loc['pid'])->order_by_asc('pid')->select('sharer')->findArray();
	foreach($lst as $item) {
		$ret[] = $item['sharer'];
	}
	*/
	if($setShareRatio) {
		$lst = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $loc['pid'])->order_by_asc('pid')->select('sharer')->select('shareRatio')->findArray();
		foreach($lst as $item) {
			$shareRatio = $item['shareRatio'];
			// 持ち分に指定がある場合
			if(isset($shareRatio) && $shareRatio !== '') {
				$ret[] = $item['sharer'].'（持分'.$shareRatio.'）';
			}
			else {
				$ret[] = $item['sharer'];
			}
		}
	}
	else {
		$lst = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $loc['pid'])->order_by_asc('pid')->select('sharer')->findArray();
		foreach($lst as $item) {
			$ret[] = $item['sharer'];
		}
	}
	// 20210204 E_Update
	return $ret;
}

/**
 * 支払管理情報取得
 * @param unknown $pid
 */
function getPayContractInfo($pid){
	$paycontract = ORM::for_table(TBLPAYCONTRACT)->findOne($pid)->asArray();

	$detailList = [];
	$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($details)){

		// foreach($details as $detail){
		// 	$registrants = ORM::for_table(TBLCONTRACTREGISTRANT)->where('payContractPid', $detail['pid'])->where_null('deleteDate')->findArray();
		// 	$detail['registrants'] = $registrants;
		// 	$detailList[] = $detail;
		// }

		//$paycontract['details'] = $detailList;

		$paycontract['details'] = $details;
	}
	else {
		$paycontract['details'] = [];
	}

	return $paycontract;
}

/**
 * 入金管理情報取得
 * @param unknown $pid
 */
function getReceiveContractInfo($pid){
	$receivecontract = ORM::for_table(TBLRECEIVECONTRACT)->findOne($pid)->asArray();

	$detailList = [];
	$details = ORM::for_table(TBLRECEIVECONTRACTDETAIL)->where('receiveContractPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($details)){

		$receivecontract['details'] = $details;
	}
	else {
		$receivecontract['details'] = [];
	}

	return $receivecontract;
}

/**
 * 事業収支情報取得
 * @param unknown $pid
 */
function getPlan($pid){
	$plan = ORM::for_table(TBLPLAN)->findOne($pid)->asArray();

	$detailList = [];
	$details = ORM::for_table(TBLPLANDETAIL)->where('planPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($details)){

		$plan['details'] = $details;
	}
	else {
		$plan['details'] = [];
	}

	return $plan;
}

/**
 * 事業収支情報取得
 * @param unknown $pid
 */
function getPlanInfo($pid){
	$plan = ORM::for_table(TBLPLAN)->findOne($pid)->asArray();

	$detailList = [];
	$details = ORM::for_table(TBLPLANDETAIL)->where('planPid', $pid)->where_null('deleteDate')->order_by_asc('backNumber')->findArray();
	if(isset($details)){

		$plan['details'] = $details;
	}
	else {
		$plan['details'] = [];
	}

	$rent = ORM::for_table(TBLPLANRENTROLL)->where('planPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($rent) && sizeof($rent) > 0 ){
		$plan['rent'] = $rent[0];
	}
	else {
		$plan['rent'] = null;
	}

	$rentdetails = ORM::for_table(TBLPLANRENTROLLDETAIL)->where('planPid', $pid)->where_null('deleteDate')->order_by_asc('backNumber')->findArray();
	if(isset($rentdetails)){

		$plan['rentdetails'] = $rentdetails;
	}
	else {
		$plan['rentdetails'] = [];
	}

	return $plan;
}

//20200909 S_Edd
/**
 * 事業収支情報履歴取得
 * @param unknown $pid
 */
function getPlanInfoHistory($pid){
	$planHistory = ORM::for_table(TBLPLANHISTORY)->findOne($pid)->asArray();

	$detailHistorys = ORM::for_table(TBLPLANDETAILHISTORY)->where('planHistoryPid', $pid)->where_null('deleteDate')->order_by_asc('backNumber')->findArray();
	if(isset($detailHistorys)) {
		$planHistory['details'] = $detailHistorys;
	}
	else {
		$planHistory['details'] = [];
	}

	$rentHistory = ORM::for_table(TBLPLANRENTROLLHISTORY)->where('planHistoryPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($rentHistory) && sizeof($rentHistory) > 0 ) {
		$planHistory['rent'] = $rentHistory[0];
	}
	else {
		$planHistory['rent'] = null;
	}

	$rentdetailHistorys = ORM::for_table(TBLPLANRENTROLLDETAILHISTORY)->where('planHistoryPid', $pid)->where_null('deleteDate')->order_by_asc('backNumber')->findArray();
	if(isset($rentdetailHistorys)) {
		$planHistory['rentdetails'] = $rentdetailHistorys;
	}
	else {
		$planHistory['rentdetails'] = [];
	}

	return $planHistory;
}
//20200909 E_Add

function searchCellPos($sheet, $keyword, $startPos) {

	if(strpos('$', $keyword) === false) {
		$keyword = '$' . $keyword . '$';
	}
	$str = $sheet->getCell('A'.$startPos)->getValue();
	
	$loop = 0;
	$hasKeyword = false;
	do {
		if(!isset($str) || $str === '' || strpos($str, $keyword) === false) {
			$startPos++;
			$str = $sheet->getCell('A'.$startPos)->getValue();
			$loop++;
		}
		else {
			$hasKeyword = true;
			break;
		}
	}
	while((!$hasKeyword || $startPos < 450) && $loop < 200);

	if(!$hasKeyword) {
		return -1;
	}

	return $startPos;
}

// 20210524 S_Add
function searchCell($sheet, $keyword, $startColumn, $endColumn, $startRow, $endRow) {
	$ret = null;

	if(strpos('$', $keyword) === false) {
		$keyword = '$' . $keyword . '$';
	}

	$checkRow = $startRow;
	// 行ループ
	while($checkRow <= $endRow) {
		// 列番号初期化
		if($checkRow == $startRow) $checkColumn = $startColumn;
		else $checkColumn = 1;

		// 列ループ
		while ($checkColumn <= $endColumn) {
			$val = $sheet->getCellByColumnAndRow($checkColumn, $checkRow)->getValue();
			if (strpos($val, $keyword) !== false) {
				$ret = $sheet->getCellByColumnAndRow($checkColumn, $checkRow);
				return $ret;
			}
			$checkColumn++;
		}
		$checkRow++;
	}
	return $ret;
}
// 20210524 E_Add

/**
 * 物件プラン取得
 */
function getLandPlan($pid) {
	$data = new stdClass();

	$land = ORM::for_table(TBLTEMPLANDINFO)->findOne($pid)->asArray();
	$data->land = $land;

	$plans = ORM::for_table(TBLBUKKENPLANINFO)->where('tempLandInfoPid', $pid)->order_by_asc('pid')->findArray();
	if(isset($plans)){
		$data->plans = $plans;
	}

	// 20230511 S_Update
	// // 20220329 S_Update
	// // $sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $pid)->order_by_asc('pid')->findArray();
	// $sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $pid)->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
	// // 20220329 E_Update
	$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
	// 20230511 E_Update
	if(isset($sales)){

		// 20230227 S_Add
		$detailList = [];
		foreach($sales as $sale){
			// 物件売契約添付ファイル
			$salesAttaches = ORM::for_table(TBLBUKKENSALESATTACH)->where('bukkenSalesInfoPid', $sale['pid'])->where('attachFileType', '0')->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
			if(isset($salesAttaches)){
				$sale['salesAttaches'] = $salesAttaches;
			}
			else
			{
				$sale['salesAttaches'] = [];
			}
			$detailList[] = $sale;
		}
		$sales = $detailList;
		// 20230227 E_Add

		$data->sales = $sales;
	}
	return $data;
}

// 20211021 S_Add
function convert_dt($date, $format) {
	if(!isset($date) || $date == '') return '';
	return date($format, strtotime($date));
}
// 20211021 E_Add

// 20220615 S_Update
//function convert_jpdt($date) {
function convert_jpdt($date, $format = 'm.d', $delimiter = '.') {
// 20220615 E_Update
	$ret = '';// 20220615 Add
	if(!isset($date) || $date == '') return $ret;
	$year = substr($date, 0, 4);
	if ($date >= 20190501) {        //令和元年（2019年5月1日以降）
		$name = "R";
		$year = $year - 2018;
	} else if ($date >= 19890108) { //平成元年（1989年1月8日以降）
		$name = "H";
		$year = $year - 1988;
	} else if ($date >= 19261225) { //昭和元年（1926年12月25日以降）
		$name = "S";
		$year = $year - 1925;
	} else if ($date >= 19120730) { //大正元年（1912年7月30日以降）
		$name = "T";
		$year = $year - 1911;
	} else if ($date >= 18680125) { //明治元年（1868年1月25日以降）
		$name = "M";
		$year = $year - 1867;
	} else {
		$name = 'AD';
	}
	$day = new DateTime($date);
	// 20220615 S_Update
//	return $name.$year.'.'.date_format($day, 'm.d');
	if($format == 'name') $ret = $name;
	else if($format == 'year') $ret = $year;
	else $ret = $name . $year . $delimiter . date_format($day, $format);
	return $ret;
	// 20220615 E_Update
}
// 20210525 S_Add
// 20211021 S_Update
//function convert_jpdt_kanji($date) {
function convert_jpdt_kanji($date, $format = 'm月d日') {
// 20211021 E_Update
	if(!isset($date) || $date == '') return '';
	$year = substr($date, 0, 4);
	if ($date >= 20190501) {        //令和元年（2019年5月1日以降）
		$name = "令和";
		$year = $year - 2018;
	} else if ($date >= 19890108) { //平成元年（1989年1月8日以降）
		$name = "平成";
		$year = $year - 1988;
	} else if ($date >= 19261225) { //昭和元年（1926年12月25日以降）
		$name = "昭和";
		$year = $year - 1925;
	} else if ($date >= 19120730) { //大正元年（1912年7月30日以降）
		$name = "大正";
		$year = $year - 1911;
	} else if ($date >= 18680125) { //明治元年（1868年1月25日以降）
		$name = "明治";
		$year = $year - 1867;
	} else {
		$name = '西暦';
	}
	$day = new DateTime($date);
	// 20211021 S_Update
//	return $name.$year.'年'.date_format($day, 'm月d日');
	return $name.$year.'年'.date_format($day, $format);
	// 20211021 E_Update
}
// 20210525 E_Add

function notNull($val) {
	return isset($val) && $val != '';
}

function equalVal($obj, $key, $val) {
	return isset($obj[$key]) && $obj[$key] == $val;
}

function showDay($day) {
	if(isset($day) && $day !== '') {
		$val = date('Y年m月d日', strtotime($day));
	}
	else {
		$val = '';
	}
	return $val;
}

// 20210728 S_Add
/**
 * 仕入契約情報から支払契約情報連携
 */
function setPayByContract($contract, $userId) {
	// コード名称マスタを取得
	$codenames = ORM::for_table(TBLCODENAMEMST)->where_Like('code', 'SYS1%')->order_by_asc('code')->findArray();
	if(sizeof($codenames) > 0) {
		foreach($codenames as $codename) {
			$paycontract = null;
			$contractor = null;// 契約者
			// 契約カテゴリ<-コード名称.コードからSYS1を除外した値
			// SYS101:契約支払連携（売買代金）,SYS102:契約支払連携（仲介手数料）,SYS103:契約支払連携（業務委託料）
			$contractCategory = $codename['code'];
			$contractCategory = str_replace('SYS1', '', $contractCategory);

			// 支払契約情報を取得
			// 20211021 S_Update
//			$paycontracts = ORM::for_table(TBLPAYCONTRACT)->where('contractInfoPid', $contract['pid'])->where('contractCategory', $contractCategory)->order_by_asc('pid')->findArray();
			$paycontracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $contract['tempLandInfoPid'])->where('contractInfoPid', $contract['pid'])->where('contractCategory', $contractCategory)->order_by_asc('pid')->findArray();
			// 20211021 E_Update
			if(sizeof($paycontracts) == 0) {
				$paycontract = ORM::for_table(TBLPAYCONTRACT)->create();
				$paycontract['tempLandInfoPid'] = $contract['tempLandInfoPid'];// 土地情報PID
				$paycontract['contractInfoPid'] = $contract['pid'];            // 仕入契約情報PID
				$paycontract['contractCategory'] = $contractCategory;          // 契約カテゴリ
				$paycontract['taxEffectiveDay'] = date("Ymd");                 // 消費税適応日<-システム日付
				setInsert($paycontract, $userId);
			} else {
				$pid = null;
				foreach($paycontracts as $temp) {
					$pid = $temp['pid'];
					break;
				}
				$paycontract = ORM::for_table(TBLPAYCONTRACT)->findOne($pid);
				setUpdate($paycontract, $userId);
				// 削除扱いの場合
				if($paycontract['deleteDate'] != null) {
					$paycontract['deleteUserId'] = null;
					$paycontract['deleteDate'] = null;
				}
			}

			// 支取引先名称・取引先住所を設定する
			$supplierName = null;
			$supplierAddress = null;
			// 仕入契約者情報を取得
			$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
			if(isset($sellers)) {
				$index = 0;
				foreach($sellers as $seller) {
					$index++;
					// 契約カテゴリが01:売買代金の場合
					if($paycontract['contractCategory'] === '01' && $index == 1) {
						$supplierName = $seller['contractorName'];     // 取引先名称<-契約者名
						$supplierAddress = $seller['contractorAdress'];// 取引先住所<-契約者住所
					}
					if(strlen($contractor) > 0) $contractor .= ',';
					$contractor .= $seller['pid'];
				}
			}
			// 契約カテゴリが02:仲介手数料の場合
			if($paycontract['contractCategory'] === '02') {
				$supplierName = $contract['intermediaryName'];         // 取引先名称<-仲介会社
				$supplierAddress = $contract['intermediaryAddress'];   // 取引先住所<-仲介会社住所
			}
			// 契約カテゴリが03:業務委託料の場合
			else if($paycontract['contractCategory'] === '03') {
				$supplierName = $contract['outsourcingName'];          // 取引先名称<-業務委託先
				$supplierAddress = $contract['outsourcingAddress'];    // 取引先住所<-業務委託先住所
			}
			$paycontract['supplierName'] = $supplierName;              // 取引先名称
			$paycontract['supplierAddress'] = $supplierAddress;        // 取引先住所
			// 契約カテゴリが01:売買代金の場合
			if($paycontract['contractCategory'] === '01') {
				$paycontract['bank'] = $contract['bank'];              // 銀行名
				$paycontract['branchName'] = $contract['branchName'];  // 支店
				$paycontract['accountType'] = $contract['accountType'];// 口座種別
				$paycontract['accountName'] = $contract['accountName'];// 口座
				$paycontract['bankName'] = $contract['bankName'];      // 名義
			}
			$paycontract->save();

			$cntDetail = 0;// 明細件数

			// コードマスタを取得
			$codes = ORM::for_table(TBLCODE)->where('code', $codename['code'])->order_by_asc('displayOrder')->findArray();
			if(sizeof($codes) > 0) {
				foreach($codes as $code) {
					$paycontractdetail = null;

					// 支払契約詳細情報を取得
					$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $paycontract['pid'])->where('paymentCode', $code['name'])->order_by_asc('pid')->findArray();
					if(sizeof($details) == 0) {
						$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->create();
						$paycontractdetail['payContractPid'] = $paycontract['pid'];          // 支払契約情報PID
						$paycontractdetail['tempLandInfoPid'] = $contract['tempLandInfoPid'];// 土地情報PID
						$paycontractdetail['paymentCode'] = $code['name'];                   // 支払コード<-コード.名称
						$paycontractdetail['fbOutPutFlg'] = '0';                             // FB出力済フラグ<-0:未連携
						setInsert($paycontractdetail, $userId);
					} else {
						$pid = null;
						foreach($details as $temp) {
							$pid = $temp['pid'];
							break;
						}
						$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->findOne($pid);
						setUpdate($paycontractdetail, $userId);
						// 削除扱いの場合
						if($paycontractdetail['deleteDate'] != null) {
							$paycontractdetail['deleteUserId'] = null;
							$paycontractdetail['deleteDate'] = null;
						}
					}
					$paycontractdetail['contractor'] = $contractor;                    // 契約者
					// 20231207 S_Update
					// $paycontractdetail['payPriceTax'] = $contract[$code['codeDetail']];// 支払金額（税込）
					// 売買代金（土地）
					if($code['codeDetail'] == 'tradingLandPrice'){
						// 20231218 S_Update
						// $paycontractdetail['payPriceTax'] = intval($contract['tradingLandPrice']) + intval($contract['tradingLeasePrice']);
						if((intval($contract['decisionPrice']) - intval($contract['tradingBuildingPrice'])) > 0){
							// 決済代金-売買代金（建物）
							$paycontractdetail['payPriceTax'] = intval($contract['decisionPrice']) - intval($contract['tradingBuildingPrice']);
						}
						else{
							$paycontractdetail['payPriceTax'] = 0;
						}
						// 20231218 E_Update
						$paycontractdetail['payPrice'] = $paycontractdetail['payPriceTax'];
					}
					// 固都税清算金（建物）
					else if($code['codeDetail'] == 'fixedBuildingTax'){
						$paycontractdetail['payPriceTax'] = intval($contract['fixedBuildingTax']) + intval($contract['fixedBuildingTaxOnlyTax']);
						$paycontractdetail['payPrice'] = $contract['fixedBuildingTax'];
						$paycontractdetail['payTax'] = $contract['fixedBuildingTaxOnlyTax'];
					}
					else{
						$paycontractdetail['payPriceTax'] = $contract[$code['codeDetail']];// 支払金額（税込）
						$paycontractdetail['payPrice'] = $paycontractdetail['payPriceTax'];
					}
					// 20231207 E_Update
					$payPriceTax = intval($paycontractdetail['payPriceTax']);

					// 支払金額（税別）・消費税を設定する
					// 支払種別を取得
					$paymentType = ORM::for_table(TBLPAYMENTTYPE)->findOne($paycontractdetail['paymentCode'])->asArray();
					// 課税フラグが1:対象の場合
					if($paymentType['taxFlg'] === '1') {
						// 税率を取得する
						$taxRate = ORM::for_table(TBLTAX)->where_lte('effectiveDay', $paycontract['taxEffectiveDay'])->max('taxRate');
						$taxRate = intval($taxRate);
						$payPrice = ceil($payPriceTax / (1 + $taxRate / 100));
						$payTax = $payPriceTax - $payPrice;
						$paycontractdetail['payPrice'] = $payPrice;
						$paycontractdetail['payTax'] = $payTax;
					}

					// 支払予定日・支払確定日を設定する
					// 決済代金
					if($code['codeDetail'] === 'decisionPrice') {
						$paycontractdetail['contractFixDay'] = $contract['decisionDay'];  // 支払確定日<-決済日
						$paycontractdetail['contractDay'] = $contract['deliveryFixedDay'];// 支払予定日<-決済予定日
					}
					// 20231218 S_Add
					// 売買代金（土地）
					else if($code['codeDetail'] == 'tradingLandPrice') {
						$paycontractdetail['contractFixDay'] = $contract['decisionDay'];  // 支払確定日<-決済日
						$paycontractdetail['contractDay'] = $contract['deliveryFixedDay'];// 支払予定日<-決済予定日
					}
					// 売買代金（建物）
					else if($code['codeDetail'] == 'tradingBuildingPrice') {
						$paycontractdetail['contractFixDay'] = $contract['decisionDay'];  // 支払確定日<-決済日
						$paycontractdetail['contractDay'] = $contract['deliveryFixedDay'];// 支払予定日<-決済予定日
					}
					// 20231218 E_Add
					// 内金１
					else if($code['codeDetail'] === 'deposit1') {
						if($contract['deposit1DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit1Day'];// 支払確定日<-内金１日付
						else $paycontractdetail['contractDay'] = $contract['deposit1Day'];                                     // 支払予定日<-内金１日付
					}
					// 内金２
					else if($code['codeDetail'] === 'deposit2') {
						if($contract['deposit2DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit2Day'];// 支払確定日<-内金２日付
						else $paycontractdetail['contractDay'] = $contract['deposit2Day'];                                     // 支払予定日<-内金２日付
					}
					// 内金３
					else if($code['codeDetail'] === 'deposit3') {
						if($contract['deposit3DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit3Day'];// 支払確定日<-内金３日付
						else $paycontractdetail['contractDay'] = $contract['deposit3Day'];                                     // 支払予定日<-内金３日付
					}
					// 内金４
					else if($code['codeDetail'] === 'deposit4') {
						if($contract['deposit4DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit4Day'];// 支払確定日<-内金４日付
						else $paycontractdetail['contractDay'] = $contract['deposit4Day'];                                     // 支払予定日<-内金４日付
					}
					// 20231207 S_Add
					// 内金５
					else if($code['codeDetail'] === 'deposit5') {
						if($contract['deposit5DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit5Day'];// 支払確定日<-内金５日付
						else $paycontractdetail['contractDay'] = $contract['deposit5Day'];                                     // 支払予定日<-内金５日付
					}
					// 内金６
					else if($code['codeDetail'] === 'deposit6') {
						if($contract['deposit6DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit6Day'];// 支払確定日<-内金６日付
						else $paycontractdetail['contractDay'] = $contract['deposit6Day'];                                     // 支払予定日<-内金６日付
					}
					// 内金７
					else if($code['codeDetail'] === 'deposit7') {
						if($contract['deposit7DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit7Day'];// 支払確定日<-内金７日付
						else $paycontractdetail['contractDay'] = $contract['deposit7Day'];                                     // 支払予定日<-内金７日付
					}
					// 内金８
					else if($code['codeDetail'] === 'deposit8') {
						if($contract['deposit8DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit8Day'];// 支払確定日<-内金８日付
						else $paycontractdetail['contractDay'] = $contract['deposit8Day'];                                     // 支払予定日<-内金８日付
					}
					// 内金９
					else if($code['codeDetail'] === 'deposit9') {
						if($contract['deposit9DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit9Day'];// 支払確定日<-内金９日付
						else $paycontractdetail['contractDay'] = $contract['deposit9Day'];                                     // 支払予定日<-内金９日付
					}
					// 内金１０
					else if($code['codeDetail'] === 'deposit10') {
						if($contract['deposit10DayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['deposit10Day'];// 支払確定日<-内金１０日付
						else $paycontractdetail['contractDay'] = $contract['deposit10Day'];                                     // 支払予定日<-内金１０日付
					}
					// 20231207 E_Add
					// 手付金
					else if($code['codeDetail'] === 'earnestPrice') {
						if($contract['earnestPriceDayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['earnestPriceDay'];// 支払確定日<-手付金日付
						else $paycontractdetail['contractDay'] = $contract['earnestPriceDay'];                                         // 支払予定日<-手付金日付
					}
					// 固都税清算金
					else if($code['codeDetail'] === 'fixedTax') {
						if($contract['decisionDayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['decisionDay'];// 支払確定日<-決済日
						else $paycontractdetail['contractDay'] = $contract['decisionDay'];                                     // 支払予定日<-決済日
					}
					// 20231208 S_Add
					// 固都税清算金（土地）
					else if($code['codeDetail'] === 'fixedLandTax') {
						if($contract['decisionDayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['decisionDay'];// 支払確定日<-決済日
						else $paycontractdetail['contractDay'] = $contract['decisionDay'];                                     // 支払予定日<-決済日
					}
					// 固都税清算金（建物）
					else if($code['codeDetail'] === 'fixedBuildingTax') {
						if($contract['decisionDayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['decisionDay'];// 支払確定日<-決済日
						else $paycontractdetail['contractDay'] = $contract['decisionDay'];                                     // 支払予定日<-決済日
					}
					// 20231208 E_Add
					// 留保金
					else if($code['codeDetail'] === 'retainage') {
						if($contract['retainageDayChk'] == '1') $paycontractdetail['contractFixDay'] = $contract['retainageDay'];// 支払確定日<-<-留保金支払(明渡)日
						else $paycontractdetail['contractDay'] = $contract['retainageDay'];                                      // 支払予定日<-<-留保金支払(明渡)日
					}
					// 仲介手数料
					else if($code['codeDetail'] === 'intermediaryPrice') {
						$paycontractdetail['contractFixDay'] = $contract['intermediaryPricePayDay'];// 支払確定日<-仲介手数料支払日
					}
					// 業務委託料
					else if($code['codeDetail'] === 'outsourcingPrice') {
						$paycontractdetail['contractFixDay'] = $contract['outsourcingPricePayDay'];// 支払確定日<-業務委託料支払日
					}

					// 支払金額（税込）が0の場合、削除扱いとする
					if($payPriceTax == 0) {
						setDelete($paycontractdetail, $userId);
					}
					else $cntDetail++;

					$paycontractdetail->save();
				}
			}
			// 明細件数が0の場合、削除扱いとする
			if($cntDetail == 0) {
				setDelete($paycontract, $userId);
				$paycontract->save();
			}
		}
	}
}

/**
 * 売り契約情報から支払契約情報連携
 */
function setPayBySale($sale, $userId) {
	// コード名称マスタを取得
	$codenames = ORM::for_table(TBLCODENAMEMST)->where_Like('code', 'SYS2%')->order_by_asc('code')->findArray();
	if(sizeof($codenames) > 0) {
		foreach($codenames as $codename) {
			$paycontract = null;
			// 契約カテゴリ<-コード名称.コードからSYS2を除外した値
			// SYS202:売契約支払連携（仲介手数料）,SYS203:売契約支払連携（業務委託料）
			$contractCategory = $codename['code'];
			$contractCategory = str_replace('SYS2', '', $contractCategory);

			// 支払契約情報を取得
			// 20211021 S_Update
//			$paycontracts = ORM::for_table(TBLPAYCONTRACT)->where('bukkenSalesInfoPid', $sale['pid'])->where('contractCategory', $contractCategory)->order_by_asc('pid')->findArray();
			$paycontracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $sale['tempLandInfoPid'])->where('bukkenSalesInfoPid', $sale['pid'])->where('contractCategory', $contractCategory)->order_by_asc('pid')->findArray();
			// 20211021 S_Update
			if(sizeof($paycontracts) == 0) {
				$paycontract = ORM::for_table(TBLPAYCONTRACT)->create();
				$paycontract['tempLandInfoPid'] = $sale['tempLandInfoPid'];// 土地情報PID
				$paycontract['bukkenSalesInfoPid'] = $sale['pid'];         // 物件売契約情報PID
				$paycontract['contractCategory'] = $contractCategory;      // 契約カテゴリ
				$paycontract['taxEffectiveDay'] = date("Ymd");             // 消費税適応日<-システム日付
				setInsert($paycontract, $userId);
			} else {
				$pid = null;
				foreach($paycontracts as $temp) {
					$pid = $temp['pid'];
					break;
				}
				$paycontract = ORM::for_table(TBLPAYCONTRACT)->findOne($pid);
				setUpdate($paycontract, $userId);
				// 削除扱いの場合
				if($paycontract['deleteDate'] != null) {
					$paycontract['deleteUserId'] = null;
					$paycontract['deleteDate'] = null;
				}
			}

			// 支取引先名称・取引先住所を設定する
			$supplierName = null;
			$supplierAddress = null;
			// 契約カテゴリが02:仲介手数料の場合
			if($paycontract['contractCategory'] === '02') {
				$supplierName = $sale['salesIntermediary'];            // 取引先名称<-仲介会社
				$supplierAddress = $sale['salesIntermediaryAddress'];  // 取引先住所<-仲介会社住所
			}
			// 契約カテゴリが03:業務委託料の場合
			else if($paycontract['contractCategory'] === '03') {
				$supplierName = $sale['salesOutsourcingName'];         // 取引先名称<-業務委託先
				$supplierAddress = $sale['salesOutsourcingAddress'];   // 取引先住所<-業務委託先住所
			}
			$paycontract['supplierName'] = $supplierName;              // 取引先名称
			$paycontract['supplierAddress'] = $supplierAddress;        // 取引先住所

			$paycontract->save();

			$cntDetail = 0;// 明細件数

			// コードマスタを取得
			$codes = ORM::for_table(TBLCODE)->where('code', $codename['code'])->order_by_asc('displayOrder')->findArray();
			if(sizeof($codes) > 0) {
				foreach($codes as $code) {
					$paycontractdetail = null;

					// 支払契約詳細情報を取得
					$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $paycontract['pid'])->where('paymentCode', $code['name'])->order_by_asc('pid')->findArray();
					if(sizeof($details) == 0) {
						$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->create();
						$paycontractdetail['payContractPid'] = $paycontract['pid'];          // 支払契約情報PID
						$paycontractdetail['tempLandInfoPid'] = $sale['tempLandInfoPid'];    // 土地情報PID
						$paycontractdetail['paymentCode'] = $code['name'];                   // 支払コード<-コード.名称
						$paycontractdetail['fbOutPutFlg'] = '0';                             // FB出力済フラグ<-0:未連携
						setInsert($paycontractdetail, $userId);
					} else {
						$pid = null;
						foreach($details as $temp) {
							$pid = $temp['pid'];
							break;
						}
						$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->findOne($pid);
						setUpdate($paycontractdetail, $userId);
						// 削除扱いの場合
						if($paycontractdetail['deleteDate'] != null) {
							$paycontractdetail['deleteUserId'] = null;
							$paycontractdetail['deleteDate'] = null;
						}
					}
					$paycontractdetail['payPriceTax'] = $sale[$code['codeDetail']];// 支払金額（税込）
					$payPriceTax = intval($paycontractdetail['payPriceTax']);

					// 支払金額（税別）・消費税を設定する
					// 支払種別を取得
					$paymentType = ORM::for_table(TBLPAYMENTTYPE)->findOne($paycontractdetail['paymentCode'])->asArray();
					// 課税フラグが1:対象の場合
					if($paymentType['taxFlg'] === '1') {
						// 税率を取得する
						$taxRate = ORM::for_table(TBLTAX)->where_lte('effectiveDay', $paycontract['taxEffectiveDay'])->max('taxRate');
						$taxRate = intval($taxRate);
						$payPrice = ceil($payPriceTax / (1 + $taxRate / 100));
						$payTax = $payPriceTax - $payPrice;
						$paycontractdetail['payPrice'] = $payPrice;
						$paycontractdetail['payTax'] = $payTax;
					}

					// 支払予定日・支払確定日を設定する
					// 仲介手数料
					if($code['codeDetail'] === 'salesBrokerageFee') {
						$paycontractdetail['contractFixDay'] = $sale['salesBrokerageFeePayDay'];    // 支払確定日<-仲介手数料支払日
					}
					// 業務委託料
					else if($code['codeDetail'] === 'salesOutsourcingPrice') {
						$paycontractdetail['contractFixDay'] = $sale['salesOutsourcingPricePayDay'];// 支払確定日<-業務委託料支払日
					}

					// 支払金額（税込）が0の場合、削除扱いとする
					if($payPriceTax == 0) {
						setDelete($paycontractdetail, $userId);
					}
					else $cntDetail++;

					$paycontractdetail->save();
				}
			}
			// 明細件数が0の場合、削除扱いとする
			if($cntDetail == 0) {
				setDelete($paycontract, $userId);
				$paycontract->save();
			}
		}
	}
}

/**
 * 支払契約情報から仕入契約情報連携
 */
function setContractByPay($paycontract, $userId) {
	// 仕入契約情報PID・契約カテゴリが未設定の場合、対象外
	if($paycontract['contractInfoPid'] == 0 || $paycontract['contractCategory'] == '') return;
	// 契約カテゴリが01:売買代金の場合、対象外
	if($paycontract['contractCategory'] === '01') return;

	// 仕入契約情報を取得
	$contract = ORM::for_table(TBLCONTRACTINFO)->findOne($paycontract['contractInfoPid']);
	if(!empty($contract)) {
		// コード<-SYS1+契約カテゴリの値
		$code = 'SYS1' . $paycontract['contractCategory'];
		// コード名称マスタを取得
		$codename = ORM::for_table(TBLCODENAMEMST)->findOne($code);
		if(!empty($codename)) {
			// コードマスタを取得
			$codes = ORM::for_table(TBLCODE)->where('code', $codename['code'])->order_by_asc('displayOrder')->findArray();
			if(sizeof($codes) > 0) {
				$hasData = false;
				foreach($codes as $code) {
					// 支払契約詳細情報を取得
					$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $paycontract['pid'])->where('paymentCode', $code['name'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
					if(sizeof($details) > 0) {
						foreach($details as $detail) {
							// 仲介手数料
							if($code['codeDetail'] === 'intermediaryPrice') {
								$contract['intermediaryPrice'] = $detail['payPriceTax'];         // 仲介手数料<-支払金額（税込）
								$contract['intermediaryPricePayDay'] = $detail['contractFixDay'];// 仲介手数料支払日<-支払確定日
								$hasData = true;
							}
							// 業務委託料
							else if($code['codeDetail'] === 'outsourcingPrice') {
								$contract['outsourcingPrice'] = $detail['payPriceTax'];         // 業務委託料<-支払金額（税込）
								$contract['outsourcingPricePayDay'] = $detail['contractFixDay'];// 業務委託料支払日<-支払確定日
								$hasData = true;
							}
						}
					}
				}
				if($hasData) {
					setUpdate($contract, $userId);
					$contract->save();
				}
			}
		}
	}
}

/**
 * 支払契約情報から物件売契約情報連携
 */
function setSaleByPay($paycontract, $userId) {
	// 物件売契約情報PID・契約カテゴリが未設定の場合、対象外
	if($paycontract['bukkenSalesInfoPid'] == 0 || $paycontract['contractCategory'] == '') return;

	// 物件売契約情報を取得
	$sale = ORM::for_table(TBLBUKKENSALESINFO)->findOne($paycontract['bukkenSalesInfoPid']);
	if(!empty($sale)) {
		// コード<-SYS2+契約カテゴリの値
		$code = 'SYS2' . $paycontract['contractCategory'];
		// コード名称マスタを取得
		$codename = ORM::for_table(TBLCODENAMEMST)->findOne($code);
		if(!empty($codename)) {
			// コードマスタを取得
			$codes = ORM::for_table(TBLCODE)->where('code', $codename['code'])->order_by_asc('displayOrder')->findArray();
			if(sizeof($codes) > 0) {
				$hasData = false;
				foreach($codes as $code) {
					// 支払契約詳細情報を取得
					$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $paycontract['pid'])->where('paymentCode', $code['name'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
					if(sizeof($details) > 0) {
						foreach($details as $detail) {
							// 仲介手数料
							if($code['codeDetail'] === 'salesBrokerageFee') {
								$sale['salesBrokerageFee'] = $detail['payPriceTax'];             // 仲介手数料<-支払金額（税込）
								$sale['salesBrokerageFeePayDay'] = $detail['contractFixDay'];    // 仲介手数料支払日<-支払確定日
								$hasData = true;
							}
							// 業務委託料
							else if($code['codeDetail'] === 'salesOutsourcingPrice') {
								$sale['salesOutsourcingPrice'] = $detail['payPriceTax'];         // 業務委託料<-支払金額（税込）
								$sale['salesOutsourcingPricePayDay'] = $detail['contractFixDay'];// 業務委託料支払日<-支払確定日
								$hasData = true;
							}
						}
					}
				}
				if($hasData) {
					setUpdate($sale, $userId);
					$sale->save();
				}
			}
		}
	}
}
// 20210728 E_Add

// 20230917 S_Add
/**
 * 賃貸情報を取得
 * @param pid 賃貸情報Pid
 */
function getRental($pid,$getIts = false) {
	$ren = ORM::for_table(TBLRENTALINFO)->findOne($pid)->asArray();

	if (!$getIts) {
		//賃貸契約・賃貸入金取得
		$ren['rentalContracts'] = getRentalContracts($pid, null);

		//賃貸入金
		$ren['rentalReceives'] = getRentalReceives($pid);

		// 20231027 S_Add
		// 立ち退き一覧
		$evictions = getEvictionInfos(null,null, $ren['pid']);
		$ren['evictionsMap'] = $evictions;
		// 20231027 E_Add
	}
	return $ren;
}

/**
 * 賃貸契約・入金を取得
 * @param rentalInfoPid 賃貸情報Pid
 */
function getRentalContract_Receives($rentalInfoPid) {
	$data = new stdClass();
	$data->rentalContracts = getRentalContracts($rentalInfoPid, null);
	$data->rentalReceives = getRentalReceives($rentalInfoPid);
	return $data;
}

/**
 * 賃貸入金を取得
 * @param rentalInfoPid 賃貸情報Pid
 */
function getRentalReceives($rentalInfoPid) {
	$results = array();

	$receiveMonths = array();
	$rentalReceives = ORM::for_table(TBLRENTALRECEIVE)->where('rentalInfoPid', $rentalInfoPid)->where_null('deleteDate')->order_by_asc('receiveMonth')->findArray();
	if (isset($rentalReceives)) {
		foreach ($rentalReceives as $rev) {
			if (in_array($rev['receiveMonth'], $receiveMonths) == false) {
				$receiveMonths[] = $rev['receiveMonth'];
			}
		}

		foreach ($receiveMonths as $m) {
			$obj = new stdClass();
			$obj->receiveMonth = $m;
			$obj->receiveFlgGroup = '0';//未選択
			$objDetails = array();
			foreach ($rentalReceives as $rev) {
				if ($rev['receiveMonth'] == $m) {
					$objDetails[] = $rev;
				}
			}
			$obj->details = $objDetails;
			$results[] = $obj;
		}
	}
	return $results;
}
/**
 * 契約の賃貸一覧を取得
 * @param contractInfoPid 契約情報PID
 */
function getRentalsForContract($contractInfoPid) {
	$query = ORM::for_table(TBLRENTALINFO)
	->table_alias('p1')
	->select('p1.*')
	->select_expr('(select count(p2.pid) from tblrentalcontract p2 WHERE p2.deleteDate is null and p2.rentalInfoPid = p1.pid)', 'countContract')
	->where_null('p1.deleteDate');
	
	$query = $query->where('p1.contractInfoPid', $contractInfoPid);
	return $query->order_by_desc('p1.pid')->findArray();
}

/**
 * 賃貸契約取得
 * @param rentalInfoPid 賃貸情報
 * @param rentalContractPid 賃貸契約
 */
function getRentalContracts($rentalInfoPid, $rentalContractPid) {
	$queryRC = ORM::for_table(TBLRENTALCONTRACT)
	->table_alias('p1')
	->select('p1.*')
	->select('p2.roomNo')
	->select('p2.borrowerName')
	->select('p2.rentPrice', 'rentPriceRefMap')// 20231010 Add
	->left_outer_join(TBLRESIDENTINFO, array('p1.residentInfoPid', '=', 'p2.pid'), 'p2')
	->where_null('p1.deleteDate')
	->where_null('p2.deleteDate');
	
	if (isset($rentalContractPid) && $rentalContractPid > 0) {
		$results = $queryRC->findOne($rentalContractPid)->asArray();
	}
	else {
		$queryRC = $queryRC->where('p1.rentalInfoPid', $rentalInfoPid);
		$results = $queryRC->order_by_asc('p1.pid')->findArray();
		// 20231101 S_Add
		if(isset($results)){
			foreach ($results as &$renCon) {
				$evic = getEvic($renCon);
				$renCon['roomRentExemptionStartDateEvicMap'] = isset($evic) ? $evic->roomRentExemptionStartDate : '';
			}
		}
		// 20231101 E_Add
	}	
	return $results;
}

// 20231016 S_Add
/**
 * 賃貸契約取得(出力用)
 * @param rentalInfoPid 賃貸情報
 */
function getRentalContractsForExport($rentalInfoPid) {
	$queryRC = ORM::for_table(TBLRENTALCONTRACT)
	->table_alias('p1')
	->select('p1.*')
	->select('p2.roomNo')
	->select('p2.borrowerName')
	->select('p2.rentPrice', 'rentPriceRefMap')// 20231010 Add
	->select('p3.address', 'l_addressMap')
	// ->select('p3.structure', 'l_structureMap')// 20231019 S_Delete
	->select('p4.contractFormNumber', 'contractFormNumberMap')
	->left_outer_join(TBLRESIDENTINFO, array('p1.residentInfoPid', '=', 'p2.pid'), 'p2')
	->left_outer_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p3.pid'), 'p3')
	->left_outer_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p4.pid'), 'p4')
	->where_null('p1.deleteDate')
	->where_null('p2.deleteDate');
	
	$queryRC = $queryRC->where('p1.rentalInfoPid', $rentalInfoPid);
	$results = $queryRC->order_by_asc('p1.pid')->findArray();
	return $results;
}
// 20231016 E_Add

/**
 * 立退きを取得
 * @param contractInfoPid 契約情報Pid
 * @param evictionInfoPid 立退き情報Pid
 */
// 20231027 S_Update
// function getEvictionInfos($contractInfoPid, $evictionInfoPid) {
function getEvictionInfos($contractInfoPid, $evictionInfoPid, $rentalInfoPid = null) {
// 20231027 E_Update
	$queryRC = ORM::for_table(TBLEVICTIONINFO)
	->table_alias('p1')
	->distinct()
	->select('p1.*')
	->select('p2.roomNo')
	->select('p2.borrowerName')
	->select('p3.apartmentName')
	->inner_join(TBLRESIDENTINFO, array('p1.residentInfoPid', '=', 'p2.pid'), 'p2')
	->inner_join(TBLRENTALINFO, array('p1.rentalInfoPid', '=', 'p3.pid'), 'p3')
	->where_null('p1.deleteDate')
	->where_null('p2.deleteDate')
	->where_null('p3.deleteDate');
	
	if (isset($evictionInfoPid) && $evictionInfoPid > 0) {
		$results = $queryRC->findOne($evictionInfoPid)->asArray();
	}
	// 20231027 S_Add
	else if (isset($rentalInfoPid) && $rentalInfoPid > 0) {
		$queryRC = $queryRC->where('p1.rentalInfoPid', $rentalInfoPid);
		$results = $queryRC->order_by_asc('p1.pid')->findArray();
	}
	// 20231027 E_Add
	else {
		$queryRC = $queryRC->where('p1.contractInfoPid', $contractInfoPid);
		$results = $queryRC->order_by_asc('p1.pid')->findArray();
	}	
	return $results;
}

/**
 * 各月の日数
 */
function getDayInMonth($datestring) {
	if (isset($datestring) && $datestring != '') {
		$date = strtotime($datestring);
		return date("t", $date);
	}
	return null;
}

/**
 * 部署コードを取得
 */
function getDepament($tempLandInfoPid)
{
	$query = ORM::for_table(TBLTEMPLANDINFO)
		->select('department')
		->where('pid', $tempLandInfoPid)->find_one();
	return $query->department;
}

function createReceiveContract($renCon)
{
	$obj = new stdClass();
	$obj->rentalContractPid = $renCon->pid; //賃貸契約PID
	$obj->tempLandInfoPid = $renCon->tempLandInfoPid; //土地情報PID
	$obj->depCode = getDepament($renCon->tempLandInfoPid); //部署コード
	$obj->contractInfoPid = $renCon->contractInfoPid; //仕入契約情報PID

	return $obj;
}

/**
 * 入金契約詳細情報を作成
 * ren:賃貸情報
 * revCon:入金契約情報
 * renCon:賃貸契約情報
 * renRec:賃貸入金情報
 */
function createReceiveContractDetail($ren, $revCon, $renCon, $renRec)
{
	// 20231010 S_Add
	$receivePrice = $renRec->receivePrice;
	$price_Tax = getPrice_Tax($renRec->receiveCode, $renRec->receiveDay, $receivePrice);
	// 20231010 E_Add

	$obj = new stdClass();
	$obj->rentalReceivePid = $renRec->pid; //賃貸入金PID
	$obj->receiveContractPid = $revCon->pid; //入金契約情報PID
	$obj->tempLandInfoPid = $renRec->tempLandInfoPid; //土地情報PID

	$obj->banktransferPid = $ren->bankPid; //振込銀行マスタPID
	$obj->banktransferName = $renCon->banktransferName; //振込名義人
	$obj->banktransferNameKana = $renCon->banktransferNameKana; //振込名義人カナ

	$obj->receiveCode = $renRec->receiveCode; //入金コード

	// 20231010 S_Update
	// if ($renCon->rentPriceTax != null && $renRec->receivePrice != null) {
	// 	$obj->receivePrice = $renRec->receivePrice - $renCon->rentPriceTax; //入金金額（税別）
	// } else {
	// 	$obj->receivePrice = $renRec->receivePrice; //入金金額（税別）
	// }
	// $obj->receiveTax = $renCon->rentPriceTax; //消費税
	// $obj->receivePriceTax = $renRec->receivePrice; //入金金額（税込）
	$obj->receivePrice = $price_Tax->price; //入金金額（税別）
	$obj->receiveTax = $price_Tax->tax; //消費税
	$obj->receivePriceTax = $receivePrice; //入金金額（税込）
	// 20231010 E_Update
	
	$obj->contractFixDay = $renRec->receiveDay; //入金確定日
	$obj->receiveMethod = $renCon->paymentMethod; //入金方法

	//契約者
	$contractSellerInfoPids = array();
	$sellers = searchSellerName($ren->contractInfoPid);
	if ($sellers != null) {
		foreach ($sellers as $seller) {
			$contractSellerInfoPids[] = $seller['pid'];
		}
	}
	$obj->contractor = implode(',', $contractSellerInfoPids);

	return $obj;
}

/**
 * 契約の所有者名検索
 */
// 20231010 S_Update
// function searchSellerName($contractInfoPid)
function searchSellerName($contractInfoPid, $isGetMore = false)
// 20231010 E_Update
{
	// 20231010 S_Add
	$cons = array();
	$cons[] = array('p1.contractInfoPid' => $contractInfoPid);
	if($isGetMore){
		$codes = ORM::for_table(TBLCODE)->where('code', 'SYS401')->select('codeDetail')->findArray();
		if(sizeof($codes) > 0) {
			foreach ($codes as $code) {
				$cons[] = array('pid' => $code['codeDetail']);
			}
		}
	}
	// 20231010 E_Add

	$query = ORM::for_table(TBLCONTRACTSELLERINFO)
		->table_alias('p1')
		->select('p1.pid')
		->select('p1.contractorName')
		// 20231010 S_Update
		// ->where('p1.contractInfoPid', $contractInfoPid)
		->where_any_is($cons)
		// 20231010 E_Update
		->where_null('p1.deleteDate');

	return $query->order_by_asc('pid')->find_array();
}
// 20230917 E_Add

// 20231010 S_Add
/**
 * 入金月取得
 */
function getReceiveMonths($dateStrFrom, $dateStrTo) {
	$arr = array();

	$dateFrom = new DateTime($dateStrFrom);
	$dateCheck = $dateFrom->format('Ym');
	$arr[] = $dateCheck;
	$dateTo = new DateTime($dateStrTo);

	// $interval = $dateFrom->diff($dateTo);
	$limit = $dateTo->format('Ym');

	while ($dateCheck < $limit) {
		$dateCheck = date('Ym', strtotime("+1 months", strtotime($dateCheck . '01')));
		$arr[] = $dateCheck;
	};
	return $arr;
}

// 20231027 S_Update
// function createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $loanPeriodEndDate) {
function createRentalReceives($rentalCT, $rentPrice, $ownershipRelocationDate, $evic) {
// 20231027 E_Update
	$objs = array();

	// 登録日
	$createDate = $rentalCT->createDate;

	// 支払いサイト
	$usance = $rentalCT->usance;
	if (!isset($usance) || $usance == '') {
		$usance = '1';// 1:翌月、2:翌々月
	}

	// 支払日
	$paymentDay = $rentalCT->paymentDay;
	if (!isset($paymentDay) || $paymentDay == '' || $paymentDay == '0') {
		$paymentDay = '31';
	}

	if (strlen($paymentDay) == 1) {
		$paymentDay = '0' . $paymentDay;
	}

	// 賃貸契約開始日
	$loanPeriodStartDate = $ownershipRelocationDate;
	if (!isset($loanPeriodStartDate) || empty($loanPeriodStartDate)) {
		$loanPeriodStartDate = date('Ymd', strtotime($createDate));
	}

	// 賃貸契約終了日
	// 20231106 S_Add
	$loanPeriodEndDate = $rentalCT->loanPeriodEndDate;
	
	// 明渡日YYMM
	$surrenderDateYYMM = null;

	if($evic != null){
		// 明渡予定日
		if(isset($evic->surrenderScheduledDate) && !empty($evic->surrenderScheduledDate)){
			$loanPeriodEndDate = $evic->surrenderScheduledDate;
		}
		// 明渡日
		if(isset($evic->surrenderDate) && !empty($evic->surrenderDate)){
			$surrenderDateYYMM = substr($evic->surrenderDate, 0, 6);

			if(!isset($evic->surrenderScheduledDate) || empty($evic->surrenderScheduledDate) || $evic->surrenderDate > $evic->surrenderScheduledDate){
				$loanPeriodEndDate = $evic->surrenderDate;
			}
		}
	}	
	if (!isset($loanPeriodEndDate) || empty($loanPeriodEndDate)) {
		// 一年間
		$loanPeriodEndDate = date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
	}
	// 20231106 E_Add

	// 入金月
	$receiveMonths = getReceiveMonths($loanPeriodStartDate, $loanPeriodEndDate);

	// 賃貸入金作成
	foreach ($receiveMonths as $receiveMonth) {
		$obj = new stdClass();
		$obj->rentalInfoPid = $rentalCT->rentalInfoPid;
		$obj->rentalContractPid = $rentalCT->pid;
		$obj->contractInfoPid = $rentalCT->contractInfoPid;
		$obj->locationInfoPid = $rentalCT->locationInfoPid;
		$obj->tempLandInfoPid = $rentalCT->tempLandInfoPid;
		$obj->receivePrice = $rentPrice;
		$obj->receiveCode = $rentalCT->receiveCode;
		$obj->receiveFlg = '0';
		$obj->receiveMonth = $receiveMonth;

		// 仮入金日
		// 20231027 S_Update
		// $dateTemp = date('Ymd', strtotime("+" . $usance . " months", strtotime($receiveMonth . '01')));
		$dateTemp = date('Ymd', strtotime("-" . $usance . " months", strtotime($receiveMonth . '01')));
		// 20231027 E_Update

		// 各月の日数
		$dayMaxInMonth = getDayInMonth($dateTemp);

		// まず、日まで設定
		$dayTemp = $paymentDay;

		// 各月の日数は日までより小さい場合
		if (intval($dayMaxInMonth) < intval($paymentDay)) {
			$dayTemp = $dayMaxInMonth;
		}

		if (strlen($dayTemp) == 1) {
			$dayTemp = '0' . $dayTemp;
		}

		// 入金日
		// 20231027 S_Update
		// $obj->receiveDay = substr($dateTemp, 0, 6) . $dayTemp;
		if(isset($surrenderDateYYMM)){
			// 明渡日以降の場合、入金日を設定しない
			if(substr($dateTemp, 0, 6) > $surrenderDateYYMM){
				$obj->receiveFlg = '2';
			}
			else{
				$obj->receiveDay = substr($dateTemp, 0, 6) . $dayTemp;
			}
		}
		else{
			$obj->receiveDay = substr($dateTemp, 0, 6) . $dayTemp;
		}
		// 20231027 E_Update

		$objs[] = $obj;
	}
	return $objs;
}
/**
 * 賃貸情報の所有権移転日を取得
 */
function getOwnershipRelocationDate($rentalInfoPid)
{
	$query = ORM::for_table(TBLRENTALINFO)
		->select('ownershipRelocationDate')
		->where('pid', $rentalInfoPid)->find_one();
	return $query->ownershipRelocationDate;
}

/**
 * 入居者情報の賃料等を取得
 */
function getRentPrice($residentInfoPid)
{
	$query = ORM::for_table(TBLRESIDENTINFO)
		->select('rentPrice')
		->where('pid', $residentInfoPid)->find_one();
	return $query->rentPrice;
}

/**
 * 入金種別の存在をチェック
 */
function isExistsReceiveType($receiveCode)
{
	$query = ORM::for_table(TBLRECEIVETYPE)->where_null('deleteDate');

	$query = $query->where('receiveCode', $receiveCode);
	return $query->count() > 0;
}

/**
 * 消費税・税別の計算
 */
function getPrice_Tax($receiveCode, $taxEffectiveDay, $receivePriceTax)
{
	$obj = new stdClass();

	$price = null;
	$tax = null;
	if ($receiveCode != null && $receiveCode != '' && isExistsReceiveType($receiveCode)) {
		$taxRate = 0;

		if ($taxEffectiveDay != null && $taxEffectiveDay != '') {
			$taxRate = ORM::for_table(TBLTAX)->where_lte('effectiveDay', $taxEffectiveDay)->max('taxRate');
			$taxRate = intval($taxRate);
		}
		if ($receivePriceTax != null) {
			$price = ceil($receivePriceTax / (1 + $taxRate / 100));
			$tax = $receivePriceTax - $price;
		}
	}
	$obj->price = $price;
	$obj->tax = $tax;

	return $obj;
}
// 20231010 E_Add
// 20231016 S_Add
/**
 * 日付計算
 */
function calDiffDate($dateStrFrom, $dateStrTo) {

	$dateFrom = new DateTime($dateStrFrom);
	$dateTo = new DateTime($dateStrTo);

	$interval = $dateFrom->diff($dateTo);
	return $interval;
}

/**
 * 振込名を取得
 */
function getBankName($bankPid)
{
	if(isset($bankPid)){
		$query = ORM::for_table(TBLBANK)
			->select('displayName')
			->where('pid', $bankPid)->find_one();
		return $query->displayName;
	}
	return "";
}
// 20231016 E_Add

// 20231019 S_Add
function getLocationPidByBuilding($locationInfoPid){
	$query = ORM::for_table(TBLLOCATIONINFO)
	->select('pid')
	->where_null('deleteDate')
	->where('ridgePid', $locationInfoPid);

	return $query->find_one()->pid;
}
function getLocationInfoForReport($locationInfoPid){
	$query = ORM::for_table(TBLLOCATIONINFO)
	->select('structure', 'l_structureMap');

	return $query->find_one($locationInfoPid);
}
// 20231019 E_Add

// 20231027 S_Add
/**
 * 立ち退き情報を取得
 */
function getEvic($renCon){
	$isObject = is_object($renCon);// 20231116 Add

	$loanPeriodStartDate = getLoanPeriodStartDate($renCon);
	$loanPeriodEndDate = getLoanPeriodEndDate($renCon);

	$query = ORM::for_table(TBLEVICTIONINFO)
	->table_alias('p1')
	->select('p1.*')
	->where_null('p1.deleteDate');
	// 20231116 S_Update
	// $query = $query->where('p1.rentalInfoPid', $renCon->rentalInfoPid);
	// $query = $query->where('p1.residentInfoPid', $renCon->residentInfoPid);
	if($isObject){
		$query = $query->where('p1.rentalInfoPid', $renCon->rentalInfoPid);
		$query = $query->where('p1.residentInfoPid', $renCon->residentInfoPid);
	}
	else{
		$query = $query->where('p1.rentalInfoPid', $renCon['rentalInfoPid']);
		$query = $query->where('p1.residentInfoPid', $renCon['residentInfoPid']);
	}
	// 20231116 E_Update

	$query = $query->where_raw("(p1.surrenderScheduledDate BETWEEN '" . $loanPeriodStartDate . "' AND '" . $loanPeriodEndDate . "')");
	
	$obj = $query->order_by_desc('p1.pid')->find_one();
	if(!isset($obj) || !($obj->pid > 0)){
		$query = ORM::for_table(TBLEVICTIONINFO)
		->table_alias('p1')
		->select('p1.*')
		->where_null('p1.deleteDate');
		// 20231116 S_Update
		// $query = $query->where('p1.rentalInfoPid', $renCon->rentalInfoPid);
		// $query = $query->where('p1.residentInfoPid', $renCon->residentInfoPid);
		if($isObject){
			$query = $query->where('p1.rentalInfoPid', $renCon->rentalInfoPid);
			$query = $query->where('p1.residentInfoPid', $renCon->residentInfoPid);
		}
		else{
			$query = $query->where('p1.rentalInfoPid', $renCon['rentalInfoPid']);
			$query = $query->where('p1.residentInfoPid', $renCon['residentInfoPid']);
		}
		// 20231116 E_Update
		$query = $query->where_raw("(p1.surrenderScheduledDate > '" . $loanPeriodEndDate . "')");
		
		$obj = $query->order_by_asc('p1.surrenderScheduledDate')->find_one();
	
	}
	return $obj;
}
/**
 * 立退きを紐づけるた賃貸契約を取得
 */
function getRentalContract($rentalInfoPid, $residentInfoPid, $surrenderScheduledDate){
	$query = ORM::for_table(TBLRENTALCONTRACT)
	->table_alias('p1')
	->select('p1.*')
	->where_null('p1.deleteDate');

	$query = $query->where('p1.rentalInfoPid', $rentalInfoPid);
	$query = $query->where('p1.residentInfoPid', $residentInfoPid);
	$query = $query->where_raw("('". $surrenderScheduledDate ."' BETWEEN COALESCE(loanPeriodStartDate,DATE_FORMAT(createDate, '%Y%m%d'))
	and COALESCE(loanPeriodEndDate,DATE_FORMAT(DATE_ADD(createDate, INTERVAL 11 MONTH), '%Y%m%d')))");
	
    $obj = $query->order_by_desc('p1.loanPeriodEndDate')->find_one();
	
	if(!isset($obj) || !($obj->pid > 0)){
		$query = ORM::for_table(TBLRENTALCONTRACT)
		->table_alias('p1')
		->select('p1.*')
		->where_null('p1.deleteDate');
	
		$query = $query->where('p1.rentalInfoPid', $rentalInfoPid);
		$query = $query->where('p1.residentInfoPid', $residentInfoPid);
		$query = $query->where_raw("(p1.loanPeriodEndDate < '". $surrenderScheduledDate ."')");
		
		$obj = $query->order_by_desc('p1.loanPeriodEndDate')->find_one();
	}
	return $obj;
}
// 20231027 E_Add

// 20231106 S_Add
/**
 * 賃貸契約開始日
 */
function getLoanPeriodStartDate($rentalCT){
	// 20231116 S_Update
	// if (!isset($rentalCT->loanPeriodStartDate) || empty($rentalCT->loanPeriodStartDate)) {
	// 	return date('Ymd', strtotime($rentalCT->createDate));// 賃貸契約の登録日
	// }
	// else{
	// 	return $rentalCT->loanPeriodStartDate;
	// }
	$isObject = is_object($rentalCT);

	if($isObject){
		if (!isset($rentalCT->loanPeriodStartDate) || empty($rentalCT->loanPeriodStartDate)) {
			return date('Ymd', strtotime($rentalCT->createDate));// 賃貸契約の登録日
		}
		else{
			return $rentalCT->loanPeriodStartDate;
		}
	}
	else{
		if (!isset($rentalCT['loanPeriodStartDate']) || empty($rentalCT['loanPeriodStartDate'])) {
			return date('Ymd', strtotime($rentalCT['createDate']));// 賃貸契約の登録日
		}
		else{
			return $rentalCT['loanPeriodStartDate'];
		}
	}
	// 20231116 E_Update
}
/**
 * 賃貸契約終了日
 */
function getLoanPeriodEndDate($rentalCT){
	// 20231116 S_Update
	// if (!isset($rentalCT->loanPeriodEndDate) || empty($rentalCT->loanPeriodEndDate)) {
	// 	$loanPeriodStartDate = getLoanPeriodStartDate($rentalCT);
	// 	return date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
	// }
	// else{
	// 	return $rentalCT->loanPeriodEndDate;
	// }
	$isObject = is_object($rentalCT);

	if($isObject){
		if (!isset($rentalCT->loanPeriodEndDate) || empty($rentalCT->loanPeriodEndDate)) {
			$loanPeriodStartDate = getLoanPeriodStartDate($rentalCT);
			return date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
		}
		else{
			return $rentalCT->loanPeriodEndDate;
		}
	}
	else{
		if (!isset($rentalCT['loanPeriodEndDate']) || empty($rentalCT['loanPeriodEndDate'])) {
			$loanPeriodStartDate = getLoanPeriodStartDate($rentalCT);
			return date('Ymd', strtotime("+11 months", strtotime($loanPeriodStartDate)));
		}
		else{
			return $rentalCT['loanPeriodEndDate'];
		}
	}
	// 20231116 E_Update
}
// 20231106 E_Add

// 20231110 S_Add
function exitByDuplicate(){
	echo json_encode(array('statusMap' => 'NG', 'msgMap' => '他のユーザーが登録中です。しばらく待ってから、再度登録ボタンを押してください。'));
	exit;
}
// 20231110 E_Add
?>
