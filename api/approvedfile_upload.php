<?php
require '../header.php';
require '../util.php';

$infoId = $_POST['infoId'];
$fullPath  = __DIR__ . '/../uploads/approval';
if(!file_exists($fullPath)){	
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath.'/'.$infoId;
if (!file_exists($dirPath)) {
	mkdir($dirPath);
}
$dirPath = $fullPath.'/'.$infoId.'/'.$uniq;
mkdir($dirPath);

$filePath = $dirPath.'/'.$fileName;

move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

$map = ORM::for_table(TBLINFORMATION)->find_one($infoId);
$map->approvalAttachFileName = $fileName;
$map->approvalAttachFilePath = 'backend/uploads/approval/'.$infoId.'/'.$uniq.'/';
$map->save();

echo json_encode($map->asArray());

?>
