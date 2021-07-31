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
    $userId = null;// 20210728 Add
    if(isset($sale->pid) && $sale->pid > 0){
        $saleSave = ORM::for_table(TBLBUKKENSALESINFO)->find_one($sale->pid);
        setUpdate($saleSave, $sale->updateUserId);
        $userId = $sale->updateUserId;// 20210728 Add
    }
    else {
        $saleSave = ORM::for_table(TBLBUKKENSALESINFO)->create();
        setInsert($saleSave, isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId);
        $userId = isset($sale->updateUserId) && $sale->updateUserId ? $sale->updateUserId : $sale->createUserId;// 20210728 Add
    }
    copyData($sale, $saleSave, array('pid', 'salesContractDayMap', 'salesContractSchDayMap', 'salesDecisionSchDayMap', 'salesDecisionDayMap', 
        'salesLocationMap', 'salesLocationStr', 'updateUserId', 'updateDate', 'createUserId', 'createDate'));
    $saleSave->save();

    setPayBySale($saleSave, $userId);// 20210728 Add
}

$ret = ORM::for_table(TBLBUKKENSALESINFO)->find_one($saleSave->pid)->asArray();
echo json_encode($ret);

?>