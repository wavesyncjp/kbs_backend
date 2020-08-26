<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

//更新
if(isset($param->updateUserId) && $param->updateUserId > 0){
	$plan = ORM::for_table(TBLPLANHISTORY)->find_one($param->pid);
	setUpdate($plan, $param->updateUserId);
}

//登録
else {
	$plan = ORM::for_table(TBLPLANHISTORY)->create();	
	setInsert($plan, $param->createUserId);
}
/*画面に入力項目があって（.ts）planのカラムにないものを('')で除外。
'updateUserId', 'updateDate', 'createUserId', 'createDate'は上でセットしているので */
copyData($param, $plan, array('pid','createUserId','createDate','updateUserId','updateDate','details','rent','rentdetails',
//日付MAP
'createDayMap','startDayMap','upperWingDayMap','completionDayMap','scheduledDayMap','createHistoryDayMap',
//数値MAP
'afterCityPlanTaxMap','afterFixedTaxMap','afterTaxationCityMap','afterTaxationMap','buildAreaMap',
'buildInterestMap','buildLoanMap','buildPeriodMap','buildValuationMap','buysellUnitsMap','cityPlanTaxBuildMap','cityPlanTaxLandMap','createHistoryDayMap',
'entranceMap','fixedTaxBuildMap','fixedTaxLandMap','groundMap','landEvaluationMap','landInterestMap',
'landLoanMap','landPeriodMap','mechanicalMap','parkingIndoorMap','parkingMap','parkingOutdoorMap',
'salesAreaMap','siteAreaBuyMap','siteAreaCheckMap','taxationCityMap','taxationMap','totalAreaMap','totalUnitsMap','underAreaMap','undergroundMap'

));
$plan->save();

//事業収支詳細
//detailhistory
if(isset($param->details)){
	foreach ($param->details as $detail){
		
		$detailSave = ORM::for_table(TBLPLANDETAILHISTORY)->create();
				
		copyData($detail, $detailSave, array('pid','updateUserId','updateDate','createUserId','createDate','deleteUserId'
		,'burdenDaysMap','commissionRateMap','complePriceMonthMap','dismantlingMonthMap'
		,'priceMap','priceTaxMap','rentMap','routePriceMap','totalMonthsMap','unitPriceMap','valuationMap'));		

		$detailSave->planHistoryPid = $plan->pid;
		$detailSave->createUserId = $plan->createUserId;
		$detailSave->createDate = $plan->createDate;

		if($plan->tempLandInfoPid > 0){
			$detailSave->tempLandInfoPid = $plan->tempLandInfoPid;
		}
		$detailSave->save();
		
	}
}

//rentrollhistory
if(isset($param->rent)) {
	$rent = $param->rent;
	
	$rentSave = ORM::for_table(TBLPLANRENTROLLHISTORY)->create();
		
	copyData($rent, $rentSave, array('pid','updateUserId','updateDate','createUserId','createDate','deleteUserId'
	,'commonFeeMap','monthlyOtherIncomeMap','salesExpense1AMap','salesExpense1BMap','salesExpense1CMap','salesExpense1DMap'
	,'salesExpense2AMap','salesExpense2BMap','salesExpense2CMap','salesExpense2DMap'
	,'salesExpense3AMap','salesExpense3BMap','salesExpense3CMap','salesExpense3DMap'
	,'tsuboUnitPriceAMap','tsuboUnitPriceBMap','tsuboUnitPriceCMap','tsuboUnitPriceDMap'));	

	$rentSave->planHistoryPid = $plan->pid;
	$rentSave->createUserId = $plan->createUserId;
	$rentSave->createDate = $plan->createDate;
	if($plan->tempLandInfoPid > 0){

	
		$rentSave->tempLandInfoPid = $plan->tempLandInfoPid;
	}
	$rentSave->save();
	$param->planRentRollPid = $rentSave->pid;
}


//rentdetailhistory
if(isset($param->rentdetails)){
	foreach ($param->rentdetails as $rentdetail){
		
		$rentdetailSave = ORM::for_table(TBLPLANRENTROLLDETAILHISTORY)->create();
		
		copyData($rentdetail, $rentdetailSave, array('pid','updateUserId','updateDate','createUserId','createDate','deleteUserId','rentUnitPriceMap','securityDepositMap','spaceMap'));

		$rentdetailSave->planHistoryPid = $plan->pid;
		$rentdetailSave->createUserId = $plan->createUserId;
		$rentdetailSave->createDate = $plan->createDate;
		if($plan->tempLandInfoPid > 0){
			$rentdetailSave->tempLandInfoPid = $plan->tempLandInfoPid;
		}
		$rentdetailSave->planRentRollPid = $param->planRentRollPid;
		$rentdetailSave->save();
	}
}


$plan = getPlanInfoHistory($plan->pid);
echo json_encode($param );

?>