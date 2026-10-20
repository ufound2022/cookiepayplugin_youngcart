<?php
$sub_menu = "400900";
include_once('./_common.php');
include_once(G5_LIB_PATH.'/json.lib.php');
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

//$cookiepay = json_decode(file_get_contents('php://input'), true);
$reserveid = $_POST['reserveid'];
$billkey = $_POST['billkey'];

// s: cookiepay-plugin > 보안검토 항목11 > v1.4 > 261014
// 이 파일은 권한 확인도 위조 방지 토큰 확인도 없이 요청에 담긴 BILLKEY 로
// 고객의 저장된 카드를 결제한다. 관리자가 속아서 링크를 열거나, 관리자 화면에
// 주입된 스크립트가 실행되면 그대로 결제가 일어난다.
// 권한과 토큰을 확인하고, 빌링키는 요청이 아니라 서버에서 조회한다.
require_once G5_PATH."/cookiepay/cookiepay.guard.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

$guardAuthError = auth_check_menu($auth, $sub_menu, 'w', true);

// check_admin_token() 은 실패 시 자체적으로 alert 후 종료해 AJAX 응답이 깨지므로 쓰지 않는다.
// 확인 전용 함수를 쓴다. 발급 함수를 부르면 새 토큰이 생겨 언제나 불일치한다.
$guardToken = isset($_POST['token']) ? $_POST['token'] : '';
$guardTokenOk = cookiepay_guard_admin_token_ok($guardToken);

// 빌링키는 요청에서 받지 않고 서버에서 조회한다.
// 조회 대상은 재결제할 회차 행(cookiepay_pg_subscribe_result)이다. 원래 버튼이
// data-billkey 로 싣던 값과 같은 출처라 동작이 바뀌지 않는다.
// 처음에는 등록 행(userlist)에서 읽었는데, 두 테이블의 값이 다르면 쿠키페이가
// E001(등록된 정보가 없습니다)로 거부한다.
$guardSubscribe = sql_fetch("
    SELECT RESERVE_ID, BILLKEY
    FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT."
    WHERE RESERVE_ID = '" . sql_real_escape_string((string) $reserveid) . "'
      AND BILLKEY != ''
    ORDER BY `id` DESC
    LIMIT 1
");

if (empty($guardSubscribe['BILLKEY'])) {
    $guardSubscribe = sql_fetch("
        SELECT RESERVE_ID, BILLKEY
        FROM ".COOKIEPAY_PG_SUBSCRIBE_USERLIST."
        WHERE RESERVE_ID = '" . sql_real_escape_string((string) $reserveid) . "'
        ORDER BY `id` DESC
        LIMIT 1
    ");
}

@cookiepay_payment_log("관리자 재결제 빌링키 조회", "RESERVE_ID ".$reserveid." / BILLKEY ".(isset($guardSubscribe['BILLKEY']) ? $guardSubscribe['BILLKEY'] : '(없음)'), 3);

$guardRet = cookiepay_guard_admin_repay($guardAuthError, $guardTokenOk, $guardSubscribe);

if (!$guardRet['ok']) {
    @cookiepay_payment_log("관리자 재결제 차단 ({$guardRet['code']})", $guardRet['msg']." / RESERVE_ID ".$reserveid, 3);
    echo json_encode(array('RESULTCODE' => '9999', 'RESULTMSG' => $guardRet['msg']));
    exit;
}

// 빌링키는 요청값을 버리고 서버 조회 값을 쓴다.
$billkey = $guardRet['billkey'];
// e: cookiepay-plugin > 보안검토 항목11 > v1.4 > 261014

$cookiepayApi = cookiepay_get_api_account_info($default, 9);

$tokenheaders = array(); 
array_push($tokenheaders, "content-type: application/json; charset=utf-8");

$token_url = COOKIEPAY_TOKEN_URL;

$token_request_data = array(
    'pay2_id' => $cookiepayApi['api_id'],
    'pay2_key'=> $cookiepayApi['api_key'],
);

$req_json = json_encode($token_request_data, TRUE);

$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $token_url);
curl_setopt($ch,CURLOPT_POST, false);
curl_setopt($ch,CURLOPT_POSTFIELDS, $req_json);
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
curl_setopt($ch,CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, $tokenheaders);
$RES_STR = curl_exec($ch);
curl_close($ch);
$RES_ARR = json_decode($RES_STR,TRUE);


/*
Array
(
    [RTN_CD] => 0000
    [RTN_MSG] => 성공
    [TOKEN] => eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfX2NpX2xhc3RfcmVnZW5lcmF0ZSI6MTc0NjY4Njc2OCwidXNlciI6InNhbmRib3hfN2ZMQVhFUWExTCIsImlhdCI6MTc0NjY4Njc2OCwiZXhwIjoxNzQ2NjkwMzY4fQ.R4ED_CAzyQ1fevVXy1-JJ3FdXGk-js3Zzykh8_-_Nwg
)
*/

/* 여기 까지 */
if($RES_ARR['RTN_CD'] == '0000'){

    $headers = array(); 
    array_push($headers, "content-type: application/json; charset=utf-8");
    array_push($headers, "ApiKey: ".$cookiepayApi['api_key']);
    array_push($headers, "TOKEN: ".$RES_ARR['TOKEN']);

    $cookiepayments_url = COOKIEPAY_SCHEDULE_REQUEST_PAYMENT;
    
    $request_data_array = array(
                            'API_ID' => "{$cookiepayApi['api_id']}",
                            'RESERVE_ID' => "{$reserveid}",
                            'BILLKEY' => "{$billkey}"
    );

    $cookiepayments_json = json_encode($request_data_array, TRUE);
    
    $ch = curl_init(); // curl 초기화
    
    curl_setopt($ch,CURLOPT_URL, $cookiepayments_url);
    curl_setopt($ch,CURLOPT_POST, false);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepayments_json);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result_decode_array = json_decode($response, true);
    //var_dump($response);

    // s: cookiepay-plugin > 재결제 계측 > v1.5 > 261019
    // 이 파일은 돈이 움직이는 호출인데 API 원문을 전혀 남기지 않아
    // 실패 원인을 추적할 수 없었다. 요청과 응답을 기록한다.
    @cookiepay_payment_log("관리자 재결제 요청", $cookiepayments_json, 3);
    @cookiepay_payment_log("관리자 재결제 응답", $response, 3);
    // e: cookiepay-plugin > 재결제 계측 > v1.5 > 261019

    # 암호화 전문 복호화하기(S)

    /*
        string(203) "{ "RESULTCODE": "0000", "RESULTMSG": "성공", "ENC_DATA": "4cH9f5+UybM2fog8K7DrGLBF9SC+6KUPARsdxnYwD/h/hF5FgM64Ah0uISQQibQI/GdPRZhOGpC30OMyf/eloVTeBgMqtH5Sl6bT2LoWvcn39KjfvPZrtaP/gjb8OS+W" }"
    */

    // s: cookiepay-plugin > 재결제 오류 전달 > v1.5 > 261019
    // 아래는 E103 만 따로 처리하고, 그 밖의 오류는 ENC_DATA 없이 복호화를 호출해
    // "ENC_DATA 필수 항목의 값이 없습니다" 라는 엉뚱한 2차 오류만 남긴다.
    // 쿠키페이가 실패를 알려 준 경우 그 사유를 그대로 관리자에게 보여 준다.
    if (isset($result_decode_array['RESULTCODE'])
        && $result_decode_array['RESULTCODE'] != '0000'
        && $result_decode_array['RESULTCODE'] != 'E103') {

        @cookiepay_payment_log(
            "관리자 재결제 실패 ({$result_decode_array['RESULTCODE']})",
            $result_decode_array['RESULTMSG']." / RESERVE_ID ".$reserveid." / BILLKEY ".$billkey,
            3
        );

        echo json_encode(array(
            'RESULTCODE' => $result_decode_array['RESULTCODE'],
            'RESULTMSG'  => $result_decode_array['RESULTMSG'],
        ));
        exit;
    }
    // e: cookiepay-plugin > 재결제 오류 전달 > v1.5 > 261019

    if($result_decode_array['RESULTCODE'] == "E103") {

        $result_array = array();
        $result_array['RESULTCODE'] = $result_decode_array['RESULTCODE'];
        $result_array['RESULTMSG'] = $result_decode_array['RESULTMSG'];

        $sql = "update `cookiepay_pg_subscribe_userlist`
            set RESERVE_SCHEDULE_CANCEL_DATE = '".substr($result_array['RESULTMSG'],0,19)."', pay_status='2' 
        where RESERVE_ID = '".$_POST['reserveid']."' limit 1 ";
        #sql_query($sql);

        $result_json = json_encode($result_array);
        echo $result_json;
        exit;
    }

    $headers = array(); 
    array_push($headers, "content-type: application/json; charset=utf-8");
    array_push($headers, "ApiKey: ".$cookiepayApi['api_key']);

    $cookiepay_api_url = COOKIEPAY_EDI_DECRYPT_URL;

    $edi_date = date('YmdHis');
    $request_data_array = array(
        'API_ID' => "{$cookiepayApi['api_id']}",
        'ENC_DATA' => "{$result_decode_array['ENC_DATA']}",
    );

    $cookiepay_api_json = json_encode($request_data_array, TRUE);

    $ch = curl_init(); // curl 초기화

    curl_setopt($ch,CURLOPT_URL, $cookiepay_api_url);
    curl_setopt($ch,CURLOPT_POST, false);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepay_api_json);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);
    curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $result_array = json_decode($response, true);

    // s: cookiepay-plugin > 재결제 계측 > v1.5 > 261019
    @cookiepay_payment_log("관리자 재결제 복호화 응답", $response, 3);
    // e: cookiepay-plugin > 재결제 계측 > v1.5 > 261019

    @cookiepay_payment_log("[통지] 정기(구독) 실패 재결제 결과 > 승인날짜 : ", isset($result_array['decryptData']['ACCEPT_DATE']) ? $result_array['decryptData']['ACCEPT_DATE'] : '(없음)', 3);

    /*
    Array
    (
        [RESULTCODE] => 0000
        [RESULTMSG] => 성공
        [decryptData] => Array
            (
                [RESULTCODE] => 0000
                [RESULTMSG] => 성공
                [API_ID] => sandbox_7fLAXEQa1L
                [BILLKEY] => as0jfv1530o4gcw437hc
                [RESERVE_ID] => 10z8899ups1wgcc8cw0os93qcle
                [RESERVE_ORDERNO] => 2025052014493791
                [PAY_CNT] => 6
                [LAST_PAY_CNT] => 50
                [TRY_CNT] => 1
                [NEXT_PAY_DATE] => 2025-06-20
                [ORDERNO] => SRS20250520150721140817
                [AMOUNT] => 200
                [TID] => cTS25052015072098435
                [BUYERNAME] => 최고관리자
                [BUYERPHONE] => 01076764624
                [BUYERADDRESS] => 
                [BUYEREMAIL] => naribi3@nate.com
                [BUYERID] => admin
                [USERID] => admin
                [PRODUCTCODE] => 2025052014493791
                [PRODUCTNAME] => 감
                [ACCEPT_DATE] => 20250520150720
                [ACCEPT_NO] => 01827647
                [CARDCODE] => 
                [CARDNAME] => 
                [CARDNO] => **********396626
                [QUOTA] => 00
                [ETC1] => 700
                [ETC2] => 
                [ETC3] => 
                [ETC4] => 
                [ETC5] => 
            )

    )
    */

    if($result_array['RESULTCODE'] == "0000") { 
        // RESERVE_SCHEDULE_CANCEL_DATE > 날짜 업데이트
        // pay_status > 필드값 2로 업데이트

        # 재결제 버튼 중복 입력 방지(승인건)
        if($result_array['decryptData']['RESULTCODE'] == "0000") { 

            sleep(1);
            @cookiepay_payment_log("[통지] 정기(구독) 중복체크 쿼리(승인) SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE TID='{$result_array['decryptData']['TID']}' AND TID != '' ORDER BY `id` DESC LIMIT 1 ", 3);
            $od_tno = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE TID='{$result_array['decryptData']['TID']}' AND TID != '' ORDER BY `id` DESC LIMIT 1 ");

            #echo "중복체크 : <br>";
            #print_r2($od_tno);
            #exit;

            if($result_array['decryptData']['RESULTCODE'] == "0000" && !empty($od_tno['id'])) { 


                # 재결제가 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
                /*
                $sql_u2 = "update `cookiepay_pg_subscribe_result` 
                            set pay_status = '1', 
                                repay_check = 'N' 
                            where RESERVE_ID = '".$result_array['decryptData']['RESERVE_ID']."' 
                                AND RESERVE_NOW_PAY_CNT = '".$result_array['decryptData']['PAY_CNT']."' 
                                
                        ";
                */
                $sql_u2 = "update `cookiepay_pg_subscribe_result` 
                            set repay_check = 'N' 
                            where RESERVE_ID = '".$result_array['decryptData']['RESERVE_ID']."' 
                                AND RESERVE_NOW_PAY_CNT = '".$result_array['decryptData']['PAY_CNT']."' 
                                
                        ";
                        //AND pay_status='3' 

                $result_u2 = sql_query($sql_u2);

                @cookiepay_payment_log("[통지] 정기(구독) 중복 결제정보 입력 차단 ", 3);

                $repay_result_array = array("RESULTCODE" => "0000", "RESULTMSG" => "결제성공");
                $repay_result_json = json_encode($repay_result_array);

                echo $repay_result_json;
                exit;

            }
        }

        # 영카트 주문내역 테이블에 저장한다(S)
        $od = sql_fetch(" SELECT * FROM {$g5['g5_shop_order_table']} WHERE od_id='{$result_array['decryptData']['RESERVE_ORDERNO']}' ORDER BY `od_id` DESC LIMIT 1 ");

        $sql_read = "SELECT * FROM {$g5['g5_shop_order_table']} WHERE od_id='{$result_array['decryptData']['RESERVE_ORDERNO']}' ORDER BY `od_id` DESC LIMIT 1";
        @cookiepay_payment_log("[통지] g5_shop_order Select Query : ", $sql_read, 3);

        $od_id = date('YmdHis').rand(11,99);

        // 주문서에 입력(g5_shop_order)
        $sql = " insert {$g5['g5_shop_order_table']}
        set od_id             = '$od_id',
            mb_id             = '{$od['mb_id']}',
            od_pwd            = '{$od['od_pwd']}',
            od_name           = '{$od['od_name']}',
            od_email          = '{$od['od_email']}',
            od_tel            = '{$od['od_tel']}',
            od_hp             = '{$od['od_hp']}',
            od_zip1           = '{$od['od_zip1']}',
            od_zip2           = '{$od['od_zip2']}',
            od_addr1          = '{$od['od_addr1']}',
            od_addr2          = '{$od['od_addr2']}',
            od_addr3          = '{$od['od_addr3']}',
            od_addr_jibeon    = '{$od['od_addr_jibeon']}',
            od_b_name         = '{$od['od_b_name']}',
            od_b_tel          = '{$od['od_b_tel']}',
            od_b_hp           = '{$od['od_b_hp']}',
            od_b_zip1         = '{$od['od_b_zip1']}',
            od_b_zip2         = '{$od['od_b_zip2']}',
            od_b_addr1        = '{$od['od_b_addr1']}',
            od_b_addr2        = '{$od['od_b_addr2']}',
            od_b_addr3        = '{$od['od_b_addr3']}',
            od_b_addr_jibeon  = '{$od['od_b_addr_jibeon']}',
            od_deposit_name   = '{$od['od_deposit_name']}',
            od_memo           = '{$od['od_memo']}',
            od_cart_count     = '{$od['od_cart_count']}',
            od_cart_price     = '{$od['od_cart_price']}',
            od_cart_coupon    = '{$od['od_cart_coupon']}',
            od_send_cost      = '{$od['od_send_cost']}',
            od_send_coupon    = '{$od['od_send_coupon']}',
            od_send_cost2     = '{$od['od_send_cost2']}',
            od_coupon         = '{$od['od_coupon']}',
            od_receipt_price  = '{$od['od_receipt_price']}',
            od_receipt_point  = '{$od['od_receipt_point']}',
            od_bank_account   = '{$result_array['decryptData']['CARDNAME']}',
            od_receipt_time   = '{$od['od_receipt_time']}',
            od_misu           = '{$od['od_misu']}',
            od_pg             = '{$od['od_pg']}',
            od_tno            = '{$result_array['decryptData']['TID']}',
            od_app_no         = '{$result_array['decryptData']['ACCEPT_NO']}',
            od_escrow         = '{$od['od_escrow']}',
            od_tax_flag       = '{$od['od_tax_flag']}',
            od_tax_mny        = '{$od['od_tax_mny']}',
            od_vat_mny        = '{$od['od_vat_mny']}',
            od_free_mny       = '{$od['od_free_mny']}',
            od_status         = '입금',
            od_shop_memo      = '',
            od_hope_date      = '{$od['od_hope_date']}',
            od_time           = '".G5_TIME_YMDHIS."',
            od_ip             = '{$_SERVER['REMOTE_ADDR']}',
            od_settle_case    = '정기(구독)',
            od_other_pay_type = '{$od['od_other_pay_type']}',
            od_test           = '{$od['od_test']}',
            od_casseqno       = '{$result_array['decryptData']['RESERVE_ID']}'  
            ";

            // ## 영카트 플러그인 > 정기(구독) > S
            // od_casseqno = '$od_reserve_id' 추가
            // ## 영카트 플러그인 > 정기(구독) > E
            
        #echo "sql : ".$sql;
        #exit;
        @cookiepay_payment_log("[통지] g5_shop_order insert Query : ", $sql, 3);

        if($result_array['decryptData']['RESULTCODE'] == "0000") { 

            #echo "sql : ".$sql;
            #exit;

            $result = sql_query($sql, false);
        }

        $od_cart_sql = " SELECT * FROM {$g5['g5_shop_cart_table']} WHERE od_id='{$result_array['decryptData']['RESERVE_ORDERNO']}' ORDER BY `ct_id` DESC ";
        # SELECT * FROM g5_shop_cart WHERE od_id='2025050809341263' ORDER BY `ct_id` DESC
        $result_cart = sql_query($od_cart_sql);

        for($i = 0; $opt=sql_fetch_array($result_cart); $i++) {

            # 반복 저장(S)
            #echo $opt['ct_id']."<br>";
            $od_cart_sql_insert = "insert into {$g5['g5_shop_cart_table']}
            set od_id             = '{$od_id}',
                mb_id             = '{$opt['mb_id']}',
                it_id             = '{$opt['it_id']}',
                it_name           = '{$opt['it_name']}',
                it_sc_type        = '{$opt['it_sc_type']}',
                it_sc_method      = '{$opt['it_sc_method']}',
                it_sc_price       = '{$opt['it_sc_price']}',
                it_sc_minimum     = '{$opt['it_sc_minimum']}',
                it_sc_qty         = '{$opt['it_sc_qty']}',
                ct_status         = '입금',
                ct_history        = '',
                ct_price          = '{$opt['ct_price']}',
                ct_point          = '{$opt['ct_point']}',
                cp_price          = '{$opt['cp_price']}',
                ct_point_use      = '{$opt['ct_point_use']}',
                ct_stock_use      = '{$opt['ct_stock_use']}',
                ct_option         = '{$opt['ct_option']}',
                ct_qty            = '{$opt['ct_qty']}',
                ct_notax          = '{$opt['ct_notax']}',
                io_id             = '{$opt['io_id']}',
                io_type           = '{$opt['io_type']}',
                io_price          = '{$opt['io_price']}',
                ct_time           = '".G5_TIME_YMDHIS."',
                ct_ip             = '{$_SERVER['REMOTE_ADDR']}',
                ct_send_cost      = '{$opt['ct_send_cost']}',
                ct_direct         = '{$opt['ct_direct']}',
                ct_select         = '{$opt['ct_select']}',
                ct_select_time    = '{$opt['ct_select_time']}'

            ";

            @cookiepay_payment_log("[통지] g5_shop_cart insert Query : ", $od_cart_sql_insert, 3);

            #echo "od_cart_sql_insert : ".$od_cart_sql_insert."<br>";
            if($result_array['decryptData']['RESULTCODE'] == "0000") { 
                $result2 = sql_query($od_cart_sql_insert, false);
            }
            # 반복 저장(E)

        }
    
        # 영카트 주문내역 테이블에 저장한다(E)

        #$od_id = date('YmdHis').rand(11,99); // 주문번호 생성 중복 주석처리 > 250527
        $cookiepay_pg_subscribe_result = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE RESERVE_ID='{$result_array['decryptData']['RESERVE_ID']}' AND RESERVE_RECURRENCE_TYPE != '' AND RESERVE_START_PAY_CNT != '' AND RESERVE_LAST_PAY_CNT != '' ORDER BY `id` DESC LIMIT 1 ");

        if($result_array['decryptData']['RESULTCODE'] == "0000") { 
            $pay_status_str = "1";
            # 최종결제 결과코드
            $RESERVE_LAST_PAY_RESULTCODE = $result_array['decryptData']['RESULTCODE'];
        } else { 
            $pay_status_str = "3";         
            # 최종결제 결과메세지
            $RESERVE_LAST_PAY_RESULTMSG = $result_array['decryptData']['RESULTMSG'];
        }

        # 재결제가 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
        $sql_u2 = "update `cookiepay_pg_subscribe_result` 
                      set pay_status = '".$pay_status_str."', 
                          repay_check = 'N' 
                    where RESERVE_ID = '".$_POST['reserveid']."' 
                          AND RESERVE_NOW_PAY_CNT = '".$result_array['decryptData']['PAY_CNT']."' 
                          
                  ";
                  //AND pay_status='3' 

        $result_u2 = sql_query($sql_u2);


        # 실패때 PAY_DATE 처리를 하기 위한 로직(S)
        $accept_date_str = "";
        if(empty($result_array['decryptData']['ACCEPT_DATE'])) { 
            $accept_date_str = date('Y-m-d H:i:s');
        } else {
            $accept_date_str = $result_array['decryptData']['ACCEPT_DATE'];
        }
        # 실패때 PAY_DATE 처리를 하기 위한 로직(E)
            
        $SQL_I = "INSERT INTO `cookiepay_pg_subscribe_result` set 
                            RESULTCODE              = '".$result_array['decryptData']['RESULTCODE']."', 
                            RESULTMSG               = '".$result_array['decryptData']['RESULTMSG']."', 
                            USERID                  = '".$result_array['decryptData']['USERID']."', 
                            ORDERNO                 = '".$od_id."', 
                            AMOUNT                  = '".$result_array['decryptData']['AMOUNT']."', 
                            PRODUCT_NAME            = '".$result_array['decryptData']['PRODUCTNAME']."', 
                            TID                     = '".$result_array['decryptData']['TID']."', 
                            ACCEPTDATE              = '".$result_array['decryptData']['ACCEPT_DATE']."', 
                            ACCEPTNO                = '".$result_array['decryptData']['ACCEPT_NO']."', 
                            PAY_DATE                = '".$accept_date_str."', 
                            CARDNAME                = '".$result_array['decryptData']['CARDNAME']."',
                            CARDCODE                = '".$result_array['decryptData']['CARDCODE']."',
                            QUOTA                   = '".$result_array['decryptData']['QUOTA']."',
                            BILLKEY                 = '".$result_array['decryptData']['BILLKEY']."',
                            GENDATE                 = '".$result_array['decryptData']['ACCEPT_DATE']."',
                            RESERVE_RESULTCODE      = '".$result_array['decryptData']['RESERVE_RESULTCODE']."',
                            RESERVE_RESULTMSG       = '".$result_array['decryptData']['RESERVE_RESULTMSG']."',
                            RESERVE_ID              = '".$result_array['decryptData']['RESERVE_ID']."',
                            RESERVE_ORDERNO         = '".$result_array['decryptData']['RESERVE_ORDERNO']."',
                            RESERVE_RECURRENCE_TYPE = '".$cookiepay_pg_subscribe_result['RESERVE_RECURRENCE_TYPE']."',
                            RESERVE_PAY_DAY         = '".$cookiepay_pg_subscribe_result['RESERVE_PAY_DAY']."',
                            RESERVE_NOW_PAY_CNT     = '".$result_array['decryptData']['PAY_CNT']."',
                            RESERVE_START_PAY_CNT   = '".$cookiepay_pg_subscribe_result['RESERVE_START_PAY_CNT']."',
                            RESERVE_LAST_PAY_CNT    = '".$cookiepay_pg_subscribe_result['RESERVE_LAST_PAY_CNT']."',
                            RESERVE_NEXT_PAY_DATE   = '".$result_array['decryptData']['NEXT_PAY_DATE']."',
                            RESERVE_RETURN_URL      = '".$result_array['decryptData']['RESERVE_RETURN_URL']."',
                            ETC1                    = '".$result_array['decryptData']['ETC1']."',
                            ETC2                    = '".$result_array['decryptData']['ETC2']."',
                            ETC3                    = '".$result_array['decryptData']['ETC3']."',
                            ETC4                    = '".$result_array['decryptData']['ETC4']."',
                            ETC5                    = '".$result_array['decryptData']['ETC5']."',
                            PGNAME                  = '".$default['de_pg_service']."',
                            pay_type                = '9',
                            pay_status              = '".$pay_status_str."'
        ";

        #echo "SQL_I : ".$SQL_I;
        #exit;

        $result_i = sql_query($SQL_I);

        $sql_u = "update `cookiepay_pg_subscribe_userlist`
                    set RESERVE_LAST_PAY_RESULTCODE = '".$RESERVE_LAST_PAY_RESULTCODE."', 
                        RESERVE_LAST_PAY_RESULTMSG = '".$RESERVE_LAST_PAY_RESULTMSG."', 
                        pay_status='".$pay_status_str."'

                where RESERVE_ID = '".$_POST['reserveid']."' limit 1 ";
        $result_u = sql_query($sql_u);

    } 

    if(!empty($result_u)) { 

        $repay_result_array = array("RESULTCODE" => "{$result_array['decryptData']['RESULTCODE']}", "RESULTMSG" => "{$result_array['decryptData']['RESULTMSG']}");
        $repay_result_json = json_encode($repay_result_array);

        echo $repay_result_json;
        exit;
    }

    # 암호화 전문 복호화하기 (E)


}

// s: cookiepay-plugin > 재결제 응답 보강 > v1.5 > 261019
// 위 블록은 토큰 발급이 실패하면(else 가 없다) 아무것도 출력하지 않고, 안에서도
// 출력 없이 빠져나가는 경로가 여럿이다. 호출부는 JSON.parse 를 하므로 빈 응답이
// "Unexpected end of JSON input" 으로만 보여 원인을 알 수 없다.
// 여기까지 내려왔다면 재결제가 끝나지 않은 것이므로 실패를 돌려주고 로그를 남긴다.
@cookiepay_payment_log(
    "관리자 재결제 응답 없음",
    "RESERVE_ID ".$reserveid
    ." / 토큰발급 RTN_CD ".(isset($RES_ARR['RTN_CD']) ? $RES_ARR['RTN_CD'] : '(응답없음)')
    ." / 토큰응답 ".(isset($RES_STR) ? $RES_STR : '(없음)'),
    3
);

echo json_encode(array(
    'RESULTCODE' => '9999',
    'RESULTMSG'  => '재결제 처리가 완료되지 않았습니다. 결제 로그를 확인해 주세요.',
));
exit;
// e: cookiepay-plugin > 재결제 응답 보강 > v1.5 > 261019
?>

