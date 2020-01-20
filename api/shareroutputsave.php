<?php
require '../header.php';
require '../util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo 'NOT SUPPORT';
	exit;
}

$postparam = file_get_contents("php://input");
$param = json_decode($postparam);

foreach($param->sharers  as $sharer) {    
    $saveSharer = ORM::for_table(TBLSHARERINFO)->find_one($sharer->pid);
    if(isset($saveSharer)) {        
        if($saveSharer->outPutFlg !== $sharer->outPutFlg) {            
            $saveSharer->outPutFlg = $sharer->outPutFlg;
            setUpdate($saveSharer, $param->userId);
            $saveSharer->save();            
        }
    }
}

?>