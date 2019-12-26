<?php
require '../header.php';
require '../util.php';
require("../vendor/autoload.php");

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
$filePath = $fullPath.'/template1.xlsx'; 

//Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getSheet(0);

// 売買代金
$str = $sheet->getCell('A76')->getValue();
$sheet->setCellValue("A76", str_replace('$tradingPrice$', formatNumber($contract['tradingPrice'], true), $str));

// 売買代金（土地）
$str = $sheet->getCell('A77')->getValue();
$sheet->setCellValue("A77", str_replace('$tradingLandPrice$', formatNumber($contract['tradingLandPrice'], true), $str));

//$sheet->insertNewRowBefore(232, 4); // 232行目から4行追加


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