<?php
require '../header.php';
require '../util.php';

$locationInfoId = $_POST['locationInfoId'];
$fullPath  = __DIR__ . '/../uploads/location';
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

$map = ORM::for_table(TBLLOCATIONATTACH)->create();
$map->locationInfoPid = $locationInfoId;
$map->attachFileName = $fileName;
$map->attachFilePath = 'backend/uploads/location/'.$uniq.'/';	
$map->save();

echo json_encode($map->asArray());

#echo $_FILES['file']['name'];


?>