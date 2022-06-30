<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if($param->pid > 0) {
	// 20220701 S_Add
	// 掲示板添付ファイル
	$attachFiles = ORM::for_table(TBLINFOATTACH)->where('infoPid', $param->pid)->where_null('deleteDate')->find_many();
	if(sizeof($attachFiles) > 0) {
		foreach($attachFiles as $attachFile) {
			// $split = explode('/', $attachFile->attachFilePath);
			// $dir = $fullPath . '/' . $split[sizeof($split) - 4] . '/' . $split[sizeof($split) - 3] . '/' . $split[sizeof($split) - 2];// location/pid/uniq/
			// deleteDirectory($dir);
			setDelete($attachFile, $param->deleteUserId);
			$attachFile->save();
		}
	}
	// 20220701 E_Add

	$info = ORM::for_table(TBLINFORMATION)->find_one($param->pid);
	setDelete($info, $param->deleteUserId);
}
$info->save();

?>