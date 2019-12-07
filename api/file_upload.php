<?php
require '../header.php';
require '../util.php';

$isAttach = $_POST['isAttach'];
$bukkenId = $_POST['bukkenId'];
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

if($isAttach == '1'){
	$map = ORM::for_table('tblfileattach')->create();
	$map->tempLandInfoPid = $bukkenId;
	$map->attachFileName = $fileName;
	$map->attachFilePath = 'backend/uploads/'.$uniq.'/';
	$map->attachFileRemark = $_POST['comment'];
	$map->save();
	
}
else {
	$map = ORM::for_table('tblmapattach')->create();
	$map->tempLandInfoPid = $bukkenId;
	$map->mapFileName = $fileName;
	$map->mapFilePath = 'backend/uploads/'.$uniq.'/';
	$map->save();
	
}
echo json_encode($map->asArray());

#echo $_FILES['file']['name'];


?>