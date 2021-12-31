<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLINFORMATION)->where_null('deleteDate');

// 件名
if(isset($param->infoSubject) && $param->infoSubject !== ''){
	$query = $query->where_like('infoSubject', '%'.$param->infoSubject.'%');
}
// 掲載終了
if(isset($param->finishFlg) && sizeof($param->finishFlg) > 0){
	$query = $query->where_in('finishFlg', $param->finishFlg);
}
// 日付
if(isset($param->infoDate) && $param->infoDate != ''){
	$query = $query->where('infoDate', $param->infoDate);
}

if(isset($param->today) && $param->today != ''){
	$query = $query->where_lte('infoDate', now());
}

// 20211227 S_Add
// 掲示板タイプ
if(isset($param->infoType) && $param->infoType !== ''){
	$query = $query->where('infoType', $param->infoType);
}
// 承認フラグ
if(isset($param->approvalFlg) && $param->approvalFlg != ''){
	$query = $query->where('approvalFlg', $param->approvalFlg);
}
// 20211227 E_Add

$query = $query->order_by_desc('infoDate')->order_by_desc('updateDate');

if(isset($param->count) && $param->count > 0){
	$query = $query->limit($param->count);
}

$lands = $query->find_array();
echo json_encode($lands);

?>