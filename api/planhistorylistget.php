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
    , \'金額(現状)\' as planHistoryName
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
                WHERE planPid = ' . $param->pid . '
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
        , planHistoryName
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
                WHERE planPid = '. $param->pid . '
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
        WHERE planPid = ' . $param->pid . '
    ) H4
    ON H3.paymentCode = H4.paymentCode
    AND H3.planHistoryPid = H4.planHistoryPid

    INNER JOIN tblpaymenttype M
    ON H3.paymentCode = M.paymentCode

    INNER JOIN tblplanhistory H
    ON H3.planHistoryPid= H.pid

    WHERE H3.planHistoryPid IS NOT NULL

    UNION

    SELECT
        CASE WHEN costFlg = 01 THEN 1999 WHEN costFlg = 02 THEN 2999 WHEN costFlg = 03 THEN 3999 END as paymentCode
        , 0 as planHistoryPid
        , SUM(price) as price
        , CASE WHEN costFlg = 01 THEN \'土地合計\' WHEN costFlg = 02 THEN \'建物合計\' WHEN costFlg = 03 THEN \'その他合計\' END as paymentName
        , 9 as costFlg
        , 9 as addFlg 
        , \'金額(現状)\' as planHistoryName
    FROM tblplandetail PD
    INNER JOIN tblpaymenttype M
    ON PD.paymentCode = M.paymentCode

    WHERE PD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid, costFlg

    UNION

    SELECT
        CASE WHEN costFlg = 01 THEN 1999 WHEN costFlg = 02 THEN 2999 WHEN costFlg = 03 THEN 3999 END as paymentCode
        , planHistoryPid
        , SUM(price) as price
        , CASE WHEN costFlg = 01 THEN \'土地合計\' WHEN costFlg = 02 THEN \'建物合計\' WHEN costFlg = 03 THEN \'その他合計\' END as paymentName
        , 9 as costFlg
        , 9 as addFlg 
        , planHistoryName
    FROM tblplandetailhistory HD
    INNER JOIN tblpaymenttype M
    ON HD.paymentCode = M.paymentCode

    INNER JOIN tblplanhistory H
    ON HD.planHistoryPid= H.pid

    WHERE HD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid, costFlg

    UNION

    SELECT
        9997 as paymentCode
        , 0 as planHistoryPid
        , TRUNCATE(P.landLoan * P.landInterest / 12 * P.landPeriod / 100 , 0) as price
        , \'土地関係金利\'as paymentName
        , 9 as costFlg
        , 9 as addFlg 
        ,\'金額(現状)\' as planHistoryName
    FROM tblplandetail PD
    INNER JOIN tblpaymenttype M
    ON PD.paymentCode = M.paymentCode

    INNER JOIN tblplan P
    ON PD.planPid = P.pid

    WHERE PD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    UNION

    SELECT
        9997 as paymentCode
        , planHistoryPid
        , TRUNCATE(H.landLoan * H.landInterest / 12 * H.landPeriod / 100 , 0) as price
        , \'土地関係金利\'as paymentName
        , 9 as costFlg
        , 9 as addFlg
        , planHistoryName
    FROM tblplandetailhistory HD
    INNER JOIN tblpaymenttype M
    ON HD.paymentCode = M.paymentCode

    INNER JOIN tblplanhistory H
    ON HD.planHistoryPid= H.pid

    WHERE HD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    UNION

    SELECT
        9998 as paymentCode
        , 0 as planHistoryPid
        , TRUNCATE(P.buildLoan * P.buildInterest / 12 * P.buildPeriod / 100 , 0) as price
        , \'建物関係金利\'as paymentName
        , 9 as costFlg
        , 9 as addFlg 
        ,\'金額(現状)\' as planHistoryName
    FROM tblplandetail PD
    INNER JOIN tblpaymenttype M
    ON PD.paymentCode = M.paymentCode

    INNER JOIN tblplan P
    ON PD.planPid = P.pid

    WHERE PD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    UNION

    SELECT
        9998 as paymentCode
        , planHistoryPid
        , TRUNCATE(H.buildLoan * H.buildInterest / 12 * H.buildPeriod / 100 , 0) as price
        , \'建物関係金利\'as paymentName
        , 9 as costFlg
        , 9 as addFlg
        , planHistoryName
    FROM tblplandetailhistory HD
    INNER JOIN tblpaymenttype M
    ON HD.paymentCode = M.paymentCode

    INNER JOIN tblplanhistory H
    ON HD.planHistoryPid= H.pid

    WHERE HD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    UNION

    SELECT
        9999 as paymentCode
        , 0 as planHistoryPid
        , SUM(price) + TRUNCATE(P.landLoan * P.landInterest / 12 * P.landPeriod / 100 , 0) + TRUNCATE(P.buildLoan * P.buildInterest / 12 * P.buildPeriod / 100 , 0) as price
        , \'PJ原価\'as paymentName
        , 9 as costFlg
        , 9 as addFlg 
        ,\'金額(現状)\' as planHistoryName
    FROM tblplandetail PD
    INNER JOIN tblpaymenttype M
    ON PD.paymentCode = M.paymentCode

    INNER JOIN tblplan P
    ON PD.planPid = P.pid

    WHERE PD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    UNION

    SELECT 
        9999 as paymentCode
        , planHistoryPid
        , SUM(price) + TRUNCATE(H.landLoan * H.landInterest / 12 * H.landPeriod / 100 , 0) + TRUNCATE(H.buildLoan * H.buildInterest / 12 * H.buildPeriod / 100 , 0) as price
        , \'PJ原価\'as paymentName
        , 9 as costFlg
        , 9 as addFlg
        , planHistoryName
    FROM tblplandetailhistory HD
    INNER JOIN tblpaymenttype M
    ON HD.paymentCode = M.paymentCode

    INNER JOIN tblplanhistory H
    ON HD.planHistoryPid= H.pid

    WHERE HD.planPid = ' . $param->pid . '
    GROUP BY planHistoryPid

    ORDER BY paymentCode, costFlg, addFlg, planHistoryPid'
)->findArray();

echo json_encode($history);

//20200827_変更前_278行
//ORDER BY planHistoryPid, costFlg, addFlg, paymentCode'

?>
