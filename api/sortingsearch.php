<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLSORTING)
			->table_alias('p1')
			->select('p1.*')
			->select('p2.bukkenNo', 'bukkenNo')
			->select('p2.contractBukkenNo', 'contractBukkenNo')
			->select('p2.bukkenName', 'bukkenName')
			->inner_join(TBLTEMPLANDINFO, array('p1.tempLandInfoPid', '=', 'p2.pid'), 'p2');

$query = $query->where_null('p1.deleteDate');

// 取引日付
if(isset($param->transactionDate)  && $param->transactionDate !== ''){
	$query = $query->where('p1.transactionDate', $param->transactionDate);
}

// 入出金区分
if(isset($param->contractType)  && $param->contractType !== ''){
	$query = $query->where('p1.contractType', $param->contractType);
}

// 支払種別
if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where('p1.paymentCode', $param->paymentCode);
}

// 貸方勘定科目
if(isset($param->creditorKanjyoCode)  && $param->creditorKanjyoCode !== ''){
	$query = $query->where('p1.creditorKanjyoCode', $param->creditorKanjyoCode);
}

// 借方勘定科目
if(isset($param->debtorKanjyoCode)  && $param->debtorKanjyoCode !== ''){
	$query = $query->where('p1.debtorKanjyoCode', $param->debtorKanjyoCode);
}

// 物件番号
if(isset($param->bukkenNo)  && $param->bukkenNo !== ''){
	$query = $query->where('p2.bukkenNo', $param->bukkenNo);
}

// 契約物件番号
if(isset($param->contractBukkenNo)  && $param->contractBukkenNo !== ''){
	$query = $query->where('p2.contractBukkenNo', $param->contractBukkenNo);
}

// 物件名称(部分一致)
if(isset($param->bukkenName_Like)  && $param->bukkenName_Like !== ''){
	$query = $query->where_Like('p2.bukkenName', '%'.$param->bukkenName_Like.'%');
}

// 出力済フラグ
if(isset($param->outPutFlg)  && $param->outPutFlg !== ''){
	$query = $query->where('p1.outPutFlg', $param->outPutFlg);
}

$detail = $query->order_by_desc('p1.pid')->find_array();
echo json_encode($detail);

?>