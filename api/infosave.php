<?php
require '../header.php';
require '../util.php';
require 'sendmail.php';// 20220330 Add

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);
$isRegist = false;// 20220330 Add
$addedFileSendFlg = $param->addedFileSendFlg;// 20220519 Add

// 20220517 S_Add
// 承認フラグが1:承認済の場合
if($param->approvalFlg == '1') {
	// 承認日時<-システム日時
	$param->approvalDateTime = date('Y-m-d H:i:s');
}
// 20220517 E_Add

//更新
if($param->pid > 0){
	$info = ORM::for_table(TBLINFORMATION)->find_one($param->pid);
	setUpdate($info, $param->updateUserId);
}
//登録
else {
	$info = ORM::for_table(TBLINFORMATION)->create();
	setInsert($info, $param->createUserId);
	$isRegist = true;// 20220330 Add
}
// 20230313 S_Update
// copyData($param, $info, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'attachFiles', 'addedFileSendFlg'));
copyData($param, $info, array('pid', 'updateUserId', 'updateDate', 'createUserId', 'createDate', 'attachFiles', 'addedFileSendFlg', 'displayOrder'));
// 20230313 E_Update
$info->save();

// 20230309 S_Add
$attachFiles = $param->attachFiles;

// if($isRegist && count($attachFiles) > 0) {
if($isRegist && isset($param->attachFiles) && count($attachFiles) > 0) {
	$infoId = $info->pid;
	$fullPath = __DIR__ . '/../uploads/information';
	if(!file_exists($fullPath)) {
		if (!mkdir($fullPath)) {
			die('NG');
		}
	}
	foreach($attachFiles as $attachFile) {
		if($attachFile->pid < 1 && strpos($attachFile->attachFilePath, 'backend/uploads/') !== false) {
			$uniq = getGUID();
			$dirPath = $fullPath . '/' . $infoId;
			if (!file_exists($dirPath)) {
				if (!mkdir($dirPath)) {
					die('NG');
				}
			}

			$dirPath = $dirPath . '/' . $uniq;
			if (!mkdir($dirPath)) {
				die('NG');
			}

			$filePath = $dirPath . '/' . $attachFile->attachFileName;

			$filePathSrc = __DIR__ . '/../../' . $attachFile->attachFilePath . $attachFile->attachFileName;

			// ファイルコピー
			if(!copy($filePathSrc, $filePath)) {
				die('copy NG : ' . $filePath);
			}

			$map = ORM::for_table(TBLINFOATTACH)->create();
			$map->infoPid = $infoId;
			$map->attachFileName = $attachFile->attachFileName;
			$map->attachFilePath = 'backend/uploads/information/' . $infoId . '/' . $uniq . '/';
			setInsert($map, $param->createUserId);
			$map->save();
		}
	}
}
// 20230309 E_Add

// 20220330 S_Add
// 掲示板タイプが1:お知らせの場合
// 20230215 S_Update
// if($info['infoType'] == '1') {
// 掲示板タイプが1:お知らせ（名古屋支店）もしくは、2:お知らせ（大阪支店）の場合
if($info['infoType'] == '1' || $info['infoType'] == '2') {
// 20230215 E_Update
	$targets = [];
	// 新規登録の場合
	if($isRegist) {
		$users = ORM::for_table(TBLUSER)->table_alias('p1')->select('p1.*')
		->inner_join(TBLCODE, array('p1.userId', '=', 'p2.codeDetail'), 'p2')
		->where_not_null('p1.mailAddress')->where_null('p1.deleteDate')
		->where('p2.code', 'SYS301')->where_null('p2.deleteDate')
		->order_by_asc('displayOrder')->find_array();
		$addresses = [];
		foreach($users as $user) {
			$addresses[] = $user;
		}
		$target['mailAddress'] = $addresses;
		$target['userName'] = '関係各位';
		$target['convSubject_1'] = $info['infoSubject'];
		$target['convBody_1'] = $info['infoDetail'] != '' ? $info['infoDetail'] . '(改行)(改行)' : '';
		$targets[] = $target;

		// メール送信
		sendMail('SYS302', 'noticeEntry', $targets);
	}
	// 20220519 S_Add
	// 追加ファイル送付にチェックがある場合
	else if($addedFileSendFlg == '1')
	{
		$users = ORM::for_table(TBLUSER)->table_alias('p1')->select('p1.*')
		->inner_join(TBLCODE, array('p1.userId', '=', 'p2.codeDetail'), 'p2')
		->where_not_null('p1.mailAddress')->where_null('p1.deleteDate')
		->where('p2.code', 'SYS301')->where_null('p2.deleteDate')
		->order_by_asc('displayOrder')->find_array();
		$addresses = [];
		foreach($users as $user) {
			$addresses[] = $user;
		}
		$target['mailAddress'] = $addresses;
		$target['userName'] = '関係各位';
		$target['convSubject_1'] = $info['infoSubject'];
		$targets[] = $target;

		// メール送信
		sendMail('SYS302', 'noticeAddedFileSend', $targets);
	}
	// 20220519 E_Add
	// 承認フラグが1:承認済もしくは、5:承認済（追加書類有）の場合
	else if($info['approvalFlg'] == '1' || $info['approvalFlg'] == '5') {
		$target = ORM::for_table(TBLUSER)->find_one($info['createUserId']);
		$target['userName'] .= '様';
		$target['convSubject_1'] = $info['infoSubject'];
		$target['convBody_1'] = $info['answer'] != '' ? $info['answer'] . '(改行)(改行)' : '';
		$targets[] = $target;

		// メール送信
		sendMail('SYS302', 'noticeApproved', $targets);
	}
	// 20220519 S_Add
	// 確認フラグが1:修正ありの場合
	else if($info['confirmFlg'] == '1') {
		$target = ORM::for_table(TBLUSER)->find_one($info['createUserId']);
		$target['userName'] .= '様';
		$target['convSubject_1'] = $info['infoSubject'];
		$targets[] = $target;

		// メール送信
		sendMail('SYS302', 'noticeModifyRequest', $targets);
	}
	// 確認フラグが2:確認済の場合
	else if($info['confirmFlg'] == '2') {
		$target = ORM::for_table(TBLUSER)->find_one($info['createUserId']);
		$target['userName'] .= '様';
		$target['convSubject_1'] = $info['infoSubject'];
		$targets[] = $target;

		// メール送信
		sendMail('SYS302', 'noticeConfirmed', $targets);
	}
	// 20220519 E_Add
}
// 20220330 E_Add

$ret = ORM::for_table(TBLINFORMATION)->findOne($info->pid)->asArray();
echo json_encode($ret);

?>