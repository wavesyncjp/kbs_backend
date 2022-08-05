<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$fullPath = __DIR__ . '/../uploads';
$fullPathLoc = $fullPath . '/location';
$fullPathContract = $fullPath . '/contract';// 20220521 Add
if(!file_exists($fullPath) || !file_exists($fullPathLoc) || !file_exists($fullPathContract)) {
	if(!mkdir($fullPath) || !mkdir($fullPathLoc) || !mkdir($fullPathContract)) {
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
			continue;// 20220805 Add
//			die('file_exists NG : ' . $file);
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

// 20220521 S_Add
$pidList = [];
$sharerPidList = [];
// 20220521 E_Add

// 所在地情報
if(isset($param->locations)) {
	$locations = $param->locations;
	// pidでソート
	$sort = [];
	foreach($locations as $location) {
		$sort[] = $location->pid;
	}
	array_multisort($sort, SORT_ASC, $locations);
	

	// $pidList = [];// 20220521 Delete
	$newLocations = [];
	$newBottomLands = [];// 20210616 Add
	foreach($locations as $location) {
		$loc = ORM::for_table(TBLLOCATIONINFO)->create();	
		setInsert($loc, $param->createUserId);
		$loc->tempLandInfoPid = $land->pid;

		// 20220614 S_Update
//		copyData($location, $loc, array('pid', 'tempLandInfoPid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate', 'attachFiles'));
		copyData($location, $loc, array('pid', 'tempLandInfoPid', 'contractDetail', 'bukkenName', 'floorAreaRatio', 'dependTypeMap', 'sharers', 'delSharers', 'createUserId', 'createDate', 'updateUserId', 'updateDate', 'attachFiles', 'bottomLands', 'delBottomLands', 'residents', 'delResidents'));
		// 20220614 E_Update
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
				$dirPath = $fullPathLoc . '/' . $loc->pid;
				if(!file_exists($dirPath) && !mkdir($dirPath)) {
					die('mkdir NG : ' + $dirPath);
				}
				$dirPath = $dirPath . '/' . $uniq;
				if(!mkdir($dirPath)) {
					die('mkdir NG : ' + $dirPath);
				}

				// コピー元
				$file = __DIR__ . '/../../' . $locFile->attachFilePath . $locFile->attachFileName;
				if(!file_exists($file)) {
					continue;// 20220805 Add
//					die('file_exists NG : ' . $file);
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
				$newLocFile->attachFilePath = 'backend/uploads/location/' . $loc->pid . '/' . $uniq . '/';
				$newLocFile->attachFileName = $locFile->attachFileName;
				$newLocFile->save();
			}
		}

		// 20210616 S_Add
		// 共有者情報
		if(isset($location->sharers)) {
			foreach($location->sharers as $sharer) {
				$newSharer = ORM::for_table(TBLSHARERINFO)->create();
				setInsert($newSharer, $param->createUserId);
				copyData($sharer, $newSharer, array('pid', 'tempLandInfoPid','locationInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
				$newSharer->tempLandInfoPid = $land->pid;
				$newSharer->locationInfoPid = $loc->pid;
				$newSharer->save();

				// 20220521 S_Add
				// key:oldPid,value:newPid
				$sharerPidList[$sharer->pid] = $newSharer->pid;
				// 20220521 E_Add
			}
		}
		// 底地情報
		if(isset($location->bottomLands)) {
			foreach($location->bottomLands as $bottomLand) {
				$newBottomLand = ORM::for_table(TBLBOTTOMLANDINFO)->create();
				setInsert($newBottomLand, $param->createUserId);
				copyData($bottomLand, $newBottomLand, array('pid', 'tempLandInfoPid','locationInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
				$newBottomLand->tempLandInfoPid = $land->pid;
				$newBottomLand->locationInfoPid = $loc->pid;
				$newBottomLand->save();

				// 作成データ
				$newBottomLands[] = $newBottomLand;
			}
		}
		// 20210616 E_Add
		// 20220614 S_Add
		// 入居者情報
		if(isset($location->residents)) {
			foreach($location->residents as $resident) {
				$newResident = ORM::for_table(TBLRESIDENTINFO)->create();
				setInsert($newResident, $param->createUserId);
				copyData($resident, $newResident, array('pid', 'tempLandInfoPid','locationInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate'));
				$newResident->tempLandInfoPid = $land->pid;
				$newResident->locationInfoPid = $loc->pid;
				$newResident->save();
			}
		}
		// 20220614 E_Add
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
	// 20210616 S_Add
	foreach($newBottomLands as $newBottomLand) {
		$bottomLand = ORM::for_table(TBLBOTTOMLANDINFO)->find_one($newBottomLand->pid);
		// 底地（bottomLandPid）を更新
		if(isset($pidList[$bottomLand->bottomLandPid])) {
			$bottomLand->bottomLandPid = $pidList[$bottomLand->bottomLandPid];
		}
		$bottomLand->save();
	}
	// 20210616 E_Add
}

// 20220521 S_Add
// 仕入契約情報
$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
if(sizeof($contracts) > 0) {
	foreach($contracts as $contract) {
		$newContract = ORM::for_table(TBLCONTRACTINFO)->create();
		// カラムをコピー
		$notCopy = array('pid', 'contractInfoPid', 'locationInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate');
		foreach($contract as $key => $value) {
			if(in_array($key, $notCopy)) continue;
			$newContract[$key] = $value;
		}
		setInsert($newContract, $param->createUserId);
		$newContract->tempLandInfoPid = $land->pid;
		$newContract->save();

		// 仕入契約詳細情報
		$details = ORM::for_table(TBLCONTRACTDETAILINFO)->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
		if(sizeof($details) > 0) {
			foreach($details as $detail) {
				$newDetail = ORM::for_table(TBLCONTRACTDETAILINFO)->create();
				// カラムをコピー
				$notCopy = array('pid', 'contractInfoPid', 'locationInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate');
				foreach($detail as $key => $value) {
					if(in_array($key, $notCopy)) continue;
					$newDetail[$key] = $value;
				}
				setInsert($newDetail, $param->createUserId);
				$newDetail->contractInfoPid = $newContract->pid;
				// 所在地情報PID（locationInfoPid）を更新
				if(isset($pidList[$detail['locationInfoPid']])) {
					$newDetail->locationInfoPid = $pidList[$detail['locationInfoPid']];
				}
				$newDetail->save();

				// 仕入契約登記人情報
				$registrants = ORM::for_table(TBLCONTRACTREGISTRANT)->where('contractInfoPid', $contract['pid'])->where('contractDetailInfoPid', $detail['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
				if(isset($registrants)) {
					foreach($registrants as $registrant) {
						$newRegistrant = ORM::for_table(TBLCONTRACTREGISTRANT)->create();
						// カラムをコピー
						$notCopy = array('pid', 'contractInfoPid', 'contractDetailInfoPid', 'sharerInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate');
						foreach($registrant as $key => $value) {
							if(in_array($key, $notCopy)) continue;
							$newRegistrant[$key] = $value;
						}
						setInsert($newRegistrant, $param->createUserId);
						$newRegistrant->contractInfoPid = $newContract['pid'];
						$newRegistrant->contractDetailInfoPid = $newDetail['pid'];
						// 共有者情報PID（sharerInfoPid）を更新
						if(isset($sharerPidList[$registrant['sharerInfoPid']])) {
							$newRegistrant->sharerInfoPid = $sharerPidList[$registrant['sharerInfoPid']];
						}
						$newRegistrant->save();
					}
				}
			}
		}

		// 仕入契約者情報
		$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
		if(sizeof($sellers) > 0) {
			foreach($sellers as $seller) {
				$newSeller = ORM::for_table(TBLCONTRACTSELLERINFO)->create();
				// カラムをコピー
				$notCopy = array('pid', 'contractInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate');
				foreach($seller as $key => $value) {
					if(in_array($key, $notCopy)) continue;
					$newSeller[$key] = $value;
				}
				setInsert($newSeller, $param->createUserId);
				$newSeller->contractInfoPid = $newContract['pid'];
				$newSeller->save();
			}
		}

		// 計画地の公図
		$contractFiles = ORM::for_table(TBLCONTRACTFILE)->where('contractInfoPid', $contract['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
		if(sizeof($contractFiles) > 0) {
			foreach($contractFiles as $contractFile) {
				$newContractFile = ORM::for_table(TBLCONTRACTFILE)->create();
				// カラムをコピー
				$notCopy = array('pid', 'contractInfoPid', 'createUserId', 'createDate', 'updateUserId', 'updateDate');
				foreach($contractFile as $key => $value) {
					if(in_array($key, $notCopy)) continue;
					$newContractFile[$key] = $value;
				}
				setInsert($newContractFile, $param->createUserId);
				$newContractFile->contractInfoPid = $newContract['pid'];

				// 新フォルダ作成
				$uniq = getGUID();
				$dirPath = $fullPathContract . '/' . $newContract['pid'];
				if(!file_exists($dirPath) && !mkdir($dirPath)) {
					die('mkdir NG : ' + $dirPath);
				}
				$dirPath = $dirPath . '/' . $uniq;
				if(!mkdir($dirPath)) {
					die('mkdir NG : ' + $dirPath);
				}

				// コピー元
				$file = __DIR__ . '/../../' . $contractFile['attachFilePath'] . $contractFile['attachFileName'];
				if(!file_exists($file)) {
					continue;// 20220805 Add
//					die('file_exists NG : ' . $file);
				}
				// コピー先
				$newfile = $dirPath . '/' . $contractFile['attachFileName'];

				// ファイルコピー
				if(!copy($file, $newfile)) {
					die('copy NG : ' . $newfile);
				}

				$newContractFile->attachFilePath = 'backend/uploads/contract/' . $newContract['pid'] . '/' . $uniq . '/';
				$newContractFile->attachFileName = $contractFile['attachFileName'];
				$newContractFile->save();
			}
		}
	}
}
// 20220521 E_Add

echo json_encode(getLandInfo($land->pid));

?>