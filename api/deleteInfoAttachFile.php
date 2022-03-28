<?php
require '../header.php';
require '../util.php';

$fullPath = __DIR__ . '/../uploads/information';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$file = ORM::for_table(TBLINFOATTACH)->find_one($param->pid);	

$split = explode('/', $file->attachFilePath);
$dir = $fullPath . '/' . $split[sizeof($split) - 2];
deleteDirectory($dir);
$file->delete();

$result = [
	"status" => $dir,
];

echo json_encode($result);

?>
