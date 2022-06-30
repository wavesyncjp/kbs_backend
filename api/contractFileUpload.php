<?php
require '../header.php';
require '../util.php';

$contractInfoId = $_POST['contractInfoId'];
$createUserId = $_POST['createUserId'];// 20220701 Add
$fullPath = __DIR__ . '/../uploads/contract';
if(!file_exists($fullPath)){	
	if (!mkdir($fullPath)) {
		die('NG');
	}	
}

#$fileName = mb_convert_encoding($_FILES['file']['name'],'cp932','utf8');
$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath . '/' . $contractInfoId;
if (!file_exists($dirPath)) {
	if (!mkdir($dirPath)) {
		die('NG');
	}
}
$dirPath = $dirPath . '/' . $uniq;
if (!mkdir($dirPath)) {
	die('NG');
}

$filePath = $dirPath . '/' . $fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLCONTRACTFILE)->create();
$map->contractInfoPid = $contractInfoId;
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/contract/' . $contractInfoId . '/' . $uniq . '/';
setInsert($map, $createUserId);// 20220701 Add
$map->save();

echo json_encode($map->asArray());

#echo $_FILES['file']['name'];

?>
