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

$lands = ORM::for_table("tbltemplandinfo")
            ->table_alias('p1')
            ->distinct()
            ->select('p1.*')
            ->inner_join(TBLCONTRACTINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2')
            ->where_in('p1.result', ['01', '02', '03', '04'])
            ->where_null('p1.deleteDate')->limit(100)->find_array();

echo json_encode($lands);

?>