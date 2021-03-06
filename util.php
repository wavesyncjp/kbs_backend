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
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->findArray();
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

	//契約者	
	$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
	if(isset($sellers)){	
		$contract['sellers'] = $sellers;
	}
	else {
		$contract['sellers'] = [];
	}	

	//地図添付
	$contractFiles = ORM::for_table(TBLCONTRACTFILE)->where('contractInfoPid', $pid)->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	if(isset($contractFiles)){
		$contract['contractFiles'] = $contractFiles;
	}

	return $contract;
}

/**
 * 
 * @param unknown $data
 */
function setDelete($data, $userId){
	$data->deleteUserId = $userId;
	$data->deleteDate = date('Y-m-d H:i:s');
}

function setInsert($data, $userId){
	$data->createUserId = $userId;
	$data->createDate = date('Y-m-d H:i:s');
	//$data->updateUserId = $userId;
	//$data->updateDate = date('Y-m-d H:i:s');
}

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

//セルに値設定
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

//ブロックコピー
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

//cellコピー
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

//cellコピー
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


function convert_jpdt($date) {
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
	return $name.$year.'.'.date_format($day, 'm.d');
}
// 20210525 S_Add
function convert_jpdt_kanji($date) {
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
	return $name.$year.'年'.date_format($day, 'm月d日');
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

?>