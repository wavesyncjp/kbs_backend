<?php
require '../header.php';
require '../util.php';

$infoId = $_POST['infoId'];
$createUserId = $_POST['createUserId'];// 20220701 Add
// 20211227 S_Update
// $fullPath  = __DIR__ . '/../uploads';
$fullPath  = __DIR__ . '/../uploads/information';
// 20211227 E_Update
if (!file_exists($fullPath)) {
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

#$fileName = mb_convert_encoding($_FILES['file']['name'],'cp932','utf8');
$fileName = $_FILES['file']['name'];

$uniq = getGUID();
// 20211227 S_Update
// $dirPath = $fullPath.'/'.$uniq;
$dirPath = $fullPath . '/' . $infoId;
if (!file_exists($dirPath)) {
	if (!mkdir($dirPath)) {
		die('NG');
	}
}
$dirPath = $dirPath . '/' . $uniq;
if (!mkdir($dirPath)) {
	die('NG');
}
// 20211227 E_Update
mkdir($dirPath);

$filePath = $dirPath.'/'.$fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLINFORMATION)->find_one($infoId);
$map->attachFileName = $fileName;
// 20211227 S_Update
// $map->attachFilePath = 'backend/uploads/'.$uniq.'/';
$map->attachFilePath = 'backend/uploads/information/' . $infoId . '/' . $uniq . '/';
// 20211227 E_Update
setUpdate($map, $createUserId);// 20220708 Add
$map->save();

echo json_encode($map->asArray());
#echo $_FILES['file']['name'];

?>
