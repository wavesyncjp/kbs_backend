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
	
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $pid)->where_null('deleteDate')->findArray();
	if(isset($locs)){	
		$land['locations'] = $locs;
	}
	return $land;
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

?>