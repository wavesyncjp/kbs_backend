<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLUSER)
			->table_alias('p1')
			->select_many('p1.userId', 'p1.loginId', 'p1.password', 'p1.userName', 'p1.userNameKana', 'p1.employeeCode', 'p1.authority', 'p1.mailAddress', 'p1.createUserId', 'p1.createDate', 'p1.updateUserId', 'p1.updateDate', 'p1.deleteUserId', 'p1.deleteDate')
			->select('ud.depCode', 'depCode')
			->select('p2.depName')
			->left_outer_join('tbluserdepartment', array('p1.userId', '=', 'ud.userId'), 'ud') // Add 20251022 テーブル名を定数にする
			->left_outer_join(TBLDEPARTMENT, array('ud.depCode', '=', 'p2.depCode'), 'p2');

$query = $query->where_null('p1.deleteDate');

if(isset($param->userId) && $param->userId !== ''){
	$query = $query->where_like('p1.userId', $param->userId.'%');
}
if(isset($param->userName) && $param->userName !== ''){
	$query = $query->where_like('p1.userName', '%'.$param->userName.'%');
}
if(isset($param->depCode) && $param->depCode !== ''){
	$query = $query->where('ud.depCode', $param->depCode)
					->where_null('ud.deleted_at');
}
if(isset($param->authority) && $param->authority !== ''){
	$query = $query->where('p1.authority', $param->authority);
}

//$deps = $query->where_not_equal('loginId', '0001')->where_not_equal('loginId', '0002')->order_by_asc('userId')->find_array();
try {
	$rows = $query->where_not_in('p1.loginId', ['0001', '0002', '0003', '0004', '0005'])->order_by_asc('p1.userId')->find_array();

	//　ユーザーごとにグループ化
	$result = [];
	foreach ($rows as $r) {
		$uid = $r['userId'];
		if (!isset($result[$uid])) {
			$result[$uid] = [
				'userId'   => $r['userId'],
				'userName' => $r['userName'],
				'authority'=> $r['authority'],
				'userNameKana'=> $r['userNameKana'],
				'password'=> $r['password'],
				'employeeCode'=> $r['employeeCode'],
				'mailAddress' => $r['mailAddress'],
				'loginId' => $r['loginId'],
				'createUserId' => $r['createUserId'],
				'createDate' => $r['createDate'],
				'updateUserId' => $r['updateUserId'],
				'updateDate' => $r['updateDate'],
				'deleteUserId' => $r['deleteUserId'],
				'deleteDate' => $r['deleteDate'],
				'departments' => [],
			];
		}

		if (!empty($r['depCode'])) {
			$result[$uid]['departments'][] = [
			'depCode' => $r['depCode'],
			'depName' => $r['depName'],
			];
		}
	}
} catch (\Exception $th) {
	echo $th->getMessage();
}

echo json_encode($result);

?>