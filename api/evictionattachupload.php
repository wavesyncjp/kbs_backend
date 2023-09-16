<?php
require '../header.php';
require '../util.php';

$evictionInfoPid = $_POST['evictionInfoPid'];
$createUserId = $_POST['createUserId'];
$fullPath = __DIR__ . '/../uploads/evictionAttach';
if (!file_exists($fullPath)) {
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath . '/' . $evictionInfoPid;
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

$map = ORM::for_table(TBLEVICTIONINFOATTACH)->create();
$map->evictionInfoPid = $evictionInfoPid;
$map->evictionInfoFileName = $fileName;
$map->eictionInfoFilePath = 'backend/uploads/evictionAttach/' . $evictionInfoPid . '/' . $uniq . '/';
setInsert($map, $createUserId);
$map->save();

echo json_encode($map->asArray());
?>
