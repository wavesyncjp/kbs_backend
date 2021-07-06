<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLKANJYO)->where_null('deleteDate');

// 勘定科目コード
if(isset($param->kanjyoCode) && $param->kanjyoCode !== ''){
	$query = $query->where('kanjyoCode', $param->kanjyoCode);
}

// 勘定科目名称
if(isset($param->kanjyoName_Like) && $param->kanjyoName_Like !== ''){
	$query = $query->where_like('kanjyoName', '%'.$param->kanjyoName_LIKE.'%');
}

// 取引先名
if(isset($param->supplierName_Like) && $param->supplierName_Like !== ''){
	$query = $query->where_like('supplierName', '%'.$param->supplierName_Like.'%');
}

//課税フラグ
if(isset($param->taxFlg) && $param->taxFlg !== ''){
	$query = $query->where('taxFlg',$param->taxFlg);
}

$kanjyos = $query->order_by_asc('kanjyoCode')->find_array();
echo json_encode($kanjyos);

?>
