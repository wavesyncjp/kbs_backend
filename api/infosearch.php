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
// 20230213 S_Add
// clct掲示板タイプ
if(isset($param->clctInfoType) && $param->clctInfoType != ''){
	$query = $query->where_in('infoType', $param->clctInfoType);
}
// 20230213 E_Add
// 承認フラグ
if(isset($param->approvalFlg) && $param->approvalFlg != ''){
	$query = $query->where('approvalFlg', $param->approvalFlg);
}
// 20211227 E_Add

$query = $query->order_by_desc('infoDate')->order_by_desc('updateDate');

if(isset($param->count) && $param->count > 0){
	$query = $query->limit($param->count);
}

$infos = $query->find_array();
// 20220329 S_Add
if(sizeof($infos) > 0) {
	$infoList = [];
	foreach($infos as $info) {
		$attachFiles = ORM::for_table(TBLINFOATTACH)->where('infoPid', $info['pid'])->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
		if(sizeof($attachFiles) > 0) {
			$info['attachFiles'] = $attachFiles;
		}
		$infoList[] = $info;
	}
	$infos = $infoList;
}
// 20220329 E_Add
echo json_encode($infos);

?>