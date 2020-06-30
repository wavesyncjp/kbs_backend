<?php
require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

/*
if(isset($param->bukkenName) && $param->bukkenName !== '') {
    $lands = ORM::for_table("tbltemplandinfo")->where_like("bukkenName", "%" . $param->bukkenName . "%")->where_null('deleteDate')->limit(100)->find_array();
}
else {
    $lands = ORM::for_table("tbltemplandinfo")->where_null('deleteDate')->limit(100)->find_array();
}
*/

$lands = ORM::for_table("tbltemplandinfo")->where_in('result', ['02', '03', '04'])->where_null('deleteDate')->limit(100)->find_array();

echo json_encode($lands);

?>