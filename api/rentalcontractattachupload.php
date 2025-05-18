<?php
require '../header.php';
require '../util.php';

$rentalContractPid = $_POST['rentalContractPid'];
$createUserId = $_POST['createUserId'];
$fullPath = __DIR__ . '/../uploads/rentalContractAttach';
if (!file_exists($fullPath)) {
	if (!mkdir($fullPath)) {
		die('NG');
	}
}

$fileName = $_FILES['file']['name'];

$uniq = getGUID();
$dirPath = $fullPath . '/' . $rentalContractPid;
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

$map = ORM::for_table(TBLRENTALCONTRACTATTACH)->create();
$map->rentalContractPid = $rentalContractPid;
$map->rentalContractFileName = $fileName;
$map->rentalContractFilePath = 'backend/uploads/rentalContractAttach/' . $rentalContractPid . '/' . $uniq . '/';
setInsert($map, $createUserId);
$map->save();

echo json_encode($map->asArray());
?>
