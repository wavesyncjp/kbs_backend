<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLBANK)->where_null('deleteDate');

// 入出金区分
if(isset($param->contractType) && $param->contractType !== ''){
	$query = $query->where('contractType', $param->contractType);
}

// 表示名称
if(isset($param->displayName) && $param->displayName !== ''){
	$query = $query->where_like('displayName', '%'.$param->displayName.'%');
}

// 銀行名
if(isset($param->bankName) && $param->bankName !== ''){
	$query = $query->where_like('bankName', '%'.$param->bankName.'%');
}

// 支店名
if(isset($param->branchName) && $param->branchName !== ''){
	$query = $query->where_like('branchName', '%'.$param->branchName.'%');
}

// 預金種目
if(isset($param->depositType) && $param->depositType !== ''){
	$query = $query->where('depositType', $param->depositType);
}

//口座番号
if(isset($param->accountNumber) && $param->accountNumber !== ''){
	$query = $query->where_like('accountNumber',$param->accountNumber.'%');
}

//口座名義
if(isset($param->accountHolder) && $param->accountHolder !== ''){
	$query = $query->where_like('accountHolder','%'.$param->accountHolder.'%');
}

$bank = $query->order_by_asc('pid')->find_array();
echo json_encode($bank);

?>
