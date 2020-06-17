<?php
require '../header.php';

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

if(isset($param->bukkenName) && $param->bukkenName !== '') {
    $lands = ORM::for_table("tbltemplandinfo")->where_like("bukkenName", "%" . $param->bukkenName . "%")->where_null('deleteDate')->find_array();
}
else {
    $lands = ORM::for_table("tbltemplandinfo")->where_null('deleteDate')->find_array();
}
echo json_encode($lands);

?>