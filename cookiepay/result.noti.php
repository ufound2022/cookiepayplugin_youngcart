<?php 
#error_reporting(E_ALL); 
#ini_set("display_errors", 1); 

include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 결제 결과 통지 수신 후 처리
#exit;

$cookiepay = json_decode(file_get_contents('php://input'), true);

# 정기(반복) 결제 통지 수신(S) 
/*
([통지]수신)
{
    "CP_ID":"CTS18304",
    "API_ID":"sandbox_7fLAXEQa1L",
    "ORDERNO":"SRS20250508093947857860",
    "VENDOR_ORDER_NO":null,
    "AMOUNT":"200",
    "TID":"cTS25050809394695923",
    "USERID":"",
    "BUYERNAME":"\ucd5c\uace0\uad00\ub9ac\uc790",
    "BUYEREMAIL":"",
    "PRODUCTCODE":"2025050809",
    "PRODUCTNAME":"\uac10",
    "ACCEPT_DATE":"20250508093947",
    "ACCEPT_NO":"",
    "CARDCODE":"",
    "CARDNAME":"IBK\ube44\uc528\uccb4\ud06c",
    "CARDNO":"",
    "QUOTA":"",
    "ETC1":"",
    "ETC2":"",
    "ETC3":"",
    "ETC4":"",
    "ETC5":"",
    "ETC7":null,
    "PAY_METHOD":"CARD",
    "SMS_INPUT":"",
    "BILLKEY":"4degzpumk8owg00o33ij",
    "GENDATE":"2025-05-08 09:35:39",
    "BUYERADDRESS":"",
    "BUYERPHONE":"01076764624",
    "RESULTCODE":"0000",
    "RESULTMSG":"\uc0c1\uacf5",
    "RESERVE_ID":"8c96vu3u8ls84c8cocg02mf5lkrk",
    "RESERVE_ORDERNO":"2025050809341263",
    "PAY_CNT":"2",
    "LAST_PAY_CNT":"5",
    "TRY_CNT":"1",
    "NEXT_PAY_DATE":"2025-06-10"
}
*/
# 정기(반복) 결제 통지 수신(E) 

# (1) select * from cookiepay_pg_subscribe_userlist where billkey='{RESERVE_ORDERNO}' > 기존 주문번호 검색
# (2) 해당 주문번호 조회된 값으로 거래내역 입력 > 주문번호는 생성한다.

@cookiepay_payment_log("[통지]스타트", ' : OK', 3);
@cookiepay_payment_log("[통지]수신", json_encode($cookiepay), 3);

$params_sb['ORDERNO'] = isset($cookiepay['ORDERNO']) && !empty($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : ''; 
$params_sb['AMOUNT'] = isset($cookiepay['AMOUNT']) && !empty($cookiepay['AMOUNT']) ? $cookiepay['AMOUNT'] : '';
$params_sb['USERID'] = isset($cookiepay['USERID']) && !empty($cookiepay['USERID']) ? $cookiepay['USERID'] : ''; 
$params_sb['BUYERNAME'] = isset($cookiepay['BUYERNAME']) && !empty($cookiepay['BUYERNAME']) ? $cookiepay['BUYERNAME'] : ''; 
$params_sb['BUYEREMAIL'] = isset($cookiepay['BUYEREMAIL']) && !empty($cookiepay['BUYEREMAIL']) ? $cookiepay['BUYEREMAIL'] : ''; 
$params_sb['PRODUCTNAME'] = isset($cookiepay['PRODUCTNAME']) && !empty($cookiepay['PRODUCTNAME']) ? $cookiepay['PRODUCTNAME'] : '';  
$params_sb['PRODUCTCODE'] = isset($cookiepay['PRODUCTCODE']) && !empty($cookiepay['PRODUCTCODE']) ? $cookiepay['PRODUCTCODE'] : ''; 
$params_sb['RESERVE_ORDERNO'] = isset($cookiepay['RESERVE_ORDERNO']) && !empty($cookiepay['RESERVE_ORDERNO']) ? $cookiepay['RESERVE_ORDERNO'] : '';
$params_sb['PAY_CNT'] = isset($cookiepay['PAY_CNT']) && !empty($cookiepay['PAY_CNT']) ? $cookiepay['PAY_CNT'] : '';
$params_sb['TID'] = isset($cookiepay['TID']) && !empty($cookiepay['TID']) ? $cookiepay['TID'] : '';
$params_sb['ACCEPT_NO'] = isset($cookiepay['ACCEPT_NO']) && !empty($cookiepay['ACCEPT_NO']) ? $cookiepay['ACCEPT_NO'] : '';
$params_sb['ACCEPT_DATE'] = isset($cookiepay['ACCEPT_DATE']) && !empty($cookiepay['ACCEPT_DATE']) ? $cookiepay['ACCEPT_DATE'] : '';
$params_sb['RESERVE_ID'] = isset($cookiepay['RESERVE_ID']) && !empty($cookiepay['RESERVE_ID']) ? $cookiepay['RESERVE_ID'] : '';
$params_sb['CARDNAME'] = isset($cookiepay['CARDNAME']) && !empty($cookiepay['CARDNAME']) ? $cookiepay['CARDNAME'] : '';

$params_sb['BILLKEY'] = isset($cookiepay['BILLKEY']) && !empty($cookiepay['BILLKEY']) ? $cookiepay['BILLKEY'] : '';
$params_sb['GENDATE'] = isset($cookiepay['GENDATE']) && !empty($cookiepay['GENDATE']) ? $cookiepay['GENDATE'] : '';

$params_sb['RESULTCODE'] = isset($cookiepay['RESULTCODE']) && !empty($cookiepay['RESULTCODE']) ? $cookiepay['RESULTCODE'] : '';
$params_sb['RESULTMSG'] = isset($cookiepay['RESULTMSG']) && !empty($cookiepay['RESULTMSG']) ? $cookiepay['RESULTMSG'] : '';
$params_sb['NEXT_PAY_DATE'] = isset($cookiepay['NEXT_PAY_DATE']) && !empty($cookiepay['NEXT_PAY_DATE']) ? $cookiepay['NEXT_PAY_DATE'] : '';

$params_sb['ETC1'] = isset($cookiepay['ETC1']) && !empty($cookiepay['ETC1']) ? $cookiepay['ETC1'] : '';
$params_sb['ETC2'] = isset($cookiepay['ETC2']) && !empty($cookiepay['ETC2']) ? $cookiepay['ETC2'] : '';
$params_sb['ETC3'] = isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3']) ? $cookiepay['ETC3'] : '';
$params_sb['ETC4'] = isset($cookiepay['ETC4']) && !empty($cookiepay['ETC4']) ? $cookiepay['ETC4'] : '';
$params_sb['ETC5'] = isset($cookiepay['ETC5']) && !empty($cookiepay['ETC5']) ? $cookiepay['ETC5'] : '';

#$params_sb['RESERVE_ORDERNO'] = "2025050809341263";
#$params_sb['PAY_CNT'] = 3;

//$od_tno = sql_fetch(" SELECT * FROM {$g5['g5_shop_order_table']} WHERE tno='{$params_sb['TID']}' ORDER BY `od_id` DESC LIMIT 1 ");
@cookiepay_payment_log("[통지] 정기(구독) 중복체크 쿼리(승인) SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE TID='{$params_sb['TID']}' AND TID != '' ORDER BY `id` DESC LIMIT 1 ", 3);
$od_tno = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE TID='{$params_sb['TID']}' AND TID != '' ORDER BY `id` DESC LIMIT 1 ");
# 정기(구독) 중복 주문번호(성공건)

# 정기(구독) 재결제실패 노티는 막는다.
//$subscribe_result = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE RESERVE_ORDERNO='{$params_sb['RESERVE_ORDERNO']}' AND repay_check='Y' AND RESERVE_NOW_PAY_CNT = '".."' AND RESULTCODE != '0000' ORDER BY `od_id` DESC LIMIT 1 ");

if($params_sb['RESULTCODE'] == "0000" && !empty($od_tno['id'])) { 
    @cookiepay_payment_log("[통지] 정기(구독) 중복 결제정보 입력 차단 ", 3);

    # 재결제가 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
    $sql_u2 = "update `cookiepay_pg_subscribe_result` 
                set pay_status = '1', 
                    repay_check = 'N' 
                where RESERVE_ID = '".$params_sb['RESERVE_ID']."' 
                    AND RESERVE_NOW_PAY_CNT = '".$params_sb['PAY_CNT']."' 
                    
            ";
            //AND pay_status='3' 

    $result_u2 = sql_query($sql_u2);
    exit;
}

# 정기(구독) 반복결제 결제내역 입력(S)
if(!empty($params_sb['RESERVE_ORDERNO']) && $params_sb['PAY_CNT'] >= 2) { 

    # 반복결제 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
    $sql_u22 = "update `cookiepay_pg_subscribe_result` 
                set 
                    repay_check = 'N' 
                where RESERVE_ID = '".$params_sb['RESERVE_ID']."' 
                    AND RESERVE_NOW_PAY_CNT = '".$params_sb['PAY_CNT']."' 
                    
            ";
            //AND pay_status='3' 

    $result_u22 = sql_query($sql_u22);

    $od = sql_fetch(" SELECT * FROM {$g5['g5_shop_order_table']} WHERE od_id='{$params_sb['RESERVE_ORDERNO']}' ORDER BY `od_id` DESC LIMIT 1 ");

    $sql_read = "SELECT * FROM {$g5['g5_shop_order_table']} WHERE od_id='{$params_sb['RESERVE_ORDERNO']}' ORDER BY `od_id` DESC LIMIT 1";
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
        od_bank_account   = '{$params_sb['CARDNAME']}',
        od_receipt_time   = '{$od['od_receipt_time']}',
        od_misu           = '{$od['od_misu']}',
        od_pg             = '{$od['od_pg']}',
        od_tno            = '{$params_sb['TID']}',
        od_app_no         = '{$params_sb['ACCEPT_NO']}',
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
        od_casseqno       = '{$params_sb['RESERVE_ID']}'  
        ";

        // ## 영카트 플러그인 > 정기(구독) > S
        // od_casseqno = '$od_reserve_id' 추가
        // ## 영카트 플러그인 > 정기(구독) > E
        
    #echo "sql : ".$sql;
    #exit;
    @cookiepay_payment_log("[통지] g5_shop_order insert Query : ", $sql, 3);

    if($params_sb['RESULTCODE'] == "0000") { 
        $result = sql_query($sql, false);
    }

    $od_cart_sql = " SELECT * FROM {$g5['g5_shop_cart_table']} WHERE od_id='{$params_sb['RESERVE_ORDERNO']}' ORDER BY `ct_id` DESC ";
    # SELECT * FROM g5_shop_cart WHERE od_id='2025050809341263' ORDER BY `ct_id` DESC
    $result_cart = sql_query($od_cart_sql);

    for($i = 0; $opt=sql_fetch_array($result_cart); $i++) {

        # 반복 저장(S)
        //echo $opt['ct_id']."<br>";
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
        if($params_sb['RESULTCODE'] == "0000") { 
            $result2 = sql_query($od_cart_sql_insert, false);
        }
        # 반복 저장(E)

    }
    

    # 취소때문에 COOKIEPAY_PG_RESULT 테이블에도 정보 입력해야함
    $sql_result_insert = "insert into ".COOKIEPAY_PG_RESULT." 
    set RESULTCODE              = '{$params_sb['RESULTCODE']}',
        RESULTMSG               = '{$params_sb['RESULTMSG']}',
        ORDERNO                 = '{$od_id}',
        AMOUNT                  = '{$params_sb['AMOUNT']}',
        TID                     = '{$params_sb['TID']}',
        ACCEPTNO                = '{$params_sb['ACCEPT_NO']}',
        ACCEPTDATE              = '{$params_sb['ACCEPT_DATE']}',
        ACCOUNTNO               = '',
        RECEIVERNAME            = '',
        DEPOSITENDDATE          = '',
        CARDNAME                = '{$params_sb['CARDNAME']}',
        CARDCODE                = '',
        PGNAME                  = '{$default['de_pg_service']}',
        pay_type                = '9',
        pay_status              = '{$pay_status_str}'     
    ";

   $result22 = sql_query($sql_result_insert, false);
    @cookiepay_payment_log("[통지] COOKIEPAY_PG_RESULT insert Query : ", $sql_result_insert, 3);
    
    # 취소때문에 COOKIEPAY_PG_VERIFY 테이블에도 정보 입력해야함

    $sql_verify_insert = "insert into ".COOKIEPAY_PG_VERIFY." 
    set RESULTCODE              = '{$params_sb['RESULTCODE']}',
        RESULTMSG               = '{$params_sb['RESULTMSG']}',
        ORDERNO                 = '{$od_id}',
        AMOUNT                  = '{$params_sb['AMOUNT']}',
        BUYERNAME               = '{$params_sb['BUYERNAME']}',
        BUYEREMAIL              = '{$params_sb['BUYEREMAIL']}',
        PRODUCTNAME             = '{$params_sb['PRODUCTNAME']}',
        PRODUCTCODE             = '{$params_sb['PRODUCTCODE']}',
        PAYMETHOD               = 'CARD_BATCH',
        BUYERID                 = '{$od['mb_id']}',
        TID                     = '{$params_sb['TID']}',
        ACCEPTNO                = '{$params_sb['ACCEPT_NO']}',
        ACCEPTDATE              = '{$params_sb['ACCEPT_DATE']}',
        CANCELDATE              = '',
        CANCELMSG               = '',
        ACCOUNTNO               = '',
        RECEIVERNAME            = '',
        DEPOSITENDDATE          = '',
        CARDNAME                = '{$params_sb['CARDNAME']}',
        CARDCODE                = ''
    
    ";
    @cookiepay_payment_log("[통지] COOKIEPAY_PG_VERIFY insert Query : ", $sql_verify_insert, 3);

    $result3 = sql_query($sql_verify_insert, false);


    # COOKIEPAY_PG_SUBSCRIBE_RESULT 테이블에서 > 기존 거래내역을 가저온다
    $cookiepay_pg_subscribe_result = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE RESERVE_ID='{$params_sb['RESERVE_ID']}' AND RESERVE_RECURRENCE_TYPE != '' AND RESERVE_START_PAY_CNT != '' AND RESERVE_LAST_PAY_CNT != '' ORDER BY `id` DESC LIMIT 1 ");

    # 정기(구독) 반복 결제내역(S)

    $pay_status_str = "0";
    if($params_sb['RESULTCODE'] == "0000") { 
        $pay_status_str = "1";
    } else { 
        $pay_status_str = "3";
    }

    # GENDATE 공백일시 날짜 값 업데이트
    if(empty($params_sb['GENDATE'])) { 
        $params_sb['GENDATE'] = date('Y-m-d H:i:s');
    }
    $sql_pg_result_insert = "insert into ".COOKIEPAY_PG_SUBSCRIBE_RESULT."
    set RESULTCODE              = '{$params_sb['RESULTCODE']}',
        RESULTMSG               = '{$params_sb['RESULTMSG']}',
        USERID                  = '{$params_sb['USERID']}', 
        ORDERNO                 = '{$od_id}',
        AMOUNT                  = '{$params_sb['AMOUNT']}',
        PRODUCT_NAME            = '{$params_sb['PRODUCTNAME']}',
        TID                     = '{$params_sb['TID']}',
        ACCEPTDATE              = '{$params_sb['ACCEPT_DATE']}',
        ACCEPTNO                = '{$params_sb['ACCEPT_NO']}',
        PAY_DATE                = '{$params_sb['GENDATE']}',
        CASH_BILL_NO            = '',
        CARDNAME                = '{$params_sb['CARDNAME']}',
        ACCOUNTNO               = '',
        RECEIVERNAME            = '',
        DEPOSITENDDATE          = '',
        CARDCODE                = '',
        QUOTA                   = '',
        BILLKEY                 = '{$params_sb['BILLKEY']}',
        GENDATE                 = '".date('YmdHis', strtotime($params_sb['GENDATE']))."',
        RESERVE_RESULTCODE      = '{$params_sb['RESULTCODE']}',
        RESERVE_RESULTMSG       = '{$params_sb['RESULTMSG']}',
        RESERVE_ID              = '{$params_sb['RESERVE_ID']}',
        RESERVE_ORDERNO         = '{$params_sb['RESERVE_ORDERNO']}',
        RESERVE_RECURRENCE_TYPE = '{$cookiepay_pg_subscribe_result['RESERVE_RECURRENCE_TYPE']}',
        RESERVE_PAY_DAY         = '".substr($params_sb['NEXT_PAY_DATE'], -2)."',
        RESERVE_NOW_PAY_CNT     = '{$params_sb['PAY_CNT']}',
        RESERVE_START_PAY_CNT   = '{$cookiepay_pg_subscribe_result['RESERVE_START_PAY_CNT']}',
        RESERVE_LAST_PAY_CNT    = '{$cookiepay_pg_subscribe_result['RESERVE_LAST_PAY_CNT']}',
        RESERVE_NEXT_PAY_DATE   = '{$params_sb['NEXT_PAY_DATE']}',
        RESERVE_RETURN_URL      = '',
        ETC1                    = '{$params_sb['ETC1']}',
        ETC2                    = '{$params_sb['ETC2']}',
        ETC3                    = '{$params_sb['ETC3']}',
        ETC4                    = '{$params_sb['ETC4']}',                
        ETC5                    = '{$params_sb['ETC5']}',
        PGNAME                  = '{$default['de_pg_service']}',
        pay_type                = '9',
        pay_status              = '{$pay_status_str}'
    ";

    @cookiepay_payment_log("[통지] ".COOKIEPAY_PG_SUBSCRIBE_RESULT." insert Query : ", $sql_pg_result_insert, 3);

    $result4 = sql_query($sql_pg_result_insert, false);

    # 정기(구독) 등록고객 테이블 업데이트
    $sql_subscribe_userlist = " update ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." 
        set RESERVE_NOW_PAY_CNT = {$params_sb['PAY_CNT']},
            RESERVE_LAST_PAY_RESULTCODE = '".$params_sb['RESULTCODE']."',
            RESERVE_LAST_PAY_RESULTMSG  = '".$params_sb['RESULTMSG']."'
        where RESERVE_ID = '{$params_sb['RESERVE_ID']}' limit 1 ";
    sql_query($sql_subscribe_userlist);

    exit;
}
# 정기(구독) 반복결제 결제내역 입력(E)




@cookiepay_payment_log("[통지]결제결과 저장 성공1", $cookiepay, 3);

$cookiepay['ACCEPT_NO'] = isset($cookiepay['ACCEPT_NO']) && !empty($cookiepay['ACCEPT_NO']) ? $cookiepay['ACCEPT_NO'] : '';
$cookiepay['TID'] = isset($cookiepay['TID']) && !empty($cookiepay['TID']) ? $cookiepay['TID'] : '';
$cookiepay['ORDERNO'] = isset($cookiepay['ORDERNO']) && !empty($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : '';

$resultMode = null;

if($cookiepay['ACCEPT_NO'] == "00000000" || $cookiepay['PAY_METHOD'] == "VACCOUNT") { 
    $cookiepay['ACCEPT_NO'] = "11111111";
}


if(!empty($cookiepay['ACCEPT_NO']) && !empty($cookiepay['TID']) && !empty($cookiepay['ORDERNO'])) {
    $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
    $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';

    $cookiepay['RESULTCODE'] = '0000';
    $cookiepay['RESULTMSG'] = '성공';
    $cookiepay['ACCEPTDATE'] = $cookiepay['ACCEPT_DATE'];
    $cookiepay['ACCEPTNO'] = $cookiepay['ACCEPT_NO'];
    unset($cookiepay['ACCEPT_DATE']);
    unset($cookiepay['ACCEPT_NO']);

    if ($payStatus == '') {
        // insert
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
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $values['pay_type'] = "'{$cookiepay['ETC3']}'";
        }
        $values['pay_status'] = 1;
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("[통지]결제결과 저장 성공1", $sql, 3);
            $resultMode = 'insert';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패1", $sql, 3);
        }
    } else if ($payStatus != 1) {
        // update
        $set = [];
        foreach ($cookiepay as $key => $val) {
            if (in_array($key, $pgResultColumns)) {
                $set[$key] = "{$key}='{$val}'";
            }
        }
        $set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $set['pay_type'] = "pay_type='{$cookiepay['ETC3']}'";
        }
        $set['pay_status'] = "pay_status=1";
        $setStr = implode(",", $set);

        $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='{$cookiepay['ORDERNO']}'";

        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("[통지]결제결과 저장 성공2", $sql, 3);
            $resultMode = 'update';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패2", $sql, 3);
        }
    }

    if ($resultMode == 'insert' || $resultMode == 'update') {
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) {
            $cookiepayApi = cookiepay_get_api_account_info($default, $cookiepay['ETC3']);
        } else {
            $cookiepayApi = cookiepay_get_api_account_info($default, 3);
        }

        // 결제 검증
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
        );

        $token_url = COOKIEPAY_TOKEN_URL;

        $request_data = array(
            'pay2_id' => $cookiepayApi['api_id'],
            'pay2_key'=> $cookiepayApi['api_key'],
        );

        $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resultJson = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resultJson, true);

        if($result['RTN_CD'] == '0000') {

            $paycert_url = COOKIEPAY_VERIFY_URL;

            $headers = array(
                'content-type: application/json; charset=utf-8',
                'TOKEN: ' . $result['TOKEN'],
            );

            $request_data = array(
                'tid' => $cookiepay['TID'],
            );

            $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $paycert_url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);
            $verify = json_decode($response, true);

            $pgVerify = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_VERIFY." WHERE ORDERNO='{$cookiepay['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
            $verifyId = isset($pgVerify['id']) && !empty($pgVerify['id']) ? $pgVerify['id'] : null;

            // 결제 검증 결과 테이블에 저장
            if (is_null($verifyId)) {
                // column 쿼리 처리
                $columnStr = implode(",", $pgVerifyColumns);

                // values 쿼리 처리
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
            } else {
                $set = [];
                foreach ($verify as $key => $val) {
                    if (in_array($key, $pgVerifyColumns)) {
                        $set[$key] = "{$key}='{$val}'";
                    }
                }
                $setStr = implode(",", $set);
                $sql = "UPDATE ".COOKIEPAY_PG_VERIFY." SET {$setStr} WHERE ORDERNO='{$cookiepay['ORDERNO']}'";
                $res = sql_query($sql, false);
            }

            if ($res) {
                @cookiepay_payment_log("[통지]결제검증결과 저장 성공3", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제검증결과 저장 실패3", $sql, 3);
            }

            if($verify['RESULTCODE'] == '0000') {
                
                // s: cookiepay-plugin v1.2
                $exists_sql = "select od_id from {$g5['g5_shop_order_table']} where od_id = '{$cookiepay['ORDERNO']}'";
                $exists_order = sql_fetch($exists_sql);
                if (!isset($exists_order['od_id']))
                {
                    $sql = "select * from ".COOKIEPAY_SHOP_ORDER." where od_id = '{$cookiepay['ORDERNO']}'";
                    $order_data = sql_fetch($sql);
                    if ($order_data)
                    {
                        $sql = "SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}'";
                        $pg_data = sql_fetch($sql);
                        
                        $i_price = $order_data['od_price'] + $order_data['od_send_cost'] + $order_data['od_send_cost2'] - $order_data['od_temp_point'] - $order_data['od_send_coupon'];
                        $od_receipt_time = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3 \\4:\\5:\\6", $pg_data['ACCEPTDATE']);
                        $od_misu = $i_price - $pg_data['AMOUNT'];
                        if ($od_misu == 0)
                        {
                            $od_status = '입금';
                        }
                        else
                        {
                            $od_status = '주문';
                        }
                        
                        // 복합과세 금액
                        $od_tax_mny = round($i_price / 1.1);
                        $od_vat_mny = $i_price - $od_tax_mny;
                        $od_free_mny = 0;
                        if ($default['de_tax_flag_use'])
                        {
                            $od_tax_mny = $order_data['comm_tax_mny'] ? (int) $order_data['comm_tax_mny'] : 0;
                            $od_vat_mny = $order_data['comm_vat_mny'] ? (int) $order_data['comm_vat_mny'] : 0;
                            $od_free_mny = $order_data['comm_free_mny'] ? (int) $order_data['comm_free_mny'] : 0;
                        }
                        
                        $sql = " insert {$g5['g5_shop_order_table']}
                                    set od_id               = '{$order_data['od_id']}',
                                        mb_id               = '{$order_data['mb_id']}',
                                        od_name             = '{$order_data['od_name']}',
                                        od_email            = '{$order_data['od_email']}',
                                        od_tel              = '{$order_data['od_tel']}',
                                        od_hp               = '{$order_data['od_hp']}',
                                        od_zip1             = '{$order_data['od_zip1']}',
                                        od_zip2             = '{$order_data['od_zip2']}',
                                        od_addr1            = '{$order_data['od_addr1']}',
                                        od_addr2            = '{$order_data['od_addr2']}',
                                        od_addr3            = '{$order_data['od_addr3']}',
                                        od_addr_jibeon      = '{$order_data['od_addr_jibeon']}',
                                        od_deposit_name     = '{$order_data['od_deposit_name']}',
                                        od_b_name           = '{$order_data['od_b_name']}',
                                        od_b_tel            = '{$order_data['od_b_tel']}',
                                        od_b_hp             = '{$order_data['od_b_hp']}',
                                        od_b_zip1           = '{$order_data['od_b_zip1']}',
                                        od_b_zip2           = '{$order_data['od_b_zip2']}',
                                        od_b_addr1          = '{$order_data['od_b_addr1']}',
                                        od_b_addr2          = '{$order_data['od_b_addr2']}',
                                        od_b_addr3          = '{$order_data['od_b_addr3']}',
                                        od_b_addr_jibeon    = '{$order_data['od_b_addr_jibeon']}',
                                        od_memo             = '{$order_data['od_memo']}',
                                        od_cart_count       = '{$order_data['od_cart_count']}',
                                        od_cart_price       = '{$order_data['od_cart_price']}',
                                        od_cart_coupon      = '{$order_data['od_cart_coupon']}',
                                        od_send_cost        = '{$order_data['od_send_cost']}',
                                        od_send_cost2       = '{$order_data['od_send_cost2']}',
                                        od_send_coupon      = '{$order_data['od_send_coupon']}',
                                        od_receipt_price    = '{$pg_data['AMOUNT']}',
                                        od_cancel_price     = '{$order_data['od_cancel_price']}',
                                        od_receipt_point    = '{$order_data['od_temp_point']}',
                                        od_refund_price     = '{$order_data['od_refund_price']}',
                                        od_bank_account     = '{$pg_data['CARDCODE']}',
                                        od_receipt_time     = '{$od_receipt_time}',
                                        od_coupon           = '{$order_data['od_coupon']}',
                                        od_misu             = '{$od_misu}',
                                        od_shop_memo        = '{$order_data['od_shop_memo']}',
                                        od_mod_history      = '{$order_data['od_mod_history']}',
                                        od_status           = '{$od_status}',
                                        od_hope_date        = '{$order_data['od_hope_date']}',
                                        od_settle_case      = '{$order_data['od_settle_case']}',
                                        od_other_pay_type   = '{$order_data['od_other_pay_type']}',
                                        od_test             = '{$order_data['od_test']}',
                                        od_mobile           = '{$order_data['od_mobile']}',
                                        od_pg               = '{$pg_data['PGNAME']}',
                                        od_tno              = '{$pg_data['TID']}',
                                        od_app_no           = '{$pg_data['ACCEPTNO']}',
                                        od_escrow           = '{$order_data['od_escrow']}',
                                        od_casseqno         = '{$order_data['od_casseqno']}',
                                        od_tax_flag         = '{$order_data['od_tax_flag']}',
                                        od_tax_mny          = '{$od_tax_mny}',
                                        od_vat_mny          = '{$od_vat_mny}',
                                        od_free_mny         = '{$od_free_mny}',
                                        od_delivery_company = '{$order_data['od_delivery_company']}',
                                        od_invoice          = '{$order_data['od_invoice']}',
                                        od_invoice_time     = '{$order_data['od_invoice_time']}',
                                        od_cash             = '{$order_data['od_cash']}',
                                        od_cash_no          = '{$order_data['od_cash_no']}',
                                        od_cash_info        = '{$order_data['od_cash_info']}',
                                        od_time             = '".G5_TIME_YMDHIS."',
                                        od_pwd              = '{$order_data['od_pwd']}',
                                        od_ip               = '{$order_data['od_ip']}'
                                        ";
                        $result = sql_query($sql, false);
                        @cookiepay_payment_log("[통지]주문정보 저장", $sql, 3);

                        # s: cookiepay-plugin > 장바구니 업데이트 > v1.2 > 240321
                        if(!empty($cookiepay['ETC5'])) { 
                            $cart_status      = '입금';

                            $sql_cart = "update {$g5['g5_shop_cart_table']}
                                    set od_id = '{$order_data['od_id']}',
                                        ct_status = '{$cart_status}'
                                    where od_id = '{$cookiepay['ETC5']}'
                                    and ct_select = '1' ";
                            $result_cart = sql_query($sql_cart, false);
                            @cookiepay_payment_log("[통지]카트정보 업데이트", $sql_cart, 3);

                        }
                        # e: cookiepay-plugin > 장바구니 업데이트 > v1.2 > 240321
                                                
                    }
                } else {          
                    // s: cookiepay-plugin v1.2.1 > 240412
                    // 이곳에서 가상계좌라면 업데이트 처리한다. > Table name : g5_shop_order (S)
                    // 무통장 관련 체크 > 쿠키페이 테이블 부터 조회
                    # 1 : cookiepay_pg_result.pay_status : 1 처리
                    # 2 : g5_shop_order.od_status : 입금처리

                    /*
                    {
                        "RESULTCODE":"0000",
                        "RESULTMSG":"\uc131\uacf5",
                        "ORDERNO":"2024041117142479",
                        "AMOUNT":"1050",
                        "BUYERNAME":"\ucd5c\uace0\uad00\ub9ac\uc790",
                        "BUYEREMAIL":"sales@cookiepayments.com",
                        "PRODUCTNAME":"\ubca0\uc774\uc2a4 \ucee4\ubc84",
                        "PRODUCTCODE":"145000",
                        "PAYMETHOD":"VACCOUNT",
                        "BUYERID":"admin",
                        "ACCEPTNO":"",
                        "ACCEPTDATE":"20240411171624",
                        "TID":"XEH24041117161416188",
                        "CANCELDATE":"",
                        "CANCELMSG":"",
                        "ACCOUNTNO":"08201108797596",
                        "RECEIVERNAME":"(\uc8fc)\uc774\ub85c\ud640\ub529\uc2a4",
                        "DEPOSITENDDATE":"20240418235959",
                        "CARDNAME":"\uc911\uc18c\uae30\uc5c5\uc740\ud589",
                        "CARDCODE":"03"
                    }
                    */

                    if(!empty($cookiepay['ORDERNO'])) { 
                        $sql_shop_order = "update {$g5['g5_shop_order_table']} 
                                set od_receipt_price='{$cookiepay['AMOUNT']}', od_status = '입금', od_tno='{$pg_data['TID']}', od_misu=0, od_receipt_time=now() 
                                where od_id = '{$cookiepay['ORDERNO']}'
                                limit 1 ";
                        $result_shop_order = sql_query($sql_shop_order, false);

                        //set od_receipt_price='{$cookiepay['AMOUNT']}', od_status = '입금', od_receipt_time=now() 
                        $sql_shop_cart = "update {$g5['g5_shop_cart_table']}         
                                set ct_status = '입금' 
                                where od_id = '{$cookiepay['ORDERNO']}'
                                ";
                        $result_shop_cart = sql_query($sql_shop_cart, false);

                    }

                    @cookiepay_payment_log("[통지]가상계좌 결제완료 처리 Order SQL :  ----------> :", $sql_shop_order, 3);
                    @cookiepay_payment_log("[통지]가상계좌 결제완료 처리 Cart SQL :  ----------> :", $sql_shop_cart, 3);

                    @cookiepay_payment_log("[통지]수신 res ----------> :", $response, 3);
                    // 이곳에서 가상계좌라면 업데이트 처리한다. > Table name : g5_shop_order (E)
                    // e: cookiepay-plugin v1.2.1 > 240412
                }
                
                // e: cookiepay-plugin v1.2
                
                // 결제 검증 성공
                @cookiepay_payment_log("[통지]결제검증 성공4", $response, 3);
                echo '<html>
                        <body>
                        <RESULT>SUCCESS</RESULT>
                        </body>
                      </html>';
            } else {
                // 결제 검증 실패시 결제 취소 처리
                @cookiepay_payment_log("[통지]결제검증 실패4", $response, 3);
            
                $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

                if ($ret['status'] === true) {
                    @cookiepay_payment_log("[통지]결제취소 성공5", $ret['data'], 3);
                    $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                } else {
                    @cookiepay_payment_log("[통지]결제취소 실패5", $ret['data'], 3);
                }

                $cancelArr = json_decode($ret['data'], true);

                $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 성공6", $sql, 3);
                } else {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 실패6", $sql, 3);
                }
            }
        } else {
            // 결제 검증 토큰 발행 실패시 결제 취소 처리
            @cookiepay_payment_log("[통지]결제 검증 토큰 발행 실패7", $resultJson, 3);
            
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("[통지]결제취소 성공7", $ret['data'], 3);
                $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
            } else {
                @cookiepay_payment_log("[통지]결제취소 실패7", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("[통지]결제취소결과 저장 성공8", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제취소결과 저장 실패8", $sql, 3);
            }
        }
    }
}