<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$sale = json_decode($postparam);


if(isset($sale->deleteUserId) && $sale->deleteUserId > 0) {
    ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid)->delete();			
}
else {
    if(isset($sale->pid) && $sale->pid > 0){
        $saleSave = ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid);
        setUpdate($saleSave, $sale->updateUserId);			
    }
    else {
        $saleSave = ORM::for_table(TBLBUKKENSALESINFO)->create();
        setInsert($saleSave, isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId);			
    }		
    copyData($sale, $saleSave, array('pid', 'salesContractDayMap', 'salesContractSchDayMap', 'salesDecisionSchDayMap', 'salesDecisionDayMap'));		
    $saleSave->save();
}

$ret = ORM::for_table(TBLBUKKENSALESINFO)->find_one($saleSave->pid)->asArray();
echo json_encode($ret);

?>