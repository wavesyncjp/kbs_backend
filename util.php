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
            $str = str_replace('$'. $keywords[$i] . '$', $vals[$i], $str);
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
		$single = $sheet->getCell('A' . ($startPos + $blockRowCount ))->getValue();
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
			$sheet->setCellValue('A'. ($copyPos + $blockRowCount), $single);
		}

	}	

}

//cellコピー
function copyRows($sheet,$srcRow,$dstRow,$height,$width) {
    for ($row = 0; $row < $height; $row++) {
        for ($col = 0; $col < $width; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $srcRow + $row);
			$style = $sheet->getStyleByColumnAndRow($col, $srcRow + $row);						
			$dstCell = 'A' . (string)($dstRow + $row);		
            $sheet->setCellValue($dstCell, $cell->getValue());
            $sheet->duplicateStyle($style, $dstCell);
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

function getCodeTitle($lst, $codeDetail) {
	foreach($lst as $code) {
		if($code['codeDetail'] === $codeDetail){
			return $code['name'];
		}
	}
	return '';
}

function getRegistrants($details, $loc) {
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

	$lst = ORM::for_table(TBLSHARERINFO)->where_in('pid', $ids)->order_by_asc('pid')->select('sharer')->findArray();
	foreach($lst as $item) {
		$ret[] = $item['sharer'];
	}
	return $ret;
}

?>