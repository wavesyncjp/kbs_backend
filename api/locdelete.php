<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$userId = $param->userId;


foreach ($param->pid as $pid){
	ORM::raw_execute("UPDATE tbllocationinfo SET deleteUserId = :userId, deleteDate = now() WHERE pid = :pid ", 
		array('userId' => $userId,'pid' => $pid));
	ORM::raw_execute("UPDATE tblstockcontractinfo SET deleteUserId = :userId, deleteDate = now() WHERE locationInfoPid = :pid ",
			array('userId' => $userId,'pid' => $pid));
}
echo 'OK';

?>