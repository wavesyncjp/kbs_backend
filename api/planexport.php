<?php
require("../vendor/autoload.php");
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

// 対象テーブル取得
$plan = ORM::for_table(TBLPLAN)->findOne($param->pid)->asArray();
$details = ORM::for_table(TBLPLANDETAIL)->where('planPid', $param->pid)->where_null('deleteDate')->order_by_asc('backNumber')->findArray();
$bukken = ORM::for_table(TBLTEMPLANDINFO)->where('pid', $plan['tempLandInfoPid'])->where_null('deleteDate')->findOne();
$rent = ORM::for_table(TBLPLANRENTROLL)->where('planPid', $param->pid)->where_null('deleteDate')->findOne()->asArray();
$rentDetails = ORM::for_table(TBLPLANRENTROLLDETAIL)->where('planPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/収支帳票.xlsx'; 

// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

$data = array();
// 計画情報(tblPlan)
foreach($plan as $key => $value) {
    $data[$key] = $value;
    // 着工日、上棟日、竣工日
    if(($key === 'startDay' || $key === 'upperWingDay' || $key === 'completionDay') && isset($value) && $value !== '') {
        $data[$key] = date_create($value)->format('Y/m/d');
    }
    // 当社JV比率、土地関係金利、建物関係金利 
    if(($key === 'jvRatio' || $key === 'landInterest' || $key === 'buildInterest') && isset($value) && $value !== '') {
        // 20200826 S_Update
//        $data[$key] = $value.'%';
        $data[$key] = round($value / 100, 3);
        // 20200826 E_Update
    }
    // 20201016 S_Add
    // 住宅用地の率、土地評価額、課税標準額（固定）、課税標準額（都市）、既存建物評価額
    // 固定資産税（土地）、都市計画税（土地）、固定資産税（建物）、都市計画税（建物）
    // 取得後固定資産税、取得後都市計画税、取得後課税標準額（固定）
    // 融資金額(土地)、融資金額(建物)
    if($key === 'residentialRate' || $key === 'landEvaluation' || $key === 'taxation' || $key === 'taxationCity' || $key === 'buildValuation'
        || $key === 'fixedTaxLand' || $key === 'cityPlanTaxLand' || $key === 'fixedTaxBuild' || $key === 'cityPlanTaxBuild'
        || $key === 'afterFixedTax' || $key === 'afterCityPlanTax' || $key === 'afterTaxationCity'
        || $key === 'landLoan' || $key === 'buildLoan') {
        $data[$key] = floatval($value);
    }
    // 20201016 E_Add
}
// コード値変換
// 決済期別
if(isset($plan['period']) && $plan['period'] !== '') {
    $code = ORM::for_table(TBLCODE)->where('code', '017')->where('codeDetail', $plan['period'])->where_null('deleteDate')->findOne();
    if(isset($code)) {
        $data['periodName'] = $code['name'];
    }
}
// 構造規模
if(isset($plan['structureScale']) && $plan['structureScale'] !== '') {
    $code = ORM::for_table(TBLCODE)->where('code', '018')->where('codeDetail', $plan['structureScale'])->where_null('deleteDate')->findOne();
    if(isset($code)) {
        $data['structureScaleName'] = $code['name'];
    }
}
// 権利関係
if(isset($plan['rightsRelationship']) && $plan['rightsRelationship'] !== '') {
    $code = ORM::for_table(TBLCODE)->where('code', '011')->where('codeDetail', $plan['rightsRelationship'])->where_null('deleteDate')->findOne();
    if(isset($code)) {
        $data['rightsRelationshipName'] = $code['name'];
    }
}

// 支払種別取得
$types = ORM::for_table(TBLPAYMENTTYPE)->where('addFlg', '1')->where_null('deleteDate')->select('paymentCode')->select('paymentName')->findArray();
// 支払名称に変換する画面表示順を定義
$nums = array(6, 7, 8, 9, 10, 19, 20, 21, 22, 23, 35, 36);

$data['bukkenName'] = $bukken['bukkenName'];// 物件名
$data['address'] = $plan['address'];// 所在地

// 計画詳細情報(tblPlanDetail)
foreach($details as $detail) {
    // 20201016 S_Update
//    $data['price_' . $detail['backNumber']] = $detail['price'];
    $data['price_' . $detail['backNumber']] = floatval($detail['price']);
    // 20201016 E_Update
    $data['unitPrice_' . $detail['backNumber']] = floatval($detail['unitPrice']);
    $data['routePrice_' . $detail['backNumber']] = floatval($detail['routePrice']);
    $data['burdenDays_' . $detail['backNumber']] = floatval($detail['burdenDays']);
    $data['complePriceMonth_' . $detail['backNumber']] = floatval($detail['complePriceMonth']);
    $data['dismantlingMonth_' . $detail['backNumber']] = floatval($detail['dismantlingMonth']);
    $data['totalMonths_' . $detail['backNumber']] = $detail['totalMonths'];
    $data['valuation_' . $detail['backNumber']] = floatval($detail['valuation']);
    $data['rent_' . $detail['backNumber']] = floatval($detail['rent']);
    $data['commissionRate_' . $detail['backNumber']] = $detail['commissionRate'];

    // 支払名称設定
    if(in_array($detail['backNumber'], $nums) && isset($detail['paymentCode']) && $detail['paymentCode'] !== '' ) {
        $payName = '';
        foreach($types as $type) {
            if($type['paymentCode'] === $detail['paymentCode']){
                $payName = $type['paymentName'];
                break;
            }
        }
        $data['paymentName_' . $detail['backNumber']] = $payName;
    }
}

// レントロール情報(tblPlanRentRoll)
foreach($rent as $key => $value) {
    $data[$key] = $value;
    // 稼働率、売却時想定利回り、経費率A、経費率B、経費率C、経費率D、利回りA、利回りB、利回りC、利回りD
    if(($key === 'occupancyRate' || $key === 'salesProfits'
        || $key === 'expenseRatio1' || $key === 'expenseRatio2' || $key === 'expenseRatio3' || $key === 'expenseRatio4'
        || $key === 'profitsA' || $key === 'profitsB' || $key === 'profitsC' || $key === 'profitsD')
        && isset($value) && $value !== '') {
        // 20200826 S_Update
//        $data[$key] = $value.'%';
        $data[$key] = round($value / 100, 3);
        // 20200826 E_Update
    }
    // 20201016 S_Add
    // 販売経費１A、販売経費１B、販売経費１C、販売経費１D
    // 販売経費２A、販売経費２B、販売経費２C、販売経費２D
    // 販売経費３A、販売経費３B、販売経費３C、販売経費３D
    // 販売経費４A、販売経費４B、販売経費４C、販売経費４D
    // 坪単価A、坪単価B、坪単価C、坪単価D
    // 共益費、その他収入（月額）
    if($key === 'salesExpense1A' || $key === 'salesExpense1B' || $key === 'salesExpense1C' || $key === 'salesExpense1D'
        || $key === 'salesExpense2A' || $key === 'salesExpense2B' || $key === 'salesExpense2C' || $key === 'salesExpense2D'
        || $key === 'salesExpense3A' || $key === 'salesExpense3B' || $key === 'salesExpense3C' || $key === 'salesExpense3D'
        || $key === 'salesExpense4A' || $key === 'salesExpense4B' || $key === 'salesExpense4C' || $key === 'salesExpense4D'
        || $key === 'tsuboUnitPriceA' || $key === 'tsuboUnitPriceB' || $key === 'tsuboUnitPriceC' || $key === 'tsuboUnitPriceD'
        || $key === 'commonFee' || $key === 'monthlyOtherIncome') {
        $data[$key] = floatval($value);
    }
    // 20201016 E_Add
}

// レントロール詳細情報(tblPlanRentRollDetail)
foreach($rentDetails as $rentDetail) {
    $data['targetArea_' . $rentDetail['backNumber']] = $rentDetail['targetArea'];
    $data['space_' . $rentDetail['backNumber']] = $rentDetail['space'];
    $data['rentUnitPrice_' . $rentDetail['backNumber']] = floatval($rentDetail['rentUnitPrice']);
    $data['securityDeposit_' . $rentDetail['backNumber']] = $rentDetail['securityDeposit'];
}

// NOI利回り検討シート
$sheet = $spreadsheet->getSheet(0);
$sheetPos = array(
    'AE2' => 'settlement',
    'AA3' => 'bukkenName',
    'AE3' => 'periodName',
    'AB4' => 'landOwner',
    'AE4' => 'rightsRelationshipName',
    'AB5' => 'landContract',
    'H7' => 'address',
    'L7' => 'traffic',
    'AC7' => 'designOffice',
    'J8' => 'siteAreaBuy',
    'R8' => 'groundType',
    'X8' => 'structureScaleName',
    'AC8' => 'construction',
    'J9' => 'siteAreaCheck',
    'R9' => 'restrictedArea',
    'X9' => 'ground',
    'AB9' => 'startDay',
    'J10' => 'buildArea',
    'R10' => 'floorAreaRate',
    'X10' => 'underground',
    'AB10' => 'upperWingDay',
    'J11' => 'entrance',
    'R11' => 'coverageRate',
    'X11' => 'totalUnits',
    'AB11' => 'completionDay',
    'J12' => 'parking',
    'X12' => 'buysellUnits',
    'J13' => 'underArea',
    'X13' => 'parkingIndoor',
    'Z13' => 'parkingOutdoor',
    'J14' => 'totalArea',
    'Z14' => 'mechanical',
    'J15' => 'salesArea',
    'I18' => 'jvRatio',
    'H20' => 'price_1',
    'L20' => 'routePrice_1',
    'H22' => 'price_2',
    'H23' => 'price_3',
    'H24' => 'price_4',
    //'H25' => 'price_5',
    'L25' => 'burdenDays_5',
    'H27' => 'price_6',
    'H28' => 'price_7',
    'H29' => 'price_8',
    'H30' => 'price_9',
    'H31' => 'price_10',
    'H33' => 'price_11',
    'L33' => 'unitPrice_11',
    'H36' => 'price_12',
    'H37' => 'price_13',
    'H38' => 'price_14',
    'H39' => 'price_15',
    'H41' => 'price_16',
    'H42' => 'price_17',
    'H43' => 'price_18',
    'H45' => 'price_19',
    'H46' => 'price_20',
    'H47' => 'price_21',
    'H48' => 'price_22',
    'H49' => 'price_23',
    //'H52' => 'price_24',
    'L52' => 'complePriceMonth_24',
    //'H54' => 'price_25',
    'L54' => 'dismantlingMonth_25',
    'H55' => 'price_26',
    'H56' => 'price_27',
    'H59' => 'price_28',
    'H60' => 'price_29',
    'H64' => 'price_30',
    //'H66' => 'price_31',
    'L66' => 'valuation_31',
    //'H67' => 'price_32',
    //'H68' => 'price_33',
    'L68' => 'rent_33',
    'H69' => 'price_34',
    'L69' => 'totalMonths_34',
    'H71' => 'price_35',
    'H72' => 'price_36',
    //'H73' => 'price_37',
    'L73' => 'commissionRate_37',
    //'H74' => 'price_38',
    'H75' => 'price_39',

    'G27' => 'paymentName_6',
    'G28' => 'paymentName_7',
    'G29' => 'paymentName_8',
    'G30' => 'paymentName_9',
    'G31' => 'paymentName_10',
    'G45' => 'paymentName_19',
    'G46' => 'paymentName_20',
    'G47' => 'paymentName_21',
    'G48' => 'paymentName_22',
    'G49' => 'paymentName_23',
    'G71' => 'paymentName_35',
    'G72' => 'paymentName_36',

    'T72' => 'residentialRate',
    'T73' => 'landEvaluation',
    'T74' => 'taxation',
    'T75' => 'taxationCity',
    'T76' => 'buildValuation',
    'T80' => 'afterTaxation',
    'T81' => 'afterTaxationCity',

    'Z72' => 'fixedTaxLand',
    'Z73' => 'cityPlanTaxLand',
    'Z75' => 'fixedTaxBuild',
    'Z76' => 'cityPlanTaxBuild',
    'Z80' => 'afterFixedTax',
    'Z81' => 'afterCityPlanTax',

    'F78' => 'landInterest',
    'L78' => 'landLoan',
    'L79' => 'landPeriod',
    'F80' => 'buildInterest',
    'L80' => 'buildLoan',
    'L81' => 'buildPeriod',
    'T19' => 'occupancyRate',
    'AD19' => 'salesProfits',
    'T23' => 'expenseRatio1',
    'X23' => 'expenseRatio2',
    'AA23' => 'expenseRatio3',
    'AD23' => 'expenseRatio4',
    'Q43' => 'targetArea_1',
    'Q44' => 'targetArea_2',
    'Q45' => 'targetArea_3',
    'Q46' => 'targetArea_4',
    'Q47' => 'targetArea_5',
    'Q48' => 'targetArea_6',
    'Q49' => 'targetArea_7',
    'Q50' => 'targetArea_8',
    'Q51' => 'targetArea_9',
    'Q52' => 'targetArea_10',
    'Q53' => 'targetArea_11',
    'Q54' => 'targetArea_12',
    'Q55' => 'targetArea_13',
    'Q56' => 'targetArea_14',
    'Q57' => 'targetArea_15',
    'S43' => 'space_1',
    'S44' => 'space_2',
    'S45' => 'space_3',
    'S46' => 'space_4',
    'S47' => 'space_5',
    'S48' => 'space_6',
    'S49' => 'space_7',
    'S50' => 'space_8',
    'S51' => 'space_9',
    'S52' => 'space_10',
    'S53' => 'space_11',
    'S54' => 'space_12',
    'S55' => 'space_13',
    'S56' => 'space_14',
    'S57' => 'space_15',
    'S59' => 'space_17',
    'S60' => 'space_18',
    'S61' => 'space_19',
    'W43' => 'rentUnitPrice_1',
    'W44' => 'rentUnitPrice_2',
    'W45' => 'rentUnitPrice_3',
    'W46' => 'rentUnitPrice_4',
    'W47' => 'rentUnitPrice_5',
    'W48' => 'rentUnitPrice_6',
    'W49' => 'rentUnitPrice_7',
    'W50' => 'rentUnitPrice_8',
    'W51' => 'rentUnitPrice_9',
    'W52' => 'rentUnitPrice_10',
    'W53' => 'rentUnitPrice_11',
    'W54' => 'rentUnitPrice_12',
    'W55' => 'rentUnitPrice_13',
    'W56' => 'rentUnitPrice_14',
    'W57' => 'rentUnitPrice_15',
    'W58' => 'rentUnitPrice_16',
    'W59' => 'rentUnitPrice_17',
    'W60' => 'rentUnitPrice_18',
    'W61' => 'rentUnitPrice_19',
    'AA43' => 'securityDeposit_1',
    'AA44' => 'securityDeposit_2',
    'AA45' => 'securityDeposit_3',
    'AA46' => 'securityDeposit_4',
    'AA47' => 'securityDeposit_5',
    'AA48' => 'securityDeposit_6',
    'AA49' => 'securityDeposit_7',
    'AA50' => 'securityDeposit_8',
    'AA51' => 'securityDeposit_9',
    'AA52' => 'securityDeposit_10',
    'AA53' => 'securityDeposit_11',
    'AA54' => 'securityDeposit_12',
    'AA55' => 'securityDeposit_13',
    'AA56' => 'securityDeposit_14',
    'AA57' => 'securityDeposit_15',
    'AA58' => 'securityDeposit_16',
    'AA59' => 'securityDeposit_17',
    'AA60' => 'securityDeposit_18',
    'AA61' => 'securityDeposit_19',
    'AF64' => 'commonFee',
    'AB66' => 'monthlyOtherIncome'
);
foreach ($sheetPos as $key => $value) {
    $sheet->setCellValue($key, isset($data[$value]) ? $data[$value] : '');
}

// 表面利回り検討シート
$sheet = $spreadsheet->getSheet(1);
$sheetPos2 = array(
    'AE2' => 'settlement',
    'AA3' => 'bukkenName',
    'AE3' => 'periodName',
    'AB4' => 'landOwner',
    'AE4' => 'rightsRelationshipName',
    'AB5' => 'landContract',
    'H7' => 'address',
    'L7' => 'traffic',
    'AC7' => 'designOffice',
    'J8' => 'siteAreaBuy',
    'R8' => 'groundType',
    'X8' => 'structureScaleName',
    'AC8' => 'construction',
    'J9' => 'siteAreaCheck',
    'R9' => 'restrictedArea',
    'X9' => 'ground',
    'AB9' => 'startDay',
    'J10' => 'buildArea',
    'R10' => 'floorAreaRate',
    'X10' => 'underground',
    'AB10' => 'upperWingDay',
    'J11' => 'entrance',
    'R11' => 'coverageRate',
    'X11' => 'totalUnits',
    'AB11' => 'completionDay',
    'J12' => 'parking',
    'X12' => 'buysellUnits',
    'J13' => 'underArea',
    'X13' => 'parkingIndoor',
    'Z13' => 'parkingOutdoor',
    'J14' => 'totalArea',
    'Z14' => 'mechanical',
    'J15' => 'salesArea',
    'I18' => 'jvRatio',
    'H20' => 'price_1',
    'L20' => 'routePrice_1',
    'H22' => 'price_2',
    'H23' => 'price_3',
    'H24' => 'price_4',
//    'H25' => 'price_5',
    'L25' => 'burdenDays_5',
    'H27' => 'price_6',
    'H28' => 'price_7',
    'H29' => 'price_8',
    'H30' => 'price_9',
    'H31' => 'price_10',
    'H33' => 'price_11',
    'L33' => 'unitPrice_11',
    'H36' => 'price_12',
    'H37' => 'price_13',
    'H38' => 'price_14',
    'H39' => 'price_15',
    'H41' => 'price_16',
    'H42' => 'price_17',
    'H43' => 'price_18',
    'H45' => 'price_19',
    'H46' => 'price_20',
    'H47' => 'price_21',
    'H48' => 'price_22',
    'H49' => 'price_23',
    //'H52' => 'price_24',
    'L52' => 'complePriceMonth_24',
    //'H54' => 'price_25',
    'L54' => 'dismantlingMonth_25',
    'H55' => 'price_26',
    'H56' => 'price_27',
    'H59' => 'price_28',
    'H60' => 'price_29',
    'H64' => 'price_30',
    //'H66' => 'price_31',
    'L66' => 'valuation_31',
    //'H67' => 'price_32',
    //'H68' => 'price_33',
    'L68' => 'rent_33',
    'H69' => 'price_34',
    'L69' => 'totalMonths_34',
    'H71' => 'price_35',
    'H72' => 'price_36',
    //'H73' => 'price_37',
    'L73' => 'commissionRate_37',
    //'H74' => 'price_38',
    'H75' => 'price_39',
    
    'G27' => 'paymentName_6',
    'G28' => 'paymentName_7',
    'G29' => 'paymentName_8',
    'G30' => 'paymentName_9',
    'G31' => 'paymentName_10',
    'G45' => 'paymentName_19',
    'G46' => 'paymentName_20',
    'G47' => 'paymentName_21',
    'G48' => 'paymentName_22',
    'G49' => 'paymentName_23',
    'G71' => 'paymentName_35',
    'G72' => 'paymentName_36',

    'T72' => 'residentialRate',
    'T73' => 'landEvaluation',
    'T74' => 'taxation',
    'T75' => 'taxationCity',
    'T76' => 'buildValuation',
    'T80' => 'afterTaxation',
    'T81' => 'afterTaxationCity',

    'Z72' => 'fixedTaxLand',
    'Z73' => 'cityPlanTaxLand',
    'Z75' => 'fixedTaxBuild',
    'Z76' => 'cityPlanTaxBuild',
    'Z80' => 'afterFixedTax',
    'Z81' => 'afterCityPlanTax',

    'F78' => 'landInterest',
    'L78' => 'landLoan',
    'L79' => 'landPeriod',
    'F80' => 'buildInterest',
    'L80' => 'buildLoan',
    'L81' => 'buildPeriod',
    'T19' => 'occupancyRate',
    'Q43' => 'targetArea_1',
    'Q44' => 'targetArea_2',
    'Q45' => 'targetArea_3',
    'Q46' => 'targetArea_4',
    'Q47' => 'targetArea_5',
    'Q48' => 'targetArea_6',
    'Q49' => 'targetArea_7',
    'Q50' => 'targetArea_8',
    'Q51' => 'targetArea_9',
    'Q52' => 'targetArea_10',
    'Q53' => 'targetArea_11',
    'Q54' => 'targetArea_12',
    'Q55' => 'targetArea_13',
    'Q56' => 'targetArea_14',
    'Q57' => 'targetArea_15',
    'S43' => 'space_1',
    'S44' => 'space_2',
    'S45' => 'space_3',
    'S46' => 'space_4',
    'S47' => 'space_5',
    'S48' => 'space_6',
    'S49' => 'space_7',
    'S50' => 'space_8',
    'S51' => 'space_9',
    'S52' => 'space_10',
    'S53' => 'space_11',
    'S54' => 'space_12',
    'S55' => 'space_13',
    'S56' => 'space_14',
    'S57' => 'space_15',
    'S59' => 'space_17',
    'S60' => 'space_18',
    'S61' => 'space_19',
    'W43' => 'rentUnitPrice_1',
    'W44' => 'rentUnitPrice_2',
    'W45' => 'rentUnitPrice_3',
    'W46' => 'rentUnitPrice_4',
    'W47' => 'rentUnitPrice_5',
    'W48' => 'rentUnitPrice_6',
    'W49' => 'rentUnitPrice_7',
    'W50' => 'rentUnitPrice_8',
    'W51' => 'rentUnitPrice_9',
    'W52' => 'rentUnitPrice_10',
    'W53' => 'rentUnitPrice_11',
    'W54' => 'rentUnitPrice_12',
    'W55' => 'rentUnitPrice_13',
    'W56' => 'rentUnitPrice_14',
    'W57' => 'rentUnitPrice_15',
    'W58' => 'rentUnitPrice_16',
    'W59' => 'rentUnitPrice_17',
    'W60' => 'rentUnitPrice_18',
    'W61' => 'rentUnitPrice_19',
    'AA43' => 'securityDeposit_1',
    'AA44' => 'securityDeposit_2',
    'AA45' => 'securityDeposit_3',
    'AA46' => 'securityDeposit_4',
    'AA47' => 'securityDeposit_5',
    'AA48' => 'securityDeposit_6',
    'AA49' => 'securityDeposit_7',
    'AA50' => 'securityDeposit_8',
    'AA51' => 'securityDeposit_9',
    'AA52' => 'securityDeposit_10',
    'AA53' => 'securityDeposit_11',
    'AA54' => 'securityDeposit_12',
    'AA55' => 'securityDeposit_13',
    'AA56' => 'securityDeposit_14',
    'AA57' => 'securityDeposit_15',
    'AA58' => 'securityDeposit_16',
    'AA59' => 'securityDeposit_17',
    'AA60' => 'securityDeposit_18',
    'AA61' => 'securityDeposit_19',
    'AF64' => 'commonFee',
    'AB66' => 'monthlyOtherIncome',

    'R27' => 'salesExpenseName1',
    'T27' => 'salesExpense1A',
    'X27' => 'salesExpense1B',
    'AA27' => 'salesExpense1C',
    'AD27' => 'salesExpense1D',
    'R29' => 'salesExpenseName2',
    'T29' => 'salesExpense2A',
    'X29' => 'salesExpense2B',
    'AA29' => 'salesExpense2C',
    'AD29' => 'salesExpense2D',
    'R31' => 'salesExpenseName3',
    'T31' => 'salesExpense3A',
    'X31' => 'salesExpense3B',
    'AA31' => 'salesExpense3C',
    'AD31' => 'salesExpense3D',
    'T39' => 'profitsA',
    'X39' => 'profitsB',
    'AA39' => 'profitsC',
    'AD39' => 'profitsD'
);
foreach ($sheetPos2 as $key => $value) {
    $sheet->setCellValue($key, isset($data[$value]) ? $data[$value] : '');
}

// 簡易版（利回り）シート
$sheet = $spreadsheet->getSheet(2);
$sheet3Pos = array(
    'D2' => 'address',
    'G6' => 'price_1',
    'I6' => 'routePrice_1',
    'G8' => 'price_3',
    'G9' => 'price_4',
    'G10' => 'price_5',
    'I10' => 'burdenDays_5',
    'G13' => 'price_11',
    'I13' => 'unitPrice_11',
    'G14' => 'price_14',
    'G18' => 'price_24',
    'I18' => 'complePriceMonth_24',
    'G20' => 'price_25',
    'I20' => 'dismantlingMonth_25',
    'G27' => 'price_30',
    'G28' => 'price_33',
    'I28' => 'rent_33',
    'G30' => 'price_37',
    'I30' => 'commissionRate_37',
    'G31' => 'price_38',
    'G32' => 'price_39'
);
foreach ($sheet3Pos as $key => $value) {
    $sheet->setCellValue($key, isset($data[$value]) ? $data[$value] : '');
}

// 簡易版（土地売り）シート
$sheet = $spreadsheet->getSheet(3);
$sheet4Pos = array(
    'D2' => 'address',
    'G6' => 'price_1',
    'I6' => 'routePrice_1',
    'G8' => 'price_3',
    'G9' => 'price_4',
    'G10' => 'price_5',
    'I10' => 'burdenDays_5',
    'I13' => 'unitPrice_11',
    'G18' => 'price_24',
    'I18' => 'complePriceMonth_24',
    'G20' => 'price_25',
    'I20' => 'dismantlingMonth_25',
    'G27' => 'price_30',
    'G28' => 'price_33',
    'I28' => 'rent_33',
    'G30' => 'price_37',
    'I30' => 'commissionRate_37',
    'G31' => 'price_38',
    'G32' => 'price_39',

    'E53' => 'tsuboUnitPriceA',
    'G53' => 'tsuboUnitPriceB',
    'H53' => 'tsuboUnitPriceC',
    'I53' => 'tsuboUnitPriceD'
);
foreach ($sheet4Pos as $key => $value) {
    $sheet->setCellValue($key, isset($data[$value]) ? $data[$value] : '');
}

//\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)->disableCalculationCache();
//\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)->clearCalculationCache();

// 保存
$filename = "収支帳票_" . date("YmdHis") . 'xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
// 20201016 S_Add
// Excel側の数式を再計算させる
$writer->setPreCalculateFormulas(false);
// 20201016 E_Add
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

?>