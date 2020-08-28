<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Content-type:  application/json; charset=UTF-8');

require 'idiorm.php';

ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
ORM::configure('mysql:host=sddb0040052539.cgidb;dbname=sddb0040052539');
ORM::configure('username', 'sd_dba_LTE2MjA0');
ORM::configure('password', 'password32');

#ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
#ORM::configure('mysql:host=mysql19.onamae.ne.jp;dbname=cesrh_metpro');
#ORM::configure('username', 'cesrh_dbuser');
#ORM::configure('password', 'dbadmin123!');

#ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
#ORM::configure('mysql:host=localhost;dbname=koshiba_bds');
#ORM::configure('username', 'root');
#ORM::configure('password', '');

ORM::configure('id_column_overrides', array(
		'tbltemplandinfo'=>'pid',
		'tbllocationinfo'=>'pid',
		'tblcontractinfo'=>'pid',
		'tblcontractdetailinfo'=>'pid',
		'tblbukken'=>'bukkenId',
		'tbluser'=>'userId',
		'tbltoken'=>'userId',
		'tbldepartment'=>'depCode',
		'tblemployee'=>'employeeCode',
		'tblfileattach'=>'pid',
		'tblmapattach'=>'pid',
		'tblcontractfile'=>'pid',
		'tblinformation'=>'pid',
		'tblsharerinfo'=>'pid',		
		'tblcode'=>array('code', 'codeDetail'),
		'tblcodenamemst' => 'code',
		'tblcontractsellerinfo'=>'pid',
		'tblcontractregistrant'=>'pid',
		'tblpaymenttype'=>'paymentCode',
		'tblplan'=>'pid',
		'tblplandetail'=>'pid',
		'tblpaycontract'=>'pid',
		'tblpaycontractdetail'=>'pid',
		'tblplanrentroll' => 'pid',
		'tblplanrentrolldetail' => 'pid',		
		'tbltax'=>'pid',
		'tblbukkenplaninfo' =>'pid',
		'tblbukkensalesinfo'=>'pid',
		'tblcontracttypefix'=>'pid',
		'tblcsvinfo' => 'pid',
		'tblcsvinfodetail' => 'pid',
		'tblplanhistory'=>'pid',
		'tblplandetailhistory'=>'pid',
		'tblplanrentrollhistory' => 'pid',
		'tblplanrentrolldetailhistory' => 'pid',
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
define('TBLINFORMATION','tblinformation');
define('TBLSHARERINFO', 'tblsharerinfo');
define('TBLCODE', 'tblcode');
define('TBLCODENAMEMST', 'tblcodenamemst');
define('TBLCONTRACTSELLERINFO', 'tblcontractsellerinfo');
define('TBLCONTRACTREGISTRANT', 'tblcontractregistrant');
define('TBLPAYMENTTYPE', 'tblpaymenttype');
define('TBLPLAN', 'tblplan');
define('TBLPLANDETAIL', 'tblplandetail');
define('TBLPAYCONTRACT', 'tblpaycontract');
define('TBLPAYCONTRACTDETAIL', 'tblpaycontractdetail');
define('TBLPLANRENTROLL', 'tblplanrentroll');
define('TBLPLANRENTROLLDETAIL', 'tblplanrentrolldetail');
define('TBLTAX', 'tbltax');
define('TBLBUKKENPLANINFO', 'tblbukkenplaninfo');
define('TBLBUKKENSALESINFO', 'tblbukkensalesinfo');
define('TBLCONTRACTTYPEFIX', 'tblcontracttypefix');
define('TBLCSVINFO', 'tblcsvinfo');
define('TBLCSVINFODETAIL', 'tblcsvinfodetail');
define('TBLPLANHISTORY', 'tblplanhistory');
define('TBLPLANDETAILHISTORY', 'tblplandetailhistory');
define('TBLPLANRENTROLLHISTORY', 'tblplanrentrollhistory');
define('TBLPLANRENTROLLDETAILHISTORY', 'tblplanrentrolldetailhistory');

?>