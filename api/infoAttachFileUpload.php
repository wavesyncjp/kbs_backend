<?php
require '../header.php';
require '../util.php';

// 20220701 S_Update
// $infoPid = $_POST['infoPid'];
$infoId = $_POST['infoId'];
$createUserId = $_POST['createUserId'];
// 20220701 E_Update
$fullPath = __DIR__ . '/../uploads/information';
if(!file_exists($fullPath)){	
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

#$fileName = mb_convert_encoding($_FILES['file']['name'],'cp932','utf8');
$fileName = $_FILES['file']['name'];

$uniq = getGUID();
// 20220701 S_Update
/*
$dirPath = $fullPath . '/' . $infoId . '/' . $uniq;
mkdir($dirPath);
*/
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
// 20220701 E_Update

$filePath = $dirPath . '/' . $fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLINFOATTACH)->create();
$map->infoPid = $infoId;
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/information/' . $infoId . '/' . $uniq . '/';
setInsert($map, $createUserId);// 20220701 Add
$map->save();

echo json_encode($map->asArray());
#echo $_FILES['file']['name'];

?>
