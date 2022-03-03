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
	$attachFiles = ORM::for_table(TBLFILEATTACH)->where('tempLandInfoPid', $pid)->order_by_desc('updateDate')->findArray();
	if(isset($attachFiles)){
		$land['attachFiles'] = $attachFiles;
	}
	$locList = [];
	// 20220303 S_Update
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->findArray();
	// $locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('blockNumber')->order_by_asc('buildingNumber')->order_by_asc('address')->findArray();
	// 20220303 E_Update
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

	$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $pid)->order_by_asc('pid')->findArray();
	if(isset($sales)){
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

// 20211021 S_Update
//function convert_jpdt($date) {
function convert_jpdt($date, $format = 'm.d') {
// 20211021 E_Update
	if(!isset($date) || $date == '') return '';
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
	// 20211021 S_Update
//	return $name.$year.'.'.date_format($day, 'm.d');
	return $name.$year.'.'.date_format($day, $format);
	// 20211021 E_Update
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
					$paycontractdetail['payPriceTax'] = $contract[$code['codeDetail']];// 支払金額（税込）
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
	if(sizeof($contract) > 0) {
		// コード<-SYS1+契約カテゴリの値
		$code = 'SYS1' . $paycontract['contractCategory'];
		// コード名称マスタを取得
		$codename = ORM::for_table(TBLCODENAMEMST)->findOne($code);
		if(sizeof($codename) > 0) {
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
	if(sizeof($sale) > 0) {
		// コード<-SYS2+契約カテゴリの値
		$code = 'SYS2' . $paycontract['contractCategory'];
		// コード名称マスタを取得
		$codename = ORM::for_table(TBLCODENAMEMST)->findOne($code);
		if(sizeof($codename) > 0) {
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

?>