<?php
require '../header.php';
require '../util.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->ids)){
	// 対象がFB出力済フラグの場合
	if($param->target == 'fbOutPutFlg') {
		$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where_null('deleteDate')->where_in('pid', $param->ids)->order_by_asc('pid')->find_array();
		if(sizeof($details) > 0) {
			foreach($details as $detail) {
				$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->findOne($detail['pid']);
				setUpdate($paycontractdetail, $param->updateUserId);

				// FB出力済フラグが1:出力済ではない場合
				if($paycontractdetail['fbOutPutFlg'] != '1') {
					$paycontractdetail['fbOutPutFlg'] = '1';         // FB出力済フラグ<-1:出力済
					$paycontractdetail['fbOutPutDate'] = date("Ymd");// FB出力日<-システム日付
					$paycontractdetail['fbOutPutTime'] = date("His");// FB出力時刻<-システム時刻
				}
				$paycontractdetail->save();
			}
		}
	}
	// 対象が出力済フラグの場合
	if($param->target == 'outPutFlg') {
		$sorts = ORM::for_table(TBLSORTING)->where_null('deleteDate')->where_in('pid', $param->ids)->order_by_asc('pid')->find_array();
		if(sizeof($sorts) > 0) {
			foreach($sorts as $sort) {
				$sorting = ORM::for_table(TBLSORTING)->findOne($sort['pid']);
				setUpdate($sorting, $param->updateUserId);

				// 出力済フラグが1:出力済ではない場合
				if($sorting['outPutFlg'] != '1') {
					$sorting['outPutFlg'] = '1';         // 出力済フラグ<-1:出力済
					$sorting['outPutDate'] = date("Ymd");// 出力日<-システム日付
					$sorting['outPutTime'] = date("His");// 出力時刻<-システム時刻
				}
				$sorting->save();
			}
		}
	}
}

?>