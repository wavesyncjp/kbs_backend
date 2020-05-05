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
$filePath = $fullPath.'/売買契約.xlsx'; 
//$filePath = $fullPath.$template['reportFormPath'].$template['reportFormName'];

//Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getSheet(0);


//契約者
$pos = searchCellPos($sheet, 'contractorName', 10);
$sellers = $contract['sellers'];
if(isset($sellers)) {
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
}

$keyword = 'contractorName';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    //A43 契約者名
    $pos = $nextPos;

    $name = '';
    if(sizeof($sellers) === 1) {
        $name = $sellers[0][$keyword];
    }
    else {
        $index = 0;
        $sl = [];
        foreach($sellers as $seller) {
            $sl[] = '「甲' . mb_convert_kana(($index + 1), 'N') . '」';
            if($index > 0) $name .= '売主：';
            if($index < sizeof($sellers) - 1) {                
                $name .= $seller[$keyword].'（以下「甲' . mb_convert_kana(($index + 1), 'N') . '」という。）';
            }
            else {
                $name .= $seller[$keyword].'（以下「甲' . mb_convert_kana(($index + 1), 'N') . '」といい' . implode('', $sl) . 'を併せて「甲」という。）';
            }

            $index++;
        }
    }

    bindCell('A'.$pos , $sheet, $keyword, $name);    
}


$keyword = 'tradingPrice';
$nextPos = searchCellPos($sheet, $keyword , $pos);
if($nextPos != -1) {
    // 売買代金
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword , formatNumber($contract[$keyword], true));    
}

$keyword = 'tradingLandPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    // 売買代金（土地）
    $pos = $nextPos;
    bindCell('A'.$pos, $sheet, $keyword, formatNumber($contract[$keyword], true));
}

$keyword = 'settlementAfter';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    // 和解成立後
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, $contract[$keyword]);
}

$keyword = 'tradingPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    // 売買代金
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], true));
}

//明渡期日
$val = '';
if(isset($contract['vacationDay']) && $contract['vacationDay'] !== '') {
    $val = date('Y年m月d日', strtotime($contract['vacationDay']));
}
else {
    $val = '□□□年□□月□□日';
}

$keyword = 'vacationDay';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, $val);
}
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, $val);
}


//優先分譲面積
$keyword = 'prioritySalesArea';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], false));
}

//優先分譲戸数（階）
$keyword = 'prioritySalesFloor';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword , formatNumber($contract[$keyword ], false));
}

//優先分譲予定価格
$keyword = 'prioritySalesPlanPrice';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if($nextPos != -1) {
    $pos = $nextPos;
    bindCell('A' . $pos, $sheet, $keyword, formatNumber($contract[$keyword], false));
}

//土地
$belongIds = []; //不可分
$detailIds = []; //売主
$teichiIds = []; //底地
foreach($contract['details'] as $detail) {
    if($detail['contractDataType'] == '01') {
        $detailIds[] = $detail['locationInfoPid'];
    }
    else if($detail['contractDataType'] === '02') {
        $belongIds[] = $detail['locationInfoPid'];
    }
    else if($detail['contractDataType'] === '03') {
        $teichiIds[] = $detail['locationInfoPid'];
    }
}

$matsubi1Comment = [];
$count = 0;
if(sizeof($detailIds) > 0) {
    $count = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('buildingNotyet', 1)->where_null('deleteDate')->count();
}
//$specialTerms7_start$
$keyword = 'specialTerms7_start';
$nextPos = searchCellPos($sheet, $keyword, $pos);
$keyword = 'specialTerms7_end';
$termEnd = searchCellPos($sheet, $keyword, $nextPos);
if($count > 0) {
    bindCell('A' . $nextPos, $sheet, 'specialTerms7_start' , '', false);
    bindCell('A' . $termEnd, $sheet, 'specialTerms7_end' , '', false);
    $matsubi1Comment[] = '※上記　　、相続手続き中';
}
else {
    while($termEnd >= $nextPos) {
        $sheet->removeRow($termEnd);
        $termEnd--;
    }    
}

//specialTerms8
$keyword = 'specialTerms8';
$nextPos = searchCellPos($sheet, $keyword, $pos);
$count = 0;
if(sizeof($detailIds) > 0) {
    $count = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('inheritanceNotyet', 1)->where_null('deleteDate')->count();
}
if($count > 0) {
    bindCell('A' . $nextPos, $sheet, 'specialTerms8' , '', false);
    $matsubi1Comment[] = '※土地、　　　　　　　　　　　地番上に未登記建物あり';
}
else {
    $sheet->removeRow($nextPos);        
}
$pos = $nextPos;

//specialTerms9_1
$keyword = 'specialTerms9_1';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if(sizeof($sellers) >= 2) {
    $term9 = [];
    for($cnt = 0 ; $cnt < sizeof($sellers) ; $cnt++) {
        $term9[] = '甲' . mb_convert_kana(($cnt + 1), 'N');
    }
    $str = '６　本契約に基づく甲の義務について、' . implode('、', $term9) . 'は、連帯して乙に対して責任を負';
    bindCell('A' . $nextPos, $sheet, 'specialTerms9_1' , $str, false);
}
else {
    $sheet->removeRow($nextPos);  
}

//specialTerms9_2
$keyword = 'specialTerms9_2';
$nextPos = searchCellPos($sheet, $keyword, $pos);
if(sizeof($sellers) >= 2) {
    bindCell('A' . $nextPos, $sheet, 'specialTerms9_2' , '', false);
}
else {
    $sheet->removeRow($nextPos); 
}
$pos = $nextPos;

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
        bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], [$seller['contractorAdress'], $seller['contractorName']]);
        $pos += $blockCount;
    }
}
else {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], ['', '']);
}


$codeLandList = ORM::for_table(TBLCODE)->where('code', '002')->where_null('deleteDate')->findArray();
$codeTypeList = ORM::for_table(TBLCODE)->where('code', '003')->where_null('deleteDate')->findArray();

//---------------------------------

//contractTypeTitle
$keyword = 'contractTypeTitle';
$pos = searchCellPos($sheet, $keyword, $pos);
if($template['reportFormType'] == '04') {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, 'contractTypeTitle', '借地権の場合、（次の土地に係る借地権）');
}
else if($template['reportFormType'] == '05') {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, 'contractTypeTitle', '地上権の場合、（次の土地に係る地上権）');
}
else if($template['reportFormType'] == '06') {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, 'contractTypeTitle', '敷地権付区分建物の場合、（敷地権の目的たる土地の表示）');
}
else {
    $sheet->removeRow($pos);
}

//荷主所有地(土地)
$locs = [];
if($template['reportFormType'] == '01' || $template['reportFormType'] == '03') {
    if(sizeof($detailIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
}
else if($template['reportFormType'] == '04' || $template['reportFormType'] == '05' || $template['reportFormType'] == '06') {
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

        //登記名義人
        $regists = getRegistrants($contract['details'], $loc);

        $keyword = 'l_address';
        $pos = searchCellPos($sheet, $keyword, $pos);
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['l_address', 'blockNumber', 'landCategory', 'area', 'sharer'], 
            [$loc['address'], $loc['blockNumber'], getCodeTitle($codeLandList, $loc['landCategory']), $loc['area'], sizeof($regists) > 0 ?  $regists[0] : "" ]); 

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
            copyRows($sheet,$pos + $increase, $pos, $increase - 1, 1, true);
            
            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - $increase - 1;

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
//土地荷主なし ->　削除
else {    
    //$sheet->removeRow($pos + $blockCount - 1); 
    for($i = 0 ; $i < $blockCount; $i++) {
        $sheet->removeRow($pos);    
    }        
}


//荷主所有地(建物)
$locs = [];
if($template['reportFormType'] == '05') {
    if(sizeof($detailIds) > 0) {
        $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
    }
}
else if($template['reportFormType'] != '01' && $template['reportFormType'] != '06') {
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
        $regists = getRegistrants($contract['details'], $loc);

        $keyword = 'b_address';
        $pos = searchCellPos($sheet, $keyword, $pos);
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['b_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer'], 
            [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType']), $loc['structure'], $loc['floorSpace'], sizeof($regists) > 0 ?  $regists[0] : "" ]); 

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
            copyRows($sheet,$pos + $increase, $pos, $increase - 1, 1, true);
            
            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - $increase - 1;

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
//土地荷主なし
else {
    //$sheet->removeRow($pos, $blockCount);
    for($i = 0 ; $i < $blockCount; $i++) {
        $sheet->removeRow($pos);    
    }  
}


//（一棟の建物の表示）
$keyword = 'ob_address';
$blockCount = 3;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($detailIds) > 0 && $template['reportFormType'] != '01') {
    $tempLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '04')->where_not_null('ridgePid')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
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
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['ob_address', 'structure', 'floorSpace'], [$loc['address'], $loc['structure'], $loc['floorSpace']]); 
    }

}
else {
    //$sheet->removeRow($pos-1, $blockCount+1);
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
            copyRows($sheet,$pos + $increase, $pos, $increase - 1, 1, true);
            
            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - $increase - 1;

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
    //$sheet->removeRow($pos-1, $blockCount+1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);    
    }    
}

//$matsubi1_comment$
$keyword = 'matsubi1_comment';
$pos = searchCellPos($sheet, $keyword, $pos);
if(sizeof($matsubi1Comment) > 0) {
    if(sizeof($matsubi1Comment) === 2) {
        copyBlock($sheet, $pos, 1, 1, true);
        $sheet->setCellValue('A' . $pos, $matsubi1Comment[0]);
        $sheet->setCellValue('A' . ($pos + 1), $matsubi1Comment[1]);
    }
    else {
        $sheet->setCellValue('A' . $pos, $matsubi1Comment[0]);
    }    
}
else {
    $sheet->removeRow($pos+1);
    $sheet->removeRow($pos);
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
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['fl_address', 'blockNumber', 'landCategory', 'area'], 
            [$loc['address'], $loc['blockNumber'], getCodeTitle($codeLandList, $loc['landCategory']), $loc['area']]);         

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
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        $keyword = 'fb_address';
        $pos = searchCellPos($sheet, $keyword, $pos);
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['fb_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace'], 
            [$loc['address'], $loc['buildingNumber'], getCodeTitle($codeTypeList, $loc['dependType']), $loc['structure'], $loc['floorSpace'] ]);         

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
$blockCount = 3;
$pos = searchCellPos($sheet, $keyword, $pos);
$locs = [];
if(sizeof($belongIds) > 0) {
    $tempLocs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '04')->where_not_null('ridgePid')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
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
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['ob_address', 'structure', 'floorSpace'], [$loc['address'], $loc['structure'], $loc['floorSpace']]); 
    }

}
else {
    //$sheet->removeRow($pos-1, $blockCount+1);
    for($i = 0 ; $i < $blockCount + 1; $i++) {
        $sheet->removeRow($pos-1);    
    }
}

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
        $regists = getRegistrants($contract['details'], $loc);

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
            copyRows($sheet,$pos + $increase, $pos, $increase - 1, 1, true);
            
            $keyword = 'repear_sharer';
            $pos = searchCellPos($sheet, $keyword, $pos) - $increase - 1;

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

//敷地面積
if($contract['equiExchangeFlg'] == 1) {
    $keyword = 'siteArea';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, ['siteArea', 'siteAvailableArea'], [$contract['siteArea'], $contract['siteAvailableArea']]); 

    $keyword = 'structure';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'structure', $contract['structure']); 

    $keyword = 'scale';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'scale', $contract['scale']); 

    $keyword = 'totalFloorArea';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'totalFloorArea', $contract['totalFloorArea']);
    
    $keyword = 'acquisitionConfirmDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'acquisitionConfirmDay', showDay($contract['acquisitionConfirmDay']));

    $keyword = 'startScheduledDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'startScheduledDay', showDay($contract['startScheduledDay']));

    $keyword = 'prioritySalesAgreementDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'prioritySalesAgreementDay', showDay($contract['prioritySalesAgreementDay']));

    $keyword = 'finishScheduledDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'finishScheduledDay', showDay($contract['finishScheduledDay']));

    $keyword = 'deliveryDay';
    $pos = searchCellPos($sheet, $keyword, $pos);
    bindCell('A' . $pos, $sheet, 'deliveryDay', showDay($contract['deliveryDay']));

}
else {
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
?>