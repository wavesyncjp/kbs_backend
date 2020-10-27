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
$sales = ORM::for_table(TBLBUKKENSALESINFO)->where('tempLandInfoPid', $param->pid)->where_null('deleteDate')->order_by_asc('pid')->findArray();
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
// 居住表示
$sheet->setCellValue('D2', $bukken['residence']);

// 売り契約ブロック
$pos = 5;
// データが複数ある場合、ブロックをコピー
if(sizeof($sales) > 1) {
    copyBlockWithVal($sheet, $pos, 5, sizeof($sales) - 1, 20);
}
foreach($sales as $sale) {
    // 買主
    $sheet->setCellValue('F'.$pos, $sale['salesName']);
    // 金額
    $sheet->setCellValue('I'.$pos, formatYenNumber($sale['salesTradingPrice']));
    // 契約日
    $sheet->setCellValue('J'.$pos, convert_jpdt($sale['salesContractDay']));
    // 決済日
    $sheet->setCellValue('K'.$pos, convert_jpdt($sale['salesDecisionDay']));
    // 固都税清算金（土地）
    $sheet->setCellValue('M'.$pos, formatYenNumber($sale['salesFixedLandTax']));
    // 固都税清算金（建物）
    $sheet->setCellValue('M'.($pos + 1), formatYenNumber($sale['salesFixedBuildingTax']));
    // 固都税清算金（消費税）
    $sheet->setCellValue('M'.($pos + 2), formatYenNumber($sale['salesFixedConsumptionTax']));
    // その他清算金１
    $sheet->setCellValue('O'.($pos + 0), formatYenNumber($sale['salesLiquidation1']));
    // その他清算金２
    $sheet->setCellValue('O'.($pos + 1), formatYenNumber($sale['salesLiquidation2']));
    // その他清算金３
    $sheet->setCellValue('O'.($pos + 2), formatYenNumber($sale['salesLiquidation3']));
    // その他清算金４
    $sheet->setCellValue('O'.($pos + 3), formatYenNumber($sale['salesLiquidation4']));
    // その他清算金５
    $sheet->setCellValue('O'.($pos + 4), formatYenNumber($sale['salesLiquidation5']));
    $pos += 5;
}
// データが存在しない場合
if(sizeof($sales) == 0) {
    $sheet->setCellValue('C'.$pos, '');
    $sheet->setCellValue('F'.$pos, '');// 買主
    $sheet->setCellValue('I'.$pos, '');// 金額
    $sheet->setCellValue('J'.$pos, '');// 契約日
    $sheet->setCellValue('K'.$pos, '');// 決済日
    $sheet->setCellValue('M'.$pos, '');// 固都税清算金（土地）
    $sheet->setCellValue('M'.($pos + 1), '');// 固都税清算金（建物）
    $sheet->setCellValue('M'.($pos + 2), '');// 固都税清算金（消費税）
    $sheet->setCellValue('O'.($pos + 0), '');// その他清算金１
    $sheet->setCellValue('O'.($pos + 1), '');// その他清算金２
    $sheet->setCellValue('O'.($pos + 2), '');// その他清算金３
    $sheet->setCellValue('O'.($pos + 3), '');// その他清算金４
    $sheet->setCellValue('O'.($pos + 4), '');// その他清算金５
    $pos += 5;
}

// 契約ブロック
$contractPos = 12 + 5 * (sizeof($sales) >= 1 ? sizeof($sales) - 1 : 0);
$firstContractPos = $contractPos;

// 11行目に1行余白をいれる　※通貨・ユーザー定義の書式が不正になるバグの暫定対応
copyBlockWithVal($sheet, $contractPos -2, 1, 1, 20);
$contractPos += 1;

// データが複数ある場合、ブロックをコピー
if(sizeof($contracts) > 1) {
    copyBlockWithVal($sheet, $contractPos, 1, sizeof($contracts) - 1, 20);
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
        'address' => $address,
        'deposit' => $deposit,
        'depositDay' => $depositDay,
        'area' => $area
    );

    // （担当）
    $sheet->setCellValue('A'.$contractPos, $contractStaff);
    // 売却・等価交換
    $sheet->setCellValue('C'.$contractPos, $status);
    // 所在地
    $sheet->setCellValue('D'.$contractPos, $address);
    $sheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);
    // 地権者
    $sheet->setCellValue('E'.$contractPos, $contractors[0]);
    // 契約日
    $sheet->setCellValue('F'.$contractPos, $status !== 'メトロス買取済' ? convert_jpdt($contract['contractDay']) : '');
    // 金額
    if($status === '解除（等価交換）' || $status === '解除') {
        $sheet->setCellValue('G'.$contractPos, '');
    }
    else {
//        $sheet->setCellValue('G'.$contractPos, formatYenNumber($contract['tradingPrice']));
        $sheet->setCellValue('G'.$contractPos, $contract['tradingPrice']);
    }
    // 内金（手付等）
    if($status === 'メトロス買取済') $deposit = '－';// 20201027 Add
    $sheet->setCellValue('H'.$contractPos, $deposit);
    $sheet->getStyle('H'.$contractPos)->getAlignment()->setWrapText(true);
    // 内金（手付）支払日
    if($status === 'メトロス買取済') $depositDay = '－';// 20201027 Add
    $sheet->setCellValue('I'.$contractPos, $depositDay);
    $sheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);
    // 決済代金
//    $sheet->setCellValue('J'.$contractPos, emptyStatus($status, formatYenNumber($contract['decisionPrice'])));
    $sheet->setCellValue('J'.$contractPos, emptyStatus($status, $contract['decisionPrice']));
    // 固都税清算金
    $sheet->setCellValue('L'.$contractPos, emptyStatus($status, formatYenNumber($contract['fixedTax'])));
    // 引渡期日
    $sheet->setCellValue('M'.$contractPos, emptyStatus($status, convert_jpdt($contract['deliveryFixedDay'])));
    // 決済日
    $sheet->setCellValue('N'.$contractPos, emptyStatus($status, convert_jpdt($contract['decisionDay'])));
    // 即決和解の有無等
    $promptDecideFlg = '';
    if($status === '解除（等価交換）' || $status === '解除') $promptDecideFlg = '';
    else if($status === 'メトロス買取済') $promptDecideFlg = '（旧所有者：' . $contractors[0] . '）';
    else if ($contract['promptDecideFlg'] === '0') $promptDecideFlg = '無';
    else if ($contract['promptDecideFlg'] === '1') $promptDecideFlg = '有';
    $sheet->setCellValue('O'.$contractPos, $promptDecideFlg);
    // 留保金
    $sheet->setCellValue('P'.$contractPos, emptyStatus($status, formatYenNumber($contract['retainage'])));
    // 明渡期日
    $sheet->setCellValue('Q'.$contractPos, emptyStatus($status, convert_jpdt($contract['vacationDay'])));
    // 留保金支払（明渡）日
    $sheet->setCellValue('R'.$contractPos, emptyStatus($status, convert_jpdt($contract['retainageDay'])));
    // 売買面積（㎡）
    //$sheet->setCellValue('S'.$contractPos, emptyStatus($status, $area));
    $sheet->setCellValue('S'.$contractPos, $area);
    $sheet->getStyle('S'.$contractPos)->getAlignment()->setWrapText(true);

    // 塗りつぶし・文字色設定
    if($status === 'メトロス買取済') {
        // 塗りつぶし：黄色
        $sheet->getStyle('C'.$contractPos.':S'.$contractPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
    }
    else if($status === '解除（等価交換）' || $status === '解除') {
        // 塗りつぶし：グレー
        // 文字色：赤
        $sheet->getStyle('C'.$contractPos.':S'.$contractPos)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('BFBFBF');
        $sheet->getStyle('C'.$contractPos)->getFont()->getColor()->applyFromArray(['rgb' => 'FF0000']);
    }

    $contractPos++;
}
// データが存在しない場合
if(sizeof($contracts) == 0) {
    $sheet->setCellValue('A'.$contractPos, '');// （担当）
    $sheet->setCellValue('C'.$contractPos, '');// 売却・等価交換
    $sheet->setCellValue('D'.$contractPos, '');// 所在地
    $sheet->setCellValue('E'.$contractPos, '');// 地権者
    $sheet->setCellValue('F'.$contractPos, '');// 契約日
    $sheet->setCellValue('G'.$contractPos, '');// 金額
    $sheet->setCellValue('H'.$contractPos, '');// 内金（手付等）
    $sheet->setCellValue('I'.$contractPos, '');// 内金（手付）支払日
    $sheet->setCellValue('J'.$contractPos, '');// 決済代金
    $sheet->setCellValue('L'.$contractPos, '');// 固都税清算金
    $sheet->setCellValue('M'.$contractPos, '');// 引渡期日
    $sheet->setCellValue('N'.$contractPos, '');// 決済日
    $sheet->setCellValue('O'.$contractPos, '');// 即決和解の有無等
    $sheet->setCellValue('P'.$contractPos, '');// 留保金
    $sheet->setCellValue('Q'.$contractPos, '');// 明渡期日
    $sheet->setCellValue('R'.$contractPos, '');// 留保金支払（明渡）日
    $sheet->setCellValue('S'.$contractPos, '');// 売買面積（㎡）
    $contractPos++;
}

// 合計の計算式
$sheet->setCellValue('G'.$contractPos, '=SUM(G' . $firstContractPos . ':G' . ($contractPos - 1) .')');
$sheet->setCellValue('J'.$contractPos, '=SUM(J' . $firstContractPos . ':K' . ($contractPos - 1) .')');
$sheet->setCellValue('S'.$contractPos, '=SUM(S' . $firstContractPos . ':S' . ($contractPos - 1) .')');

// 情報提供者
$sheet->setCellValue('P'.($contractPos + 2), getInfoOffer($bukken['infoOffer']));

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
    copyBlockWithVal($sheet, $payPos, 1, sizeof($payList1) - 1, 20);
}
foreach($payList1 as $payDetail) {
    // 支払先
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
    // 摘要
    $sheet->setCellValue('F'.$payPos, $payDetail['paymentName']);
    // 契約金額
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['contractPrice']));
    // 20200921 S_Update
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['contractPriceTax']));
    $contractPriceTax = $payDetail['contractPriceTax'];
    if($contractPriceTax === '0') $contractPriceTax = '';
    else $contractPriceTax = formatYenNumber($contractPriceTax);
    $sheet->setCellValue('G'.$payPos, $contractPriceTax);
    // 20200921 E_Update
    // 支払金額
//    $sheet->setCellValue('H'.$payPos, formatYenNumber($payDetail['payPrice']));
    $sheet->setCellValue('H'.$payPos, $payDetail['payPriceTax']);
    // 支払時期
    $sheet->setCellValue('I'.$payPos, $payDetail['paymentSeason']);
    // 支払予定日
    $sheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractDay']));
    // 支払日
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
    // 契約者
    $sheet->setCellValue('L'.$payPos, getContractor($newList, $payDetail));
    // 備考
    $sheet->setCellValue('O'.$payPos, $payDetail['detailRemarks']);
    $payPos++;
}
// データが存在しない場合
if(sizeof($payList1) == 0) {
    $sheet->setCellValue('C'.$payPos, '');// 支払先
    $sheet->setCellValue('F'.$payPos, '');// 摘要
    $sheet->setCellValue('G'.$payPos, '');// 契約金額
    $sheet->setCellValue('H'.$payPos, '');// 支払金額
    $sheet->setCellValue('I'.$payPos, '');// 支払時期
    $sheet->setCellValue('J'.$payPos, '');// 支払予定日
    $sheet->setCellValue('K'.$payPos, '');// 支払日
    $sheet->setCellValue('L'.$payPos, '');// 契約者
    $sheet->setCellValue('O'.$payPos, '');// 備考
    $payPos++;
}

// 合計の計算式
$sheet->setCellValue('H'.$payPos, '=SUM(H' . $firstPayPos . ':H' . ($payPos - 1) .')');

// 水道光熱費等経費一覧
$payPos += 4;
$firstPayPos = $payPos;
if(sizeof($payList2) > 1) {
    copyBlockWithVal($sheet, $payPos, 1, sizeof($payList2) - 1, 20);
}
foreach($payList2 as $payDetail) {
    // 支払先
    $sheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
    // 摘要
    $sheet->setCellValue('F'.$payPos, $payDetail['paymentName']);
    // 金額
//    $sheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['payPrice']));
    $sheet->setCellValue('G'.$payPos, $payDetail['payPriceTax']);
    // 支払方法
    $sheet->setCellValue('H'.$payPos, getPayMethodName($payDetail['paymentMethod']));
    // 支払日
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
    // 備考
    $sheet->setCellValue('J'.$payPos, $payDetail['detailRemarks']);

    $payPos++;
}
// データが存在しない場合
if(sizeof($payList2) == 0) {
    $sheet->setCellValue('C'.$payPos, ''); //支払先
    $sheet->setCellValue('F'.$payPos, ''); //摘要
    $sheet->setCellValue('G'.$payPos, ''); //金額
    $sheet->setCellValue('H'.$payPos, ''); //支払方法
    $sheet->setCellValue('I'.$payPos, ''); //支払日
    $sheet->setCellValue('J'.$payPos, ''); //備考
    $payPos++;
}

// 合計の計算式
$sheet->setCellValue('G'.$payPos, '=SUM(G' . $firstPayPos . ':G' . ($payPos - 1) .')');

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
        // 居住表示
        $clonedWorksheet->setCellValue('D2', $bukken['residence']);

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
        // 所在地
        $clonedWorksheet->setCellValue('D'.$contractPos, $data['address']);
        $clonedWorksheet->getStyle('D'.$contractPos)->getAlignment()->setWrapText(true);
        // 売主
        $clonedWorksheet->setCellValue('E'.$contractPos, $data['contractorName']);
        // 契約日
        $clonedWorksheet->setCellValue('F'.$contractPos, convert_jpdt($contract['contractDay']));
        // 金額
        $clonedWorksheet->setCellValue('G'.$contractPos, formatYenNumber($contract['tradingPrice']));
        // 内金（手付等）
        $clonedWorksheet->setCellValue('H'.$contractPos, getDeposit2($contract));
        $clonedWorksheet->getStyle('H'.$contractPos)->getAlignment()->setWrapText(true);
        // 内金（手付）支払日
        $clonedWorksheet->setCellValue('I'.$contractPos, getDepositDay2($contract));
        $clonedWorksheet->getStyle('I'.$contractPos)->getAlignment()->setWrapText(true);
        // 決済代金
        $clonedWorksheet->setCellValue('J'.$contractPos, formatYenNumber($contract['decisionPrice']));
        // 固都税清算金
        $clonedWorksheet->setCellValue('L'.$contractPos, formatYenNumber($contract['fixedTax']));
        // 支払完了日
        $clonedWorksheet->setCellValue('M'.$contractPos, convert_jpdt($contract['decisionDay']));
        // 留保金
        $clonedWorksheet->setCellValue('N'.$contractPos, formatYenNumber($contract['retainage']));
        // 明渡期日
        $clonedWorksheet->setCellValue('O'.$contractPos, convert_jpdt($contract['vacationDay']));
        // 留保金支払（明渡）日
        $clonedWorksheet->setCellValue('P'.$contractPos, convert_jpdt($contract['retainageDay']));
        // 売買面積（㎡）
        $clonedWorksheet->setCellValue('Q'.$contractPos, $data['area']);
        $clonedWorksheet->getStyle('Q'.$contractPos)->getAlignment()->setWrapText(true);

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
            copyBlockWithVal($clonedWorksheet, $payPos, 1, sizeof($subPayList) - 1, 20);
        }
        foreach($subPayList as $payDetail) {
            // 支払先
            $clonedWorksheet->setCellValue('C'.$payPos, $payDetail['supplierName']);
            // 摘要
            $clonedWorksheet->setCellValue('F'.$payPos, $payDetail['paymentName']);
            // 支払金額
//            $clonedWorksheet->setCellValue('G'.$payPos, formatYenNumber($payDetail['payPrice']));
            $clonedWorksheet->setCellValue('G'.$payPos, $payDetail['payPriceTax']);
            // 支払時期
            $clonedWorksheet->setCellValue('H'.$payPos, $payDetail['paymentSeason']);
            // 支払予定日
            $clonedWorksheet->setCellValue('I'.$payPos, convert_jpdt($payDetail['contractDay']));
            // 支払日
            if(isCancel($newList, $payDetail)) {
                $clonedWorksheet->setCellValue('J'.$payPos, '解除');
            }
            else {
                $clonedWorksheet->setCellValue('J'.$payPos, convert_jpdt($payDetail['contractFixDay']));
            }
            // 契約者
            $clonedWorksheet->setCellValue('K'.$payPos, $data['contractorName']);
            // 備考
            $clonedWorksheet->setCellValue('N'.$payPos, $payDetail['detailRemarks']);
            $payPos++;
        }
        // データが存在しない場合
        if(sizeof($subPayList) == 0) {
            $clonedWorksheet->setCellValue('C'.$payPos, '');// 支払先
            $clonedWorksheet->setCellValue('F'.$payPos, '');// 摘要
            $clonedWorksheet->setCellValue('G'.$payPos, '');// 支払金額
            $clonedWorksheet->setCellValue('H'.$payPos, '');// 支払時期
            $clonedWorksheet->setCellValue('I'.$payPos, '');// 支払予定日
            $clonedWorksheet->setCellValue('J'.$payPos, '');// 支払日
            $clonedWorksheet->setCellValue('K'.$payPos, '');// 契約者
            $clonedWorksheet->setCellValue('N'.$payPos, '');// 備考
            $payPos++;
        }
    }
}

// コピー元ﾒﾄﾛｽ買取シート削除
$spreadsheet->removeSheetByIndex(1);

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
    ->select('p2.blockNumber', 'blockNumber')
    ->select('p2.buildingNumber', 'buildingNumber')
    ->select('p2.area', 'area')
    ->select('p1.contractHave', 'contractHave')// 20201014 Add
    ->inner_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p2.pid'), 'p2')
    ->where('p1.contractDataType', '01')
    ->where('p1.contractInfoPid', $contractPid)
    ->order_by_asc('p1.pid')->findArray();
    return $lst;
}

/**
 * 所在地取得
 */
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
            // 売買が02:実測売買もしくは、03:実測売買(想定有効面積)の場合
            if($tradingType == '02' || $tradingType == '03') {
                if(isset($data['contractHave']) && $data['contractHave'] != '') {
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

    // 20201020 S_Update
//    if($contract['deposit1DayChk']=='1' && $contract['deposit1'] > 0) $deposit[] = '¥'.number_format($contract['deposit1']);
    if($contract['deposit1'] > 0) $deposit[] = '¥'.number_format($contract['deposit1']);
    // 20201020 E_Update
    else  $deposit[] = '－';
    // 20201020 S_Update
//    if($contract['deposit2DayChk']=='1' && $contract['deposit2'] > 0) $deposit[] = '¥'.number_format($contract['deposit2']);
    if($contract['deposit2'] > 0) $deposit[] = '¥'.number_format($contract['deposit2']);
    // 20201020 E_Update
    else  $deposit[] = '－';
    // 20201020 S_Update
//    if($contract['earnestPriceDayChk']=='1' && $contract['earnestPrice'] > 0) $deposit[] = '¥'.number_format($contract['earnestPrice']);
    if($contract['earnestPrice'] > 0) $deposit[] = '¥'.number_format($contract['earnestPrice']);
    // 20201020 E_Update
    else  $deposit[] = '－';
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

    if($contract['deposit1DayChk'] == '1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') $depositDay[] = convert_jpdt($contract['deposit1Day']);
    else  $depositDay[] = '－';
    if($contract['deposit2DayChk'] == '1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') $depositDay[] = convert_jpdt($contract['deposit2Day']);
    else  $depositDay[] = '－';
    if($contract['earnestPriceDayChk'] == '1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
    else  $depositDay[] = '－';
    return implode(chr(10), $depositDay);
}

/**
 * 内金（手付等）取得（ﾒﾄﾛｽ買取シート）
 */
function getDeposit2($contract) { 
    $deposit = [];
    if($contract['deposit1DayChk'] == '1' && $contract['deposit1'] > 0) $deposit[] = '¥'.number_format($contract['deposit1']);
    else  $deposit[] = '－';
    if($contract['deposit2DayChk'] == '1' && $contract['deposit2'] > 0) $deposit[] = '¥'.number_format($contract['deposit2']);
    else  $deposit[] = '－';
    if($contract['earnestPriceDayChk'] == '1' && $contract['earnestPrice'] > 0) $deposit[] = '¥'.number_format($contract['earnestPrice']);
    else  $deposit[] = '－';
    return implode(chr(10), $deposit);
}

/**
 * 内金（手付）支払日取得（ﾒﾄﾛｽ買取シート）
 */
function getDepositDay2($contract) {
    $depositDay = [];
    if($contract['deposit1DayChk'] == '1' && isset($contract['deposit1Day']) && $contract['deposit1Day'] != '') $depositDay[] = convert_jpdt($contract['deposit1Day']);
    else  $depositDay[] = '－';
    if($contract['deposit2DayChk'] == '1' && isset($contract['deposit2Day']) && $contract['deposit2Day'] != '') $depositDay[] = convert_jpdt($contract['deposit2Day']);
    else  $depositDay[] = '－';
    if($contract['earnestPriceDayChk'] == '1' && isset($contract['earnestPriceDay']) && $contract['earnestPriceDay'] != '') $depositDay[] = convert_jpdt($contract['earnestPriceDay']);
    else  $depositDay[] = '－';
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