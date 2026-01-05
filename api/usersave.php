<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

// 20251214 S_Update
/*
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
copyData($param, $info, array('updateUserId', 'updateDate', 'createUserId', 'createDate', 'depName'));
$info->save();

$ret = ORM::for_table(TBLUSER)->findOne($info->userId)->asArray();
echo json_encode($ret);
*/
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

	// 20251210 S_Add
	// if (!empty($deps)) {
	// 	$place = [];
	// 	$binds = [];
	// 	foreach ($deps as $d) {
	// 		$place[] = "(?, ?, NOW(), NOW(), NULL)";
	// 		$binds[] = $info->userId;
	// 		$binds[] = $d;
	// 	}

	// 	$sql = "INSERT INTO tbluserdepartment
	// 		(userId, depCode, created_at, updated_at, deleteDate) VALUES ".implode(',', $place)."
	// 		ON DUPLICATE KEY UPDATE
	// 			deleteDate = NULL,
	// 			updated_at = CURRENT_TIMESTAMP";
	// 	ORM::raw_execute($sql, $binds);

	// 	// 重複を削除
	// 	$in = implode(',', array_fill(0, count($deps), '?'));
	// 	$sql = "
	// 	UPDATE tbluserdepartment
	// 	SET deleteDate = NOW()
	// 	WHERE userId = ?
	// 		AND deleteDate IS NULL
	// 		AND depCode NOT IN ($in)
	// 	";
	// 	ORM::raw_execute($sql, array_merge([$info->userId], $deps));
	// } else {
	// 	// 部署情報が空で送信されたときは論理削除
	// 	ORM::raw_execute("
	// 	UPDATE tbluserdepartment
	// 	SET deleteDate = NOW()
	// 	WHERE userId = ?
	// 		AND deleteDate IS NULL
	// 	", [$info->userId]);
	// }

	$userDepartmentUserId = $param->updateUserId ?? $param->createUserId;
    if (!empty($deps)) {

        // upsert
        foreach ($deps as $depCode) {
            $row = ORM::for_table(TBLUSERDEPARTMENT)
                ->where('userId', $info->userId)
                ->where('depCode', $depCode)
                ->find_one();

            if (!$row) {
                $row = ORM::for_table(TBLUSERDEPARTMENT)->create();
                $row->userId  = $info->userId;
                $row->depCode = $depCode;
                setInsert($row, $userDepartmentUserId);
            } else {
				$row->deleteDate = null;
				$row->deleteUserId = null;
            	setUpdate($row, $userDepartmentUserId);
			}
            $row->save();
        }

        // 送信された部署コードに含まれない部署は論理削除
        $rowsToDelete = ORM::for_table(TBLUSERDEPARTMENT)
            ->where('userId', $info->userId)
            ->where_null('deleteDate')
            ->where_not_in('depCode', $deps)
            ->find_many();

        foreach ($rowsToDelete as $row) {
            setDelete($row, $userDepartmentUserId);
            $row->save();
        }

    } else {
        // 送信された部署コードが空の場合、全て論理削除
        $rowsToDeleteAll = ORM::for_table(TBLUSERDEPARTMENT)
            ->where('userId', $info->userId)
            ->where_null('deleteDate')
            ->find_many();

        foreach ($rowsToDeleteAll as $row) {
            setDelete($row, $userDepartmentUserId);
            $row->save();
        }
    }

	$db->commit();
	// 20251210 E_Add

	$ret = ORM::for_table(TBLUSER)->findOne($info->userId)->asArray();
	echo json_encode($ret);
} catch (\Throwable $th) {
	$db->rollBack();
	echo $th->getMessage();
}
// 20251214 E_Update
?>