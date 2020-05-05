<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLCONTRACTTYPEFIX)->where_null('deleteDate');
if(notNull($param->promptDecideFlg)) {
    $query = $query->where('promptDecideFlg', $param->promptDecideFlg);
}
if(notNull($param->equiExchangeFlg)) {
    $query = $query->where('equiExchangeFlg', $param->equiExchangeFlg);
}
if(notNull($param->tradingType)) {
    $query = $query->where('tradingType', $param->tradingType);
}
if(notNull($param->indivisibleFlg)) {
    $query = $query->where('indivisibleFlg', $param->indivisibleFlg);
}

$query = $query->order_by_asc('displayOrder');

$lst = $query->findArray();

echo json_encode($lst);

?>