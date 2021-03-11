<?php
require '../header.php';
require '../util.php';

$fullPath  = __DIR__ . '/../uploads/location';// 20210312 Add

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = $param->userId;
$pid = $param->pid;

$count = ORM::for_table(TBLCONTRACTDETAILINFO)->where('locationInfoPid', $pid)->where_null('deleteDate')->count();
if($count > 0) {	
	echo json_encode(array('status' => 'NG'));
	exit;
}

//共有者情報
$sharers = ORM::for_table(TBLSHARERINFO)->where('locationInfoPid', $pid)->where_null('deleteDate')->find_many();
if(isset($sharers)) {
	foreach($sharers as $sharer) {
		setDelete($sharer, $userId);
		$sharer->delete();
	}
}
// 20210312 S_Add
//謄本添付ファイル
$attachFiles = ORM::for_table(TBLLOCATIONATTACH)->where('locationInfoPid', $pid)->where_null('deleteDate')->find_many();
if(isset($attachFiles)) {
	foreach($attachFiles as $attachFile) {
		$split = explode('/', $attachFile->attachFilePath);
		$dir = $fullPath.'/'.$split[sizeof($split) - 2];
		deleteDirectory($dir);
		setDelete($attachFile, $userId);
		$attachFile->delete();
	}
}
// 20210312 E_Add
$loc = ORM::for_table(TBLLOCATIONINFO)->find_one($pid);
if(isset($loc)) {
	setDelete($loc, $userId);
	$loc->delete();
}

echo json_encode(array('status' => 'OK'));

?>