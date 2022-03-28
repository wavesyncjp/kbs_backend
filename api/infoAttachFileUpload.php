<?php
require '../header.php';
require '../util.php';

$infoPid = $_POST['infoPid'];
$fullPath = __DIR__ . '/../uploads/information';
if(!file_exists($fullPath)){	
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

#$fileName = mb_convert_encoding($_FILES['file']['name'],'cp932','utf8');
$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath . '/' . $infoId . '/' . $uniq;
mkdir($dirPath);

$filePath = $dirPath . '/' . $fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLINFOATTACH)->create();
$map->infoPid = $infoPid;
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/information/' . $infoId . '/' . $uniq . '/';
$map->save();

echo json_encode($map->asArray());
#echo $_FILES['file']['name'];

?>
