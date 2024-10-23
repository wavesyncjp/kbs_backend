<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

// 20241022 S_Update
// $query = ORM::for_table(TBLRECEIVETYPE)->where_null('deleteDate');
$query = ORM::for_table(TBLRECEIVETYPE);

if(!isset($param->isAllData)){
	$query = $query->where_null('deleteDate');
}
// 20241022 E_Update

// 支払コード
if(isset($param->receiveCode) && $param->receiveCode !== ''){
	$query = $query->where_like('receiveCode', $param->receiveCode.'%');
}
// 支払名称
if(isset($param->receiveName) && $param->receiveName !== ''){
	$query = $query->where_like('receiveName', '%'.$param->receiveName.'%');
}
// 大分類フラグ
if(isset($param->categoryFlg)  && $param->categoryFlg !== ''){
	$query = $query->where('categoryFlg', $param->categoryFlg);
}
$ret = $query->order_by_asc('receiveCode')->find_array();
echo json_encode($ret);

?>