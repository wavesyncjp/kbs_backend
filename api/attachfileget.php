<?php
require '../header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$files = getFiles($param->parentPid, $param->fileType, $param->attachFileType);

echo json_encode($files);

/**
 * 添付ファイル取得
 * $pid:物件売契約情報PID　または　仕入契約情報PID
 * $fileType:1=仕入契約添付ファイル(tblContractAttach), 2=物件売契約添付ファイル(tblBukkenSalesAttach)
 * $attachFileType:対象テーブルの添付ファイル区分
 */
function getFiles($pid,$fileType,$attachFileType){
	$files = [];
	// 仕入契約添付ファイル
	if($fileType == 1) {
		$files = ORM::for_table(TBLCONTRACTATTACH)->where('contractInfoPid', $pid)->where_in('attachFileType',explode(',', $attachFileType))->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	}
	// 物件売契約添付ファイル
	else if($fileType == 2) {
		$files = ORM::for_table(TBLBUKKENSALESATTACH)->where('bukkenSalesInfoPid', $pid)->where_in('attachFileType', explode(',', $attachFileType))->where_null('deleteDate')->order_by_desc('updateDate')->findArray();
	}
	// 20250418 S_Add
	// 賃貸契約添付ファイル
	else if($fileType == 3) {
		$files = array_map(function($file) {
			return [
				'attachFileName' => $file['rentalContractFileName'],
				'attachFilePath' => $file['rentalContractFilePath'],
			];
		}, ORM::for_table(TBLRENTALCONTRACTATTACH)
			->where('rentalContractPid', $pid)
			->where_null('deleteDate')
			->order_by_desc('updateDate')
			->findArray());
	}
	// 立退き情報添付ファイル
	else if($fileType == 4) {
		$files = array_map(function($file) {
			return [
				'attachFileName' => $file['evictionInfoFileName'],
				'attachFilePath' => $file['eictionInfoFilePath'],
			];
		}, ORM::for_table(TBLEVICTIONINFOATTACH)
			->where('evictionInfoPid', $pid)
			->where_null('deleteDate')
			->order_by_desc('updateDate')
			->findArray());
	}
	// 20250418 E_Add
	return $files;
}

?>