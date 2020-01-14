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
#ORM::configure('mysql:host=localhost;dbname=koshiba_bds');
#ORM::configure('username', 'root');
#ORM::configure('password', '');

ORM::configure('id_column_overrides', array(
		'tbltemplandinfo'=>'pid',
		'tbllocationinfo'=>'pid',
		'tblcontractinfo'=>'pid',
		'tblcontractdetailinfo'=>'pid',
		'tblcontractdependinfo'=>'pid',
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
		'tblcode' =>array('code', 'codeDetail'),
		'tblcodenamemst' => 'code'
) );

$FILE_PATH = 'uploads';
		
define('TBLTEMPLANDINFO','tbltemplandinfo');
define('TBLLOCATIONINFO','tbllocationinfo');
define('TBLCONTRACTINFO','tblcontractinfo');
define('TBLCONTRACTDETAILINFO','tblcontractdetailinfo');
define('TBLCONTRACTDEPENDINFO','tblcontractdependinfo');
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
define('TBLCODENAMEMST', 'tblcodenamemst')

?>