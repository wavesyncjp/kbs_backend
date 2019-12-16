<?php
require '../header.php';
require '../util.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLCONTRACTDETAILINFO)
			->table_alias('p1')
            ->select('p1.locationInfoPid')
            ->inner_join(TBLCONTRACTINFO, array('p1.contractInfoPid', '=', 'p2.pid'), 'p2')
            ->where('p2.tempLandInfoPid', $param->tempLandInfoPid)            
            ->where_null('p1.deleteDate')
            ->where_null('p2.deleteDate');

$ret = $query->find_array();

$ids = array();
foreach($ret as $val){
    $ids[] = $val['locationInfoPid'];
}

echo json_encode($ids);

?>