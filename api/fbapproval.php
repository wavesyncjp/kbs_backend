<?php
require '../header.php';
require '../util.php';

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

if(isset($param->ids)){
	$details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where_null('deleteDate')->where_in('pid', $param->ids)->order_by_asc('pid')->find_array();
	if(sizeof($details) > 0) {
		foreach($details as $detail) {
			$paycontractdetail = ORM::for_table(TBLPAYCONTRACTDETAIL)->findOne($detail['pid']);
			setUpdate($paycontractdetail, $param->updateUserId);

			// FB承認フラグが1:承認済ではない場合
			if($paycontractdetail['fbApprovalFlg'] != '1') {
				$paycontractdetail['fbApprovalFlg'] = '1';// 1:承認済
			}
			// FB承認フラグが1:承認済の場合
			else {
				$paycontractdetail['fbApprovalFlg'] = '0';// 0:未承認
			}
			$paycontractdetail->save();
		}
	}
}

?>