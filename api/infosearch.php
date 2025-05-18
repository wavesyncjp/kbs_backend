<?php

require '../header.php';
require '../util.php';// 20250502 Add

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

// 20230308 S_Update
// $query = ORM::for_table(TBLINFORMATION)->where_null('deleteDate');
$isSortType = isset($param->sortType) && $param->sortType === '1';

$query = ORM::for_table(TBLINFORMATION)->table_alias('p1');

if($isSortType) {
	$query = $query->select('p1.*')
			->select('p2.displayOrder')
			->left_outer_join(TBLCODE, ' p2.code = 038 and p2.codeDetail = p1.approvalFlg ', 'p2')
			->where_null('p1.deleteDate');
}
else {
	$query = $query->where_null('deleteDate');
}
// 20230308 E_Update

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

// 20230306 S_Add
// 詳細（本文）
if(isset($param->infoDetail_Like) && $param->infoDetail_Like != ''){
	$query = $query->where_like('infoDetail', '%'. $param->infoDetail_Like . '%');
}
// 20230306 E_Add

// 20250502 S_Add
if(isset($param->infoType) && $param->infoType != '0')
{
	$query = getQueryExpertTempland($param, $query, 'p1.templandInfoPid');
}
// 20250502 E_Add

// 20230308 S_Update
// $query = $query->order_by_desc('infoDate')->order_by_desc('updateDate');
if($isSortType) {
	$query = $query->order_by_asc('infoSubjectType')->order_by_asc('displayOrder');
}
else {
	$query = $query->order_by_desc('infoDate')->order_by_desc('p1.updateDate');
}
// 20230308 E_Update

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
		// 20230927 S_Add
		$approvalFilesMap = ORM::for_table(TBLINFOAPPROVALATTACH)->where('infoPid', $info['pid'])->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
		if(sizeof($approvalFilesMap) > 0) {
			$info['approvalFilesMap'] = $approvalFilesMap;
		}
		// 20230927 E_Add
		$infoList[] = $info;
	}
	$infos = $infoList;
}
// 20220329 E_Add
echo json_encode($infos);

?>