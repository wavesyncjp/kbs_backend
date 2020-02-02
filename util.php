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
	return ret;
}

?>