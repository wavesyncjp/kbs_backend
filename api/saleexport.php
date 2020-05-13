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

$bukken = ORM::for_table(TBLTEMPLANDINFO)->findOne($param->pid)->asArray();
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->order_by_asc('pid')->findArray();
$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $param->pid)->order_by_asc('pid')->findArray();
$payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $param->pid)->order_by_asc('pid')->findArray();
$paymentTypeData = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate')->findArray();

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/売買取引管理表.xlsx'; 
//Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

//売買取引管理表
$sheet = $spreadsheet->getSheet(0);

$sheet->setCellValue("A2", $bukken['contractBukkenNo']);
$sheet->setCellValue("D2", $bukken['residence']);

//売り契約ブロック
$pos = 5; //スタート
if(sizeof($sales) > 1) {
    copyBlockWithValue($sheet, $pos, 5, sizeof($sales) - 1, 20);
}
foreach($sales as $sale) {
    $sheet->setCellValue("F".$pos, $sale['salesName']);
    $sheet->setCellValue("I".$pos, $sale['salesTradingPrice']);
    $sheet->setCellValue("J".$pos, convert_jpdt($sale['salesContractDay']));
    $sheet->setCellValue("K".$pos, convert_jpdt($sale['salesDecisionDay']));
    $sheet->setCellValue("M".$pos, $sale['salesFixedLandTax']);
    $sheet->setCellValue("M".($pos + 1), $sale['salesFixedBuildingTax']);
    $sheet->setCellValue("M".($pos + 2), $sale['salesFixedConsumptionTax']);
    $sheet->setCellValue("O".($pos + 0), $sale['salesLiquidation1']);
    $sheet->setCellValue("O".($pos + 1), $sale['salesLiquidation2']);
    $sheet->setCellValue("O".($pos + 2), $sale['salesLiquidation3']);
    $sheet->setCellValue("O".($pos + 3), $sale['salesLiquidation4']);
    $sheet->setCellValue("O".($pos + 4), $sale['salesLiquidation5']);    
    $pos += 5;
}

//契約ブロック
$contractPos = 12 + 5 * (sizeof($sales) - 1);
if(sizeof($contracts) > 1) {
    copyBlockWithValue($sheet, $contractPos, 1, sizeof($contracts) - 1, 20);
}
$newList = [];
foreach($contracts as $contract) {

    $contractStaff = getUserName($contract['contractStaff']);
    $contractors = getSellerName($contract['pid']);
    $status = contractStatus($contract);
    $locs = getLocation($contract['pid']);
    $address = getAddress($locs);
    $deposit = getDeposit($contract);
    $depositDay = getDepositDay($contract);
    $area = getArea($locs);
    $newList[] = array(
        'contractorName' => $contractors[0], 
        'contractor' => $contractors[1], 
        'status' => $status, 
        'pid' => $contract['pid'],
        'contractStaff' => $contractStaff,
        'address' => $address,
        'deposit' => $deposit,
        'depositDay' => $depositDay,
        'area' => $area
    );

    $sheet->setCellValue('A'.$contractPos, $contractStaff); //（担当）    
    $sheet->setCellValue('C'.$contractPos, $status); //売却・等価交換    
    $sheet->setCellValue('D'.$contractPos, $address); //所在地
    $sheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);    
    $sheet->setCellValue('E'.$contractPos, $contractors[0]); //地権者
    $sheet->setCellValue('F'.$contractPos, $status !== 'メトロス買取済' ? convert_jpdt($contract['contractDay']) : ''); //契約日

    if($status == '解除（等価交換）' || $status == '解除') {
        $sheet->setCellValue('G'.$contractPos, ''); //金額
    }
    else {
        $sheet->setCellValue('G'.$contractPos, $contract['tradingPrice']); //金額
    }    
    $sheet->setCellValue('H'.$contractPos, $deposit); //内金（手付等）
    $sheet->getStyle('H'.$contractPos)->getAlignment()->setWrapText(true);

    $sheet->setCellValue('I'.$contractPos, $depositDay); //内金（手付）支払日
    $sheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);

    $sheet->setCellValue('J'.$contractPos, emptyStatus($status, $contract['decisionPrice'])); //決済代金
    $sheet->setCellValue('L'.$contractPos, emptyStatus($status, $contract['fixedTax'])); //固都税清算金
    $sheet->setCellValue('M'.$contractPos, emptyStatus($status, convert_jpdt($contract['deliveryFixedDay']))); //引渡期日
    $sheet->setCellValue('N'.$contractPos, emptyStatus($status, convert_jpdt($contract['decisionDay']))); //決済日

    $promptDecideFlg = '';
    if($status === "解除（等価交換）" || $status === "解除") $promptDecideFlg = '';
    else if($status === 'メトロス買取済') $promptDecideFlg = '（旧所有者：' . $contractors[0] . '）';
    else if ($contract['promptDecideFlg'] == '0') $promptDecideFlg = '無';
    else if ($contract['promptDecideFlg'] == '1') $promptDecideFlg = '有';    
        
    $sheet->setCellValue('O'.$contractPos, $promptDecideFlg); //即決和解の有無等

    $sheet->setCellValue('P'.$contractPos, emptyStatus($status, $contract['retainage'])); //留保金
    $sheet->setCellValue('Q'.$contractPos, emptyStatus($status, convert_jpdt($contract['vacationDay']))); //明渡期日
    $sheet->setCellValue('R'.$contractPos, emptyStatus($status, convert_jpdt($contract['retainageDay']))); //留保金支払（明渡）日

    $sheet->setCellValue('S'.$contractPos, emptyStatus($status, $area)); //売買面積（㎡）
    $sheet->getStyle('S'.$contractPos)->getAlignment()->setWrapText(true);   

    $contractPos++;
}

$sheet->setCellValue('P'.($contractPos + 2), getInfoOffer($bukken['infoOffer'])); //情報提供者

//手数料ブロック
$payList1 = [];
$payList2 = [];
$payPos = $contractPos + 5;
foreach($payContracts as $pay) {
    $details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    if(isset($details) && sizeof($details) > 0) {
        foreach($details as $detail) {
            $detail['supplierName'] = $pay['supplierName'];
            $detail['contractPrice'] = $pay['contractPrice'];
            $detail = getPaymentName($detail, $paymentTypeData);
            if(equalVal($detail, 'utilityChargesFlg', '0')) {
                $payList1[] = $detail;
            }   
            else {
                $payList2[] = $detail;
            }         
        }
    }
}
if(sizeof($payList1) > 1) {
    copyBlockWithValue($sheet, $payPos, 1, sizeof($payList1) - 1, 20);
}
foreach($payList1 as $payDetail) {
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']); //支払先
    $sheet->setCellValue('F'.$payPos, $payDetail['paymentName']); //摘要
    $sheet->setCellValue('G'.$payPos, $payDetail['contractPrice']); //契約金額
    $sheet->setCellValue('H'.$payPos, $payDetail['payPrice']); //支払金額
    $sheet->setCellValue('I'.$payPos, $payDetail['paymentSeason']); //支払時期
    $sheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractDay'])); //支払予定日

    //支払日
    if(isCancel($newList, $payDetail)) {
        $sheet->setCellValue('K'.$payPos, '解除');
    }
    else {
        $sheet->setCellValue('K'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    }        
    $sheet->setCellValue('L'.$payPos, getContractor($newList, $payDetail)); //契約者
    $sheet->setCellValue('O'.$payPos, $payDetail['detailRemarks']); //備考
    $payPos++;
}

//水道光熱費
$payPos += 4;
if(sizeof($payList2) > 1) {
    copyBlockWithValue($sheet, $payPos, 1, sizeof($payList2) - 1, 20);
}
foreach($payList2 as $payDetail) {
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']); //支払先
    $sheet->setCellValue('F'.$payPos, $payDetail['paymentName']); //摘要
    $sheet->setCellValue('G'.$payPos, $payDetail['payPrice']); //金額
    $sheet->setCellValue('H'.$payPos, getPayMethodName($payDetail['paymentMethod'])); //支払方法

    //支払日
    if(isCancel($newList, $payDetail)) {
        $sheet->setCellValue('I'.$payPos, '解除');
    }
    else {
        $sheet->setCellValue('I'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    }     
    $sheet->setCellValue('J'.$payPos, $payDetail['detailRemarks']); //備考

    $payPos++;
}

//ﾒﾄﾛｽ買取シート
$hasBaikai = false;
foreach($contracts as $contract) {
    $status = contractStatus($contract);
    if($status !== 'メトロス買取済') {
        continue;
    }
    $data = getContractData($newList, $contract['pid']);
    $names = $data['contractorName'];
    if(!notNull($names)) {
        continue;
    }
    $hasBaikai = true;
    
    //契約者をループ
    $nameList = explode('、', $names);
    foreach($nameList as $name) {
        $clonedWorksheet = clone $spreadsheet->getSheet(1);
        $clonedWorksheet->setTitle('ﾒﾄﾛｽ買取(' . $name . '様)');
        $spreadsheet->addSheet($clonedWorksheet);

        $clonedWorksheet->setCellValue("A2", $bukken['contractBukkenNo']);
        $clonedWorksheet->setCellValue("D2", $bukken['residence']);


        //契約ブロック
        $contractPos = 5;              
        $clonedWorksheet->setCellValue('A'.$contractPos, $data['contractStaff']); //（担当）    
        $clonedWorksheet->setCellValue('C'.$contractPos, $data['status']); //売却・等価交換    
        $clonedWorksheet->setCellValue('D'.$contractPos, $data['address']); //所在地
        $clonedWorksheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);    
        $clonedWorksheet->setCellValue('E'.$contractPos, $data['contractorName']); //地権者
        $clonedWorksheet->setCellValue('F'.$contractPos, convert_jpdt($contract['contractDay'])); //契約日
        $clonedWorksheet->setCellValue('G'.$contractPos, $contract['tradingPrice']); //金額            
        $clonedWorksheet->setCellValue('H'.$contractPos, getDeposit2($contract)); //内金（手付等）
        $clonedWorksheet->getStyle('H'.$contractPos)->getAlignment()->setWrapText(true);
        $clonedWorksheet->setCellValue('I'.$contractPos, getDepositDay2($contract)); //内金（手付）支払日
        $clonedWorksheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);
        $clonedWorksheet->setCellValue('J'.$contractPos, $contract['tradingBalance']); //残代金
        $clonedWorksheet->setCellValue('L'.$contractPos, $contract['fixedTax']); //固都税清算金
        $clonedWorksheet->setCellValue('M'.$contractPos, convert_jpdt($contract['decisionDay'])); //支払完了日
        $clonedWorksheet->setCellValue('N'.$contractPos, $contract['retainage']); //留保金
        $clonedWorksheet->setCellValue('O'.$contractPos, convert_jpdt($contract['vacationDay'])); //明渡期日
        $clonedWorksheet->setCellValue('P'.$contractPos, convert_jpdt($contract['retainageDay'])); //留保金支払（明渡）日
        $clonedWorksheet->setCellValue('Q'.$contractPos, $data['area']); //売買面積（㎡）
        $clonedWorksheet->getStyle('Q'.$contractPos)->getAlignment()->setWrapText(true); 

        //手数料等費用一覧
        $payPos = 9;
        $subPayList = [];
        foreach($payList1 as $pay) {
            if($pay['contractor'] == $data['contractor']) {
                $subPayList[] = $pay;
            }
        }

        if(sizeof($subPayList) > 1) {
            copyBlockWithValue($clonedWorksheet, $payPos, 1, sizeof($subPayList) - 1, 20);
        }
        foreach($subPayList as $payDetail) {
            $clonedWorksheet->setCellValue('C'.$payPos, $payDetail['supplierName']); //支払先
            $clonedWorksheet->setCellValue('F'.$payPos, $payDetail['paymentName']); //摘要
            $clonedWorksheet->setCellValue('G'.$payPos, $payDetail['payPrice']); //支払金額
            $clonedWorksheet->setCellValue('H'.$payPos, $payDetail['paymentSeason']); //支払時期
            $clonedWorksheet->setCellValue('I'.$payPos, convert_jpdt($payDetail['contractDay'])); //支払予定日
        
            //支払日
            if(isCancel($newList, $payDetail)) {
                $clonedWorksheet->setCellValue('J'.$payPos, '解除');
            }
            else {
                $clonedWorksheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractFixDay']));
            }        
            $clonedWorksheet->setCellValue('K'.$payPos, $data['contractorName']); //契約者
            $clonedWorksheet->setCellValue('N'.$payPos, $payDetail['detailRemarks']); //備考
            $payPos++;
        }

    }

}

//コピー元買取シート削除
$spreadsheet->removeSheetByIndex(1);


//保存
$filename = "売買取引管理表_" . date("YmdHis") . 'xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

//ダウンロード
readfile($savePath);

//削除
unlink($savePath);


function contractStatus($contract) {
    if($contract['decisionDayChk'] === '1') return 'メトロス買取済';
    if($contract['canncellDayChk'] === '1') return '解除（等価交換）';
    if(isset($contract['canncellDay']) && $contract['canncellDay'] != '') return '解除';
    if($contract['equiExchangeFlg'] === '1') return '等価交換';
    return '売却';
}   

function getUserName($uesrCode) {
    $lst = ORM::for_table(TBLUSER)->where('employeeCode', $uesrCode)->findArray();
    if(sizeof($lst) > 0) return $lst[0]['userName'];
    return '';
}

function getLocation($contractPid) {
    $lst = ORM::for_table(TBLCONTRACTDETAILINFO)
    ->table_alias('p1')
    ->select('p2.locationType', 'locationType')
    ->select('p2.blockNumber', 'blockNumber')
    ->select('p2.buildingNumber', 'buildingNumber')
    ->select('p2.area', 'area')
    ->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
    ->where('p1.contractDataType', '01')
    ->where('p1.contractInfoPid', $contractPid)
    ->order_by_asc('p1.pid')->findArray();
    return $lst;
}

function getAddress($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if($data['locationType'] == '01' && $data['blockNumber'] != '') $ret[] = $data['blockNumber'];
            else if($data['buildingNumber'] != '') $ret[] = $data['buildingNumber'];
        }
    }
    return implode(chr(10), $ret);
}

function getArea($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if(isset($data['area']) && $data['area'] != '') {
                $ret[] = $data['area'];
            }             
        }
    }
    return implode(chr(10), $ret);
}

function getSellerName($contractPid) {
    $lst = ORM::for_table(TBLCONTRACTSELLERINFO)->where('contractInfoPid', $contractPid)->where_null('deleteDate')->order_by_asc('pid')->select('pid')->select('contractorName')->findArray();
    $names = [];
    $ids = [];
    foreach($lst as $data) {
        $names[] = $data['contractorName'];
        $ids[] = $data['pid'];
    }

    $ret = [];
    $ret[] = implode('、', $names);
    $ret[] = implode(',', $ids);

    return $ret;
}


function getDeposit($contract) {    
    if($contract['canncellDayChk'] == '1' || (!isset($contract['canncellDay']) && $contract['canncellDay'] != '') || $contract['decisionDayChk'] == '1') return '-';
    $deposit = [];

    if($contract['deposit1DayChk']=='1' && $contract['deposit1'] > 0) $deposit[] = "¥".number_format($contract['deposit1']);
    else  $deposit[] = "－";
    if($contract['deposit2DayChk']=='1' && $contract['deposit2'] > 0) $deposit[] = "¥".number_format($contract['deposit2']);
    else  $deposit[] = "－";
    if($contract['earnestPriceDayChk']=='1' && $contract['earnestPrice'] > 0) $deposit[] = "¥".number_format($contract['earnestPrice']);
    else  $deposit[] = "－";
    return implode(chr(10), $deposit);
}

function getDepositDay($contract) {    
    if($contract['canncellDayChk'] == '1' || (!isset($contract['canncellDay']) && $contract['canncellDay'] != '') || $contract['decisionDayChk'] == '1') return '-';
    $depositDay = [];

    if($contract['deposit1DayChk']=='1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') $depositDay[] = convert_jpdt($contract['deposit1Day']);
    else  $depositDay[] = "－";
    if($contract['deposit2DayChk']=='1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') $depositDay[] = convert_jpdt($contract['deposit2Day']);
    else  $depositDay[] = "－";
    if($contract['earnestPriceDayChk']=='1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
    else  $depositDay[] = "－";
    return implode(chr(10), $depositDay);
}

function getDeposit2($contract) { 
    $deposit = [];
    if($contract['deposit1DayChk']=='1' && $contract['deposit1'] > 0) $deposit[] = "¥".number_format($contract['deposit1']);
    else  $deposit[] = "－";
    if($contract['deposit2DayChk']=='1' && $contract['deposit2'] > 0) $deposit[] = "¥".number_format($contract['deposit2']);
    else  $deposit[] = "－";
    if($contract['earnestPriceDayChk']=='1' && $contract['earnestPrice'] > 0) $deposit[] = "¥".number_format($contract['earnestPrice']);
    else  $deposit[] = "－";
    return implode(chr(10), $deposit);
}

function getDepositDay2($contract) {    
    $depositDay = [];
    if($contract['deposit1DayChk']=='1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') $depositDay[] = convert_jpdt($contract['deposit1Day']);
    else  $depositDay[] = "－";
    if($contract['deposit2DayChk']=='1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') $depositDay[] = convert_jpdt($contract['deposit2Day']);
    else  $depositDay[] = "－";
    if($contract['earnestPriceDayChk']=='1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
    else  $depositDay[] = "－";
    return implode(chr(10), $depositDay);
}

function getInfoOffer($offer) {
    if(!isset($offer) || $offer == '') return '';
    $ids = explode(',', $offer);
    $users = ORM::for_table(TBLUSER)->where_in('userId', $ids)->where_null('deleteDate')->select('userName')->findArray();
    $names = [];
    foreach($users as $user) {
        $names[] = $user['userName'];
    }
    return implode('、', $names);
}

function getPaymentName($detail,$lst) {
    if(!notNull($detail['paymentCode'])) return $detail;
    foreach($lst as $type) {
        if(equalVal($type, 'paymentCode', $detail['paymentCode'])) {
            $detail['paymentName'] = $type['paymentName'];
            $detail['utilityChargesFlg'] = $type['utilityChargesFlg'];
            return $detail;
        }
    } 
    return $detail;   
}

function getPayMethodName($method) {
    if(!notNull($method)) return '';
    $lst = ORM::for_table(TBLCODE)->where('code', '015')->where('codeDetail', $method)->where_null('deleteDate')->select('name')->findArray();
    if(isset($lst) && sizeof($lst) > 0) return $lst[0]['name'];
    return '';
}

function isCancel($lst, $payDetail) {
    foreach($lst as $contract) {
        if($contract['contractor'] == $payDetail['contractor']) {
            if($contract['status'] == '解除（等価交換）' || $contract['status'] == '解除') return true;
        }
    }    
    return false;
}

/**
 * 支払いに紐づく契約者
 */
function getContractor($lst, $payDetail) {
    foreach($lst as $contract) {
        if($contract['contractor'] == $payDetail['contractor']) {
            return $contract['contractorName'];
        }
    }    
    return '';
}

/**
 * 契約の契約者
 */
function getContractData($lst, $pid) {
    foreach($lst as $contract) {
        if($contract['pid'] == $pid) {
            return $contract;
        }
    }    
    return '';
}

/**
 * 対象ステータスで値表示
 */
function emptyStatus($status, $val) {
    if($status === "解除（等価交換）" || $status === "解除" || $status === "メトロス買取済") return '';
    return $val;
}

?>