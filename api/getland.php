<?php
require '../header.php';
require '../util.php';// 20250502 Add

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

// 20250502 S_Update
// $lands = ORM::for_table("tbltemplandinfo")
$query = ORM::for_table("tbltemplandinfo")
// 20250502 E_Update
            ->table_alias('p1')
            ->distinct()
            ->select('p1.*')
            ->inner_join(TBLCONTRACTINFO, array('p1.pid', '=', 'p2.tempLandInfoPid'), 'p2')
            ->where_in('p1.result', ['01', '02', '03', '04'])
            ->where_null('p1.deleteDate')
//            ->limit(100)
// 20250502 S_Update
            // ->find_array();
            ;
$query = getQueryExpertTempland($param, $query, 'p1.pid');
$lands = $query->find_array();       
// 20250502 E_Update

echo json_encode($lands);

?>