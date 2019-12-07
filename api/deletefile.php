<?php
require '../header.php';
require '../util.php';

$fullPath  = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//ファイル添付
if ($param->isAttach){
	$file = ORM::for_table('tblfileattach')->find_one($param->pid);	
	
	$split = explode('/', $file->attachFilePath);
	$dir = $fullPath.'/'.$split[sizeof($split) - 2];
	deleteDirectory($dir);
	$file->delete();
}
else {
	$file = ORM::for_table('tblmapattach')->find_one($param->pid);			
	
	$split = explode('/', $file->mapFilePath);
	$dir = $fullPath.'/'.$split[sizeof($split) - 2];
	deleteDirectory($dir);
	$file->delete();
}

$result = [
		"status" => $dir,
];

echo json_encode($result);

?>