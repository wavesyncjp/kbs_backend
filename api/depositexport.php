<?php
ini_set('memory_limit', '512M');// 20241004 Add

require("../vendor/autoload.php");
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'NOT SUPPORT';
    exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/預り金一覧.xlsx'; 

$depositTypes = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '048')->where_null('deleteDate')->findArray();

// 賃貸情報を取得
$ren = ORM::for_table(TBLRENTALINFO)->findOne($param->pid)->asArray();

$bukken = ORM::for_table(TBLTEMPLANDINFO)->findOne($ren['tempLandInfoPid'])->asArray();
$locationInfo = ORM::for_table(TBLLOCATIONINFO)->findOne($ren['locationInfoPid'])->asArray();

// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 買主シート
$sheet = $spreadsheet->getSheet(0);

$title = $sheet->getTitle();
$sheet->setTitle($title . '_' . $ren['apartmentName']);

// 最終列数
$endColumn = 11;
// 最終行数
$endRow = 7;

$newline = "\n";

 // 列・行の位置を初期化
 $currentColumn = 1;
 $currentRow = 1;
 $cell = null;

 
$sellers = ORM::for_table(TBLCONTRACTSELLERINFO)->select('contractorName')->select('contractorAdress')->where('contractInfoPid', $ren['contractInfoPid'])->where_null('deleteDate')->order_by_asc('pid')->findArray();
$list_contractorNameComma = getContractorName($sellers, '、');// 複数契約者名（カンマ区切り）

// 旧所有者
$cell = setCell($cell, $sheet, 'l_contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $list_contractorNameComma);

//物件の表示 名  称				
$cell = setCell($cell, $sheet, 'apartmentName', $currentColumn, $endColumn, $currentRow, $endRow, $ren['apartmentName']);

// 承継年月日
$cell = setCell($cell, $sheet, 'ownershipRelocationDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($ren['ownershipRelocationDate'], 'Y/n/j'));


//契約物件番号		
$cell = setCell($cell, $sheet, 'contractBukkenNo', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['contractBukkenNo']);

//物件の表示 所在地		
$cell = setCell($cell, $sheet, 'address', $currentColumn, $endColumn, $currentRow, $endRow, $locationInfo['address']);

//預り金（保証金）	
$cell = setCell($cell, $sheet, 'successionSecurityDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $ren['successionSecurityDeposit']);

$cell = null;
//預り金（保証金）	
$cell = setCell($cell, $sheet, 'successionSecurityDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $ren['successionSecurityDeposit']);


// 賃貸契約を取得
$renEvics = getEvictionInfos(null,null, $param->pid);

$grouped = [];

// ★ステップ2：立退き情報をグルーピング
foreach ($renEvics as $evic) {
    // 支払日を取得（優先順位：内金支払日１～７ → 返還敷金支払日）
    for ($i = 1; $i <= 7; $i++) {
        if($i == 3){
            if (!empty($evic["remainingPayedDate"]) && $evic["remainingPayedFlg"] == '1') {
                addToGroup($grouped, $evic, $evic["remainingPayedDate"] , $evic["remainingFee"], $depositTypes);
            }
        }
        else if (!empty($evic["depositPayedDate{$i}"]) && $evic["deposit{$i}PayedFlg"] == '1') {
            addToGroup($grouped, $evic, $evic["depositPayedDate{$i}"] , $evic["deposit{$i}"], $depositTypes);
        }
    }
    if (!empty($evic["returnDepositDate"]) && $evic["returnDepositFlg"] == '1') {
        addToGroup($grouped, $evic, $evic["returnDepositDate"] , $evic["returnDeposit"], $depositTypes);
    }
}

// ksort($grouped);
if (empty($grouped)) {
    $grouped[] = [
        'returnDepositSum' => 0,
        'notes' => '',
        'borrowerName' => '',
        'depositPayedDate' => ''
    ];
}
else{
    // グループを支払日順にソート（昇順）
    uasort($grouped, function ($a, $b) {
        return strtotime($a['depositPayedDate']) <=> strtotime($b['depositPayedDate']);
    });
}

$currentRow = 6;
if(sizeof($grouped) > 1) {
    $endRow += sizeof($grouped);
    copyBlockWithVal($sheet, $currentRow, 1, sizeof($grouped) - 1, $endColumn);
}

// シートをコピー
$sheet = $spreadsheet->getSheet(0);
$cell = null;

$cntEvics = 0;
foreach($grouped as $evic) {
    $cell = null;

    $rowI = 5 + $cntEvics; // I列の行番号
    $rowH = 6 + $cntEvics; // H列の行番号
    $formula = "=I{$rowI}-H{$rowH}";

    $cell = setCell($cell, $sheet, 'depositPayedDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic['depositPayedDate'], 'Y/n/j'));
    $cell = setCell($cell, $sheet, 'contractBukkenNo', $currentColumn, $endColumn, $currentRow, $endRow, $bukken['contractBukkenNo']);
    $cell = setCell($cell, $sheet, 'address', $currentColumn, $endColumn, $currentRow, $endRow, $locationInfo['address']);
    $cell = setCell($cell, $sheet, 'borrowerName', $currentColumn, $endColumn, $currentRow, $endRow, $evic['borrowerName']);
    $cell = setCell($cell, $sheet, 'returnDeposit', $currentColumn, $endColumn, $currentRow, $endRow, $evic['returnDepositSum']);
    $cell = setCell($cell, $sheet, 'remain', $currentColumn, $endColumn, $currentRow, $endRow, $formula);
    
    $cell = setCell($cell, $sheet, 'notes', $currentColumn, $endColumn, $currentRow, $endRow, $evic['notes']);

    $currentRow += 1;
    $cntEvics++;
}

// 保存
$filename = '預り金一覧_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

// ★ グルーピング関数（共通処理）
function addToGroup(&$grouped, $evic, $payDate, $amount, $depositTypes) {
    $groupKey = implode('_', [
        $evic["rentalInfoPid"] ?? '',
        $evic["residentInfoPid"] ?? '',
        $payDate
    ]);

    if (!isset($grouped[$groupKey])) {
        $grouped[$groupKey] = $evic;
        $grouped[$groupKey]['returnDepositSum'] = $amount;
        $grouped[$groupKey]['notes'] = getNote($evic, $payDate, $depositTypes);
        $grouped[$groupKey]['borrowerName'] = $evic["borrowerName"];
        $grouped[$groupKey]['depositPayedDate'] = $payDate;
    } else {
        $grouped[$groupKey]['returnDepositSum'] += $amount;
    }
}

function getNote($evic, $payDate, $depositTypes) {
    $note = $evic['roomNo'];
    $notes = [];

    for ($i = 7; $i >= 1; $i--) {
        if($i == 3){
            if ($evic["remainingPayedFlg"] === '1' && 
                $evic["remainingPayedDate"] === $payDate) {

                $notes[] = getCodeTitle($depositTypes,$evic["evictionDepositType3"]);
                break;
            }
        }
        else if ($evic["deposit{$i}PayedFlg"] === '1' && 
                $evic["depositPayedDate{$i}"] === $payDate) {
            $notes[] = getCodeTitle($depositTypes,$evic["evictionDepositType{$i}"]);
            break;
        }
    }

    if (!empty($evic["returnDepositDate"]) && 
        $evic["returnDepositFlg"] === '1' && 
        $evic["returnDepositDate"] === $payDate) {

        $notes[] = '返還敷金（保証金）';
    }

    return !empty($notes)
        ? $note . ' ' . implode('、', $notes)
        : $note;
}


/**
 * セルに値設定
 */
function setCell($cell, $sheet, $keyWord, $startColumn, $endColumn, $startRow, $endRow, $value) {
    // セルに指定がある場合
    if($cell != null) {
        // 対象のセルを開始位置とする
        $startColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $startRow = $cell->getRow();
    }
    // キーワードのセルを探す
    $cell = searchCell($sheet, $keyWord, $startColumn, $endColumn, $startRow, $endRow);
    // 対象のセルが存在する場合
    if($cell != null) {
        // 値を設定する
        $setColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $setRow = $cell->getRow();
        $sheet->setCellValueByColumnAndRow($setColumn, $setRow, $value);
    }
    return $cell;
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

/**
 * 契約者取得（指定文字区切り）
 */
function getContractorName($lst, $split) {
	$ret = [];
	if(isset($lst)) {
		foreach($lst as $data) {
			if(!empty($data['contractorName'])) $ret[] = mb_convert_kana($data['contractorName'], 'kvrn');
		}
	}
	return implode($split, $ret);
}
?>
