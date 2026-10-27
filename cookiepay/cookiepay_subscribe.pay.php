<?php
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

$ret = [
    'status' => false,
    'data' => ''
];

// s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
// 이 파일의 쓰기 쿼리는 전부 sql_query($sql, false) 라 실패해도 조용하다.
// 게다가 여러 INSERT 의 결과를 같은 $res 에 담아 앞 건의 성패가 묻혔고,
// MySQL 오류 메시지는 어디에도 남지 않아 원인 추적이 불가능했다.
// 구문 하나하나의 성패를 오류 메시지와 함께 남긴다.
if (!function_exists('cookiepay_subscribe_log_write')) {
    function cookiepay_subscribe_log_write($label, $target, $res, $sql)
    {
        if ($res) {
            @cookiepay_payment_log("{$label} 성공 ({$target})", $sql, 3);
            return true;
        }

        // 그누보드 버전마다 오류 조회 수단이 달라, 있는 것을 순서대로 쓴다.
        $err = '';
        if (function_exists('sql_error')) {
            $err = sql_error();
        } else {
            global $g5;
            if (isset($g5['connect_db']) && $g5['connect_db']) {
                $err = @mysqli_error($g5['connect_db']);
            }
        }
        if ($err === '' || $err === null) {
            $err = '(오류 메시지 없음)';
        }

        @cookiepay_payment_log("{$label} 실패 ({$target}) {$err}", $sql, 3);
        return false;
    }
}
// e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026

/*
# 넘어온 값(S)
Array
(
    [mode] => keyin_pay
    [ORDERNO] => 2025042510063712
    [PRODUCTNAME] => 감
    [AMOUNT] => 200
    [BUYERNAME] => 최고관리자
    [BUYEREMAIL] => naribi3@nate.com
    [PRODUCTCODE] => 
    [BUYERID] => admin
    [BUYERADDRESS] => 
    [BUYERPHONE] => 01076764624
    [CARDNO] => 1234134134134134
    [EXPIREDT] => 3313
    [CARDAUTH] => 
    [HANACARD_USE] => 
    [ETC1] => 379
    [ETC2] => 
    [ETC3] => 
    [ETC4] => 
    [ETC5] => 
    [CARDNO1] => 1234
    [CARDNO2] => 1341
    [CARDNO3] => 3413
    [CARDNO4] => 4134
    [EXPIREDT2] => 13
    [EXPIREDT1] => 33
    [QUOTA] => 00
    [CARDPWD] => 
    [agree] => 1
)
# 넘어온 값(E)
*/

// $cookiepay = $_POST;
$cookiepay = array();
foreach ($_POST as $key => $value) {
    $cookiepay[$key] = clean_xss_tags($value, 1, 1);
}

$mode = $cookiepay['mode'];
unset($cookiepay['mode']);

// 결제창 팝업시 결제결과 데이터 사전 생성
if ($mode == "try_pay") {

    @cookiepay_payment_log("결제 시도", json_encode($cookiepay), 3);
    
    $orderno = isset($cookiepay['ORDERNO']) && !empty($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : '';
    
    $pgResultId = '';
    if (!empty($orderno)) {
        $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $orderno) . "' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
        $pgResultId = isset($pgResult['id']) && !empty($pgResult['id']) ? $pgResult['id'] : '';
    }
    
    if (!empty($orderno) && empty($pgResultId)) {
        $columnStr = implode(",", $pgResultColumns);
        $values = [];
        foreach ($pgResultColumns as $val) {
            $values[$val] = "''";
        }
        foreach ($cookiepay as $key => $val) {
            if (array_key_exists($key, $values)) {
                $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
            }
        }
        $values['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'"; // pg사 추가
        $values['ORDERNO'] = "'" . sql_real_escape_string((string) $orderno) . "'";
        $payType = isset($cookiepay['PAY_TYPE']) && !empty($cookiepay['PAY_TYPE']) ? $cookiepay['PAY_TYPE'] : 3;
        $values['pay_type'] = "'" . sql_real_escape_string((string) $payType) . "'";
        $values['pay_status'] = "0";
        $valueStr = implode(",", $values);

        // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
        // INSERT 두 건의 결과를 같은 $res 에 담아, 앞 건의 성패가 묻히고 있었다.
        $sqlResult = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $resResult = sql_query($sqlResult, false);

        # 정기(구독)관련 테이블 (S)
        $sqlSub = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $resSub = sql_query($sqlSub, false);
        # 정기(구독)관련 테이블 (E)

        cookiepay_subscribe_log_write("결제 시도 저장", "pg_result",        $resResult, $sqlResult);
        cookiepay_subscribe_log_write("결제 시도 저장", "subscribe_result", $resSub,    $sqlSub);

        if ($resResult && $resSub) {
            $ret['status'] = true;
        }
        // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    }

    echo json_encode($ret);
    exit;
}

// s: cookiepay-plugin v1.2
if ($mode == "try_order")
{
    @cookiepay_payment_log("주문정보 임시저장 시도", json_encode($cookiepay), 3);
    
    $odId = isset($cookiepay['od_id']) && !empty($cookiepay['od_id']) ? $cookiepay['od_id'] : '';
    $odResultId = '';
    // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    // $odId 에 담아 놓고 미정의 $od_id 를 보고 있어 중복 주문 검사가 한 번도
    // 동작하지 않았다. PK 가 겹치면 아래 INSERT 가 무음으로 실패할 뿐이었다.
    if (!empty($odId))
    {
        $odResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_SHOP_ORDER." WHERE od_id='" . sql_real_escape_string((string) $odId) . "' LIMIT 1");
        $odResultId = isset($odResult['od_id']) && !empty($odResult['od_id']) ? $odResult['od_id'] : '';
    }
    // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    
    if (!empty($odId) && empty($odResultId))
    {
        // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
        // 아래 반복문은 전송된 필드의 이름을 그대로 컬럼 이름으로 쓴다. 값은
        // 이스케이프할 수 있지만 컬럼 이름은 따옴표로 감쌀 수 없으므로, 실제
        // 테이블에 있는 컬럼 이름만 통과시킨다(cookiepay.pay.php 항목1과 동일).
        require_once G5_PATH."/cookiepay/cookiepay.guard.php";

        $guardAllowed = array();
        $guardDesc = sql_query(" DESCRIBE ".COOKIEPAY_SHOP_ORDER." ", false);
        if ($guardDesc) {
            while ($guardCol = sql_fetch_array($guardDesc)) {
                $guardAllowed[] = $guardCol['Field'];
            }
        }
        // od_zip / od_b_zip 은 컬럼이 아니라 od_zip1·od_zip2 로 쪼개 넣는 가공용 키다.
        $guardAllowed[] = 'od_zip';
        $guardAllowed[] = 'od_b_zip';

        $guardRet = cookiepay_guard_filter_columns($cookiepay, $guardAllowed);

        if (!empty($guardRet['rejected'])) {
            @cookiepay_payment_log("주문정보 임시저장 허용되지 않은 항목 제외", implode(",", $guardRet['rejected']), 3);
        }

        if (!$guardRet['ok']) {
            @cookiepay_payment_log("주문정보 임시저장 차단 ({$guardRet['code']})", $guardRet['msg'], 3);
            echo json_encode($ret);
            exit;
        }

        $cookiepay = $guardRet['data'];

        $keys = array();
        $vals = array();
        // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026

        foreach ($cookiepay as $key => $val)
        {
            if ($key == 'od_zip')
            {
                $od_zip = preg_replace('/[^0-9]/', '', $val);
                $od_zip1 = substr($od_zip, 0, 3);
                $od_zip2 = substr($od_zip, 3);
                $keys['od_zip1'] = "od_zip1";
                $keys['od_zip2'] = "od_zip2";
                $vals['od_zip1'] = "'" . sql_real_escape_string((string) $od_zip1) . "'";
                $vals['od_zip2'] = "'" . sql_real_escape_string((string) $od_zip2) . "'";
            }
            else if ($key == 'od_b_zip')
            {
                $od_b_zip = preg_replace('/[^0-9]/', '', $val);
                $od_b_zip1  = substr($od_b_zip, 0, 3);
                $od_b_zip2  = substr($od_b_zip, 3);
                $keys['od_b_zip1'] = "od_b_zip1";
                $keys['od_b_zip2'] = "od_b_zip2";
                $vals['od_b_zip1'] = "'" . sql_real_escape_string((string) $od_b_zip1) . "'";
                $vals['od_b_zip2'] = "'" . sql_real_escape_string((string) $od_b_zip2) . "'";
            }
            else
            {
                $keys[$key] = "{$key}";
                $vals[$key] = "'" . sql_real_escape_string((string) $val) . "'";
            }
        }
        
        $keyStr = implode(",", $keys);
        $valStr = implode(",", $vals);
        
        $sql = " INSERT INTO ".COOKIEPAY_SHOP_ORDER." ({$keyStr}) VALUES ({$valStr}) ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("주문정보 임시저장 시도 저장 성공", $sql, 3);
            $ret['status'] = true;
        } else {
            @cookiepay_payment_log("주문정보 임시저장 시도 저장 실패", $sql, 3);
        }
    }
    
    echo json_encode($ret);
    exit;
}
// e: cookiepay-plugin v1.2

// 정기(구독)
if ($mode == "keyin_pay") {
    $cookiepayApiKeyin = cookiepay_get_api_account_info($default, 9);

    $req_json = json_encode([
        'pay2_id' => $cookiepayApiKeyin['api_id'],
        'pay2_key'=> $cookiepayApiKeyin['api_key'],
    ], true);

    /* 
    {"pay2_id":"sandbox_7fLAXEQa1L","pay2_key":"sandbox_nSlzstiG9H9i2IHid465T4DIxRi1xjl8GT9uR7cZWe"}
    */

    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, COOKIEPAY_TOKEN_URL);
    curl_setopt($ch,CURLOPT_POST, false);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $req_json);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["content-type: application/json; charset=utf-8"]);
    $tokenResJson = curl_exec($ch);
    curl_close($ch);
    $tokenRes = json_decode($tokenResJson, true);
    
    /*
    Array
    (
        [RTN_CD] => 0000
        [RTN_MSG] => 성공
        [TOKEN] => eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfX2NpX2xhc3RfcmVnZW5lcmF0ZSI6MTc0NTU0Mzg1MiwidXNlciI6InNhbmRib3hfN2ZMQVhFUWExTCIsImlhdCI6MTc0NTU0Mzg1MiwiZXhwIjoxNzQ1NTQ3NDUyfQ.dxnXeLRoQsavwvqnswKiIQMlWzw1Vo_YQUJ9GGdlP_k
    )
    */

    if ($tokenRes['RTN_CD'] == '0000') {

        $pgResultId = '';
        if (!empty($cookiepay['ORDERNO'])) {
            $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
            $pgResultId = isset($pgResult['id']) && !empty($pgResult['id']) ? $pgResult['id'] : '';
        }

        /*
        # pgResultColumns > 배열값
            $pgResultColumns = [
            'RESULTCODE',
            'RESULTMSG',
            'ORDERNO',
            'AMOUNT',
            'TID',
            'ACCEPTDATE',
            'ACCEPTNO',
            'CASH_BILL_NO',
            'CARDNAME',
            'ACCOUNTNO',
            'RECEIVERNAME',
            'DEPOSITENDDATE',
            'CARDCODE',
            'QUOTA',
            'ETC1',
            'ETC2',
            'ETC3',
            'ETC4',
            'ETC5',
            'PGNAME',
            'pay_type',
            'pay_status'
        ];
        */
        
        if (empty($pgResultId)) {

            // insert
            $columnStr = implode(",", $pgResultColumns);
            $values = [];
            foreach ($pgResultColumns as $val) {
                $values[$val] = "''";
            }
            foreach ($tokenRes as $key => $val) {
                if (array_key_exists($key, $values)) {
                    $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                }
            }
            $values['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
            $values['ORDERNO'] = "'" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
            $values['pay_type'] = "'9'";
            $values['pay_status'] = "0";
            $valueStr = implode(",", $values);

            // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
            // 세 INSERT 의 결과를 $res 하나에 덮어쓰고 $res2 는 검사조차 하지
            // 않아, 어느 테이블이 비었는지 알 수 없었다. 각각 따로 받는다.
            $sqlResult = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
            $resResult = sql_query($sqlResult, false);

            # 정기(구독)관련 테이블 (S)
            $sqlSub = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
            $resSub = sql_query($sqlSub, false);

            $sqlUser = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." ({$columnStr}) VALUES ({$valueStr}) ";
            $resUser = sql_query($sqlUser, false);
            # 정기(구독)관련 테이블 (E)

            cookiepay_subscribe_log_write("정기(구독) 시도 저장", "pg_result",         $resResult, $sqlResult);
            cookiepay_subscribe_log_write("정기(구독) 시도 저장", "subscribe_result",  $resSub,    $sqlSub);
            cookiepay_subscribe_log_write("정기(구독) 시도 저장", "subscribe_userlist", $resUser,  $sqlUser);

            // 아직 쿠키페이를 부르기 전이므로 여기서 멈추는 것이 안전하다.
            if (!$resResult || !$resSub) {
                echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE=&RESULTMSG=";</script>';
                exit;
            }
            // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
            // end insert
        }

        $headers = [
            "content-type: application/json; charset=utf-8",
            "ApiKey: {$cookiepayApiKeyin['api_key']}",
            "TOKEN: {$tokenRes['TOKEN']}"
        ];

        $cookiepayments_url = $default['de_card_test'] == '0' ? COOKIEPAY_SUBSCRIBE_URL : COOKIEPAY_TESTSUBSCRIBE_URL;

        $request_data_array = [
            'API_ID'        => $cookiepayApiKeyin['api_id'],
            'ORDERNO'       => $cookiepay['ORDERNO'],
            'PRODUCTNAME'   => $cookiepay['PRODUCTNAME'],
            'AMOUNT'        => $cookiepay['AMOUNT'],
            'BUYERNAME'     => $cookiepay['BUYERNAME'],
            'BUYEREMAIL'    => $cookiepay['BUYEREMAIL'],
            'CARDNO'        => $cookiepay['CARDNO'],
            'EXPIREDT'      => $cookiepay['EXPIREDT'],
            //'PRODUCTCODE'   => $cookiepay['PRODUCTCODE'],
            'PRODUCTCODE'   => $cookiepay['ORDERNO'],
            'BUYERID'       => $cookiepay['BUYERID'],
            'BUYERADDRESS'  => $cookiepay['BUYERADDRESS'],
            'BUYERPHONE'    => $cookiepay['BUYERPHONE'],
            'QUOTA'         => $cookiepay['QUOTA'],
            'CARDAUTH'      => $cookiepay['CARDAUTH'],
            'CARDPWD'       => $cookiepay['CARDPWD'],
            'HANACARD_USE'  => $cookiepay['HANACARD_USE'],
            'ETC1'          => $cookiepay['ETC1'],
            'ETC2'          => $cookiepay['ETC2'],
            'ETC3'          => $cookiepay['ETC3'],
            'ETC4'          => $cookiepay['ETC4'],
            'ETC5'          => $cookiepay['ETC5'],
            'TAXFREECD'         => 'Y',
            'RECURRENCE_PAY'    => 'Y', // 반복결제여부
            'RECURRENCE_TYPE'   => 'M', // 반복주기
            'PAY_DAY'           => $cookiepay['PAYMENTDATE'],
            'START_PAY_CNT'     => '1',
            'LAST_PAY_CNT'      => $cookiepay['LAST_PAY_CNT'],
            'ENC_YN'            => 'N'
        ];

        $cookiepayments_json = json_encode($request_data_array, true);

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $cookiepayments_url);
        curl_setopt($ch,CURLOPT_POST, false);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepayments_json);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch,CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $keyinResJson = curl_exec($ch);
        curl_close($ch);
        $keyinRes = json_decode($keyinResJson, true);

        ## 응답값 
        /*
            Array
            (
                [RESULTCODE] => 0000
                [RESULTMSG] => 성공
                [BILLKEY] => 590mu3o4b8wswsss2yfb
                [GENDATE] => 20250425112856
                [ORDERNO] => 2025042511283524
                [AMOUNT] => 200
                [TID] => cTS25042511285694891
                [ACCEPTDATE] => 20250425112856
                [ACCEPTNO] => 45488607
                [BUYERNAME] => 최고관리자
                [BUYERID] => admin
                [BUYERADDRESS] => 
                [BUYERPHONE] => 01076764624
                [BUYEREMAIL] => naribi3@nate.com
                [CARDCODE] =>  // 빌링키 생성, 결제때는 카드종류를 안준다.
                [ETC1] => 399
                [ETC2] => 
                [ETC3] => 
                [ETC4] => 
                [ETC5] => 
                [RESERVE_RESULTCODE] => 0000
                [RESERVE_RESULTMSG] => 성공
                [RESERVE_ID] => 90vkndplj280og48k8wgrc1ira8
                [RESERVE_ORDERNO] => 2025042511283524
                [RESERVE_RECURRENCE_TYPE] => M
                [RESERVE_PAY_DAY] => 15
                [RESERVE_NOW_PAY_CNT] => 1
                [RESERVE_START_PAY_CNT] => 1
                [RESERVE_LAST_PAY_CNT] => 10
                [RESERVE_NEXT_PAY_DATE] => 2025-05-15
                [RESERVE_RETURN_URL] => 
            )
        */

        if ($keyinRes['RESULTCODE'] == '0000') {

            # 정기(구독) 중복 주문번호(성공건)
            @cookiepay_payment_log("정기(구독) 성공", $keyinResJson, 1);

            $payStatus = '';
            if (!empty($keyinRes['ORDERNO'])) {
                $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $keyinRes['ORDERNO']) . "' ORDER BY `id` DESC LIMIT 1");
                $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';
            }

            if ($payStatus == '') {
                // insert
                // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                // 여기는 세 INSERT 가 모두 실패하던 자리다. 원인이 둘이었다.
                //  1) 값 목록($valueStr)을 만든 뒤 ", PRODUCT_NAME='...'" 을
                //     덧붙였다. UPDATE 의 SET 문법을 INSERT 의 VALUES 에 끼워
                //     넣은 것이라 컬럼 38개 : 값 39개로 어긋났고(ERROR 1136),
                //     오염된 문자열이 뒤 두 INSERT 에도 그대로 전달됐다.
                //  2) cookiepay_pg_result 에는 22개 컬럼밖에 없는데 38개짜리
                //     $pgResultColumns_Subscribe 로 INSERT 했다(ERROR 1054).
                // 전부 sql_query($sql, false) 라 조용히 실패했고 흐름도 멈추지
                // 않아, 구매자에게는 성공 화면과 RESERVE_ID 까지 보였다. 결과가
                // "쿠키페이에는 예약이 있고 가맹점 DB 에는 행이 없는" 상태다.

                // (1) 구독 계열 테이블 — 38컬럼
                $columnStr = implode(",", $pgResultColumns_Subscribe);

                $values = [];
                foreach ($pgResultColumns_Subscribe as $val) {
                    $values[$val] = "''";
                }
                foreach ($keyinRes as $key => $val) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $values['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $values['pay_type'] = "'9'";
                $values['pay_status'] = "1";
                // 상품명은 이미 $pgResultColumns_Subscribe 에 있는 컬럼이므로
                // 배열 안에서 채운다. 그래야 컬럼과 값의 개수가 어긋나지 않는다.
                if (array_key_exists('PRODUCT_NAME', $values) && isset($cookiepay['PRODUCTNAME'])) {
                    $values['PRODUCT_NAME'] = "'" . sql_real_escape_string((string) $cookiepay['PRODUCTNAME']) . "'";
                }
                if (array_key_exists('USERID', $values) && isset($cookiepay['BUYERID'])) {
                    $values['USERID'] = "'" . sql_real_escape_string((string) $cookiepay['BUYERID']) . "'";
                }
                $valueStr = implode(",", $values);

                // (2) cookiepay_pg_result — 22컬럼. 스키마가 다르므로 따로 만든다.
                $columnStrResult = implode(",", $pgResultColumns);
                $valuesResult = [];
                foreach ($pgResultColumns as $val) {
                    $valuesResult[$val] = "''";
                }
                foreach ($keyinRes as $key => $val) {
                    if (array_key_exists($key, $valuesResult)) {
                        $valuesResult[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $valuesResult['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $valuesResult['pay_type'] = "'9'";
                $valuesResult['pay_status'] = "1";
                $valueStrResult = implode(",", $valuesResult);

                $sqlResult = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStrResult}) VALUES ({$valueStrResult}) ";
                $resResult = sql_query($sqlResult, false);

                # 정기(구독)관련 테이블 (S)
                $sqlSub = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
                $resSub = sql_query($sqlSub, false);

                $sqlUser = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." ({$columnStr}) VALUES ({$valueStr}) ";
                $resUser = sql_query($sqlUser, false);
                # 정기(구독)관련 테이블 (E)

                cookiepay_subscribe_log_write("정기(구독) 결과 저장1", "pg_result",          $resResult, $sqlResult);
                cookiepay_subscribe_log_write("정기(구독) 결과 저장1", "subscribe_result",   $resSub,    $sqlSub);
                cookiepay_subscribe_log_write("정기(구독) 결과 저장1", "subscribe_userlist", $resUser,   $sqlUser);
                // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                // end insert
            } else if ($payStatus != 1) {
                // update

                //echo "which<br>";

                # 최초 기초데이터 저장된후 이곳에서 업데이트함
                // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                // 주의: 아래는 $set 을 뒤 구문으로 계속 이어 쓰는 구조다. 지금은
                // 덧붙는 컬럼이 전부 뒤쪽 테이블에 실재해 동작하지만, 컬럼을
                // 옮기면 바로 위 INSERT 와 같은 종류의 무음 실패가 된다.
                $whereOrderNo = " WHERE ORDERNO='" . sql_real_escape_string((string) $keyinRes['ORDERNO']) . "'";

                $set = [];
                foreach ($keyinRes as $key => $val) {
                    if (in_array($key, $pgResultColumns)) {
                        $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $set['PGNAME'] = "PGNAME='" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $set['pay_type'] = "pay_type='9'";
                $set['pay_status'] = "pay_status=1";
                $setStr = implode(",", $set);

                $sqlResult = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr}" . $whereOrderNo;
                $resResult = sql_query($sqlResult, false);

                # 정기(구독)관련 테이블 (S)
                $set = [];
                foreach ($keyinRes as $key => $val) {
                    if (in_array($key, $pgResultColumns_Subscribe)) {
                        $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $set['USERID'] = "USERID='" . sql_real_escape_string((string) $cookiepay['BUYERID']) . "' ";
                $set['PGNAME'] = "PGNAME='" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $set['pay_type'] = "pay_type='9'";
                $set['pay_status'] = "pay_status=1";
                $set['PAY_DATE'] = "PAY_DATE='" . sql_real_escape_string(date('Y-m-d H:i:s', strtotime($keyinRes['ACCEPTDATE']))) . "' ";
                $set['PRODUCT_NAME'] = "PRODUCT_NAME='" . sql_real_escape_string((string) $cookiepay['PRODUCTNAME']) . "' ";
                $setStr = implode(",", $set);

                $sqlSub = "UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET {$setStr}" . $whereOrderNo;
                $resSub = sql_query($sqlSub, false);

                $set['RESERVE_LAST_PAY_DATE'] = "RESERVE_LAST_PAY_DATE='".date('Y-m-d H:i:s')."' ";
                $set['RESERVE_LAST_PAY_RESULTCODE'] = "RESERVE_LAST_PAY_RESULTCODE='" . sql_real_escape_string((string) $keyinRes['RESULTCODE']) . "' "; // 최근결제상태
                $set['RESERVE_LAST_PAY_RESULTMSG'] = "RESERVE_LAST_PAY_RESULTMSG='" . sql_real_escape_string((string) $keyinRes['RESULTMSG']) . "' "; // 최근결제상태
                $setStr = implode(",", $set);
                $sqlUser = "UPDATE ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." SET {$setStr}" . $whereOrderNo;
                $resUser = sql_query($sqlUser, false);
                # 정기(구독)관련 테이블 (E)

                cookiepay_subscribe_log_write("정기(구독) 결과 저장2", "pg_result",          $resResult, $sqlResult);
                cookiepay_subscribe_log_write("정기(구독) 결과 저장2", "subscribe_result",   $resSub,    $sqlSub);
                cookiepay_subscribe_log_write("정기(구독) 결과 저장2", "subscribe_userlist", $resUser,   $sqlUser);
                // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                // end update
            }

            // 결제 검증(S) #################################################################################

                $token_url = COOKIEPAY_TOKEN_URL;

                $request_data = json_encode([
                    'pay2_id' => $cookiepayApiKeyin['api_id'],
                    'pay2_key'=> $cookiepayApiKeyin['api_key'],
                ], JSON_UNESCAPED_UNICODE);

                unset($tokenResJson);
                unset($tokenRes);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $token_url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
                $tokenResJson = curl_exec($ch);
                curl_close($ch);
                $tokenRes = json_decode($tokenResJson, true);

                if ($tokenRes['RTN_CD'] == '0000') {
                    $headers = [
                        "content-type: application/json; charset=utf-8",
                        "TOKEN: {$tokenRes['TOKEN']}"
                    ];
            
                    $request_data = json_encode([
                        "tid" => $keyinRes['TID']
                    ], JSON_UNESCAPED_UNICODE);
            
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, COOKIEPAY_VERIFY_URL);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $verifyJson = curl_exec($ch);
                    curl_close($ch);
                    $verify = json_decode($verifyJson, true);

                    // 결제 검증 결과 테이블에 저장
                    $columnStr = implode(",", $pgVerifyColumns);
                    $values = [];
                    foreach ($pgVerifyColumns as $val) {
                        $values[$val] = "''";
                    }
                    foreach ($verify as $key => $val) {
                        if (array_key_exists($key, $values)) {
                            $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                        }
                    }
                    $valueStr = implode(",", $values);
                    $sql = " INSERT INTO ".COOKIEPAY_PG_VERIFY." ({$columnStr}) VALUES ({$valueStr}) ";
                    $res = sql_query($sql, false);
                    if ($res) {
                        @cookiepay_payment_log("정기(구독) 검증결과 저장 성공3", $sql, 3);
                    } else {
                        @cookiepay_payment_log("정기(구독) 검증결과 저장 실패3", $sql, 3);
                    }

                    if($verify['RESULTCODE'] == '0000') {
                        @cookiepay_payment_log("정기(구독) 검증 성공4", $verifyJson, 3);
                    } else {
                        @cookiepay_payment_log("정기(구독) 검증 실패4", $verifyJson, 3);
                    
                        $ret = cookipay_cancel_payment($cookiepayApiKeyin['api_id'], $cookiepayApiKeyin['api_key'], $keyinRes['TID']);
            
                        if ($ret['status'] === true) {
                            @cookiepay_payment_log("정기(구독) 취소 성공5", $ret['data'], 3);
                            $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                            # 정기(구독)관련 테이블 (S)
                            $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                            # 정기(구독)관련 테이블 (E)
                        } else {
                            @cookiepay_payment_log("정기(구독) 취소 실패5", $ret['data'], 3);
                        }
            
                        $cancelArr = json_decode($ret['data'], true);
            
                        $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('"
                        . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_tid']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_code']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_msg']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_date']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_amt']) . "') ";
                        $res = sql_query($sql, false);
                        if ($res) {
                            @cookiepay_payment_log("정기(구독) 취소결과 저장 성공6", $sql, 3);
                        } else {
                            @cookiepay_payment_log("정기(구독) 취소결과 저장 실패6", $sql, 3);
                        }
                    }
                } else {
                    // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                    // 미정의 $resultJson 을 넘기고 있어 본문이 빈 채로 기록됐다.
                    @cookiepay_payment_log("정기(구독) 검증 토큰 발행 실패7", $tokenResJson, 3);
                    // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
                    
                    $ret = cookipay_cancel_payment($cookiepayApiKeyin['api_id'], $cookiepayApiKeyin['api_key'], $keyinRes['TID']);
            
                    if ($ret['status'] === true) {
                        @cookiepay_payment_log("정기(구독) 취소 성공8", $ret['data'], 3);
                        $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                        # 정기(구독)관련 테이블 (S)
                        $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                        # 정기(구독)관련 테이블 (E)
                    } else {
                        @cookiepay_payment_log("정기(구독) 취소 실패8", $ret['data'], 3);
                    }
            
                    $cancelArr = json_decode($ret['data'], true);
            
                    $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('"
                        . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_tid']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_code']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_msg']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_date']) . "', '"
                        . sql_real_escape_string((string) $cancelArr['cancel_amt']) . "') ";
                    $res = sql_query($sql, false);
                    if ($res) {
                        @cookiepay_payment_log("정기(구독) 취소결과 저장 성공9", $sql, 3);
                    } else {
                        @cookiepay_payment_log("정기(구독) 취소결과 저장 실패9", $sql, 3);
                    }
                }

            // 결제 검증(E) #################################################################################

            // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
            // 이 리다이렉트는 성공 분기 바깥에 있어, 결제 실패·토큰 발행 실패
            // 경로에서도 실행됐다. $keyinRes 가 없어 undefined index 가 나고
            // 빈 파라미터로 결과 화면이 떴다. 각 분기 안으로 옮긴다.
            echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.urlencode($keyinRes['RESULTCODE']).'&RESULTMSG='.urlencode($keyinRes['RESULTMSG']).'&type=keyin&reserve_id='.urlencode(isset($keyinRes['RESERVE_ID']) ? $keyinRes['RESERVE_ID'] : '').'";</script>';
            exit;

        } else {
            @cookiepay_payment_log("정기(구독) 실패", json_encode($keyinResJson), 3);

            $failCode = isset($keyinRes['RESULTCODE']) ? $keyinRes['RESULTCODE'] : '';
            $failMsg  = isset($keyinRes['RESULTMSG'])  ? $keyinRes['RESULTMSG']  : '';
            echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.urlencode($failCode).'&RESULTMSG='.urlencode($failMsg).'&type=keyin";</script>';
            exit;
        }
    } else {
        @cookiepay_payment_log("정기(구독) 토큰 발행 실패", $tokenResJson, 3);
    }

    // 토큰 발행 실패 등 위에서 종료하지 못한 경로.
    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE=&RESULTMSG=";</script>';
    exit;
    // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
} // end if ($mode == "keyin_pay") 
