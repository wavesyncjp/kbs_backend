<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLTEMPLANDINFO)
			->table_alias('p1')
			->distinct()
            ->select('p1.*')
            ->inner_join(TBLCONTRACTINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2');

//物件番号
if(isset($param->bukkenNo) && $param->bukkenNo !== ''){
    $query = $query->where('p1.bukkenNo', $param->bukkenNo);
}
if(isset($param->bukkenName) && $param->bukkenName !== ''){
	$query = $query->where_like('p1.bukkenName', '%'.$param->bukkenName.'%');
}
//契約番号
if(isset($param->contractNumber) && $param->contractNumber !== ''){
    $query = $query->where('p2.contractNumber', $param->contractNumber);
}

//明渡期日
if(isset($param->vacationDay) && $param->vacationDay != ''){
	$query = $query->where_raw(" TIMESTAMPDIFF(day, p2.vacationDay, '" . $param->vacationDay . "') <= 0");
}

//契約日
if(isset($param->contractDay) && $param->contractDay != ''){
	$query = $query->where_raw(" TIMESTAMPDIFF(day, p2.contractDay, '" . $param->contractDay . "') <= 0");
}

$lands = $query->find_array();
$ret = array();
foreach($lands as $land){

	// 所在地
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('address')->where_not_equal('address', '')->where_null('deleteDate')->select('address')->findOne();
	if(isset($address) ){		
		$land['remark1'] = $address['address'];		
	}
	else {
		$land['remark1'] = '';
	}

	//地番
	$address = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_not_null('blockNumber')->where_not_equal('blockNumber', '')->where_null('deleteDate')->select('blockNumber')->findOne();
	if(isset($address) && sizeof($address) > 0){
		$land['remark2'] = $address['blockNumber'];		
	}
	else {
		$land['remark2'] = '';
	}
	
	//
	$locs = ORM::for_table(TBLLOCATIONINFO)->where('tempLandInfoPid', $land['pid'])->where_null('deleteDate')->select_many('pid', 'locationType')->find_array();
	if(isset($locs) && sizeof($locs) > 0){
		$land['locations'] = $locs;		
	}
	else {
		$land['locations'] = [];
	}
    
    //契約
    $contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $land['pid'])->where_null('deleteDate')->select('pid')->find_array();
    if(isset($contracts) && sizeof($contracts) > 0){
		$arrs = array();
		foreach($contracts as $arr){
			$arrs[] = getContractInfo($arr['pid']);
		}
		$land['contracts'] = $arrs;		
	}
	else {
		$land['contracts'] = [];
    }
	
	$ret[] = $land;
}
echo json_encode($ret);

?>