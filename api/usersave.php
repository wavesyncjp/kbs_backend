<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$db = ORM::get_db();
$db->beginTransaction();

try {
	//更新
	if($param->updateUserId > 0){
		$info = ORM::for_table(TBLUSER)->find_one($param->userId);
		setUpdate($info, $param->updateUserId);
	}
	//登録
	else {
		$info = ORM::for_table(TBLUSER)->create();
		setInsert($info, $param->createUserId);
	}
	copyData($param, $info, array('updateUserId', 'updateDate', 'createUserId', 'createDate', 'depName', 'departments'));
	$info->save();

	// 部署情報の保存
	$depCodes = array_map(function ($department) {
			return $department->depCode;
		}, $param->departments);
	$deps = array_values(array_unique(array_filter($depCodes, function ($code) {
		return isset($code);
	})));

	if (!empty($deps)) {
		$place = [];
		$binds = [];
		foreach ($deps as $d) {
			$place[] = "(?, ?, NOW(), NOW(), NULL)";
			$binds[] = $info->userId;
			$binds[] = $d;
		}

		$sql = "INSERT INTO tbluserdepartment
			(userId, depCode, created_at, updated_at, deleted_at) VALUES ".implode(',', $place)."
			ON DUPLICATE KEY UPDATE
				deleted_at = NULL,
				updated_at = CURRENT_TIMESTAMP";
		ORM::raw_execute($sql, $binds);

		// 重複を削除
		$in = implode(',', array_fill(0, count($deps), '?'));
		$sql = "
		UPDATE tbluserdepartment
		SET deleted_at = NOW()
		WHERE userId = ?
			AND deleted_at IS NULL
			AND depCode NOT IN ($in)
		";
		ORM::raw_execute($sql, array_merge([$info->userId], $deps));
	} else {
		// 部署情報が空で送信されたときは論理削除
		ORM::raw_execute("
		UPDATE tbluserdepartment
		SET deleted_at = NOW()
		WHERE userId = ?
			AND deleted_at IS NULL
		", [$info->userId]);
	}

	$db->commit();

	$ret = ORM::for_table(TBLUSER)->findOne($info->userId)->asArray();
	echo json_encode($ret);
} catch (\Throwable $th) {
	$db->rollBack();
	echo $th->getMessage();
}
?>