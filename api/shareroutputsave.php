<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

$ctDetail = $param->contractDetail;
foreach($param->sharers  as $sharer) {    
    $saveSharer = ORM::for_table(TBLSHARERINFO)->find_one($sharer->pid);
    if(isset($saveSharer)) {        
        if($saveSharer->outPutFlg !== $sharer->outPutFlg) {            
            $saveSharer->outPutFlg = $sharer->outPutFlg;
            setUpdate($saveSharer, $param->userId);
            $saveSharer->save();            
        }
    }

    //仕入契約登記人情報
    if(isset($ctDetail)) {

        //削除
        if(!isset($saveSharer->outPutFlg) || $saveSharer->outPutFlg == '0') {
            ORM::for_table(TBLCONTRACTREGISTRANT)
                ->where(array('contractDetailInfoPid' => $ctDetail->pid, 'sharerInfoPid' => $saveSharer->pid))->delete_many();
        }
        else if($saveSharer->outPutFlg == '1') {
            $regist = ORM::for_table(TBLCONTRACTREGISTRANT)->where(array(
                'contractDetailInfoPid' => $ctDetail->pid,
                'sharerInfoPid' => $saveSharer->pid
            ))->findOne();

            if(!isset($regist) || $regist == null) {						
                $regist = ORM::for_table(TBLCONTRACTREGISTRANT)->create();
                $regist->contractInfoPid = $ctDetail->contractInfoPid;
                $regist->contractDetailInfoPid = $ctDetail->pid;
                $regist->sharerInfoPid = $saveSharer->pid;
                setInsert($regist, $param->userId);
                $regist->save();
            }
        }
    }
}

?>