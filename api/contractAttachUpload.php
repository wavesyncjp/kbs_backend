<?php
require '../header.php';
require '../util.php';

$contractInfoId = $_POST['contractInfoId'];
$attachFileType = $_POST['attachFileType'];
$createUserId = $_POST['createUserId'];
$fullPath = __DIR__ . '/../uploads/contractAttach';
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

$map = ORM::for_table(TBLCONTRACTATTACH)->create();
$map->contractInfoPid = $contractInfoId;
$map->attachFileType = $attachFileType;
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/contractAttach/' . $contractInfoId . '/' . $uniq . '/';
setInsert($map, $createUserId);
$map->save();

echo json_encode($map->asArray());

#echo $_FILES['file']['name'];

?>
