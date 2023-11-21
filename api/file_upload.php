<?php
require '../header.php';
require '../util.php';

$isPhoto = $_POST['isPhoto'];// 20231020 Add
$isAttach = $_POST['isAttach'];
$bukkenId = $_POST['bukkenId'];
$createUserId = $_POST['createUserId'];// 20220701 Add
$fullPath  = __DIR__ . '/../uploads';
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
$dirPath = $fullPath.'/'.$uniq;
mkdir($dirPath);
*/

if($isAttach == '1') {
	$dirPath = $fullPath . '/bukken';
	if (!file_exists($dirPath)) {
		if (!mkdir($dirPath)) {
			die('NG');
		}
	}
}
// 20231020 S_Add
else if($isPhoto == '1') {
	$dirPath = $fullPath . '/bukkenPhoto';
	if (!file_exists($dirPath)) {
		if (!mkdir($dirPath)) {
			die('NG');
		}
	}
}
// 20231020 E_Add
else {
	$dirPath = $fullPath . '/map';
	if (!file_exists($dirPath)) {
		if (!mkdir($dirPath)) {
			die('NG');
		}
	}
}

$dirPath = $dirPath . '/' . $bukkenId;
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

if($isAttach == '1') {
	// $map = ORM::for_table('tblfileattach')->create();
	$map = ORM::for_table(TBLFILEATTACH)->create();
	$map->tempLandInfoPid = $bukkenId;
	$map->attachFileName = $fileName;
	// 20220701 S_Update
	// $map->attachFilePath = 'backend/uploads/'.$uniq.'/';
	$map->attachFilePath = 'backend/uploads/bukken/' . $bukkenId . '/' . $uniq . '/';
	setInsert($map, $createUserId);
	// 20220701 E_Update
	$map->attachFileRemark = $_POST['comment'];
	$map->save();
	
}
// 20231020 S_Add
else if($isPhoto == '1') {
	$map = ORM::for_table(TBLBUKKENPHOTOATTACH)->create();
	$map->tempLandInfoPid = $bukkenId;
	$map->bukkenPhotoAttachFileName = $fileName;
	$map->bukkenPhotoAttachFilePath = 'backend/uploads/bukkenPhoto/' . $bukkenId . '/' . $uniq . '/';
	setInsert($map, $createUserId);
	$map->save();
	
}
// 20231020 E_Add
else {
	// $map = ORM::for_table('tblmapattach')->create();
	$map = ORM::for_table(TBLMAPATTACH)->create();
	$map->tempLandInfoPid = $bukkenId;
	$map->mapFileName = $fileName;
	// 20220701 S_Update
	// $map->mapFilePath = 'backend/uploads/'.$uniq.'/';
	$map->mapFilePath = 'backend/uploads/map/' . $bukkenId . '/' . $uniq . '/';
	setInsert($map, $createUserId);
	// 20220701 E_Update
	$map->save();
}

echo json_encode($map->asArray());

#echo $_FILES['file']['name'];

?>
