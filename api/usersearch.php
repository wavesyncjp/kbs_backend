<?php

require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$query = ORM::for_table(TBLUSER)
			->table_alias('p1')
			->select_many('p1.userId', 'p1.loginId', 'p1.password', 'p1.userName', 'p1.userNameKana', 'p1.employeeCode', 'p1.authority', 'p1.mailAddress', 'p1.createUserId', 'p1.createDate', 'p1.updateUserId', 'p1.updateDate', 'p1.deleteUserId', 'p1.deleteDate')
			->select('ud.depCode', 'depCode')
			->select('p2.depName')
			->left_outer_join(TBLUSERDEPARTMENT, array('p1.userId', '=', 'ud.userId'), 'ud') // Add 20251022
			->left_outer_join(TBLDEPARTMENT, array('ud.depCode', '=', 'p2.depCode'), 'p2')
			->select_expr("CASE WHEN ud.deleted_at IS NULL THEN ud.depCode END", 'depCode')
			->where_null('p1.deleteDate');

if(isset($param->userId) && $param->userId !== ''){
	$query = $query->where_like('p1.userId', $param->userId.'%');
}
if(isset($param->userName) && $param->userName !== ''){
	$query = $query->where_like('p1.userName', '%'.$param->userName.'%');
}

if(isset($param->depCode) && $param->depCode !== ''){
	$query = $query->where_raw(
    "EXISTS (
        SELECT 1 FROM tbluserdepartment ud2
            WHERE ud2.userId = p1.userId
            AND ud2.depCode = ?
            AND ud2.deleted_at IS NULL
    )",
    [$param->depCode]
);

// 部署が設定されていなくてもユーザー情報は取得する
$query = $query->where_raw(
	"ud.deleted_at IS NULL
	OR NOT EXISTS (
			SELECT 1 FROM tbluserdepartment a
			WHERE a.userId = p1.userId AND a.deleted_at IS NULL
		)"
);

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
	echo 'error';
}

echo json_encode(array_values($result));

?>