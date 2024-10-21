<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//更新
if($param->updateUserId > 0){
	$info = ORM::for_table(TBLKANJYOFIX)->find_one($param->pid);
	setUpdate($info, $param->updateUserId);
}
//登録
else {
	// 20240930 S_Add
	$query = ORM::for_table(TBLKANJYOFIX)
    ->where_null('deleteDate')
    ->where('paymentCode', $param->paymentCode)
    ->where('contractType', $param->contractType);

	$exists = $query->count() > 0;
	if($exists){
		echo json_encode(array('statusMap' => 'NG', 'msgMap' => '入出金区分とコードの組み合わせが既に登録されています。別の組み合わせを指定してください。'));
		exit;
	}
	// 20240930 E_Add

	$info = ORM::for_table(TBLKANJYOFIX)->create();
	$info->executionType = '000';// 20240802 Add
	setInsert($info, $param->createUserId);
}

copyData($param, $info, array('updateUserId', 'updateDate', 'createUserId', 'createDate'));
$info->save();

$ret = ORM::for_table(TBLKANJYOFIX)->findOne($info->pid)->asArray();
echo json_encode($ret);

?>
