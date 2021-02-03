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
$contract = getContractInfo($param->pid);
$templatePid = $param->templatePid;
$template = ORM::for_table(TBLCONTRACTTYPEFIX)->findOne($templatePid);

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
//$filePath = $fullPath.'/売買契約.xlsx';
$filePath = $fullPath.$template['reportFormPath'].$template['reportFormName'];

//Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getSheet(0);

//契約者
$pos = searchCellPos($sheet, 'contractorName', 10);
$sellers = $contract['sellers'];
if(isset($sellers)) {
    /*
    $seller = $sellers[0];
    $cellName = "A".$pos;
    $str = $sheet->getCell($cellName)->getValue();
    $sheet->setCellValue($cellName, str_replace('$contractorName$', $seller['contractorName'], $str));
    
    for($index = 1 ; $index <= 7 ; $index++) {
        $pos = $pos - 2;
        $cellName = "A".$pos;
        if(isset($contract['sellers'][$index])) {
            $obj = $sellers[$index];
            $sheet->setCellValue($cellName, $obj['contractorName'].'様');
        }
        else {
            $sheet->setCellValue($cellName, '');
        }
    }
    */
    $index = 1;

    foreach($sellers as $seller) {
        // 契約者が複数存在する場合
        if($index > 1) {
            $pos += 2;

            // 行の挿入
            $sheet->insertNewRowBefore($pos, 2);
            // セル結合
            $sheet->mergeCells('A' . $pos . ':A' . ($pos + 1));
        }

        $cellName = "A" . $pos;
        $sheet->setCellValue($cellName, $seller['contractorName'] . '様');

        $index++;
    }
}

$keyword = 'contractorName';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    //A43 契約者名
    $pos = $nextPos;

    $name = '';
    if(sizeof($sellers) === 1) {
        $name = $sellers[0][$keyword].'（以下「甲」という。）';
    } else {
        $index = 0;
        $sl = [];
        foreach($sellers as $seller) {
            $sl[] = '「甲' . mb_convert_kana(($index + 1), 'N') . '」';
            if($index > 0) $name .= '売主：';
            if($index < sizeof($sellers) - 1) {
                $name .= $seller[$keyword].'（以下「甲' . mb_convert_kana(($index + 1), 'N') . '」という。）';
            } else {
                $name .= $seller[$keyword].'（以下「甲' . mb_convert_kana(($index + 1), 'N') . '」といい' . implode('', $sl) . 'を併せて「甲」という。）';
            }
            $index++;
        }
    }
    bindCell('A'.$pos , $sheet, $keyword, $name);
}

// 実測精算単価
$val = '';
if(isset($contract['setlementPrice']) && $contract['setlementPrice'] !== '') {
    $val = $contract['setlementPrice'];
    // 20200925 S_Delete
    /*
    $val = round($val / 10000, 4);
    $val = number_format($val, 4);
    */
    // 20200925 E_Delete
    $val = mb_convert_kana($val, 'KVRN');
}

$keyword = 'setlementPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, $val);
}

// 売買代金
$keyword = 'tradingPrice';
$nextPos = searchCellPos($sheet, $keyword , $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword , formatNumber($contract[$keyword], true));
}

// 売買代金（土地）
$keyword = 'tradingLandPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A'.$pos, $sheet, $keyword, formatNumber($contract[$keyword], true));
}

// 売買代金（借地権）
$keyword = 'tradingLeasePrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A'.$pos, $sheet, $keyword, formatNumber($contract[$keyword], true));
}

// 和解成立後
$keyword = 'settlementAfter';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, $contract[$keyword]);
}

// 売買代金
$keyword = 'tradingPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], true));
}

// 明渡期日
$val = '';
if(isset($contract['vacationDay']) && $contract['vacationDay'] !== '') {
    // 20200913 S_Update
//    $val = date('Y年m月d日', strtotime($contract['vacationDay']));
    $val = mb_convert_kana(date('Y年m月d日', strtotime($contract['vacationDay'])), 'KVRN');
    // 20200913 E_Update
} else {
    $val = '　　　年　　月　　日';
}

$keyword = 'vacationDay';
// ２か所存在する場合があるため２回実行
for($i = 0 ; $i < 2; $i++) {
    $nextPos = searchCellPos($sheet, $keyword, $pos);
    if($nextPos != -1) {
        $pos = $nextPos;
        bindCell('A' . $pos, $sheet, $keyword, $val);
    }
}

// 20200914 S_Add
// 即決和解申請日
$keyword = 'settlementDay';
// 20210204 S_Update
/*
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    if(isset($contract[$keyword]) && $contract[$keyword] !== '') {
        $val = mb_convert_kana(date('Y年m月d日', strtotime($contract[$keyword])), 'KVRN');
    } else {
        $val = '　　　年　　月　　日';
    }
    bindCell('A' . $pos, $sheet, $keyword, $val);
}
*/
for($i = 0 ; $i < 3; $i++) {
    $nextPos = searchCellPos($sheet, $keyword, $pos);
    if($nextPos != -1) {
        $pos = $nextPos;
        if(isset($contract[$keyword]) && $contract[$keyword] !== '') {
            $val = mb_convert_kana(date('Y年m月d日', strtotime($contract[$keyword])), 'KVRN');
        } else {
            $val = '　　　年　　月　　日';
        }
        bindCell('A' . $pos, $sheet, $keyword, $val);
    }
}
// 20210204 E_Update
// 20200914 E_Add

// 優先分譲面積
$keyword = 'prioritySalesArea';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], false));
}

// 優先分譲戸数（階）
$keyword = 'prioritySalesFloor';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword , formatNumber($contract[$keyword ], false));
}

// 優先分譲予定価格
$keyword = 'prioritySalesPlanPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], false));
}

// 土地
$detailIds = []; // 売主
$belongIds = []; // 不可分
$teichiIds = []; // 底地
$contractAreas = []; // 地積のうち借地契約面積 20200925 Add
foreach($contract['details'] as $detail) {
    if($detail['contractDataType'] == '01') {
        $detailIds[] = $detail['locationInfoPid'];
        // 20201208 S_Update
//        $belongIds[] = $detail['locationInfoPid'];// 20200828 S_Add
        if($contract['indivisibleFlg'] === '1') $belongIds[] = $detail['locationInfoPid'];
        // 20201208 E_Update
    } else if($detail['contractDataType'] === '02') {
        // 20201208 S_Update
//        $belongIds[] = $detail['locationInfoPid'];
        if($contract['indivisibleFlg'] === '1') $belongIds[] = $detail['locationInfoPid'];
        // 20201208 E_Update
    } else if($detail['contractDataType'] === '03') {
        $teichiIds[] = $detail['locationInfoPid'];
        // 20200925 S_Add
        // Key:所在地情報PID,Value:地積のうち借地契約面積
        $contractAreas[$detail['locationInfoPid']] = $detail['contractArea'];
        // 20200925 E_Add
    }
}

$matsubi1Comment = [];
$count = 0;
if(sizeof($detailIds) > 0) {
    $count = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('inheritanceNotyet', 1)->where_null('deleteDate')->count();
}
//$specialTerms7_start$
if($pos < 150) $pos = 150;
$keyword = 'specialTerms7_start';
$nextPos = searchCellPos($sheet, $keyword, $pos);
$keyword = 'specialTerms7_end';
$termEnd = searchCellPos($sheet, $keyword, $nextPos);
if($count > 0) {
    bindCell('A' . $nextPos, $sheet, 'specialTerms7_start' , '', false);
    bindCell('A' . $termEnd, $sheet, 'specialTerms7_end' , '', false);
    $matsubi1Comment[] = '※上記　　、相続手続き中';
} else {
    while($termEnd >= $nextPos) {
        $sheet->removeRow($termEnd);
        $termEnd--;
    }
}

//specialTerms8
$keyword = 'specialTerms8';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos > 0) {
    $count = 0;
    if(sizeof($detailIds) > 0) {
        $count = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('buildingNotyet', 1)->where_null('deleteDate')->count();
    }
    if($count > 0) {
        bindCell('A' . $nextPos, $sheet, 'specialTerms8' , '', false);
        $matsubi1Comment[] = '※土地、　　　　　　　　　　　地番上に未登記建物あり';
    } else {
        $sheet->removeRow($nextPos);
    }
    $pos = $nextPos;
}

//specialTerms9_1
$keyword = 'specialTerms9_1';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos > 0) {
    if(sizeof($sellers) >= 2) {
        $term9 = [];
        for($cnt = 0 ; $cnt < sizeof($sellers) ; $cnt++) {
            $term9[] = '甲' . mb_convert_kana(($cnt + 1), 'N');
        }
        $str = '　　  ６　本契約に基づく甲の義務について、' . implode('、', $term9) . 'は、連帯して乙に対して責任を負';
    //    bindCell('A' . $nextPos, $sheet, 'specialTerms9_1' , $str, false);
        $sheet->setCellValue('A' . $nextPos, $str);
    } else {
        $sheet->removeRow($nextPos);
    }
}

//specialTerms9_2
$keyword = 'specialTerms9_2';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos > 0) {
    if(sizeof($sellers) >= 2) {
        bindCell('A' . $nextPos, $sheet, 'specialTerms9_2' , '', false);
    } else {
        $sheet->removeRow($nextPos);
    }
    $pos = $nextPos;
}

//契約者住所, 契約者名
$keyword = 'contractorAddress';
$pos = searchCellPos($sheet, $keyword, $pos);
if(sizeof($sellers) > 0) {
    $blockCount = 5;
    if(sizeof($sellers) > 1){
        copyBlock($sheet, $pos, $blockCount, (sizeof($sellers) - 1));
    }

    for($cursor = 0 ; $cursor < sizeof($sellers) ; $cursor++){
        $seller = $sellers[$cursor];
        $cellName = 'A' . $pos;
        // 20200913 S_Update
//        bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], [$seller['contractorAdress'], $seller['contractorName']]);
        bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], ['', '']);
        // 20200913 E_Update

        //$cellName = 'A' . ($pos + 3) ;
        //bindCell($cellName, $sheet, ['contractorName'], [$seller['contractorName']]);

        $pos += $blockCount;
    }
} else {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], ['', '']);

    //$cellName = 'A' . ($pos + 3) ;
    //bindCell($cellName, $sheet, ['contractorName'], ['']);
}

$codeLandList = ORM::for_table(TBLCODE)->where('code', '002')->where_null('deleteDate')->findArray();
$codeTypeList = ORM::for_table(TBLCODE)->where('code', '003')->where_null('deleteDate')->findArray();

//contractTypeTitle
$keyword = 'contractTypeTitle';
$pos = searchCellPos($sheet, $keyword, $pos);
if($template['reportFormType'] == '04') {
    $cellName = 'A' . $pos;
    // 20200828 S_Update
//    bindCell($cellName, $sheet, 'contractTypeTitle', '借地権の場合、（次の土地に係る借地権）');
    bindCell($cellName, $sheet, 'contractTypeTitle', '（次の土地に係る借地権）');
    // 20200828 E_Update
} else if($template['reportFormType'] == '05') {
    $cellName = 'A' . $pos;
    // 20200828 S_Update
//    bindCell($cellName, $sheet, 'contractTypeTitle', '地上権の場合、（次の土地に係る地上権）');
    bindCell($cellName, $sheet, 'contractTypeTitle', '（次の土地に係る地上権）');
    // 20200828 E_Update
} else if($template['reportFormType'] == '06') {
    $cellName = 'A' . $pos;
    // 20200828 S_Update
//    bindCell($cellName, $sheet, 'contractTypeTitle', '敷地権付区分建物の場合、（敷地権の目的たる土地の表示）');
    bindCell($cellName, $sheet, 'contractTypeTitle', '（敷地権の目的たる土地の表示）');
    // 20200828 E_Update
} else {
    $sheet->removeRow($pos);
}

//荷主所有地(土地)
$locs = [];
if($template['reportFormType'] == '01' || $template['reportFormType'] == '03') {
    if(sizeof($detailIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
} else if($template['reportFormType'] == '04' || $template['reportFormType'] == '05' || $template['reportFormType'] == '06') {
    if(sizeof($teichiIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $teichiIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
}
//土地荷主あり
$keyword = 'l_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$blockCount = 6;
if(isset($locs) && sizeof($locs) > 0) {
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        // 20200925 S_Add
        $contractArea = '';
        // 帳票フォーム種別が04:借地権の場合
        if($template['reportFormType'] == '04') {
            if(isset($contractAreas[$loc['pid']])) {
                $contractArea = '（うち、借地契約面積約' . $contractAreas[$loc['pid']] . '㎡）';
            }
        }
        // 20200925 E_Add

        //登記名義人
        // 20210204 S_Update
//        $regists = getRegistrants($contract['details'], $loc);
        $regists = getRegistrants($contract['details'], $loc, true);
        // 20210204 E_Update

        $keyword = 'l_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['l_address', 'blockNumber', 'landCategory', 'area', 'sharer']
            // 20200913 S_Update
//            , [$loc['address'], $loc['blockNumber'], getCodeTitle($codeLandList, $loc['landCategory']), $loc['area'], sizeof($regists) > 0 ?  $regists[0] : "" ]);
            // 20210204 S_Update
//            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['blockNumber'], 'KVRN'), getCodeTitle($codeLandList, $loc['landCategory']), mb_convert_kana(number_format($loc['area'], 2) . '㎡' . $contractArea, 'KVRN'), sizeof($regists) > 0 ?  $regists[0] : "" ]);
            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['blockNumber'], 'KVRN'), getCodeTitle($codeLandList, $loc['landCategory']), mb_convert_kana(number_format($loc['area'], 2) . '㎡' . $contractArea, 'KVRN'), sizeof($regists) > 0 ? mb_convert_kana($regists[0], 'KVRN') : "" ]);
            // 20210204 E_Update
            // 20200913 E_Update

        //[土地]文言削除
        if($cursor > 0) {
            $val = str_replace('土地', '    ', $sheet->getCell($cellName)->getValue());
            $sheet->setCellValue($cellName, $val);
        }

        //登記名義人複数
        $pos = $pos + $blockCount - 1;
        if(sizeof($regists) > 1) {
            $increase = sizeof($regists) - 1;

            //登記人分をコピー
            $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
            $sheet->insertNewRowBefore($pos, $increase);
            copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);   

            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - 1;

            foreach($regists as $regist) {
                $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                // 20210204 S_Update
//                $sheet->setCellValue('A' .  $pos, $val);
                $sheet->setCellValue('A' . $pos, mb_convert_kana($val, 'KVRN'));
                // 20210204 E_Update
                $pos++;
            }
            $sheet->setCellValue('A' .  $pos, '');
        } else {
            $sheet->setCellValue('A' .  $pos, '');
        }
    }
}
//土地荷主なし ->　削除
else {
    // 20210113 S_Delete
    /*
    for($i = 0 ; $i < $blockCount; $i++) {
        $sheet->removeRow($pos);
    }
    */
    // 20210113 E_Delete
//    $sheet->setCellValue('A' .  $pos, '');
    // 20210113 S_Add
    for($i = 0 ; $i < $blockCount - 1; $i++) {
        $sheet->removeRow($pos);
    }

    $keyword = 'repear_sharer';
    $pos = searchCellPos($sheet, $keyword, $pos);
    $sheet->setCellValue('A' .  $pos, '');
    $sheet->removeRow($pos - 1);
    // 20210113 E_Add
}

//荷主所有地(建物)
$locs = [];
if($template['reportFormType'] == '05') {
    if(sizeof($detailIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
} else if($template['reportFormType'] != '01' && $template['reportFormType'] != '06') {
    if(sizeof($detailIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '02')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
}
//土地荷主あり
$keyword = 'b_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$cellName = 'A' . $pos;
$blockCount = 7;
if(isset($locs) && sizeof($locs) > 0) {
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        //登記名義人
        // 20210204 S_Update
//        $regists = getRegistrants($contract['details'], $loc);
        $regists = getRegistrants($contract['details'], $loc, true);
        // 20210204 E_Update

        $keyword = 'b_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['b_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer']
            //20200913 S_Update
            /*
            , [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType']), 
            , replaceNewLine($loc['structure'], 14, 1), 
            , replaceNewLine($loc['floorSpace'], 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
            */
            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $loc['dependType'], '　')
            , replaceNewLine(mb_convert_kana($loc['structure'], 'KVRN'), 14, 1)
            // 20210204 S_Update
//            , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
            , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 14, 1), sizeof($regists) > 0 ? mb_convert_kana($regists[0], 'KVRN') : "" ]);
            // 20210204 E_Update
            //20200913 E_Update

        // 20200915 S_Add
        $lineCount = countNewLine($loc['structure']);
        $lineCount += countNewLine($loc['floorSpace']);
        if($lineCount > 2) {
            // セルの高さを調整
            $newHeight = 18.8 * (4 + $lineCount) / ($blockCount - 1);
            for($rowPos = 0 ; $rowPos < $blockCount - 1 ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add

        //[土地]文言削除
        if($cursor > 0) {
            $val = str_replace('建物', '    ', $sheet->getCell($cellName)->getValue());
            $sheet->setCellValue($cellName, $val);
        }

        //登記名義人複数
        $pos = $pos + $blockCount - 1;
        if(sizeof($regists) > 1) {
            $increase = sizeof($regists) - 1;

            //登記人分をコピー
            $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
            $sheet->insertNewRowBefore($pos, $increase);
            copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);

            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - 1;

            foreach($regists as $regist) {
                $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                // 20210204 S_Update
//                $sheet->setCellValue('A' .  $pos, $val);
                $sheet->setCellValue('A' . $pos, mb_convert_kana($val, 'KVRN'));
                // 20210204 E_Update
                $pos++;
            }
            $sheet->setCellValue('A' .  $pos, '');
        } else {
            $sheet->setCellValue('A' .  $pos, '');
        }
    }
}
//土地荷主なし
else {
    for($i = 0 ; $i < $blockCount; $i++) {
        $sheet->removeRow($pos);
    }
//    $sheet->setCellValue('A' .  $pos, '');
}

// 20201011 S_Update
/**********************************************************************************************
//（一棟の建物の表示）
$keyword = 'ob_address';
$blockCount = 3;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($detailIds) > 0 && $template['reportFormType'] != '01') {
    $tempLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_not_null('ridgePid')
                ->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    foreach($tempLocs as $temp) {
        $locs[] = ORM::for_table(TBLLOCATIONINFO)->findOne($temp['ridgePid'])->asArray();
    }
}
if(sizeof($locs) > 0) {
    //ブロックコピー
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        $keyword = 'ob_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        // 20200915 E_Add

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['ob_address', 'structure', 'floorSpace']
        , [mb_convert_kana($loc['address'], 'KVRN')
        , replaceNewLine($loc['structure'], 14, 1)
        , replaceNewLine($loc['floorSpace'], 14, 1)]);

        // 20200915 S_Add
        $lineCount = countNewLine(mb_convert_kana($loc['structure'], 'KVRN'));
        $lineCount += countNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'));
        if($lineCount > 2) {
            // セルの高さを調整
            $newHeight = 18.8 * (1 + $lineCount) / ($blockCount);
            for($rowPos = 0 ; $rowPos < $blockCount ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add
    }
} else {
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}

//（専有部分の建物の表示）
$keyword = 'p_address';
$blockCount = 7;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($detailIds) > 0 && $template['reportFormType'] != '01') {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}

if(sizeof($locs) > 0) {
    //ブロックコピー
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        //登記名義人
        $regists = getRegistrants($contract['details'], $loc);

        $keyword = 'p_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        // 20200915 E_Add
        
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['p_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer']
            // 20200913 S_Update
            
            //, [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType'])
            //, replaceNewLine($loc['structure'], 14, 1)
            //, replaceNewLine($loc['floorSpace'], 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
            
            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $loc['dependType'])
            , replaceNewLine(mb_convert_kana($loc['structure'], 'KVRN'), 14, 1)
            , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
            // 20200913 E_Update

        // 20200915 S_Add
        $lineCount = countNewLine(mb_convert_kana($loc['structure'], 'KVRN'));
        $lineCount += countNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'));
        if($lineCount > 2) {
            // セルの高さを調整
            $newHeight = 18.8 * (4 + $lineCount) / ($blockCount - 1);
            for($rowPos = 0 ; $rowPos < $blockCount - 1 ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add

        //登記名義人複数
        $pos = $pos + $blockCount - 1;
        if(sizeof($regists) > 1) {
            $increase = sizeof($regists) - 1;

            //登記人分をコピー
            $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
            $sheet->insertNewRowBefore($pos, $increase);
            copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);

            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - 1;

            foreach($regists as $regist) {
                $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                $sheet->setCellValue('A' .  $pos, $val);
                $pos++;
            }
            $sheet->setCellValue('A' .  $pos, '');
        } else {
            $sheet->setCellValue('A' .  $pos, '');
        }
        $sheet->removeRow($pos);// 20201008 Add
    }
} else {
    //$sheet->removeRow($pos-1, $blockCount+1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}
*************************************************************************/
//（一棟の建物の表示）
$keyword = 'ob_address';
//$blockCount = 3;
$blockCount = 10;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($detailIds) > 0 && $template['reportFormType'] != '01') {
    $tempLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_not_null('ridgePid')
                ->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->distinct()->findArray();
    $ids = [];
    foreach($tempLocs as $temp) {
        if(!in_array($temp['ridgePid'], $ids)) {
            $ids[] = $temp['ridgePid'];
            $locs[] = ORM::for_table(TBLLOCATIONINFO)->findOne($temp['ridgePid'])->asArray();
        }
    }
}
if(sizeof($locs) > 0) {
    //ブロックコピー
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        $keyword = 'ob_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        // 20200915 E_Add

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['ob_address', 'structure', 'floorSpace']
        , [mb_convert_kana($loc['address'], 'KVRN')
        , replaceNewLine(mb_convert_kana($loc['structure'], 'KVRN'), 14, 1)
        , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 14, 1)]);

        // 20200915 S_Add
        $lineCount = countNewLine($loc['structure']);
        $lineCount += countNewLine($loc['floorSpace']);
        if($lineCount > 2) {
            // セルの高さを調整
            //$newHeight = 18.8 * (1 + $lineCount) / ($blockCount);
            $newHeight = 18.8 * (1 + $lineCount) / 3;
            //for($rowPos = 0 ; $rowPos < $blockCount ; $rowPos++) {
            for($rowPos = 0 ; $rowPos < 3 ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add

        //（専有部分の建物の表示）
//        $keyword = 'p_address';
        $keyword = 'p_buildingNumber';
        //$blockCount = 7;
        $subBlockCount = 6;
        $pos = searchCellPos($sheet, $keyword, $pos);
        //$locs = [];
        $subLocs = [];
        if(sizeof($detailIds) > 0 && $template['reportFormType'] != '01') {
            //$locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
            $subLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where('ridgePid', $loc['pid'])->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
        }

        if(sizeof($subLocs) > 0) {
            //ブロックコピー
            if(sizeof($subLocs) > 1) {
                copyBlock($sheet, $pos, $subBlockCount, (sizeof($subLocs) - 1), true);
            }
            
            //データ出力（ループ）
            for($subCursor = 0 ; $subCursor < sizeof($subLocs) ; $subCursor++){
                $subLoc = $subLocs[$subCursor];

                //登記名義人
                //$regists = getRegistrants($contract['details'], $loc);
                // 20210204 S_Update
//                $regists = getSharers($subLoc);
                $regists = getSharers($subLoc, true);
                // 20210204 E_Update

//                $keyword = 'p_address';
                $keyword = 'p_buildingNumber';
                $pos = searchCellPos($sheet, $keyword, $pos);

                // 20200915 S_Add
                /*
                if($cursor > 0) {
                    // 1行挿入
                    $sheet->insertNewRowBefore($pos);
                    $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
                    $pos++;
                }
                */
                // 20200915 E_Add

                $cellName = 'A' . $pos;
//                bindCell($cellName, $sheet, ['p_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer']
                bindCell($cellName, $sheet, ['p_buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer']
                    // 20200913 S_Update
                    /*
                    , [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType'])
                    , replaceNewLine($loc['structure'], 14, 1)
                    , replaceNewLine($loc['floorSpace'], 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
                    */
//                    , [mb_convert_kana($subLoc['address'], 'KVRN'), mb_convert_kana($subLoc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $subLoc['dependType'], '　')
                    , [mb_convert_kana($subLoc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $subLoc['dependType'], '　')
                    , replaceNewLine(mb_convert_kana($subLoc['structure'], 'KVRN'), 14, 1)
                    // 20210204 S_Update
//                    , replaceNewLine(mb_convert_kana($subLoc['floorSpace'], 'KVRN'), 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
                    , replaceNewLine(mb_convert_kana($subLoc['floorSpace'], 'KVRN'), 14, 1), sizeof($regists) > 0 ? mb_convert_kana($regists[0], 'KVRN') : "" ]);
                    // 20210204 E_Update
                    // 20200913 E_Update

                // 20200915 S_Add
                $lineCount = countNewLine($subLoc['structure']);
                $lineCount += countNewLine($subLoc['floorSpace']);
                if($lineCount > 2) {
                    // セルの高さを調整
                    $newHeight = 18.8 * (3 + $lineCount) / ($subBlockCount - 1);
                    for($rowPos = 0 ; $rowPos < $subBlockCount - 1 ; $rowPos++) {
                        $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
                    }
                }
                // 20200915 E_Add

                //登記名義人複数
                $pos = $pos + $subBlockCount - 1;
                if(sizeof($regists) > 1) {
                    $increase = sizeof($regists) - 1;

                    //登記人分をコピー
                    $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
                    $sheet->insertNewRowBefore($pos, $increase);
                    copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);

                    $keyword = 'repear_sharer';
                    $pos = searchCellPos($sheet, $keyword, $pos) - 1;

                    foreach($regists as $regist) {
                        $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                        // 20210204 S_Update
//                        $sheet->setCellValue('A' .  $pos, $val);
                        $sheet->setCellValue('A' . $pos, mb_convert_kana($val, 'KVRN'));
                        // 20210204 E_Update
                        $pos++;
                    }
                    $sheet->setCellValue('A' .  $pos, '');
                } else {
                    $sheet->setCellValue('A' .  $pos, '');
                }
                //$sheet->removeRow($pos);// 20201008 Add
            }
        } else {
            //$sheet->removeRow($pos-1, $blockCount+1);
            for($i = 0 ; $i < $subBlockCount + 1; $i++) {
                $sheet->removeRow($pos-1);
            }
        }
    }
} else {
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}
// 20201011 E_Update

//$matsubi1_comment$
$keyword = 'matsubi1_comment';
$lastPos = $pos;
$pos = searchCellPos($sheet, $keyword, $pos);
if($pos > 0) {
    if(sizeof($matsubi1Comment) > 0) {
        if(sizeof($matsubi1Comment) === 2) {
            copyBlock($sheet, $pos, 1, 1, true);
            $sheet->setCellValue('A' . $pos, $matsubi1Comment[0]);
            $sheet->setCellValue('A' . ($pos + 1), $matsubi1Comment[1]);
        } else {
            $sheet->setCellValue('A' . $pos, $matsubi1Comment[0]);
        }
    } else {
        $sheet->removeRow($pos+1);
        $sheet->removeRow($pos);
    }
} else {
    $pos = $lastPos;
}

// 不可分が存在しない場合、末尾２　＜本計画地の表示＞を削除
if(sizeof($belongIds) == 0) {
    $keyword = 'fl_address';
    $pos = searchCellPos($sheet, $keyword, $pos);
    $pos -= 3;// 末尾２　＜本計画地の表示＞の位置を設定

    for($i = 1 ; $i >= 0; $i--) {
        $sheet->removeRow($pos + $i);
    }
}

//不可分所有地(土地)
$locs = [];
if(sizeof($belongIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}

$keyword = 'fl_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$blockCount = 4;
if(isset($locs) && sizeof($locs) > 0) {
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }    

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        $keyword = 'fl_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        // 20200915 E_Add

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['fl_address', 'blockNumber', 'landCategory', 'area']
            // 20200913 S_Update
//            , [$loc['address'], $loc['blockNumber'], getCodeTitle($codeLandList, $loc['landCategory']), $loc['area']]);
            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['blockNumber'], 'KVRN'), getCodeTitle($codeLandList, $loc['landCategory']), mb_convert_kana(number_format($loc['area'], 2), 'KVRN') . '㎡']);
            // 20200913 E_Update
    }
}
//土地荷主なし
else {
    //$sheet->removeRow($pos - 1, $blockCount + 1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}

//不可分所有地(建物)
$locs = [];
if(sizeof($belongIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '02')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}

$keyword = 'fb_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$blockCount = 5;
if(isset($locs) && sizeof($locs) > 0) {
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }    

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++) {
        $loc = $locs[$cursor];

        $keyword = 'fb_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        // 20200915 E_Add

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['fb_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace']
            // 20200913 S_Update
            /*
            , [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType'])
            , replaceNewLine($loc['structure'], 9, 1), 
            , replaceNewLine($loc['floorSpace'], 9, 1) ]);
            */
            , [mb_convert_kana($loc['address'], 'KVRN'), mb_convert_kana($loc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $loc['dependType'], '　')
            , replaceNewLine(mb_convert_kana($loc['structure'], 'KVRN'), 9, 1)
            , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 9, 1) ]);
            // 20200913 E_Update

        // 20200915 S_Add
        $lineCount = countNewLine($loc['structure']);
        $lineCount += countNewLine($loc['floorSpace']);
        if($lineCount > 2) {
            // セルの高さを調整
            $newHeight = 18.8 * (3 + $lineCount) / ($blockCount);
            for($rowPos = 0 ; $rowPos < $blockCount ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add
    }
}
//土地荷主なし
else {
    //$sheet->removeRow($pos - 1, $blockCount + 1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}

//（一棟の建物の表示）
$keyword = 'ob_address';
//$blockCount = 11;
$blockCount = 9;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($belongIds) > 0) {
    $tempLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '04')->where_not_null('ridgePid')->where_null('deleteDate')
                ->order_by_asc('locationType')->order_by_asc('pid')
                ->distinct()->findArray();
    $ids = [];
    foreach($tempLocs as $temp) {
        if(!in_array($temp['ridgePid'], $ids)) {
            $ids[] = $temp['ridgePid'];
            $locs[] = ORM::for_table(TBLLOCATIONINFO)->findOne($temp['ridgePid'])->asArray();
        }
    }
}
if(sizeof($locs) > 0) {
    //ブロックコピー
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos-1, $blockCount + 1, (sizeof($locs) - 1), true);
    }

    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        $keyword = 'ob_address';
        $pos = searchCellPos($sheet, $keyword, $pos);

        // 20200915 S_Add
        /*
        if($cursor > 0) {
            // 1行挿入
            $sheet->insertNewRowBefore($pos);
            $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
            $pos++;
        }
        */
        // 20200915 E_Add

        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['ob_address', 'structure', 'floorSpace']
            , [mb_convert_kana($loc['address'], 'KVRN')
            , replaceNewLine(mb_convert_kana($loc['structure'], 'KVRN'), 14, 1)
            , replaceNewLine(mb_convert_kana($loc['floorSpace'], 'KVRN'), 14, 1)]
        );

        // 20200915 S_Add
        $lineCount = countNewLine($loc['structure']);
        $lineCount += countNewLine($loc['floorSpace']);
        if($lineCount > 2) {
            // セルの高さを調整
            $newHeight = 18.8 * (1 + $lineCount) / 3;
            for($rowPos = 0 ; $rowPos < 3 ; $rowPos++) {
                $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
            }
        }
        // 20200915 E_Add

        //（専有部分の建物の表示）
//        $keyword = 'p_address';
        $keyword = 'p_buildingNumber';
//        $subBlockCount = 7;
        $subBlockCount = 5;
        $pos = searchCellPos($sheet, $keyword, $pos);
        $subLocs = [];
        if(sizeof($belongIds) > 0) {
            $subLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)
                    ->where('locationType', '04')->where('ridgePid', $loc['pid'])
                    ->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
        }
        if(sizeof($subLocs) > 0) {
            //ブロックコピー
            if(sizeof($subLocs) > 1) {
                copyBlock($sheet, $pos, $subBlockCount, (sizeof($subLocs) - 1), true);
            }

            //データ出力（ループ）
            for($subCursor = 0 ; $subCursor < sizeof($subLocs) ; $subCursor++){
                $subLoc = $subLocs[$subCursor];

                //登記名義人
//                $regists = getSharers($subLoc);// 20210204 Delete

//                $keyword = 'p_address';
                $keyword = 'p_buildingNumber';
                $pos = searchCellPos($sheet, $keyword, $pos);

                // 20200915 S_Add
                /*
                if($cursor > 0) {
                    // 1行挿入
                    $sheet->insertNewRowBefore($pos);
                    $sheet->getRowDimension($pos)->setRowHeight(18.8);// 20200917 Add
                    $pos++;
                }
                */
                // 20200915 E_Add

                $cellName = 'A' . $pos;
                // 20201008 S_Update
                /*
                bindCell($cellName, $sheet, ['p_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer']
                */
//                bindCell($cellName, $sheet, ['p_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace']
                bindCell($cellName, $sheet, ['p_buildingNumber', 'dependType', 'structure', 'floorSpace']
                // 20201008 E_Update
                    // 20200913 S_Update
                    /*
                    , [$subLoc['address'], $subLoc['buildingNumber'], getCodeTitle($codeTypeList, $subLoc['dependType'])
                    , replaceNewLine($subLoc['structure'], 14, 1)
                    , replaceNewLine($subLoc['floorSpace'], 14, 1), sizeof($regists) > 0 ?  $regists[0] : "" ]);
                    */
//                    , [mb_convert_kana($subLoc['address'], 'KVRN'), mb_convert_kana($subLoc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $subLoc['dependType'], '　')
                    , [mb_convert_kana($subLoc['buildingNumber'], 'KVRN'), getCodeTitle($codeTypeList, $subLoc['dependType'], '　')
                    , replaceNewLine(mb_convert_kana($subLoc['structure'], 'KVRN'), 14, 1)
                    , replaceNewLine(mb_convert_kana($subLoc['floorSpace'], 'KVRN'), 14, 1)]);
                    // 20200913 E_Update

                // 20200915 S_Add
                $lineCount = countNewLine($subLoc['structure']);
                $lineCount += countNewLine($subLoc['floorSpace']);
                if($lineCount > 2) {
                    // セルの高さを調整
                    // 20201008 S_Update
                    /*
                    $newHeight = 18.8 * (4 + $lineCount) / ($subBlockCount - 1);
                    */
                    $newHeight = 18.8 * (2 + $lineCount) / ($subBlockCount - 2);
                    // 20201008 E_Update
                    for($rowPos = 0 ; $rowPos < $subBlockCount - 2 ; $rowPos++) {
                        $sheet->getRowDimension($pos + $rowPos)->setRowHeight($newHeight);
                    }
                }
                // 20200915 E_Add

                // 20201008 S_Delete
                /*
                //登記名義人複数
                $pos = $pos + $subBlockCount - 1;
                if(sizeof($regists) > 1) {
                    $increase = sizeof($regists) - 1;

                    //登記人分をコピー
                    $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
                    $sheet->insertNewRowBefore($pos, $increase);
                    copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);   

                    $keyword = 'repear_sharer';
                    $pos = searchCellPos($sheet, $keyword, $pos) - 1;

                    foreach($regists as $regist) {
                        $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                        $sheet->setCellValue('A' .  $pos, $val);
                        $pos++;
                    }
                    $sheet->setCellValue('A' .  $pos, '');
                } else {
                    $sheet->setCellValue('A' .  $pos, '');
                }
                $sheet->removeRow($pos);
                */
                // 20201008 E_Delete
            }
        } else {
            for($i = 0 ; $i < $subBlockCount + 1; $i++) {
                $sheet->removeRow($pos-1);
            }
        }
    }
} else {
    //$sheet->removeRow($pos-1, $blockCount+1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}

/**********************************************************************************************
//（専有部分の建物の表示）
$keyword = 'p_address';
$blockCount = 7;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($belongIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '04')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}
if(sizeof($locs) > 0) {

    //ブロックコピー
    if(sizeof($locs) > 1) {
        copyBlock($sheet, $pos, $blockCount, (sizeof($locs) - 1), true);
    }
    //データ出力（ループ）
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        //登記名義人
        $regists = getSharers($loc);

        $keyword = 'p_address';
        $pos = searchCellPos($sheet, $keyword, $pos);
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['p_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer'],
            [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType']), $loc['structure'], $loc['floorSpace'], sizeof($regists) > 0 ?  $regists[0] : "" ]);

        //登記名義人複数
        $pos = $pos + $blockCount - 1;
        if(sizeof($regists) > 1) {
            $increase = sizeof($regists) - 1;

            //登記人分をコピー
            $sharerStr = $sheet->getCell('A' .  $pos)->getValue();
            $sheet->insertNewRowBefore($pos, $increase);
            copyRowsReverse($sheet,$pos + $increase, $pos, $increase, 1, true, false);   

            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - 1;

            foreach($regists as $regist) {
                $val = str_replace('$repear_sharer$', $regist, $sharerStr);
                $sheet->setCellValue('A' .  $pos, $val);
                $pos++;
            }
            $sheet->setCellValue('A' .  $pos, '');
        }
        else {
            $sheet->setCellValue('A' .  $pos, '');
        }

    }

}
else {
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);
    }
}
*************************************************************************/

//敷地面積
if($contract['equiExchangeFlg'] == 1) {
    //敷地面積・有効敷地面積
    $keyword = 'siteArea';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, ['siteArea', 'siteAvailableArea'], [$contract['siteArea'], $contract['siteAvailableArea']]);
    //構造
    $keyword = 'structure';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'structure', $contract['structure']);
    //規模
    $keyword = 'scale';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'scale', $contract['scale']);
    //延床面積
    $keyword = 'totalFloorArea';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'totalFloorArea', $contract['totalFloorArea']);
    //建築確認取得
    $keyword = 'acquisitionConfirmDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'acquisitionConfirmDay', showDay($contract['acquisitionConfirmDay']));
    //計画建物着工
    $keyword = 'startScheduledDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'startScheduledDay', showDay($contract['startScheduledDay']));
    //優先分譲契約締結
    $keyword = 'prioritySalesAgreementDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'prioritySalesAgreementDay', showDay($contract['prioritySalesAgreementDay']));
    //計画建物竣工
    $keyword = 'finishScheduledDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'finishScheduledDay', showDay($contract['finishScheduledDay']));
    //取得物件引渡
    $keyword = 'deliveryDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'deliveryDay', showDay($contract['deliveryDay']));
} else {
    $keyword = 'siteArea';
    $pos = searchCellPos($sheet, $keyword, $pos);
    for($i = 0 ; $i < 12; $i++) {
        $sheet->removeRow($pos-1);
    }
}

//保存
$filename = date("YmdHis") . 'xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

//ダウンロード
readfile($savePath);

//削除
unlink($savePath);

/**
 * 改行を全角スペース＋半角スペースに変換
 */
function replaceNewLine($val, $zenSpace, $hanSpace) {
    $str = PHP_EOL;
    for($i = 0 ; $i < $zenSpace; $i++) {
        $str .= '　';
    }
    for($i = 0 ; $i < $hanSpace; $i++) {
        $str .= ' ';
    }
    return preg_replace("/\r|\n/", $str, $val);//str_replace('\r', $str, $val);
}
// 20200915 S_Add
/**
 * 改行数カウント
 */
function countNewLine($val) {
    $newVal = preg_replace("/\r|\n/", 'BR', $val);
    return count(explode('BR', $newVal));
}
// 20200915 E_Add
?>