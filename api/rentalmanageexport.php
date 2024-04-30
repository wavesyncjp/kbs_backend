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

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

$fullPath  = __DIR__ . '/../template';
$filePath = $fullPath.'/賃貸管理表.xlsx'; 
// Excel操作
$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load($filePath);

// 賃貸管理表シート
$sheet = $spreadsheet->getSheet(0);

// 最終列数
$endColumn = 14;
// 最終行数
$endRow = 6;

$newline = "\n";

// 賃貸情報を取得
$ren = ORM::for_table(TBLRENTALINFO)->findOne($param->pid)->asArray();

//用途
$usePurposeList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '047')->where_null('deleteDate')->findArray();
//支払期限
$usanceList = ORM::for_table(TBLCODE)->select('codeDetail')->select('name')->where('code', '044')->where_null('deleteDate')->findArray();

$cntRenCons = 0;

// 列・行の位置を初期化
$currentColumn = 1;
$currentRow = 1;
$cell = null;

// 賃貸契約を取得
$renCons = getRentalContractsForExport2($param->pid,null);

//物件の表示 所在地		
$cell = setCell($cell, $sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow, $renCons[0]['l_addressMap']);

//物件の表示 名  称				
$cell = setCell($cell, $sheet, 'apartmentName', $currentColumn, $endColumn, $currentRow, $endRow, $ren['apartmentName']);

//支払期限
$cell = setCell($cell, $sheet, 'usance', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usanceList, (!isset($renCons[0]['usance']) || empty($renCons[0]['usance']) ? '1' : $renCons[0]['usance'])) . "分" .($renCons[0]['paymentDay'] == 0 ? '末' : $renCons[0]['paymentDay']) ."日払い");

//支払振込	
$bankName = getBankName($ren['bankPid']);			
$cell = setCell($cell, $sheet, 'bank_displayName', $currentColumn, $endColumn, $currentRow, $endRow, $bankName);

$cell = searchCell($sheet, 'roomNo', $currentColumn, $endColumn, $endRow, $endRow);
if($cell != null) {
    $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
    $currentRow = $cell->getRow();
}

if(sizeof($renCons) > 1) {
    $endRow += sizeof($renCons) - 1;
    copyBlockWithVal($sheet, $currentRow, 1, sizeof($renCons) - 1, $endColumn);
}
$cell = null;

foreach($renCons as $renCon) {
    $cntRenCons++;

    //立ち退き情報
    $evic = getEvic($renCon);
    if(!isset($evic)){
        $evic = new stdClass();
        $evic->agreementCancellationDate = null;
        $evic->surrenderDate = null;
    }
   
    //部屋番号
    $cell = setCell($cell, $sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['roomNo']);

    //用途
    $cell = setCell($cell, $sheet, 'usePurpose', $currentColumn, $endColumn, $currentRow, $endRow, getCodeTitle($usePurposeList, $renCon['usePurpose']));

    //賃借人
    $cell = setCell($cell, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['borrowerName']);

    //賃料
    $cell = setCell($cell, $sheet, 'rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPrice']);

    //賃料消費税
    $cell = setCell($cell, $sheet, 'rentPriceTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentPriceTax']);

    //共益費
    $cell = setCell($cell, $sheet, 'condoFee', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFee']);

    //共益費消費税
    $cell = setCell($cell, $sheet, 'condoFeeTax', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['condoFeeTax']);

    //敷金
    $cell = setCell($cell, $sheet, 'deposit', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['deposit']);

    //契約開始
    $cell = setCell($cell, $sheet, 'loanPeriodStartDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodStartDate'], 'Y/m/d'));

    //契約終了
    $cell = setCell($cell, $sheet, 'loanPeriodEndDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($renCon['loanPeriodEndDate'], 'Y/m/d'));

    // 立退料 情報
    // 解約日
    $cell = setCell($cell, $sheet, 'agreementCancellationDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->agreementCancellationDate, 'Y/m/d'));

    // 明渡日
    $cell = setCell($cell, $sheet, 'surrenderDate', $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($evic->surrenderDate, 'Y/m/d'));

    //明渡日に値が入っている行は灰色背景にする。
    createConditionExcel($sheet,'A' . $currentRow . ':N' . $currentRow,'=$M' . $currentRow . '<>""', 'D9D9D9');

    //備考
    $cell = setCell($cell, $sheet, 'rentalContractNotes', $currentColumn, $endColumn, $currentRow, $endRow, $renCon['rentalContractNotes']);

    $currentRow++;
}

// 合計の計算式
$currentRow -= $cntRenCons;
foreach($renCons as $renCon) {
    $sheet->setCellValue('H'. $currentRow, '=SUM(D' . $currentRow .':G' . $currentRow . ')');
    $currentRow++;
}

$sheet->setSelectedCell('A1');// 初期選択セル設定

// 入金管理シート
$sheet = $spreadsheet->getSheet(1);
// 最終列数
$endColumn = 16;
// 最終行数
$endRow = 6;

// 賃貸契約を取得
$renRevGroups = getRentalReceiveForExport($param->pid,null);

foreach($renRevGroups as $renRevGroup) {

    $yearJP = explode(".", convert_jpdt($renRevGroup->year . '0101'))[0];
    
    // シートをコピー
    $sheet = clone $spreadsheet->getSheet(1);
    $sheet->setTitle($yearJP . '_入金管理');
    $spreadsheet->addSheet($sheet);

    // 列・行の位置を初期化
    $currentColumn = 1;
    $currentRow = 2;
    $cell = null;

    foreach ($renRevGroup->groups as $key => $rev) {
        //物件の表示 名  称				
        $cell = setCell($cell, $sheet, 'apartmentName', $currentColumn, $endColumn, $currentRow, $endRow, $ren['apartmentName']);

        // 物件の表示 所在地	
        $cell = setCell($cell, $sheet, 'l_address', $currentColumn, $endColumn, $currentRow, $endRow, $rev->baseInfo['l_addressMap']);
        break;
    }

    $currentRow = 6;
    $currentColumn = 2;

    $cell = searchCell($sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow);
    if($cell != null) {
        $currentColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate ::columnIndexFromString($cell->getColumn());
        $currentRow = $cell->getRow();
    }

    if(sizeof($renRevGroup->groups) > 1) {
        $endRow += sizeof($renRevGroup->groups) * 2 - 1;
        copyBlockWithVal($sheet, $currentRow, 2, sizeof($renRevGroup->groups) - 1, $endColumn);
    }

    $cntGroup = 0;
    $currentRow -=2;
    $color = 'D0CECE';

    foreach ($renRevGroup->groups as $key => $rev) {
           
        $cntGroup++;
        $cell = null;
        //年
        $cell = setCell($cell, $sheet, 'receiveDay_Year', $currentColumn, $endColumn, $currentRow, $endRow, $cntGroup == 1 ? $yearJP : null);
   
        //部屋番号
        $cell = setCell($cell, $sheet, 'roomNo', $currentColumn, $endColumn, $currentRow, $endRow, $rev->baseInfo['roomNo']);
    
        //契約者名				
        $cell = setCell($cell, $sheet, 'contractorName', $currentColumn, $endColumn, $currentRow, $endRow, $rev->baseInfo['borrowerName']);

        //賃料等月額				
        $cell = setCell($cell, $sheet, 'ri_rentPrice', $currentColumn, $endColumn, $currentRow, $endRow, $rev->baseInfo['rentPriceRefMap']);

        for ($i = 1; $i <= 12; $i++) {
            $detail = $rev->details[$i];
            //日付			
            $cell = setCell($cell, $sheet, 'receiveDay_' . $i, $currentColumn, $endColumn, $currentRow, $endRow, convert_dt($detail->receiveDay, 'Y/m/d'), $detail->isDisable ? $color : null);
        }

        $endRow += 1;// 20240426 Add
        for ($i = 1; $i <= 12; $i++) {
            $detail = $rev->details[$i];
            //金額			
            $cell = setCell($cell, $sheet, 'receivePrice_' . $i, $currentColumn, $endColumn, $currentRow, $endRow, $detail->receivePrice, $detail->isDisable ? $color : null);
        }
    }
    
    $sheet->setSelectedCell('A1');// 初期選択セル設定
}
// コピー元入金管理シート削除
$spreadsheet->removeSheetByIndex(1);

$spreadsheet->setActiveSheetIndex(0);// 初期選択シート設定

// 保存
$filename = '賃貸管理表_' . date('YmdHis') . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$savePath = $fullPath.'/'.$filename;
$writer->save($savePath);

// ダウンロード
readfile($savePath);

// 削除
unlink($savePath);

/**
 * セルに値設定
 */
function setCell($cell, $sheet, $keyWord, $startColumn, $endColumn, $startRow, $endRow, $value, $color = null) {
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
	   
        if(isset($color)){
            $sheet->getStyle($cell->getColumn() . $cell->getRow())->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }
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
        copyRowsWithValue($sheet, $lastPos, $copyPos, $blockRowCount, $colums + 1);
    }
}

// エクセルの条件付き書式で行に色を付ける
function createConditionExcel($sheet,$range,$condition, $color){
	// Create conditional formatting rule
	$conditional = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
	$conditional->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION)
		->addCondition($condition);
	$conditional->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
	$conditional->getStyle()->getFill()->getEndColor()->setARGB($color);
	
	// Apply conditional formatting to the range
	$sheet->getStyle($range)->setConditionalStyles([$conditional]);	
}

/**
 * 賃貸契約取得(出力用)
 * @param rentalInfoPid 賃貸情報
 */
function getRentalContractsForExport2($rentalInfoPid) {
	$queryRC = ORM::for_table(TBLRENTALCONTRACT)
	->table_alias('p1')
	->select('p1.*')
	->select('p2.roomNo')
	->select('p2.borrowerName')
	->select('p3.address', 'l_addressMap')
	->select('p5.displayOrder')
	->left_outer_join(TBLRESIDENTINFO, array('p1.residentInfoPid', '=', 'p2.pid'), 'p2')
	->left_outer_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p3.pid'), 'p3')
	->left_outer_join(TBLCODE, ' p5.code = 047 and p5.codeDetail = p1.usePurpose ', 'p5')
	->where_null('p1.deleteDate')
	->where_null('p2.deleteDate');
	
	$queryRC = $queryRC->where('p1.rentalInfoPid', $rentalInfoPid);
	$results = $queryRC->order_by_expr('p5.displayOrder asc, LENGTH(p2.roomNo) asc, p2.roomNo asc')->findArray();
	return $results;
}

/**
 * 賃貸入金取得(出力用)
 * @param rentalInfoPid 賃貸情報
 */
function getRentalReceiveForExport($rentalInfoPid) {
	$queryRC = ORM::for_table(TBLRENTALCONTRACT)
	->table_alias('p1')
	->select('p1.*')
	->select('p2.roomNo')
	->select('p2.borrowerName')
	->select('p2.rentPrice', 'rentPriceRefMap')
	->select('p3.address', 'l_addressMap')
	->select('p4.receiveMonth')
	->select('p4.receiveDay')
	->select('p4.receiveFlg')
	->select('p4.rentalContractPid')
	->select('p5.receivePriceTax')
	->left_outer_join(TBLRESIDENTINFO, 'p1.residentInfoPid = p2.pid and p2.deleteDate is null', 'p2')
	->left_outer_join(TBLLOCATIONINFO, array('p1.locationInfoPid', '=', 'p3.pid'), 'p3')
	->left_outer_join(TBLRENTALRECEIVE, 'p1.pid = p4.rentalContractPid and p4.deleteDate is null', 'p4')
    ->left_outer_join(TBLRECEIVECONTRACTDETAIL, ' p5.rentalReceivePid = p4.pid and p5.deleteDate is null', 'p5')
	->where_null('p1.deleteDate');
	
	$queryRC = $queryRC->where('p1.rentalInfoPid', $rentalInfoPid);
	$rentalReceives = $queryRC->order_by_expr('p4.receiveMonth asc, LENGTH(p2.roomNo) asc, p2.roomNo asc, p4.rentalContractPid asc')->findArray();
	
    $results = array();

	$receiveYears = array();
	if (isset($rentalReceives)) {
		foreach ($rentalReceives as $rev) {
            $year = substr($rev['receiveMonth'], 0, 4);
			if (in_array($year, $receiveYears) == false) {
				$receiveYears[] = $year;
			}
		}

		foreach ($receiveYears as $y) {
			$obj = new stdClass();
			$obj->year = $y;
            $obj->groups = array();

			foreach ($rentalReceives as $rev) {
				if (substr($rev['receiveMonth'], 0, 4) == $y) {

                    $evic = getEvic($rev);

                    $roomRentExemptionStartDate = isset($evic) ? $evic->roomRentExemptionStartDate : '';
                    
                    // 20240426 S_Delete
                    // $isDisable = isset($roomRentExemptionStartDate) && !empty($roomRentExemptionStartDate) && substr($roomRentExemptionStartDate, 0, 6) <= $rev['receiveMonth'];
                    // 20240426 E_Delete

                    $key = $y.'_'.$rev['roomNo'].'_'.$rev['rentalContractPid'];
					
                    if (!isset($obj->groups[$key])) {
                        $data = new stdClass();
                        $data->baseInfo = $rev;
                        $details = array();

                        for ($i = 1; $i <= 12; $i++) {
                            $objDay = new stdClass();

                            $objDay->receiveDay = null;
                            $objDay->receivePrice = null;
                            // 20240426 S_Update
                            // $objDay->isDisable = false;
                            if(isset($roomRentExemptionStartDate) && !empty($roomRentExemptionStartDate)){
                                $ym = $y . ($i < 10 ? '0' : '') . $i;
                                if(isBeginDayInMonth($roomRentExemptionStartDate)){
                                    $objDay->isDisable = substr($roomRentExemptionStartDate, 0, 6) <= $ym;
                                }
                                else {
                                    $objDay->isDisable = substr($roomRentExemptionStartDate, 0, 6) < $ym;
                                }
                            }
                            else{
                                $objDay->isDisable = false;
                            }
                            // 20240426 E_Update

                            $details[$i] = $objDay;
                        }

                        $data->details = $details;

                        $obj->groups[$key] = $data;
                        
                    }
                    $receiveDay = $rev['receiveDay'];
                    $receivePrice = $rev['receivePriceTax'];

                    $details = $obj->groups[$key]->details;

                    for ($i = 1; $i <= 12; $i++) {

                        $m =($i < 10 ? '0' : '') . $i;

                        if($rev['receiveMonth'] == $y.$m){
                            $detail = $details[$i];

                            //入金済のレコードのみ
                            if($rev['receiveFlg'] == '1'){
                                $detail->receiveDay = $receiveDay;
                                $detail->receivePrice = $receivePrice;
                            }
                            // 20240426 S_Delete
                            // $detail->isDisable = $isDisable; 
                            // 20240426 E_Delete
                        }
                    }
				}
			}
			$results[] = $obj;
		}
	}
	return $results;
}
?>
