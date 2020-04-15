<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);


$query = ORM::for_table(TBLCONTRACTSELLERINFO)
			->table_alias('p1')
			->select('p1.*')
            ->inner_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p2.pid'), 'p2')
            ->where('p2.tempLandInfoPid', $param->tempLandInfoPid);

$sellers = $query->find_array();
echo json_encode($sellers);

?>