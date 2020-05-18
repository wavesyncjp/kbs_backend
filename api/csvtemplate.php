<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$lst = ORM::for_table(TBLCSVINFO)->where_like('csvCode', $param->type .'%')->order_by_asc('displayOrder')->findArray();

echo json_encode($lst);

?>