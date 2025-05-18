<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Content-type:  application/json; charset=UTF-8');

require 'idiorm.php';

#テスト環境
// ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
ORM::configure('mysql:host=sddb0040052539.cgidb;dbname=sddb0040052539');
ORM::configure('username', 'sd_dba_LTE2MjA0');
ORM::configure('password', 'password32');

#デモ環境
#ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
#ORM::configure('mysql:host=sddb0040039354.cgidb;dbname=sddb0040039354');
#ORM::configure('username', 'sd_dba_NTkwOTU1');
#ORM::configure('password', 'demo#pass32');

#本番環境
#ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
#ORM::configure('mysql:host=mysql19.onamae.ne.jp;dbname=cesrh_metpro');
#ORM::configure('username', 'cesrh_dbuser');
#ORM::configure('password', 'dbadmin123!');

#ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
#ORM::configure('mysql:host=localhost;dbname=koshiba_bds');
#ORM::configure('username', 'root');
#ORM::configure('password', '');

ORM::configure('id_column_overrides', array(
		'tbltemplandinfo'=>'pid'
		, 'tbllocationinfo'=>'pid'
		, 'tblcontractinfo'=>'pid'
		, 'tblcontractdetailinfo'=>'pid'
		, 'tblbukken'=>'bukkenId'
		, 'tbluser'=>'userId'
		, 'tbltoken'=>'userId'
		, 'tbldepartment'=>'depCode'
		, 'tblemployee'=>'employeeCode'
		, 'tblfileattach'=>'pid'
		, 'tblmapattach'=>'pid'
		, 'tblcontractfile'=>'pid'
		, 'tblcontractattach'=>'pid'// 20230227 Add
		, 'tbllocationattach'=>'pid'
		, 'tblinfoattach'=>'pid'// 20220329 Add
		, 'tblinformation'=>'pid'
		, 'tblsharerinfo'=>'pid'
		, 'tblcode'=>array('code', 'codeDetail')
		, 'tblkanjyo'=>'kanjyoCode'
		, 'tblkanjyofix'=>'pid'
		, 'tblcodenamemst' => 'code'
		, 'tblcontractsellerinfo'=>'pid'
		, 'tblcontractregistrant'=>'pid'
		, 'tblpaymenttype'=>'paymentCode'
		, 'tblreceivetype'=>'receiveCode' // 20210916 Add
		, 'tblplan'=>'pid'
		, 'tblplandetail'=>'pid'
		, 'tblpaycontract'=>'pid'
		, 'tblpaycontractdetail'=>'pid'
		, 'tblreceivecontract'=>'pid' // 20210916 Add
		, 'tblreceivecontractdetail'=>'pid' // 20210916 Add
		, 'tblplanrentroll' => 'pid'
		, 'tblplanrentrolldetail' => 'pid'
		, 'tbltax'=>'pid'
		, 'tblbukkenplaninfo' =>'pid'
		, 'tblbukkensalesinfo'=>'pid'
		, 'tblbukkensalesattach'=>'pid'// 20230227 Add
		, 'tblcontracttypefix'=>'pid'
		, 'tblcsvinfo' => 'pid'
		, 'tblcsvinfodetail' => 'pid'
		, 'tblplanhistory'=>'pid'
		, 'tblplandetailhistory'=>'pid'
		, 'tblplanrentrollhistory' => 'pid'
		, 'tblplanrentrolldetailhistory' => 'pid'
		, 'tblbottomlandinfo'=>'pid'
		, 'tblresidentinfo'=>'pid'// 20220614 Add
		, 'tblsorting'=>'pid'
		, 'tblbank'=>'pid'
		, 'tblmailtemplate'=>'templateId'// 20220403 Add
		// 20230917 S_Add
		, 'tblrentalinfo'=>'pid'
		, 'tblrentalcontract'=>'pid'
		, 'tblevictioninfo'=>'pid'
		, 'tblevictioninfoattach'=>'pid'
		, 'tblrentalreceive'=>'pid'
		// 20230917 E_Add
		, 'tblinfoapprovalattach'=>'pid'// 20230927 Add
		, 'tblbukkenphotoattach'=>'pid'// 20231020 Add
		, 'tblrentalcontractattach'=>'pid'// 20250418 Add
) );

$FILE_PATH = 'uploads';
		
define('TBLTEMPLANDINFO','tbltemplandinfo');
define('TBLLOCATIONINFO','tbllocationinfo');
define('TBLCONTRACTINFO','tblcontractinfo');
define('TBLCONTRACTDETAILINFO','tblcontractdetailinfo');
define('TBLBUKKEN','tblbukken');
define('TBLUSER','tbluser');
define('TBLTOKEN','tbltoken');
define('TBLDEPARTMENT','tbldepartment');
define('TBLEMPLOYEE','tblemployee');
define('TBLFILEATTACH','tblfileattach');
define('TBLMAPATTACH','tblmapattach');
define('TBLCONTRACTFILE','tblcontractfile');
define('TBLCONTRACTATTACH','tblcontractattach');// 20230227 Add
define('TBLLOCATIONATTACH','tbllocationattach');
define('TBLINFOATTACH','tblinfoattach');// 20220329 Add
define('TBLINFORMATION','tblinformation');
define('TBLSHARERINFO', 'tblsharerinfo');
define('TBLCODE', 'tblcode');
define('TBLKANJYO','tblkanjyo');
define('TBLKANJYOFIX','tblkanjyofix');
define('TBLCODENAMEMST', 'tblcodenamemst');
define('TBLCONTRACTSELLERINFO', 'tblcontractsellerinfo');
define('TBLCONTRACTREGISTRANT', 'tblcontractregistrant');
define('TBLPAYMENTTYPE', 'tblpaymenttype');
define('TBLRECEIVETYPE', 'tblreceivetype'); // 20210916 Add
define('TBLPLAN', 'tblplan');
define('TBLPLANDETAIL', 'tblplandetail');
define('TBLPAYCONTRACT', 'tblpaycontract');
define('TBLPAYCONTRACTDETAIL', 'tblpaycontractdetail');
define('TBLRECEIVECONTRACT', 'tblreceivecontract'); // 20210916 Add
define('TBLRECEIVECONTRACTDETAIL', 'tblreceivecontractdetail'); // 20210916 Add
define('TBLPLANRENTROLL', 'tblplanrentroll');
define('TBLPLANRENTROLLDETAIL', 'tblplanrentrolldetail');
define('TBLTAX', 'tbltax');
define('TBLBUKKENPLANINFO', 'tblbukkenplaninfo');
define('TBLBUKKENSALESINFO', 'tblbukkensalesinfo');
define('TBLBUKKENSALESATTACH','tblbukkensalesattach');// 20230227 Add
define('TBLCONTRACTTYPEFIX', 'tblcontracttypefix');
define('TBLCSVINFO', 'tblcsvinfo');
define('TBLCSVINFODETAIL', 'tblcsvinfodetail');
define('TBLPLANHISTORY', 'tblplanhistory');
define('TBLPLANDETAILHISTORY', 'tblplandetailhistory');
define('TBLPLANRENTROLLHISTORY', 'tblplanrentrollhistory');
define('TBLPLANRENTROLLDETAILHISTORY', 'tblplanrentrolldetailhistory');
define('TBLBOTTOMLANDINFO', 'tblbottomlandinfo');
define('TBLRESIDENTINFO', 'tblresidentinfo');// 20220614 Add
define('TBLSORTING', 'tblsorting');
define('TBLBANK', 'tblbank');
define('TBLMAILTEMPLATE', 'tblmailtemplate');// 20220403 Add
// 20230917 S_Add
define('TBLRENTALINFO', 'tblrentalinfo');
define('TBLRENTALCONTRACT', 'tblrentalcontract');
define('TBLEVICTIONINFO', 'tblevictioninfo');
define('TBLEVICTIONINFOATTACH', 'tblevictioninfoattach');
define('TBLRENTALRECEIVE', 'tblrentalreceive');
// 20230917 E_Add
define('TBLINFOAPPROVALATTACH','tblinfoapprovalattach');// 20230927 Add
define('TBLBUKKENPHOTOATTACH','tblbukkenphotoattach');// 20231020 Add
define('TBLRENTALCONTRACTATTACH', 'tblrentalcontractattach');// 20250418 Add
?>
