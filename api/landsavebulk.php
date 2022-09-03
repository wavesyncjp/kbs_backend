<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$ret = array();

if(isset($param->pids) && sizeof($param->pids) > 0) {
	foreach($param->pids as $pid) {
		$land = ORM::for_table(TBLTEMPLANDINFO)->find_one($pid);
		setUpdate($land, $param->updateUserId);
		copyData($param, $land, array('pids', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
		$land->save();

		$ret[] = ORM::for_table(TBLTEMPLANDINFO)->findOne($land->pid)->asArray();
	}
} else {
	echo 'NO SET pids';
	exit;
}

echo json_encode($ret);

?>