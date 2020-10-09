<?php

require '../header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
	echo 'NOT SUPPORT';
	exit;
}

$postdata = file_get_contents("php://input");
$param = json_decode($postdata);

$history = ORM::for_table('tbltemplandinfo')->raw_query(
    'SELECT
        H3.paymentCode
        , 0 as planHistoryPid
        , IfNull(H4.price, 0) as price
        , M.paymentName
        , M.costFlg
        , M.addFlg
    FROM
    (
        SELECT
            H1.paymentCode
            , 0 as planHistoryPid
        FROM
        (
            SELECT
                DISTINCT (paymentCode)
            FROM
            (
                SELECT
                    paymentCode
                FROM tblplandetail
                WHERE planPid = ' . $param->pid . '
                UNION ALL
                SELECT
                    paymentCode
                FROM tblplandetailhistory
                WHERE planPid = '. $param->pid . '
            ) ALLPAYMENTCD
        ) H1
    ) H3
    LEFT OUTER JOIN
    (
        SELECT
            paymentCode
            , price
            , backNumber
        FROM tblplandetail
        WHERE planPid = ' . $param->pid . '
    ) H4
    ON H3.paymentCode = H4.paymentCode
    
    INNER JOIN tblpaymenttype M
    ON H3.paymentCode = M.paymentCode
    
    UNION
    
    SELECT
        H3.paymentCode
        , H3.planHistoryPid
        , IfNull(H4.price, 0) as price
        , M.paymentName
        , M.costFlg
        , M.addFlg
    FROM
    (
        SELECT
            H1.paymentCode
            , H2.planHistoryPid
        FROM
        (
            SELECT
                DISTINCT (paymentCode)
            FROM
            (
                SELECT
                    paymentCode
                FROM tblplandetail
                WHERE planPid = ' . $param->pid . '
                UNION ALL
                SELECT
                    paymentCode
                FROM tblplandetailhistory
                WHERE planPid = ' . $param->pid . '
            ) ALLPAYMENTCD
        ) H1
        LEFT OUTER JOIN
        (
            SELECT
                DISTINCT (planHistoryPid)
            FROM tblplandetailhistory
            WHERE planPid = ' . $param->pid . '
        ) H2
        ON 1 = 1
    ) H3
    LEFT OUTER JOIN
    (
        SELECT
            paymentCode
            , planHistoryPid
            , price
            , backNumber
        FROM tblplandetailhistory 
        WHERE planPid = '. $param->pid . '
    ) H4
    ON H3.paymentCode = H4.paymentCode
    AND H3.planHistoryPid = H4.planHistoryPid
    
    INNER JOIN tblpaymenttype M
    ON H3.paymentCode = M.paymentCode

    UNION

SELECT
    9999 as paymentCode
    , 0 as planHistoryPid
    , SUM(price) as price
    , \'総合計\'as paymentName
    , 9 as costFlg
    , 9 as addFlg 

FROM tblplandetail S1
INNER JOIN tblpaymenttype M
ON S1.paymentCode = M.paymentCode
WHERE planPid = ' . $param->pid . '

GROUP BY planHistoryPid

UNION

SELECT 
     9999 as paymentCode
    , 0 as planHistoryPid
    , SUM(price) as price
    , \'総合計\'as paymentName
    , 9 as costFlg
    , 9 as addFlg

FROM tblplandetailhistory S2
INNER JOIN tblpaymenttype M
ON S2.paymentCode = M.paymentCode
WHERE planPid = ' . $param->pid . '

GROUP BY planHistoryPid


UNION

SELECT
    CASE WHEN costFlg = 01 THEN 1999 WHEN costFlg = 02 THEN 2999 ELSE 3999 END as paymentCode
    , 0 as planHistoryPid
    , SUM(price) as price
    , CASE WHEN costFlg = 01 THEN \'土地原価合計\' WHEN costFlg = 02 THEN \'建物原価合計\' ELSE \'その他費用合計\'  END as paymentName
    , 9 as costFlg
    , 9 as addFlg 
FROM tblplandetail S3
INNER JOIN tblpaymenttype M
ON S3.paymentCode = M.paymentCode
WHERE planPid = ' . $param->pid . '
GROUP BY planHistoryPid, costFlg


UNION

SELECT
    CASE WHEN costFlg = 01 THEN 1999 WHEN costFlg = 02 THEN 2999 ELSE 3999 END as paymentCode
    , planHistoryPid
    , SUM(price) as price
    , CASE WHEN costFlg = 01 THEN \'土地原価合計\' WHEN costFlg = 02 THEN \'建物原価合計\' ELSE \'その他費用合計\'  END as paymentName
    , 9 as costFlg
    , 9 as addFlg 
FROM tblplandetailhistory S4
INNER JOIN tblpaymenttype M
ON S4.paymentCode = M.paymentCode
WHERE planPid = ' . $param->pid . '
GROUP BY planHistoryPid, costFlg


    
ORDER BY costFlg, addFlg, paymentCode, planHistoryPid'
)->findArray();

echo json_encode($history);

//20200827_変更前_109行
//ORDER BY planHistoryPid, costFlg, addFlg, paymentCode'

?>