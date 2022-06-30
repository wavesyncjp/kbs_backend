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

$file = ORM::for_table(TBLINFOATTACH)->find_one($param->pid);	

$split = explode('/', $file->attachFilePath);
// 20220701 S_Update
// $dir = $fullPath . '/' . $split[sizeof($split) - 2];
$dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];// information/pid/uniq/
/*
deleteDirectory($dir);
$file->delete();
*/
setDelete($file, $param->deleteUserId);
$file->save();
// 20220701 E_Update

$result = [
	"status" => $dir,
];

echo json_encode($result);

?>
