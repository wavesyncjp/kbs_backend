<?php
require '../header.php';
require '../util.php';

$infoId = $_POST['infoId'];
$fullPath  = __DIR__ . '/../uploads';
if(!file_exists($fullPath)){	
	if (!mkdir($fullPath)) {
		die('NG');
	}	
}

#$fileName = mb_convert_encoding($_FILES['file']['name'],'cp932','utf8');
$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath.'/'.$uniq;
mkdir($dirPath);

$filePath = $dirPath.'/'.$fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLINFORMATION)->find_one($infoId);
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/'.$uniq.'/';
$map->save();
	

echo json_encode($map->asArray());

#echo $_FILES['file']['name'];


?>