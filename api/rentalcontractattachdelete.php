<?php
require '../header.php';
require '../util.php';

$fullPath = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$file = ORM::for_table(TBLRENTALCONTRACTATTACH)->find_one($param->pid);

$split = explode('/', $file->rentalContractFilePath);
$dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];

setDelete($file, $param->deleteUserId);
$file->save();

$result = [
	"status" => $dir,
];

echo json_encode($result);
?>
