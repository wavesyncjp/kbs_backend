<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$fullPath = __DIR__ . '/../uploads';
$fullPathLoc = $fullPath . '/location';
if(!file_exists($fullPath) || !file_exists($fullPathLoc)) {
	if(!mkdir($fullPath) || !mkdir($fullPathLoc)) {
		die('NG');
	}
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//登録
$land = ORM::for_table(TBLTEMPLANDINFO)->create();	
setInsert($land, $param->createUserId);

$maxNo = ORM::for_table(TBLTEMPLANDINFO)->where_not_equal('createUserId', '9999')->max('bukkenNo');
$maxNum = intval(ltrim($maxNo, "0")) + 1;
$nextNo = str_pad($maxNum, 6, '0', STR_PAD_LEFT);
$land->bukkenNo = $nextNo;

copyData($param, $land, array('pid', 'bukkenNo', 'locations', 'mapFiles', 'attachFiles', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
$land->save();

// 地図添付ファイル
if(isset($param->mapFiles)) {
	foreach($param->mapFiles as $mapFile) {
		$map = ORM::for_table(TBLMAPATTACH)->find_one($mapFile->pid);

		// 新フォルダ作成
		$uniq = getGUID();
		$dirPath = $fullPath . '/' . $uniq;
		if(!mkdir($dirPath)) {
			die('mkdir NG : ' + $dirPath);
		}

		// コピー元
		$file = __DIR__ . '/../../' . $map->mapFilePath . $map->mapFileName;
		if(!file_exists($file)) {
			die('file_exists NG : ' . $file);
		}
		// コピー先
		$newfile = $dirPath . '/' . $map->mapFileName;

		// ファイルコピー
		if(!copy($file, $newfile)) {
			die('copy NG : ' . $newfile);
		}

		$newMap = ORM::for_table(TBLMAPATTACH)->create();
		setInsert($newMap, $param->createUserId);
		$newMap->tempLandInfoPid = $land->pid;
		$newMap->mapFilePath = 'backend/uploads/' . $uniq . '/';
		$newMap->mapFileName = $map->mapFileName;
		$newMap->save();
	}
}

// 所在地情報
if(isset($param->locations)) {
	$locations = $param->locations;
	// pidでソート
	$sort = [];
	foreach($locations as $location) {
		$sort[] = $location->pid;
	}
	array_multisort($sort, SORT_ASC, $locations);
	

	$pidList = [];
	$newLocations = [];
	foreach($locations as $location) {
		$loc = ORM::for_table(TBLLOCATIONINFO)->create();	
		setInsert($loc, $param->createUserId);
		$loc->tempLandInfoPid = $land->pid;

		copyData($location, $loc, array('pid', 'tempLandInfoPid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate', 'attachFiles'));
		$loc->save();

		// key:oldPid,value:newPid
		$pidList[$location->pid] = $loc->pid;
		// 作成データ
		$newLocations[] = $loc;

		// 謄本添付ファイル
		if(isset($location->attachFiles)) {
			foreach($location->attachFiles as $locFile) {

				// 新フォルダ作成
				$uniq = getGUID();
				$dirPath = $fullPathLoc . '/' . $uniq;
				if(!mkdir($dirPath)) {
					die('mkdir NG : ' + $dirPath);
				}

				// コピー元
				$file = __DIR__ . '/../../' . $locFile->attachFilePath . $locFile->attachFileName;
				if(!file_exists($file)) {
					die('file_exists NG : ' . $file);
				}
				// コピー先
				$newfile = $dirPath . '/' . $locFile->attachFileName;

				// ファイルコピー
				if(!copy($file, $newfile)) {
					die('copy NG : ' . $newfile);
				}

				$newLocFile = ORM::for_table(TBLLOCATIONATTACH)->create();
				setInsert($newLocFile, $param->createUserId);
				$newLocFile->locationInfoPid = $loc->pid;
				$newLocFile->attachFilePath = 'backend/uploads/location/' . $uniq . '/';
				$newLocFile->attachFileName = $locFile->attachFileName;
				$newLocFile->save();
			}
		}
	}

	foreach($newLocations as $newLocation) {
		$loc = ORM::for_table(TBLLOCATIONINFO)->find_one($newLocation->pid);

		// 一棟の建物（ridgePid）を更新
		if(isset($pidList[$loc->ridgePid])) {
			$loc->ridgePid = $pidList[$loc->ridgePid];
		}
		// 底地（bottomLandPid）を更新
		if(isset($pidList[$loc->bottomLandPid])) {
			$loc->bottomLandPid = $pidList[$loc->bottomLandPid];
		}
		$loc->save();
	}
}

echo json_encode(getLandInfo($land->pid));

?>