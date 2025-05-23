<?php
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

$ret = [
    'status' => false,
    'data' => ''
];

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
        $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='{$orderno}' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
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
                $values[$key] = "'{$val}'";
            }
        }
        $values['PGNAME'] = "'{$default['de_pg_service']}'"; // pg사 추가
        $values['ORDERNO'] = "'{$orderno}'";
        $payType = isset($cookiepay['PAY_TYPE']) && !empty($cookiepay['PAY_TYPE']) ? $cookiepay['PAY_TYPE'] : 3;
        $values['pay_type'] = "'{$payType}'";
        $values['pay_status'] = "0";
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);

        # 정기(구독)관련 테이블 (S)
        $sql = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
        # 정기(구독)관련 테이블 (E)

        if ($res) {
            @cookiepay_payment_log("결제 시도 저장 성공", $sql, 3);
            $ret['status'] = true;
        } else {
            @cookiepay_payment_log("결제 시도 저장 실패", $sql, 3);
        }
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
    if (!empty($od_id))
    {
        $odResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_SHOP_ORDER." WHERE od_id='{$od_id}' LIMIT 1");
        $odResultId = isset($odResult['od_id']) && !empty($odResult['od_id']) ? $odResult['od_id'] : '';
    }
    
    if (!empty($odId) && empty($odResultId))
    {
        foreach ($cookiepay as $key => $val)
        {
            if ($key == 'od_zip')
            {
                $od_zip = preg_replace('/[^0-9]/', '', $val);
                $od_zip1 = substr($od_zip, 0, 3);
                $od_zip2 = substr($od_zip, 3);
                $keys['od_zip1'] = "od_zip1";
                $keys['od_zip2'] = "od_zip2";
                $vals['od_zip1'] = "'{$od_zip1}'";
                $vals['od_zip2'] = "'{$od_zip2}'";
            }
            else if ($key == 'od_b_zip')
            {
                $od_b_zip = preg_replace('/[^0-9]/', '', $val);
                $od_b_zip1  = substr($od_b_zip, 0, 3);
                $od_b_zip2  = substr($od_b_zip, 3);
                $keys['od_b_zip1'] = "od_b_zip1";
                $keys['od_b_zip2'] = "od_b_zip2";
                $vals['od_b_zip1'] = "'{$od_b_zip1}'";
                $vals['od_b_zip2'] = "'{$od_b_zip2}'";
            }
            else
            {
                $keys[$key] = "{$key}";
                $vals[$key] = "'{$val}'";
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
            $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
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
                    $values[$key] = "'{$val}'";
                }
            }
            $values['PGNAME'] = "'{$default['de_pg_service']}'";
            $values['ORDERNO'] = "'{$cookiepay['ORDERNO']}'";
            $values['pay_type'] = "'9'";
            $values['pay_status'] = "0";
            $valueStr = implode(",", $values);

            $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
            $res = sql_query($sql, false);

            # 정기(구독)관련 테이블 (S)
            $sql = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
            $res = sql_query($sql, false);

            $sql2 = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." ({$columnStr}) VALUES ({$valueStr}) ";
            $res2 = sql_query($sql2, false);
            # 정기(구독)관련 테이블 (E)

            if ($res) {
                @cookiepay_payment_log("정기(구독) 시도 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("정기(구독) 시도 저장 실패", $sql, 3);
                echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE=&RESULTMSG=";</script>';
                exit;
            }
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
                $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='{$keyinRes['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
                $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';
            }

            if ($payStatus == '') {
                // insert
                $columnStr = implode(",", $pgResultColumns_Subscribe);
                
                $values = [];
                foreach ($pgResultColumns_Subscribe as $val) {
                    $values[$val] = "''";
                }
                foreach ($keyinRes as $key => $val) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = "'{$val}'";
                    }
                }
                $values['PGNAME'] = "'{$default['de_pg_service']}'";
                $values['pay_type'] = "'9'";
                $values['pay_status'] = "1";
                $valueStr = implode(",", $values);

                # 상품명 추가
                $valueStr .= ", PRODUCT_NAME='".$cookiepay['PRODUCTNAME']."' ";

                $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
                $res = sql_query($sql, false);

                 # 정기(구독)관련 테이블 (S)
                $sql = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
                $res = sql_query($sql, false);


                $sql2 = " INSERT INTO ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." ({$columnStr}) VALUES ({$valueStr}) ";
                echo "sql2 : ".$sql2;
                # 정기(구독)관련 테이블 (E)            

                $res2 = sql_query($sql2, false);

                if ($res) {
                    @cookiepay_payment_log("정기(구독) 결과 저장 성공1", $sql, 3);
                } else {
                    @cookiepay_payment_log("정기(구독) 결과 저장 실패1", $sql, 3);
                }
                // end insert
            } else if ($payStatus != 1) {
                // update

                echo "which<br>";

                # 최초 기초데이터 저장된후 이곳에서 업데이트함
                $set = [];
                foreach ($keyinRes as $key => $val) {
                    if (in_array($key, $pgResultColumns)) {
                        $set[$key] = "{$key}='{$val}'";
                    }
                }
                $set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'";
                $set['pay_type'] = "pay_type='9'";
                $set['pay_status'] = "pay_status=1";                
                $setStr = implode(",", $set);

                $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='{$keyinRes['ORDERNO']}'";
                $res = sql_query($sql, false);

                # 정기(구독)관련 테이블 (S)
                $set = [];
                foreach ($keyinRes as $key => $val) {
                    if (in_array($key, $pgResultColumns_Subscribe)) {
                        $set[$key] = "{$key}='{$val}'";
                    }
                }
                $set['USERID'] = "USERID='".$cookiepay['BUYERID']."' ";
                $set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'";
                $set['pay_type'] = "pay_type='9'";
                $set['pay_status'] = "pay_status=1";                
                $setStr = implode(",", $set);

                $set['PAY_DATE'] = "PAY_DATE='".date('Y-m-d H:i:s', strtotime($keyinRes['ACCEPTDATE']))."' ";
                $set['PRODUCT_NAME'] = "PRODUCT_NAME='".$cookiepay['PRODUCTNAME']."' ";
                $setStr = implode(",", $set);

                $sql = "UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET {$setStr} WHERE ORDERNO='{$keyinRes['ORDERNO']}'";
                $res = sql_query($sql, false);

                //$set['PAY_DATE'] = "PAY_DATE='".date('Y-m-d H:i:s', strtotime($keyinRes['ACCEPTDATE']))."' ";  // 작업중(250520)
                $set['RESERVE_LAST_PAY_DATE'] = "RESERVE_LAST_PAY_DATE='".date('Y-m-d H:i:s')."' ";
                $set['RESERVE_LAST_PAY_RESULTCODE'] = "RESERVE_LAST_PAY_RESULTCODE='".$keyinRes['RESULTCODE']."' "; // 최근결제상태
                $set['RESERVE_LAST_PAY_RESULTMSG'] = "RESERVE_LAST_PAY_RESULTMSG='".$keyinRes['RESULTMSG']."' "; // 최근결제상태
                $setStr = implode(",", $set);
                $sql2 = "UPDATE ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." SET {$setStr} WHERE ORDERNO='{$keyinRes['ORDERNO']}'";
                $res2 = sql_query($sql2, false);
                # 정기(구독)관련 테이블 (E)

                if ($res) {
                    @cookiepay_payment_log("정기(구독) 결과 저장 성공2", $sql, 3);
                } else {
                    @cookiepay_payment_log("정기(구독) 결과 저장 실패2", $sql, 3);
                }
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
                            $values[$key] = "'{$val}'";
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
                            $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                            # 정기(구독)관련 테이블 (S)
                            $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                            # 정기(구독)관련 테이블 (E)
                        } else {
                            @cookiepay_payment_log("정기(구독) 취소 실패5", $ret['data'], 3);
                        }
            
                        $cancelArr = json_decode($ret['data'], true);
            
                        $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
                        $res = sql_query($sql, false);
                        if ($res) {
                            @cookiepay_payment_log("정기(구독) 취소결과 저장 성공6", $sql, 3);
                        } else {
                            @cookiepay_payment_log("정기(구독) 취소결과 저장 실패6", $sql, 3);
                        }
                    }
                } else {
                    @cookiepay_payment_log("정기(구독) 검증 토큰 발행 실패7", $resultJson, 3);
                    
                    $ret = cookipay_cancel_payment($cookiepayApiKeyin['api_id'], $cookiepayApiKeyin['api_key'], $keyinRes['TID']);
            
                    if ($ret['status'] === true) {
                        @cookiepay_payment_log("정기(구독) 취소 성공8", $ret['data'], 3);
                        $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                        # 정기(구독)관련 테이블 (S)
                        $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_SUBSCRIBE_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                        # 정기(구독)관련 테이블 (E)
                    } else {
                        @cookiepay_payment_log("정기(구독) 취소 실패8", $ret['data'], 3);
                    }
            
                    $cancelArr = json_decode($ret['data'], true);
            
                    $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
                    $res = sql_query($sql, false);
                    if ($res) {
                        @cookiepay_payment_log("정기(구독) 취소결과 저장 성공9", $sql, 3);
                    } else {
                        @cookiepay_payment_log("정기(구독) 취소결과 저장 실패9", $sql, 3);
                    }
                }

            // 결제 검증(E) #################################################################################

        } else {
            @cookiepay_payment_log("정기(구독) 실패", json_encode($keyinResJson), 3);
        }
    } else {
        @cookiepay_payment_log("정기(구독) 토큰 발행 실패", $tokenResJson, 3);
    }
    
    // s: cookiepay-plugin v1.2
    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.$keyinRes['RESULTCODE'].'&RESULTMSG='.$keyinRes['RESULTMSG'].'&type=keyin&reserve_id='.$keyinRes['RESERVE_ID'].'";</script>';
    // e: cookiepay-plugin v1.2
    
    exit;
} // end if ($mode == "keyin_pay") 
