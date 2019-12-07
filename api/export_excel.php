<?php
require '../header.php';

header("Content-disposition: attachment; filename=sample.xlsx");
header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

flush();
readfile('../test.xlsx');
?>