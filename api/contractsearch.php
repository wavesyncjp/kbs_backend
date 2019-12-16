<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLCONTRACTINFO)->where_null('deleteDate');
if(isset($param->tempLandInfoPid) && $param->tempLandInfoPid > 0){
	$query = $query->where('tempLandInfoPid', $param->tempLandInfoPid);
}

$contracts = $query->find_array();
$ret = array();
foreach($contracts as $contract){    
	$ctRed = getContractInfo($contract['pid']);
	
	$ret[] = $ctRed;
}
echo json_encode($ret);

?>