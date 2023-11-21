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

//ファイル添付
if ($param->isAttach){
	// $file = ORM::for_table('tblfileattach')->find_one($param->pid);
	$file = ORM::for_table(TBLFILEATTACH)->find_one($param->pid);
	
	$split = explode('/', $file->attachFilePath);
	// 20220701 S_Update
	// $dir = $fullPath.'/'.$split[sizeof($split) - 2];
	$dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];// bukken/pid/uniq/
	/*
	deleteDirectory($dir);
	$file->delete();
	*/
	setDelete($file, $param->deleteUserId);
	$file->save();
	// 20220701 E_Update
}
// 20231020 S_Add
//物件写真添付
else if ($param->isPhoto){
	$file = ORM::for_table(TBLBUKKENPHOTOATTACH)->find_one($param->pid);
	
	$split = explode('/', $file->attachFilePath);
	$dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];// bukken/pid/uniq/
	
	setDelete($file, $param->deleteUserId);
	$file->save();
}
// 20231020 E_Add
else {
	// $file = ORM::for_table('tblmapattach')->find_one($param->pid);
	$file = ORM::for_table(TBLMAPATTACH)->find_one($param->pid);
	
	$split = explode('/', $file->mapFilePath);
	// 20220701 S_Update
	// $dir = $fullPath.'/'.$split[sizeof($split) - 2];
	$dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];// map/pid/uniq/
	/*
	deleteDirectory($dir);
	$file->delete();
	*/
	setDelete($file, $param->deleteUserId);
	$file->save();
	// 20220701 E_Update
}

$result = [
	"status" => $dir,
];

echo json_encode($result);

?>
