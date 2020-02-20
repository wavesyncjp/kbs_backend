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
$pos = 28;
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

//A43 契約者名
bindCell('A43', $sheet, 'contractorName', $seller['contractorName']);

// 売買代金
bindCell('A64', $sheet, 'tradingPrice', formatNumber($contract['tradingPrice'], true));

// 売買代金（土地）
bindCell('A65', $sheet, 'tradingLandPrice', formatNumber($contract['tradingLandPrice'], true));

// 和解成立後
bindCell('A70', $sheet, 'settlementAfter', $contract['settlementAfter']);

// 売買代金
bindCell('A71', $sheet, 'tradingPrice', formatNumber($contract['tradingPrice'], true));

//明渡期日
$val = '';
if(isset($contract['vacationDay']) && $contract['vacationDay'] !== '') {
    $val = date('Y年m月d日', strtotime($contract['vacationDay']));
}
bindCell('A87', $sheet, 'vacationDay', $val);
bindCell('A92', $sheet, 'vacationDay', $val);

//優先分譲面積
bindCell('A115', $sheet, 'prioritySalesArea', formatNumber($contract['prioritySalesArea'], false));

//優先分譲戸数（階）
bindCell('A116', $sheet, 'prioritySalesFloor', formatNumber($contract['prioritySalesFloor'], false));

//優先分譲予定価格
bindCell('A118', $sheet, 'prioritySalesPlanPrice', formatNumber($contract['prioritySalesPlanPrice'], false));

$increaseRow = 0;
$ownerPos = 258;
if(sizeof($sellers) > 1) {
    $blockCount = 5;
    copyBlock($sheet, $ownerPos, $blockCount, (sizeof($sellers) - 1));
    for($cursor = 0 ; $cursor < sizeof($sellers) ; $cursor++){
        $seller = $sellers[$cursor];
        $cellName = 'A' . ($ownerPos + $cursor * $blockCount);
        bindCell($cellName, $sheet, ['contractorAddress', 'contractorName'], [$seller['contractorAddress'], $seller['contractorName']]);
    }
    $increaseRow += $blockCount * (sizeof($sellers) - 1);
}


//末尾
//土地
$landPos = 272;
$owners = [];
$belongs = [];
$detailIds = [];
foreach($contract['details'] as $detail) {
    if($detail['contractDataType'] == '01') {
        $owners[] = $detail;
        $detailIds[] = $detail['locationInfoPid'];
    }
    else if($detail['contractDataType'] === '03') {
        $belongs[] = $detail;
    }
}


//荷主所有地(土地)
$locs = ORM::for_table(TBLLOCATIONINFO)->where_in('pid', $detailIds)
                                       ->where('locationType', '01')
                                       ->order_by_asc('locationType')
                                       ->order_by_asc('pid')->findArray();

$codeList = ORM::for_table(TBLCODE)->where('code', '002')->where_null('deleteDate')->findArray();
//土地荷主あり
$landPos += $increaseRow;
$cellName = 'A' . $landPos;
if(isset($locs) && sizeof($locs) > 0) {
    $blockCount = 6;
    copyBlock($sheet, $landPos, $blockCount, (sizeof($locs) - 1), true);

    //データ出力（ループ）
    $registIncrease = 0; //複数登記のカウントアップ
    for($cursor = 0 ; $cursor < sizeof($locs) ; $cursor++){
        $loc = $locs[$cursor];

        //登記名義人
        $regists = getRegistrants($contract['details'], $loc);
        $blockPos = $landPos + $cursor * $blockCount + $registIncrease;
        $cellName = 'A' . $blockPos;
        bindCell($cellName, $sheet, ['address', 'blockNumber', 'landCategory', 'area', 'sharer'], 
            [$loc['address'], $loc['blockNumber'], getCodeTitle($codeList, $loc['landCategory']), $loc['area'], sizeof($regists) > 0 ?  $regists[0] : "" ]); 

        //登記名義人複数
        $repeatePos = $landPos + ($cursor + 1) * $blockCount - 1;
        if(sizeof($regists) > 1) {
            $increase = sizeof($regists) - 1;
            $registIncrease += $increase;

            $sheet->insertNewRowBefore($repeatePos, $increase);
            $sheet->setCellValue('A' .  $repeatePos, 'BBBB');
        }
        else {
            $sheet->setCellValue('A' .  $repeatePos, '');
        }

    }
    $increaseRow += $blockCount * (sizeof($landPos) - 1) + $registIncrease;
}
//土地荷主なし
else {
    bindCell($cellName, $sheet, ['address', 'blockNumber', 'landCategory', 'area', 'sharer'], ['', '', '', '', '']);
}


/*
//不可分
$dependBuilds = [];
$dependLands = [];
foreach($contract['depends'] as $depend) {
    $loc = ORM::for_table(TBLLOCATIONINFO)->findOne($depend['locationInfoPid'])->asArray();
    $depend['location'] = $loc;

    //建物
    if($loc['locationType'] == '02') {
        $dependBuilds[] = $depend;
    }
    //土地
    else  {
        $dependLands[] = $depend;
    }
}


//不可分（建物)
if(sizeof($dependBuilds) > 1) {
    $sheet->insertNewRowBefore(274, 6 * (sizeof($dependBuilds) - 1));
}
$startCell = 268;
$addStr = $sheet->getCell('A268')->getValue();
$numStr = $sheet->getCell('A269')->getValue();
$typeStr = $sheet->getCell('A270')->getValue();
$structureStr = $sheet->getCell('A271')->getValue();
$areaStr = $sheet->getCell('A272')->getValue();
$step = 6;
$index = 0;
foreach($dependBuilds as $build) {
    //所在
    $cellName = 'A' . ($startCell + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$address$', $build['location']['address'], $addStr));

    //家屋番号
    $cellName = 'A' . ($startCell + 1 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$buildingNumber$', $build['location']['buildingNumber'], $numStr));

    //種類
    $cellName = 'A' . ($startCell + 2 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$dependType$', $build['dependType'], $typeStr));

    //構造
    $cellName = 'A' . ($startCell + 3 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$dependStructure$', $build['dependStructure'], $structureStr));

    //床面積
    $cellName = 'A' . ($startCell + 4 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$dependFloorArea$', $build['dependFloorArea'], $areaStr));

    $index++;
}

//不可分（土地)
if(sizeof($dependLands) > 1) {
    $sheet->insertNewRowBefore(266, 5 * (sizeof($dependLands) - 1));
}
$startCell = 261;
$addStr = $sheet->getCell('A261')->getValue();
$numStr = $sheet->getCell('A262')->getValue();
$catStr = $sheet->getCell('A263')->getValue();
$areaStr = $sheet->getCell('A264')->getValue();
$step = 5;
$index = 0;
foreach($dependLands as $build) {
    //所在
    $cellName = 'A' . ($startCell + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$address$', $build['location']['address'], $addStr));

    //地　　　番
    $cellName = 'A' . ($startCell + 1 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$blockNumber$', $build['location']['blockNumber'], $numStr));

    //地　　　目
    $cellName = 'A' . ($startCell + 2 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$landCategory$', $build['location']['landCategory'], $catStr));

    //　地　　　積
    $cellName = 'A' . ($startCell + 3 + $index * $step);
    $sheet->setCellValue($cellName, str_replace('$area$', $build['location']['area'], $areaStr));

    $index++;
}

*/


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