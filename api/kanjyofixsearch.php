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

// 借方補助科目名称
if(isset($param->debtorKanjyoDetailName_Like) && $param->debtorKanjyoDetailName_Like !== ''){
	$query = $query->where_Like('debtorKanjyoDetailName', $param->debtorKanjyoDetailName_Like.'%');
}

// 貸方勘定科目コード
if(isset($param->creditorKanjyoCode) && $param->creditorKanjyoCode !== ''){
	$query = $query->where('creditorKanjyoCode', $param->creditorKanjyoCode);
}

// 貸方補助科目名称
if(isset($param->creditorKanjyoDetailName_Like) && $param->creditorKanjyoDetailName_Like !== ''){
	$query = $query->where_Like('creditorKanjyoDetailName', $param->creditorKanjyoDetailName_Like.'%');
}

// 20240802 S_Update
// //振替フラグ
// if(isset($param->transFlg) && $param->transFlg !== ''){
// 	$query = $query->where('transFlg',$param->transFlg);
// }
//振替フラグ
if(isset($param->contractType) && $param->contractType !== ''){
	$query = $query->where('contractType',$param->contractType);
}
// 20240802 E_Update

$kanjyoFixs = $query->order_by_asc('pid')->find_array();
echo json_encode($kanjyoFixs);

?>
