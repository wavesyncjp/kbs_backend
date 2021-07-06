<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLKANJYOFIX)->where_null('deleteDate');

// 支払コード
if(isset($param->paymentCode) && $param->paymentCode !== ''){
	$query = $query->where('paymentCode', $param->paymentCode);
}

// 借方勘定科目コード
if(isset($param->debtorKanjyoCode) && $param->debtorKanjyoCode !== ''){
	$query = $query->where('debtorKanjyoCode', $param->debtorKanjyoCode);
}

// 借方補助科目コード
if(isset($param->debtorKanjyoDetailCode_Like) && $param->debtorKanjyoDetailCode_Like !== ''){
	$query = $query->where_Like('debtorKanjyoDetailCode', $param->debtorKanjyoDetailCode_Like.'%');
}

// 貸方勘定科目コード
if(isset($param->creditorKanjyoCode) && $param->creditorKanjyoCode !== ''){
	$query = $query->where('creditorKanjyoCode', $param->creditorKanjyoCode);
}

// 貸方補助科目コード
if(isset($param->creditorKanjyoDetailCode_Like) && $param->creditorKanjyoDetailCode_Like !== ''){
	$query = $query->where_Like('creditorKanjyoDetailCode', $param->creditorKanjyoDetailCode_Like.'%');
}

//振替フラグ
if(isset($param->transFlg) && $param->transFlg !== ''){
	$query = $query->where('transFlg',$param->transFlg);
}

$kanjyoFixs = $query->order_by_asc('pid')->find_array();
echo json_encode($kanjyoFixs);

?>
