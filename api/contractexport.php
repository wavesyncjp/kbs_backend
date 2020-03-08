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

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");


$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/contract/landBuilding/売買契約書【土地建物・即決和解あり・公簿・不可分あり・等価交換あり】.xlsx'; 

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
    bindCell('A'.$pos , $sheet, $keyword, $seller[$keyword]);    
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


$keyword = 'contractorAddress';
$pos = searchCellPos($sheet, $keyword, $pos);
if(sizeof($sellers) > 0) {
    $blockCount = 5;
    if(sizeof($sellers) > 1){
        copyBlock($sheet, $pos, $blockCount, (sizeof($sellers) - 1));
    }    
    for($cursor = 0 ; $cursor < sizeof($sellers) ; $cursor++){
        $seller = $sellers[$cursor];
        $pos += $cursor * $blockCount;
        $cellName = 'A' . $pos;
        bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], [$seller['contractorAddress'], $seller['contractorName']]);
    }
}
else {
    $cellName = 'A' . $pos;
    bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], ['', '']);
}


$codeLandList = ORM::for_table(TBLCODE)->where('code', '002')->where_null('deleteDate')->findArray();
$codeTypeList = ORM::for_table(TBLCODE)->where('code', '003')->where_null('deleteDate')->findArray();

//土地
$belongIds = []; //不可分
$detailIds = []; //売主
foreach($contract['details'] as $detail) {
    if($detail['contractDataType'] == '01') {
        $detailIds[] = $detail['locationInfoPid'];
    }
    else if($detail['contractDataType'] === '02') {
        $belongIds[] = $detail['locationInfoPid'];
    }
}

//荷主所有地(土地)
$locs = [];
if(sizeof($detailIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}

//土地荷主あり
$keyword = 'l_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$cellName = 'A' . $pos;
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
//土地荷主なし
else {
    bindCell($cellName, $sheet, ['l_address', 'blockNumber', 'landCategory', 'area', 'sharer'], ['', '', '', '', '']);
    $pos += ($blockCount - 1);
    $sheet->setCellValue('A' .  $pos, '');
}


//荷主所有地(建物)
$locs = [];
if(sizeof($detailIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)->where('locationType', '02')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
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
    bindCell($cellName, $sheet, ['b_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace', 'sharer'], ['', '', '', '', '', '']);
    $pos += ($blockCount - 1);
    $sheet->setCellValue('A' .  $pos, '');
}

//不可分所有地(土地)
$locs = [];
if(sizeof($belongIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '01')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}

$keyword = 'fl_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$cellName = 'A' . $pos;
if(isset($locs) && sizeof($locs) > 0) {

    $blockCount = 5;

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
    bindCell($cellName, $sheet, ['fl_address', 'blockNumber', 'landCategory', 'area'], ['', '', '', '']);
}


//不可分所有地(建物)
$locs = [];
if(sizeof($belongIds) > 0) {
    $locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $belongIds)->where('locationType', '02')->where_null('deleteDate')->order_by_asc('locationType')->order_by_asc('pid')->findArray();
}
$keyword = 'fb_address';
$pos = searchCellPos($sheet, $keyword, $pos);
$cellName = 'A' . $pos;
if(isset($locs) && sizeof($locs) > 0) {
    $blockCount = 6;

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
    bindCell($cellName, $sheet, ['fb_address', 'buildingNumber', 'dependType', 'structure', 'floorSpace'], ['', '', '', '', '']);
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