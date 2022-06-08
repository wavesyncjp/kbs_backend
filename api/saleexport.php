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

// 20210411 S_Add
// 権利形態List
$codeRightsFormList = ORM::for_table(TBLCODE)->where('code', '011')->where_null('deleteDate')->findArray();
// 20210411 E_Add
$bukken = ORM::for_table(TBLTEMPLANDINFO)->findOne($param->pid)->asArray();
// 20220329 S_Update
// $sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('displayOrder')->order_by_asc('pid')->findArray();
// 20220329 E_Update
// 20201020 S_Update
//$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')>order_by_asc('pid')->findArray();
$contracts = ORM::for_table(TBLCONTRACTINFO)->where('tempLandInfoPid', $param->pid)->where_not_null('contractNow')->where_not_equal('contractNow', '')->where_null('deleteDate')->order_by_asc('pid')->findArray();
// 20201020 E_Update
$payContracts = ORM::for_table(TBLPAYCONTRACT)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
$paymentTypeData = ORM::for_table(TBLPAYMENTTYPE)->where_null('deleteDate')->findArray();

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/売買取引管理表.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 売買取引管理表
$sheet = $spreadsheet->getSheet(0);

// 契約物件番号
$sheet->setCellValue('A2', $bukken['contractBukkenNo']);
// 20210320 S_Delete
// 居住表示
//$sheet->setCellValue('D2', $bukken['residence']);
// 20210320 E_Delete

// 売り契約ブロック
$pos = 5;
// データが複数ある場合、ブロックをコピー
if(sizeof($sales) > 1) {
    copyBlockWithVal($sheet, $pos, 5, sizeof($sales) - 1, 21);
}
foreach($sales as $sale) {
    // 20210402 S_Add
    // 売主
    // 売主対象が1:Royal Houseの場合
    if($bukken['seller'] === '1')
    {
        $sheet->setCellValue('C'.$pos, 'Royal House株式会社');
    }
    // 20210402 E_Add
    // 買主
    $sheet->setCellValue('G'.$pos, $sale['salesName']);
    // 金額
    $sheet->setCellValue('J'.$pos, formatYenNumber($sale['salesTradingPrice']));
    // 契約日
    $sheet->setCellValue('K'.$pos, convert_jpdt($sale['salesContractDay']));
    // 決済日
    $sheet->setCellValue('L'.$pos, convert_jpdt($sale['salesDecisionDay']));
    // 固都税清算金（土地）
    $sheet->setCellValue('N'.$pos, formatYenNumber($sale['salesFixedLandTax']));
    // 固都税清算金（建物）
    $sheet->setCellValue('N'.($pos + 1), formatYenNumber($sale['salesFixedBuildingTax']));
    // 固都税清算金（消費税）
    // 20220608 S_Update
    // $sheet->setCellValue('N'.($pos + 2), formatYenNumber($sale['salesFixedConsumptionTax']));
    $sheet->setCellValue('N'.($pos + 2), formatYenNumber($sale['salesFixedBuildingTaxOnlyTax']));// 建物分消費税
    // 20220608 S_Update
    // その他清算金１
    $sheet->setCellValue('P'.($pos + 0), formatYenNumber($sale['salesLiquidation1']));
    // その他清算金２
    $sheet->setCellValue('P'.($pos + 1), formatYenNumber($sale['salesLiquidation2']));
    // その他清算金３
    $sheet->setCellValue('P'.($pos + 2), formatYenNumber($sale['salesLiquidation3']));
    // その他清算金４
    $sheet->setCellValue('P'.($pos + 3), formatYenNumber($sale['salesLiquidation4']));
    // その他清算金５
    $sheet->setCellValue('P'.($pos + 4), formatYenNumber($sale['salesLiquidation5']));
    $pos += 5;
}
// データが存在しない場合
if(sizeof($sales) == 0) {
    $sheet->setCellValue('C'.$pos, '');
    $sheet->setCellValue('G'.$pos, '');// 買主
    $sheet->setCellValue('J'.$pos, '');// 金額
    $sheet->setCellValue('K'.$pos, '');// 契約日
    $sheet->setCellValue('L'.$pos, '');// 決済日
    $sheet->setCellValue('N'.$pos, '');// 固都税清算金（土地）
    $sheet->setCellValue('N'.($pos + 1), '');// 固都税清算金（建物）
    $sheet->setCellValue('N'.($pos + 2), '');// 固都税清算金（消費税）
    $sheet->setCellValue('P'.($pos + 0), '');// その他清算金１
    $sheet->setCellValue('P'.($pos + 1), '');// その他清算金２
    $sheet->setCellValue('P'.($pos + 2), '');// その他清算金３
    $sheet->setCellValue('P'.($pos + 3), '');// その他清算金４
    $sheet->setCellValue('P'.($pos + 4), '');// その他清算金５
    $pos += 5;
}

// 契約ブロック
$contractPos = 12 + 5 * (sizeof($sales) >= 1 ? sizeof($sales) - 1 : 0);
$firstContractPos = $contractPos;

// 11行目に1行余白をいれる　※通貨・ユーザー定義の書式が不正になるバグの暫定対応
copyBlockWithVal($sheet, $contractPos -2, 1, 1, 21);
$contractPos += 1;

// データが複数ある場合、ブロックをコピー
if(sizeof($contracts) > 1) {
    copyBlockWithVal($sheet, $contractPos, 1, sizeof($contracts) - 1, 21);
}
$newList = [];
$residence = '';// 20210320 Add
foreach($contracts as $contract) {

    $contractStaff = getUserName($contract['contractStaff']);
    $contractors = getSellerName($contract['pid']);
    $status = contractStatus($contract);
    $locs = getLocation($contract['pid']);
//    $address = getAddress($locs);// 20210320 Delete
    // 20210320 S_Add
    $residence = getAddress($locs);
    // 20210411 S_Update
//    $blockNumber = getBlockNumber($locs);
    $blockNumber = getBlockNumber($locs, $codeRightsFormList);
    // 20210411 E_Update
    $buildingNumber = getBuildingNumber($locs);
    // 20210320 E_Add
    $deposit = getDeposit($contract);
    $depositDay = getDepositDay($contract);
    // 20201014 S_Update
//    $area = getArea($locs);
    $area = getArea($locs, $contract['tradingType']);
    // 20201014 E_Update
    $newList[] = array(
        'contractorName' => $contractors[0],
        'contractor' => $contractors[1],
        'status' => $status,
        'pid' => $contract['pid'],
        'contractStaff' => $contractStaff,
//        'address' => $address,// 20210320 Delete
        // 20210320 S_Add
        'blockNumber' => $blockNumber,
        'buildingNumber' => $buildingNumber,
        // 20210320 E_Add
        'deposit' => $deposit,
        'depositDay' => $depositDay,
        'area' => $area
    );

    // （担当）
    $sheet->setCellValue('A'.$contractPos, $contractStaff);
    // 売却・等価交換
    $sheet->setCellValue('C'.$contractPos, $status);
    // 20210320 S_Delete
    /*
    // 所在地
    $sheet->setCellValue('D'.$contractPos, $address);
    $sheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);
    */
    // 20210320 E_Delete
    // 20210320 S_Add
    // 地番
    $sheet->setCellValue('D'.$contractPos, $blockNumber);
    // 家屋番号
    $sheet->setCellValue('E'.$contractPos, $buildingNumber);
    // 20210320 E_Add
    // 地権者
    $sheet->setCellValue('F'.$contractPos, $contractors[0]);
    // 契約日
    $sheet->setCellValue('G'.$contractPos, $status !== 'メトロス買取済' ? convert_jpdt($contract['contractDay']) : '');
    // 金額
    if($status === '解除（等価交換）' || $status === '解除') {
        $sheet->setCellValue('H'.$contractPos, '');
    }
    else {
//        $sheet->setCellValue('G'.$contractPos, formatYenNumber($contract['tradingPrice']));
        $sheet->setCellValue('H'.$contractPos, $contract['tradingPrice']);
    }
    // 内金（手付等）
    if($status === 'メトロス買取済') $deposit = '－';// 20201027 Add
    $sheet->setCellValue('I'.$contractPos, $deposit);
    $sheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);
    // 内金（手付）支払日
    if($status === 'メトロス買取済') $depositDay = '－';// 20201027 Add
    $sheet->setCellValue('J'.$contractPos, $depositDay);
    $sheet->getStyle('J'.$contractPos)->getAlignment()->setWrapText(true);
    // 決済代金
//    $sheet->setCellValue('J'.$contractPos, emptyStatus($status, formatYenNumber($contract['decisionPrice'])));
    $sheet->setCellValue('K'.$contractPos, emptyStatus($status, $contract['decisionPrice']));
    // 固都税清算金
    // 20210905 S_Update
    /*
    $sheet->setCellValue('M'.$contractPos, emptyStatus($status, formatYenNumber($contract['fixedTax'])));
    */
    if(intval($contract['fixedTax']) > 0) {
        $sheet->setCellValue('M'.$contractPos, emptyStatus($status, formatYenNumber($contract['fixedTax'])));
    } else {
        $fixedTax = intval($contract['fixedLandTax']) + intval($contract['fixedBuildingTax']) + intval($contract['fixedBuildingTaxOnlyTax']);
        $sheet->setCellValue('M'.$contractPos, emptyStatus($status, formatYenNumber($fixedTax)));
    }
    // 20210905 E_Update
    // 決済予定日
    $sheet->setCellValue('N'.$contractPos, emptyStatus($status, convert_jpdt($contract['deliveryFixedDay'])));
    // 決済日
    $sheet->setCellValue('O'.$contractPos, emptyStatus($status, convert_jpdt($contract['decisionDay'])));
    // 即決和解の有無等
    $promptDecideFlg = '';
    if($status === '解除（等価交換）' || $status === '解除') $promptDecideFlg = '';
    else if($status === 'メトロス買取済') $promptDecideFlg = '（旧所有者：' . $contractors[0] . '）';
    else if ($contract['promptDecideFlg'] === '0') $promptDecideFlg = '無';
    else if ($contract['promptDecideFlg'] === '1') $promptDecideFlg = '有';
    $sheet->setCellValue('P'.$contractPos, $promptDecideFlg);
    // 留保金
    $sheet->setCellValue('Q'.$contractPos, emptyStatus($status, formatYenNumber($contract['retainage'])));
    // 明渡期日
    $sheet->setCellValue('R'.$contractPos, emptyStatus($status, convert_jpdt($contract['vacationDay'])));
    // 留保金支払（明渡）日
    $sheet->setCellValue('S'.$contractPos, emptyStatus($status, convert_jpdt($contract['retainageDay'])));
    // 売買面積（㎡）
    if($status === '解除（等価交換）' || $status === '解除') $area = '';// 20201106 Add
    //$sheet->setCellValue('S'.$contractPos, emptyStatus($status, $area));
    $sheet->setCellValue('T'.$contractPos, $area);
    $sheet->getStyle('T'.$contractPos)->getAlignment()->setWrapText(true);

    // 塗りつぶし・文字色設定
    if($status === 'メトロス買取済') {
        // 塗りつぶし：黄色
        $sheet->getStyle('C'.$contractPos.':T'.$contractPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
    }
    else if($status === '解除（等価交換）' || $status === '解除') {
        // 塗りつぶし：グレー
        // 文字色：赤
        $sheet->getStyle('C'.$contractPos.':T'.$contractPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('BFBFBF');
        $sheet->getStyle('C'.$contractPos)->getFont()->getColor()->applyFromArray(['rgb' => 'FF0000']);
    }

    $contractPos++;
}
// データが存在しない場合
if(sizeof($contracts) == 0) {
    $sheet->setCellValue('A'.$contractPos, '');// （担当）
    $sheet->setCellValue('C'.$contractPos, '');// 売却・等価交換
    // 20210320 S_Delete
//    $sheet->setCellValue('D'.$contractPos, '');// 所在地
    // 20210320 E_Delete
    // 20210320 S_Add
    $sheet->setCellValue('D'.$contractPos, '');// 地番
    $sheet->setCellValue('E'.$contractPos, '');// 家屋番号
    // 20210320 E_Add
    $sheet->setCellValue('F'.$contractPos, '');// 地権者
    $sheet->setCellValue('G'.$contractPos, '');// 契約日
    $sheet->setCellValue('H'.$contractPos, '');// 金額
    $sheet->setCellValue('I'.$contractPos, '');// 内金（手付等）
    $sheet->setCellValue('J'.$contractPos, '');// 内金（手付）支払日
    $sheet->setCellValue('K'.$contractPos, '');// 決済代金
    $sheet->setCellValue('M'.$contractPos, '');// 固都税清算金
    $sheet->setCellValue('N'.$contractPos, '');// 決済予定日
    $sheet->setCellValue('O'.$contractPos, '');// 決済日
    $sheet->setCellValue('P'.$contractPos, '');// 即決和解の有無等
    $sheet->setCellValue('Q'.$contractPos, '');// 留保金
    $sheet->setCellValue('R'.$contractPos, '');// 明渡期日
    $sheet->setCellValue('S'.$contractPos, '');// 留保金支払（明渡）日
    $sheet->setCellValue('T'.$contractPos, '');// 売買面積（㎡）
    $contractPos++;
}

// 合計の計算式
$sheet->setCellValue('H'.$contractPos, '=SUM(H' . $firstContractPos . ':H' . ($contractPos - 1) .')');
$sheet->setCellValue('K'.$contractPos, '=SUM(K' . $firstContractPos . ':L' . ($contractPos - 1) .')');
$sheet->setCellValue('T'.$contractPos, '=SUM(T' . $firstContractPos . ':T' . ($contractPos - 1) .')');

// 情報提供者
$sheet->setCellValue('Q'.($contractPos + 2), getInfoOffer($bukken['infoOffer']));

// 20210320 S_Add
// 居住表示
if($residence === '') $residence = $bukken['residence'];
$sheet->setCellValue('D2', $residence);
// 20210320 E_Add

// 手数料等費用一覧
$payList1 = [];// 手数料等費用一覧
$payList2 = [];// 水道光熱費等経費一覧
$payPos = $contractPos + 5;
$firstPayPos = $payPos;
foreach($payContracts as $pay) {
    $details = ORM::for_table(TBLPAYCONTRACTDETAIL)->where('payContractPid', $pay['pid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
    if(isset($details) && sizeof($details) > 0) {
        foreach($details as $detail) {
            $detail['supplierName'] = $pay['supplierName'];
            $detail['contractPrice'] = $pay['contractPrice'];
            $detail['contractPriceTax'] = $pay['contractPriceTax'];// 20200906 Add
            $detail = getPaymentName($detail, $paymentTypeData);
            if(!equalVal($detail, 'costFlg', '04')){// 原価フラグ=04:地権者費用は対象外
                // 光熱費フラグで分割する
                if(equalVal($detail, 'utilityChargesFlg', '0')) {
                    $payList1[] = $detail;
                }   
                else {
                    $payList2[] = $detail;
                }
            }
        }
    }
}
// データが複数ある場合、ブロックをコピー
if(sizeof($payList1) > 1) {
    copyBlockWithVal($sheet, $payPos, 1, sizeof($payList1) - 1, 21);
}
foreach($payList1 as $payDetail) {
    // 支払先
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
    // 摘要
    $sheet->setCellValue('G'.$payPos, $payDetail['paymentName']);
    // 契約金額
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['contractPrice']));
    // 20200921 S_Update
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['contractPriceTax']));
    $contractPriceTax = $payDetail['contractPriceTax'];
    if($contractPriceTax === '0') $contractPriceTax = '';
    else $contractPriceTax = formatYenNumber($contractPriceTax);
    $sheet->setCellValue('H'.$payPos, $contractPriceTax);
    // 20200921 E_Update
    // 支払金額
//    $sheet->setCellValue('H'.$payPos, formatYenNumber($payDetail['payPrice']));
    $sheet->setCellValue('I'.$payPos, $payDetail['payPriceTax']);
    // 支払時期
    $sheet->setCellValue('J'.$payPos, $payDetail['paymentSeason']);
    // 支払予定日
    $sheet->setCellValue('K'.$payPos, convert_jpdt($payDetail['contractDay']));
    // 支払日
    // 20201107 S_Update
    /*
    if(isCancel($newList, $payDetail)) {
        $sheet->setCellValue('K'.$payPos, '解除');

        // 塗りつぶしグレーかつ、赤文字
        $sheet->getStyle('K'.$payPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('BFBFBF');
        $sheet->getStyle('K'.$payPos)->getFont()->getColor()->applyFromArray(['rgb' => 'FF0000']);
    }
    else {
        $sheet->setCellValue('K'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    }
    */
    $sheet->setCellValue('L'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    // 20201107 E_Update
    // 契約者
    $sheet->setCellValue('M'.$payPos, getContractor($newList, $payDetail));
    // 備考
    $sheet->setCellValue('P'.$payPos, $payDetail['detailRemarks']);
    $payPos++;
}
// データが存在しない場合
if(sizeof($payList1) == 0) {
    $sheet->setCellValue('C'.$payPos, '');// 支払先
    $sheet->setCellValue('G'.$payPos, '');// 摘要
    $sheet->setCellValue('H'.$payPos, '');// 契約金額
    $sheet->setCellValue('I'.$payPos, '');// 支払金額
    $sheet->setCellValue('J'.$payPos, '');// 支払時期
    $sheet->setCellValue('K'.$payPos, '');// 支払予定日
    $sheet->setCellValue('L'.$payPos, '');// 支払日
    $sheet->setCellValue('M'.$payPos, '');// 契約者
    $sheet->setCellValue('P'.$payPos, '');// 備考
    $payPos++;
}

// 合計の計算式
$sheet->setCellValue('I'.$payPos, '=SUM(I' . $firstPayPos . ':I' . ($payPos - 1) .')');

// 水道光熱費等経費一覧
$payPos += 4;
$firstPayPos = $payPos;
if(sizeof($payList2) > 1) {
    copyBlockWithVal($sheet, $payPos, 1, sizeof($payList2) - 1, 21);
}
foreach($payList2 as $payDetail) {
    // 支払先
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
    // 摘要
    $sheet->setCellValue('G'.$payPos, $payDetail['paymentName']);
    // 金額
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['payPrice']));
    $sheet->setCellValue('H'.$payPos, $payDetail['payPriceTax']);
    // 支払方法
    $sheet->setCellValue('I'.$payPos, getPayMethodName($payDetail['paymentMethod']));
    // 支払日
    // 20201107 S_Update
    /*
    if(isCancel($newList, $payDetail)) {
        $sheet->setCellValue('I'.$payPos, '解除');

        // 塗りつぶしグレーかつ、赤文字
        $sheet->getStyle('I'.$payPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('BFBFBF');
        $sheet->getStyle('I'.$payPos)->getFont()->getColor()->applyFromArray(['rgb' => 'FF0000']);
    }
    else {
        $sheet->setCellValue('I'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    }
    */
    $sheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractFixDay']));
    // 20201107 E_Update
    // 備考
    $sheet->setCellValue('K'.$payPos, $payDetail['detailRemarks']);

    $payPos++;
}
// データが存在しない場合
if(sizeof($payList2) == 0) {
    $sheet->setCellValue('C'.$payPos, ''); //支払先
    $sheet->setCellValue('G'.$payPos, ''); //摘要
    $sheet->setCellValue('H'.$payPos, ''); //金額
    $sheet->setCellValue('I'.$payPos, ''); //支払方法
    $sheet->setCellValue('J'.$payPos, ''); //支払日
    $sheet->setCellValue('K'.$payPos, ''); //備考
    $payPos++;
}

// 合計の計算式
$sheet->setCellValue('H'.$payPos, '=SUM(H' . $firstPayPos . ':H' . ($payPos - 1) .')');

$sheet->setSelectedCell('A1');// 初期選択セル設定 20220608 Add

// ﾒﾄﾛｽ買取シート
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
    
    // 契約者をループ
    //$nameList = explode('、', $names);
    $newName = str_replace('、', '・', $names);
    // 22文字以上の場合、カットする　※シート名の最大文字数オーバー
    if(mb_strlen($newName) > 22) {
        $newName = mb_substr($newName, 0, 22);
    }
    //foreach($nameList as $name) 
    {
        // シート名
        $clonedWorksheet = clone $spreadsheet->getSheet(1);
        $clonedWorksheet->setTitle('ﾒﾄﾛｽ買取(' . $newName . '様)');
        $spreadsheet->addSheet($clonedWorksheet);

        // 契約物件番号
        $clonedWorksheet->setCellValue('A2', $bukken['contractBukkenNo']);
        // 20210320 S_Update
        // 居住表示
//        $clonedWorksheet->setCellValue('D2', $bukken['residence']);
        $clonedWorksheet->setCellValue('D2', $residence);
        // 20210320 E_Update

        // 契約ブロック
        $contractPos = 5;
        // （担当）
        $clonedWorksheet->setCellValue('A'.$contractPos, $data['contractStaff']);
        // 20201027 S_Delete
        /*
        // 売却・等価交換
        $clonedWorksheet->setCellValue('C'.$contractPos, $data['status']);
        */
        // 20201027 E_Delete
        // 20210320 S_Delete
        /*
        // 所在地
        $clonedWorksheet->setCellValue('D'.$contractPos, $data['address']);
        $clonedWorksheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);
        */
        // 20210320 E_Delete
        // 20210320 S_Add
        // 地番
        $clonedWorksheet->setCellValue('D'.$contractPos, $data['blockNumber']);
        // 家屋番号
        $clonedWorksheet->setCellValue('E'.$contractPos, $data['buildingNumber']);
        // 20210320 E_Add
        // 売主
        $clonedWorksheet->setCellValue('F'.$contractPos, $data['contractorName']);
        // 契約日
        $clonedWorksheet->setCellValue('G'.$contractPos, convert_jpdt($contract['contractDay']));
        // 金額
        $clonedWorksheet->setCellValue('H'.$contractPos, formatYenNumber($contract['tradingPrice']));
        // 内金（手付等）
        $clonedWorksheet->setCellValue('I'.$contractPos, getDeposit2($contract));
        $clonedWorksheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);
        // 内金（手付）支払日
        $clonedWorksheet->setCellValue('J'.$contractPos, getDepositDay2($contract));
        $clonedWorksheet->getStyle('J'.$contractPos)->getAlignment()->setWrapText(true);
        // 決済代金
        $clonedWorksheet->setCellValue('K'.$contractPos, formatYenNumber($contract['decisionPrice']));
        // 固都税清算金
        // 20210905 S_Update
        /*
        $clonedWorksheet->setCellValue('M'.$contractPos, formatYenNumber($contract['fixedTax']));
        */
        if(intval($contract['fixedTax']) > 0) {
            $clonedWorksheet->setCellValue('M'.$contractPos, formatYenNumber($contract['fixedTax']));
        } else {
            $fixedTax = intval($contract['fixedLandTax']) + intval($contract['fixedBuildingTax']) + intval($contract['fixedBuildingTaxOnlyTax']);
            $clonedWorksheet->setCellValue('M'.$contractPos, formatYenNumber($fixedTax));
        }
        // 20210905 E_Update
        // 支払完了日
        $clonedWorksheet->setCellValue('N'.$contractPos, convert_jpdt($contract['decisionDay']));
        // 留保金
        $clonedWorksheet->setCellValue('O'.$contractPos, formatYenNumber($contract['retainage']));
        // 明渡期日
        $clonedWorksheet->setCellValue('P'.$contractPos, convert_jpdt($contract['vacationDay']));
        // 留保金支払（明渡）日
        $clonedWorksheet->setCellValue('Q'.$contractPos, convert_jpdt($contract['retainageDay']));
        // 売買面積（㎡）
        $clonedWorksheet->setCellValue('R'.$contractPos, $data['area']);
        $clonedWorksheet->getStyle('R'.$contractPos)->getAlignment()->setWrapText(true);

        // 手数料等費用一覧
        $payPos = 9;
        $subPayList = [];
        foreach($payList1 as $pay) {
            if($pay['contractor'] == $data['contractor']) {
                $subPayList[] = $pay;
            }
        }
        // データが複数ある場合、ブロックをコピー
        if(sizeof($subPayList) > 1) {
            copyBlockWithVal($clonedWorksheet, $payPos, 1, sizeof($subPayList) - 1, 21);
        }
        foreach($subPayList as $payDetail) {
            // 支払先
            $clonedWorksheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
            // 摘要
            $clonedWorksheet->setCellValue('G'.$payPos, $payDetail['paymentName']);
            // 支払金額
//            $clonedWorksheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['payPrice']));
            $clonedWorksheet->setCellValue('H'.$payPos, $payDetail['payPriceTax']);
            // 支払時期
            $clonedWorksheet->setCellValue('I'.$payPos, $payDetail['paymentSeason']);
            // 支払予定日
            $clonedWorksheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractDay']));
            // 支払日
            // 20201107 S_Update
            /*
            if(isCancel($newList, $payDetail)) {
                $clonedWorksheet->setCellValue('J'.$payPos, '解除');
            }
            else {
                $clonedWorksheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractFixDay']));
            }
            */
            $clonedWorksheet->setCellValue('K'.$payPos, convert_jpdt($payDetail['contractFixDay']));
            // 20201107 E_Update
            // 契約者
            $clonedWorksheet->setCellValue('L'.$payPos, $data['contractorName']);
            // 備考
            $clonedWorksheet->setCellValue('O'.$payPos, $payDetail['detailRemarks']);
            $payPos++;
        }
        // データが存在しない場合
        if(sizeof($subPayList) == 0) {
            $clonedWorksheet->setCellValue('C'.$payPos, '');// 支払先
            $clonedWorksheet->setCellValue('G'.$payPos, '');// 摘要
            $clonedWorksheet->setCellValue('H'.$payPos, '');// 支払金額
            $clonedWorksheet->setCellValue('I'.$payPos, '');// 支払時期
            $clonedWorksheet->setCellValue('J'.$payPos, '');// 支払予定日
            $clonedWorksheet->setCellValue('K'.$payPos, '');// 支払日
            $clonedWorksheet->setCellValue('L'.$payPos, '');// 契約者
            $clonedWorksheet->setCellValue('O'.$payPos, '');// 備考
            $payPos++;
        }

        $clonedWorksheet->setSelectedCell('A1');// 初期選択セル設定 20220608 Add
    }
}

// コピー元ﾒﾄﾛｽ買取シート削除
$spreadsheet->removeSheetByIndex(1);

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定 20220608 Add

// 保存
$filename = '売買取引管理表_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

/**
 * ステータス設定
 */
function contractStatus($contract) {
    // 20201014 S_Update
//    if($contract['decisionDayChk'] === '1') return 'メトロス買取済';
    // 契約状況が02:買取済の場合
    if($contract['contractNow'] === '02') return 'メトロス買取済';
//    if($contract['canncellDayChk'] === '1') return '解除（等価交換）';
    // 解約日チェック（等価交換）がONもしくは、契約状況が04:解除済(等価交換)の場合
    if($contract['canncellDayChk'] === '1' || $contract['contractNow'] === '04') return '解除（等価交換）';
//    if(isset($contract['canncellDay']) && $contract['canncellDay'] != '') return '解除';
    // 解約日に指定があるもしくは、契約状況が03:解除済の場合
    if((isset($contract['canncellDay']) && $contract['canncellDay'] != '') || $contract['contractNow'] === '03') return '解除';
    // 20201014 E_Update
    if($contract['equiExchangeFlg'] === '1') return '等価交換';
    return '売却';
}

/**
 * ユーザー名称取得
 */
// 20200901 S_Update
function getUserName($val) {
    $ret = '';

    $list = explode(',', $val);
    foreach ($list as $userId)
    {
        $lst = ORM::for_table(TBLUSER)->where('userId', $userId)->findArray();
        if(sizeof($lst) > 0)
        {
            if(strlen($ret) > 0) $ret .= ',';
            $ret .= $lst[0]['userName'];
        }
    }
    return $ret;
}
/*
 function getUserName($uesrCode) {
    $lst = ORM::for_table(TBLUSER)->where('userId', $uesrCode)->findArray();
    if(sizeof($lst) > 0) return $lst[0]['userName'];
    return '';
}
*/
// 20200901 E_Update

/**
 * 所在地情報取得
 */
function getLocation($contractPid) {
    $lst = ORM::for_table(TBLCONTRACTDETAILINFO)
    ->table_alias('p1')
    ->select('p2.locationType', 'locationType')
    ->select('p2.address', 'address')// 20210320 Add
    ->select('p2.blockNumber', 'blockNumber')
    ->select('p2.buildingNumber', 'buildingNumber')
    ->select('p2.area', 'area')
    ->select('p2.rightsForm', 'rightsForm')// 20210411 Add
    ->select('p1.contractHave', 'contractHave')// 20201014 Add
    ->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
    ->where('p1.contractDataType', '01')
    ->where('p1.contractInfoPid', $contractPid)
    ->order_by_asc('p1.pid')->findArray();
    return $lst;
}

// 20210320 S_Delete
/**
 * 所在地取得
 */
/*
function getAddress($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if($data['locationType'] === '01' && $data['blockNumber'] !== '') $ret[] = $data['blockNumber'];
            else if($data['buildingNumber'] !== '') $ret[] = $data['buildingNumber'];
        }
    }
    return implode(chr(10), $ret);
}
*/
// 20210320 E_Delete
// 20210320 S_Add
/**
 * 所在地取得（土地の１件目）
 */
function getAddress($lst) {
    $ret = '';
    if(isset($lst)) {
        foreach($lst as $data) {
            if($data['locationType'] === '01' && $data['address'] !== '') {
                $ret = $data['address'];
                break;
            }
        }
    }
    return $ret;
}

/**
 * 地番取得（改行区切り）
 */
function getBlockNumber($lst, $codeList) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            // 区分が01:土地かつ、地番に指定がある場合
            if($data['locationType'] === '01' && $data['blockNumber'] !== '') $ret[] = mb_convert_kana($data['blockNumber'], 'kvrn');
            // 20210411 S_Add
            // 区分が02:建物かつ、権利形態に指定がある場合
            else if($data['locationType'] === '02' && getCodeTitle($codeList, $data['rightsForm']) !== '')
            {
                $ret[] = getCodeTitle($codeList, $data['rightsForm']);
            }
            // 20210411 E_Add
        }
    }
    return implode(chr(10), $ret);
}

/**
 * 家屋番号取得（改行区切り）
 */
function getBuildingNumber($lst) {
    $ret = [];
    if(isset($lst)) {
        foreach($lst as $data) {
            if($data['locationType'] !== '01' && $data['buildingNumber'] !== '') $ret[] = mb_convert_kana($data['buildingNumber'], 'kvrn');
        }
    }
    return implode(chr(10), $ret);
}
// 20210320 E_Add

/**
 * 売買面積（㎡）合計取得
 */
// 20201014 S_Update
//function getArea($lst) {
function getArea($lst, $tradingType) {
// 20201014 E_Update
    $ret = 0;
    if(isset($lst)) {
        foreach($lst as $data) {
            // 20201014 S_Update
            /*
            if(isset($data['area']) && $data['area'] != '') {
                $ret += $data['area'];
            }
            */
            // 20201218 S_Update
            // 売買が01:公募売買もしくは、02:実測売買もしくは、03:実測売買(想定有効面積)の場合
//            if($tradingType == '02' || $tradingType == '03') {
            if($tradingType == '01' || $tradingType == '02' || $tradingType == '03') {
            // 20201218 E_Update
                // 20201106 S_Update
//                if(isset($data['contractHave']) && $data['contractHave'] != '') {
                if(isset($data['contractHave']) && $data['contractHave'] != '' && $data['contractHave'] > 0) {
                // 20201106 E_Update
                    $ret += $data['contractHave'];
                }
                else if(isset($data['area']) && $data['area'] != '') {
                    $ret += $data['area'];
                }
            } else {
                if(isset($data['area']) && $data['area'] != '') {
                    $ret += $data['area'];
                }
            }
            // 20201014 E_Update
        }
    }
    return $ret;
}

/**
 * 契約者取得
 */
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

/**
 * 内金（手付等）取得
 */
function getDeposit($contract) {
    // 20201027 S_Delete
//    if($contract['canncellDayChk'] == '1' || (!isset($contract['canncellDay']) && $contract['canncellDay'] != '') || $contract['decisionDayChk'] == '1') return '－';
    // 20201027 E_Delete
    $deposit = [];
    $hasTarget = false;// 20210510 Add

    // 20201020 S_Update
//    if($contract['earnestPriceDayChk']=='1' && $contract['earnestPrice'] > 0) $deposit[] = '¥'.number_format($contract['earnestPrice']);
    if($contract['earnestPrice'] > 0) {
        $deposit[] = '¥'.number_format($contract['earnestPrice']);
        $hasTarget = true;
    }
    // 20201020 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20201020 S_Update
//    if($contract['deposit1DayChk']=='1' && $contract['deposit1'] > 0) $deposit[] = '¥'.number_format($contract['deposit1']);
    if($contract['deposit1'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit1']);
        $hasTarget = true;
    }
    // 20201020 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20201020 S_Update
//    if($contract['deposit2DayChk']=='1' && $contract['deposit2'] > 0) $deposit[] = '¥'.number_format($contract['deposit2']);
    if($contract['deposit2'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit2']);
        $hasTarget = true;
    }
    // 20201020 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20210510 S_Add
    if($contract['deposit3'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit3']);
        $hasTarget = true;
    }
//    else $deposit[] = '－';// 20210511 Delete
    if($contract['deposit4'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit4']);
        $hasTarget = true;
    }
//    else $deposit[] = '－';// 20210511 Delete

    // すべて対象外の場合、－を設定
    if(!$hasTarget) {
        $deposit = [];
        $deposit[] = '－';
    }
    // 20210510 E_Add
    return implode(chr(10), $deposit);
}

/**
 * 内金（手付）支払日取得
 */
function getDepositDay($contract) {
    // 20201027 S_Delete
//    if($contract['canncellDayChk'] == '1' || (!isset($contract['canncellDay']) && $contract['canncellDay'] != '') || $contract['decisionDayChk'] == '1') return '－';
    // 20201027 E_Delete
    $depositDay = [];
    $hasTarget = false;// 20210510 Add

    if($contract['earnestPriceDayChk'] == '1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') {
        $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit1DayChk'] == '1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit1Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit2DayChk'] == '1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit2Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    // 20210510 S_Add
    if($contract['deposit3DayChk'] == '1' && isset($contract['deposit3Day']) && $contract['deposit3Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit3Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit4DayChk'] == '1' && isset($contract['deposit4Day']) && $contract['deposit4Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit4Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete

    // すべて対象外の場合、－を設定
    if(!$hasTarget) {
        $depositDay = [];
        $depositDay[] = '－';
    }
    // 20210510 E_Add
    return implode(chr(10), $depositDay);
}

/**
 * 内金（手付等）取得（ﾒﾄﾛｽ買取シート）
 */
function getDeposit2($contract) { 
    $deposit = [];
    $hasTarget = false;// 20210510 Add

    // 20201124 S_Update
//    if($contract['earnestPriceDayChk'] == '1' && $contract['earnestPrice'] > 0) $deposit[] = '¥'.number_format($contract['earnestPrice']);
    if($contract['earnestPrice'] > 0) {
        $deposit[] = '¥'.number_format($contract['earnestPrice']);
        $hasTarget = true;
    }
    // 20201124 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20201124 S_Update
//    if($contract['deposit1DayChk'] == '1' && $contract['deposit1'] > 0) $deposit[] = '¥'.number_format($contract['deposit1']);
    if($contract['deposit1'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit1']);
        $hasTarget = true;
    }
    // 20201124 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20201124 S_Update
//    if($contract['deposit2DayChk'] == '1' && $contract['deposit2'] > 0) $deposit[] = '¥'.number_format($contract['deposit2']);
    if($contract['deposit2'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit2']);
        $hasTarget = true;
    }
    // 20201124 E_Update
//    else $deposit[] = '－';// 20210511 Delete
    // 20210510 S_Add
    if($contract['deposit3'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit3']);
        $hasTarget = true;
    }
//    else $deposit[] = '－';// 20210511 Delete
    if($contract['deposit4'] > 0) {
        $deposit[] = '¥'.number_format($contract['deposit4']);
        $hasTarget = true;
    }
//    else $deposit[] = '－';// 20210511 Delete

    // すべて対象外の場合、－を設定
    if(!$hasTarget) {
        $deposit = [];
        $deposit[] = '－';
    }
    // 20210510 E_Add
    return implode(chr(10), $deposit);
}

/**
 * 内金（手付）支払日取得（ﾒﾄﾛｽ買取シート）
 */
function getDepositDay2($contract) {
    $depositDay = [];
    $hasTarget = false;// 20210510 Add

    if($contract['earnestPriceDayChk'] == '1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') {
        $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit1DayChk'] == '1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit1Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit2DayChk'] == '1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit2Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    // 20210510 S_Add
    if($contract['deposit3DayChk'] == '1' && isset($contract['deposit3Day']) && $contract['deposit3Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit3Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete
    if($contract['deposit4DayChk'] == '1' && isset($contract['deposit4Day']) && $contract['deposit4Day'] != '') {
        $depositDay[] = convert_jpdt($contract['deposit4Day']);
        $hasTarget = true;
    }
//    else $depositDay[] = '－';// 20210511 Delete

    // すべて対象外の場合、－を設定
    if(!$hasTarget) {
        $depositDay = [];
        $depositDay[] = '－';
    }
    // 20210510 E_Add
    return implode(chr(10), $depositDay);
}

/**
 * 情報提供者取得
 */
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

/**
 * 摘要（支払名称）取得
 */
function getPaymentName($detail,$lst) {
    if(!notNull($detail['paymentCode'])) return $detail;
    foreach($lst as $type) {
        if(equalVal($type, 'paymentCode', $detail['paymentCode'])) {
            $detail['paymentName'] = $type['paymentName'];
            $detail['costFlg'] = $type['costFlg'];
            $detail['utilityChargesFlg'] = $type['utilityChargesFlg'];
            return $detail;
        }
    }
    return $detail;
}

/**
 * 支払方法取得
 */
function getPayMethodName($method) {
    if(!notNull($method)) return '';
    $lst = ORM::for_table(TBLCODE)->where('code', '015')->where('codeDetail', $method)->where_null('deleteDate')->select('name')->findArray();
    if(isset($lst) && sizeof($lst) > 0) return $lst[0]['name'];
    return '';
}

/**
 * 解除判定
 */
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
    $names = [];// 20210715 Add
    foreach($lst as $contract) {
        // 20210715 S_Update
        /*
        if($contract['contractor'] == $payDetail['contractor']) {
            return $contract['contractorName'];
        }
        */
        $contractors = explode('|', $payDetail['contractor']);
        foreach($contractors as $contractor) {
            if($contract['contractor'] == $contractor) {
                $names[] = $contract['contractorName'];
            }
        }
        // 20210715 E_Update
    }
    // 20210715 S_Update
    /*return '';*/
    return implode('、', $names);
    // 20210715 E_Update
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
    if($status === '解除（等価交換）' || $status === '解除' || $status === 'メトロス買取済') return '';
    return $val;
}

/**
 * ￥変換
 */
function formatYenNumber($number) {
    if(!isset($number) || $number == '') {
        return '';
    }
    $ret = '¥' . number_format($number);

    return $ret;
}

/**
 * 行と値コピー
 */
function copyBlockWithVal($sheet, $startPos, $blockRowCount, $copyCount, $colums) {
    $sheet->insertNewRowBefore($startPos, $blockRowCount * $copyCount);
    $lastPos = $startPos + ($blockRowCount * $copyCount);
    for($cursor = 0 ; $cursor < $copyCount ; $cursor++) {
        $copyPos = $startPos  + $blockRowCount * $cursor;
        copyRowsWithValue($sheet, $lastPos, $copyPos, $blockRowCount, $colums);
    }
}

?>