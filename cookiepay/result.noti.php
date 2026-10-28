<?php 
#error_reporting(E_ALL); 
#ini_set("display_errors", 1); 

include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 결제 결과 통지 수신 후 처리
#exit;

// s: cookiepay-plugin > 통지 서명 검증 > v2.0 > 261026
// 서명 검증에는 본문 "원문" 이 필요하다. json_decode 된 배열을 다시
// 직렬화하면 공백·키 순서·한글 이스케이프가 달라져 서명이 재현되지 않는다.
// php://input 은 한 번만 읽을 수 있으므로 변수에 담아 둔다.
$cookiepayRawBody = file_get_contents('php://input');
$cookiepay = json_decode($cookiepayRawBody, true);

if (COOKIEPAY_NOTI_SIGN_MODE !== 'off') {
    require_once G5_PATH."/cookiepay/cookiepay.guard.php";

    // 가맹점이 쓰는 연동 계정 전체. 어떤 키로 서명했는지는 헤더가 알려 준다.
    // 취소 통지에는 본문에 연동 아이디가 없으므로 헤더로만 고를 수 있다.
    $signPairs = array();
    foreach (array(1, 3, 5, 7, 9) as $signPayType) {
        $signPairs[] = cookiepay_get_api_account_info($default, $signPayType);
    }

    $signRet = cookiepay_guard_noti_signature(
        $cookiepayRawBody,
        isset($_SERVER['HTTP_X_COOKIEPAY_API_ID']) ? $_SERVER['HTTP_X_COOKIEPAY_API_ID'] : '',
        isset($_SERVER['HTTP_X_COOKIEPAY_SIGNATURE']) ? $_SERVER['HTTP_X_COOKIEPAY_SIGNATURE'] : '',
        $signPairs,
        time(),
        COOKIEPAY_NOTI_SIGN_TOLERANCE
    );

    if ($signRet['ok']) {
        @cookiepay_payment_log("[통지]서명 확인", "API_ID ".$signRet['api_id'], 3);
    } else {
        // 본문 해시를 같이 남긴다. 서명이 안 맞을 때 키 문제인지 본문 바이트
        // 문제인지 가려내는 데 이 값이 결정적이다(원문 자체는 남기지 않는다).
        //
        // 실제로 도착한 X- 헤더 이름도 남긴다. NO_SIGNATURE 는 "헤더가 안 왔다"
        // 까지만 알려 줄 뿐, 쿠키페이가 안 보낸 것인지 / 이름이 규격과 다른지 /
        // 앞단에서 잘렸는지를 구분하지 못한다. 이름만 남기고 값은 남기지 않는다.
        $signSeen = array();
        foreach ($_SERVER as $signKey => $signVal) {
            if (strpos($signKey, 'HTTP_X_') === 0) {
                $signSeen[] = $signKey;
            }
        }

        // 설정에 등록된 연동쌍의 "아이디:키지문" 목록. 같은 연동 아이디가 두 칸에
        // 중복 등록돼 엉뚱한 키가 선택되는 경우를 여기서 바로 알아볼 수 있다.
        // 키 자체는 남기지 않는다.
        $signFp = array();
        foreach ($signPairs as $signPair) {
            if (empty($signPair['api_id'])) {
                continue;
            }
            $signFp[] = $signPair['api_id'] . ':'
                . (empty($signPair['api_key']) ? '(키없음)' : substr(hash('sha256', $signPair['api_key']), 0, 16));
        }

        @cookiepay_payment_log(
            "[통지]서명 검증 실패 ({$signRet['code']})",
            $signRet['msg']
                . " / mode=" . COOKIEPAY_NOTI_SIGN_MODE
                . " / bytes=" . strlen($cookiepayRawBody)
                . " / sha256=" . hash('sha256', $cookiepayRawBody)
                . " / headers=" . (empty($signSeen) ? '(X- 헤더 없음)' : implode(',', $signSeen))
                . (isset($signRet['keyfp']) ? " / keyfp=" . $signRet['keyfp'] : '')
                . " / pairs=" . implode(',', $signFp),
            3
        );

        if (COOKIEPAY_NOTI_SIGN_MODE === 'enforce') {
            // 재전송해도 결과가 같으므로 쿠키페이가 재시도하지 않도록 401 을 준다.
            header('HTTP/1.1 401 Unauthorized');
            exit;
        }
    }
}
// e: cookiepay-plugin > 통지 서명 검증 > v2.0 > 261026

# 정기(구독) 결제 통지 수신(S) 
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

// s: cookiepay-plugin > 취소 통지 처리 > v1.5 > 261019
// 취소 통지는 결제 통지와 payload 모양이 다르다(소문자 키, noti_type=cancel).
// 아래 결제 처리 분기는 ORDERNO/TID/ACCEPT_NO 대문자 키를 보므로 취소 통지는
// 모든 조건을 빠져나가 아무 처리도 되지 않았다. 여기서 먼저 받아 처리한다.
if (isset($cookiepay['noti_type']) && $cookiepay['noti_type'] === 'cancel') {
    require_once G5_PATH."/cookiepay/cookiepay.guard.php";
    require_once G5_PATH."/cookiepay/cookiepay.verify.php";

    $cnOrderno = isset($cookiepay['orderno']) ? sql_real_escape_string((string) $cookiepay['orderno']) : '';
    $cnTid     = isset($cookiepay['tid']) ? sql_real_escape_string((string) $cookiepay['tid']) : '';
    $cnDate    = isset($cookiepay['cancel_date']) ? sql_real_escape_string((string) $cookiepay['cancel_date']) : '';
    $cnAmtRaw  = isset($cookiepay['cancel_amount']) ? (int) $cookiepay['cancel_amount'] : 0;

    // 같은 취소가 두 번 들어와도 환불이 두 번 기록되지 않게 한다.
    // 통지의 tid 는 원 결제 거래번호라 관리자 취소가 남긴 cancel_tid 와 다를 수 있으므로
    // 주문번호 + 취소일시 + 취소금액으로 같은 건인지 본다.
    $cnDup = sql_fetch("
        SELECT id FROM ".COOKIEPAY_PG_CANCEL."
        WHERE orderno = '{$cnOrderno}'
          AND cancel_date = '{$cnDate}'
          AND cancel_amt = '{$cnAmtRaw}'
        LIMIT 1
    ");

    if (!empty($cnDup['id'])) {
        @cookiepay_payment_log("[통지]취소 중복 수신 무시", "ORDERNO {$cnOrderno} / {$cnDate} / {$cnAmtRaw}", 3);
        echo '<html><body><RESULT>SUCCESS</RESULT></body></html>';
        exit;
    }

    $cnPgResult = sql_fetch("
        SELECT * FROM ".COOKIEPAY_PG_RESULT."
        WHERE ORDERNO = '{$cnOrderno}'
        ORDER BY `id` DESC LIMIT 1
    ");

    $cnOrder = sql_fetch("
        SELECT od_id, od_status, od_receipt_price, od_refund_price
        FROM {$g5['g5_shop_order_table']}
        WHERE od_id = '{$cnOrderno}' LIMIT 1
    ");

    // 통지를 믿지 않고 쿠키페이에 거래를 다시 묻는다.
    $cnPayType = (isset($cnPgResult['pay_type']) && $cnPgResult['pay_type'] !== '') ? $cnPgResult['pay_type'] : 3;
    $cnApi = cookiepay_get_api_account_info($default, $cnPayType);
    $cnVerify = cookiepay_verify_tid($cnApi['api_id'], $cnApi['api_key'], isset($cookiepay['tid']) ? $cookiepay['tid'] : '');

    @cookiepay_payment_log("[통지]취소 검증 응답", json_encode($cnVerify), 3);

    $cnRet = cookiepay_guard_cancel_noti($cookiepay, $cnVerify, $cnPgResult, $cnOrder);

    if (!$cnRet['ok']) {
        @cookiepay_payment_log("[통지]취소 처리 차단 ({$cnRet['code']})", $cnRet['msg']." / ORDERNO {$cnOrderno}", 3);
        echo '<html><body><RESULT>FAIL</RESULT></body></html>';
        exit;
    }

    // 취소 이력 기록
    $cnSql = " INSERT INTO ".COOKIEPAY_PG_CANCEL."
        (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt)
        VALUES ('{$cnOrderno}', '{$cnTid}', '0000', '취소통지', '{$cnDate}', '{$cnRet['amount']}') ";
    sql_query($cnSql, false);
    @cookiepay_payment_log("[통지]취소 이력 저장", $cnSql, 3);

    // 전액 취소일 때만 결제 상태를 취소로 바꾼다. 부분 취소는 결제가 남아 있다.
    if ($cnRet['full']) {
        sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status = 2 WHERE ORDERNO = '{$cnOrderno}'", false);
    }

    // 환불 금액은 누적한다. 부분 취소가 여러 번 들어올 수 있다.
    sql_query("
        UPDATE {$g5['g5_shop_order_table']}
        SET od_refund_price = '{$cnRet['refund_total']}'
        WHERE od_id = '{$cnOrderno}' LIMIT 1
    ", false);

    @cookiepay_payment_log(
        "[통지]취소 처리 완료",
        "ORDERNO {$cnOrderno} / 이번 취소 {$cnRet['amount']} / 누적 환불 {$cnRet['refund_total']} / "
        . ($cnRet['full'] ? '전액취소' : '부분취소')
        . " / 주문상태는 변경하지 않음(관리자 확인 필요)",
        3
    );

    echo '<html><body><RESULT>SUCCESS</RESULT></body></html>';
    exit;
}
// e: cookiepay-plugin > 취소 통지 처리 > v1.5 > 261019

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

// SQL 문자열에 넣을 데이터
// sql_real_escape_string 처리.
$params_sb_sql = [];
foreach ($params_sb as $key => $value) {
    $params_sb_sql[$key] = sql_real_escape_string((string) $value);
}


$sql = "
    SELECT *
    FROM " . COOKIEPAY_PG_SUBSCRIBE_RESULT . "
    WHERE TID = '{$params_sb_sql['TID']}'
      AND TID != ''
    ORDER BY `id` DESC
    LIMIT 1
";

@cookiepay_payment_log("[통지] 정기(구독) 중복체크 쿼리(승인)" . $sql, 3);
$od_tno = sql_fetch($sql);
# 정기(구독) 중복 주문번호(성공건)

# 정기(구독) 재결제실패 노티는 막는다.

// s: cookiepay-plugin > 보안검토 항목6 > v1.3 > 261014
// 정기(구독) 통지는 외부에서 위조할 수 있다. 통지 본문의 RESULTCODE 만 보고
// 주문을 복사해 '입금' 상태로 만들면, 실제 주문을 한 번 한 사람이 그 주문번호로
// 가짜 통지를 반복 전송해 무료 주문을 계속 만들 수 있다.
// 주문을 만들기 전에 쿠키페이에 거래를 다시 확인하고 약정 금액과 대조한다.
if(!empty($params_sb['RESERVE_ORDERNO']) && $params_sb['PAY_CNT'] >= 2) {
    require_once G5_PATH."/cookiepay/cookiepay.guard.php";
    require_once G5_PATH."/cookiepay/cookiepay.verify.php";

    $guardSubscribe = sql_fetch("
        SELECT *
        FROM " . COOKIEPAY_PG_SUBSCRIBE_USERLIST . "
        WHERE RESERVE_ID = '{$params_sb_sql['RESERVE_ID']}'
        ORDER BY `id` DESC
        LIMIT 1
    ");

    $guardApi = cookiepay_get_api_account_info($default, 9);
    $guardVerify = cookiepay_verify_tid($guardApi['api_id'], $guardApi['api_key'], $params_sb['TID']);

    $guardRet = cookiepay_guard_subscribe_noti($params_sb, $guardVerify, $guardSubscribe);

    if (!$guardRet['ok']) {
        @cookiepay_payment_log("[통지] 정기(구독) 검증 차단 ({$guardRet['code']})", $guardRet['msg'], 3);
        exit;
    }

    if (!empty($guardRet['record_only'])) {
        @cookiepay_payment_log("[통지] 정기(구독) 실패 통지 — 이력만 기록", "RESERVE_ID {$params_sb['RESERVE_ID']} / {$params_sb['PAY_CNT']}회차", 3);
    } else {
        @cookiepay_payment_log("[통지] 정기(구독) 검증 통과", "금액 {$guardRet['amount']}", 3);
    }
}
// e: cookiepay-plugin > 보안검토 항목6 > v1.3 > 261014

// s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
// 이 분기는 원래 위 검증 블록보다 앞에 있었다. 그래서 위조 통지로도
// repay_check='N' / pay_status='1' UPDATE 가 무검증 실행되어, 실패한 회차의
// 재결제 버튼을 숨길 수 있었다(실패 건 은폐). 검증을 통과한 뒤에만 돌도록 옮긴다.
if($params_sb['RESULTCODE'] == "0000" && !empty($od_tno['id'])) { 
    @cookiepay_payment_log("[통지] 정기(구독) 중복 결제정보 입력 차단 ", 3);

    # 재결제가 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
    $sql_u2 = "
        UPDATE `cookiepay_pg_subscribe_result`
        SET pay_status = '1',
            repay_check = 'N'
        WHERE RESERVE_ID = '{$params_sb_sql['RESERVE_ID']}'
        AND RESERVE_NOW_PAY_CNT = '{$params_sb_sql['PAY_CNT']}'
    ";
            //AND pay_status='3' 

    $result_u2 = sql_query($sql_u2);
    exit;
}

// e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026


# 정기(구독) 반복결제 결제내역 입력(S)
if(!empty($params_sb['RESERVE_ORDERNO']) && $params_sb['PAY_CNT'] >= 2) {

    # 반복결제 성공하였으므로 > 해당회차 실패건을 재결제 버튼을 노출하지 않는다.
    $sql_u22 = "
        UPDATE `cookiepay_pg_subscribe_result`
        SET repay_check = 'N'
        WHERE RESERVE_ID = '{$params_sb_sql['RESERVE_ID']}'
        AND RESERVE_NOW_PAY_CNT = '{$params_sb_sql['PAY_CNT']}'
    ";
            //AND pay_status='3' 

    $result_u22 = sql_query($sql_u22);

    $sql_read = "
        SELECT *
        FROM {$g5['g5_shop_order_table']}
        WHERE od_id = '{$params_sb_sql['RESERVE_ORDERNO']}'
        ORDER BY `od_id` DESC
        LIMIT 1
    ";

    $od = sql_fetch($sql_read);
    @cookiepay_payment_log("[통지] g5_shop_order Select Query : ", $sql_read, 3);

    $od_id = date('YmdHis').rand(11,99);

    // 주문서에 입력(g5_shop_order)
    $sql = " 
        INSERT {$g5['g5_shop_order_table']}
        SET od_id             = '$od_id',
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
            od_bank_account   = '{$params_sb_sql['CARDNAME']}',
            od_receipt_time   = '{$od['od_receipt_time']}',
            od_misu           = '{$od['od_misu']}',
            od_pg             = '{$od['od_pg']}',
            od_tno            = '{$params_sb_sql['TID']}',
            od_app_no         = '{$params_sb_sql['ACCEPT_NO']}',
            od_escrow         = '{$od['od_escrow']}',
            od_tax_flag       = '{$od['od_tax_flag']}',
            od_tax_mny        = '{$od['od_tax_mny']}',
            od_vat_mny        = '{$od['od_vat_mny']}',
            od_free_mny       = '{$od['od_free_mny']}',
            od_status         = '입금',
            od_shop_memo      = '',
            od_hope_date      = '{$od['od_hope_date']}',
            od_time           = '".G5_TIME_YMDHIS."',
            od_ip             = '" . sql_real_escape_string((string) $_SERVER['REMOTE_ADDR']) . "',
            od_settle_case    = '정기(구독)',
            od_other_pay_type = '{$od['od_other_pay_type']}',
            od_test           = '{$od['od_test']}',
            od_casseqno       = '{$params_sb_sql['RESERVE_ID']}'  
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

    $od_cart_sql = "
        SELECT *
        FROM {$g5['g5_shop_cart_table']}
        WHERE od_id = '{$params_sb_sql['RESERVE_ORDERNO']}'
        ORDER BY `ct_id` DESC
    ";
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
            ct_ip             = '" . sql_real_escape_string((string) $_SERVER['REMOTE_ADDR']) . "',
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
    

    // s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
    // pay_status_str 은 아래 두 INSERT 가 모두 쓰는데 정의가 두 번째 것 앞에만 있어,
    // 첫 번째 INSERT 시점에는 미정의라 pay_status 가 빈 값으로 저장되고 있었다.
    // 정의를 첫 사용처 앞으로 옮긴다.
    $pay_status_str = "0";
    if($params_sb['RESULTCODE'] == "0000") {
        $pay_status_str = "1";
    } else {
        $pay_status_str = "3";
    }
    // e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026

    # 취소때문에 COOKIEPAY_PG_RESULT 테이블에도 정보 입력해야함
    $sql_result_insert = "
        INSERT INTO ".COOKIEPAY_PG_RESULT." 
        SET RESULTCODE              = '{$params_sb_sql['RESULTCODE']}',
            RESULTMSG               = '{$params_sb_sql['RESULTMSG']}',
            ORDERNO                 = '{$od_id}',
            AMOUNT                  = '{$params_sb_sql['AMOUNT']}',
            TID                     = '{$params_sb_sql['TID']}',
            ACCEPTNO                = '{$params_sb_sql['ACCEPT_NO']}',
            ACCEPTDATE              = '{$params_sb_sql['ACCEPT_DATE']}',
            ACCOUNTNO               = '',
            RECEIVERNAME            = '',
            DEPOSITENDDATE          = '',
            CARDNAME                = '{$params_sb_sql['CARDNAME']}',
            CARDCODE                = '',
            PGNAME                  = '{$default['de_pg_service']}',
            pay_type                = '9',
            pay_status              = '{$pay_status_str}'     
    ";

   $result22 = sql_query($sql_result_insert, false);
    @cookiepay_payment_log("[통지] COOKIEPAY_PG_RESULT insert Query : ", $sql_result_insert, 3);
    
    # 취소때문에 COOKIEPAY_PG_VERIFY 테이블에도 정보 입력해야함

    $sql_verify_insert = "
        INSERT INTO ".COOKIEPAY_PG_VERIFY." 
        SET RESULTCODE              = '{$params_sb_sql['RESULTCODE']}',
            RESULTMSG               = '{$params_sb_sql['RESULTMSG']}',
            ORDERNO                 = '{$od_id}',
            AMOUNT                  = '{$params_sb_sql['AMOUNT']}',
            BUYERNAME               = '{$params_sb_sql['BUYERNAME']}',
            BUYEREMAIL              = '{$params_sb_sql['BUYEREMAIL']}',
            PRODUCTNAME             = '{$params_sb_sql['PRODUCTNAME']}',
            PRODUCTCODE             = '{$params_sb_sql['PRODUCTCODE']}',
            PAYMETHOD               = 'CARD_BATCH',
            BUYERID                 = '{$od['mb_id']}',
            TID                     = '{$params_sb_sql['TID']}',
            ACCEPTNO                = '{$params_sb_sql['ACCEPT_NO']}',
            ACCEPTDATE              = '{$params_sb_sql['ACCEPT_DATE']}',
            CANCELDATE              = '',
            CANCELMSG               = '',
            ACCOUNTNO               = '',
            RECEIVERNAME            = '',
            DEPOSITENDDATE          = '',
            CARDNAME                = '{$params_sb_sql['CARDNAME']}',
            CARDCODE                = ''
    ";
    @cookiepay_payment_log("[통지] COOKIEPAY_PG_VERIFY insert Query : ", $sql_verify_insert, 3);

    $result3 = sql_query($sql_verify_insert, false);


    # COOKIEPAY_PG_SUBSCRIBE_RESULT 테이블에서 > 기존 거래내역을 가저온다
    $cookiepay_pg_subscribe_result = sql_fetch("
        SELECT *
        FROM " . COOKIEPAY_PG_SUBSCRIBE_RESULT . "
        WHERE RESERVE_ID = '{$params_sb_sql['RESERVE_ID']}'
        AND RESERVE_RECURRENCE_TYPE != ''
        AND RESERVE_START_PAY_CNT != ''
        AND RESERVE_LAST_PAY_CNT != ''
        ORDER BY `id` DESC
        LIMIT 1
    ");

    # 정기(구독) 반복 결제내역(S)

    # GENDATE 공백일시 날짜 값 업데이트
    if(empty($params_sb['GENDATE'])) { 
        $params_sb['GENDATE'] = date('Y-m-d H:i:s');
        $params_sb_sql['GENDATE'] = $params_sb['GENDATE'];
    }
    $sql_pg_result_insert = "insert into ".COOKIEPAY_PG_SUBSCRIBE_RESULT."
    set RESULTCODE              = '{$params_sb_sql['RESULTCODE']}',
        RESULTMSG               = '{$params_sb_sql['RESULTMSG']}',
        USERID                  = '{$params_sb_sql['USERID']}', 
        ORDERNO                 = '{$od_id}',
        AMOUNT                  = '{$params_sb_sql['AMOUNT']}',
        PRODUCT_NAME            = '{$params_sb_sql['PRODUCTNAME']}',
        TID                     = '{$params_sb_sql['TID']}',
        ACCEPTDATE              = '{$params_sb_sql['ACCEPT_DATE']}',
        ACCEPTNO                = '{$params_sb_sql['ACCEPT_NO']}',
        PAY_DATE                = '{$params_sb_sql['GENDATE']}',
        CASH_BILL_NO            = '',
        CARDNAME                = '{$params_sb_sql['CARDNAME']}',
        ACCOUNTNO               = '',
        RECEIVERNAME            = '',
        DEPOSITENDDATE          = '',
        CARDCODE                = '',
        QUOTA                   = '',
        BILLKEY                 = '{$params_sb_sql['BILLKEY']}',
        GENDATE                 = '".date('YmdHis', strtotime($params_sb_sql['GENDATE']))."',
        RESERVE_RESULTCODE      = '{$params_sb_sql['RESULTCODE']}',
        RESERVE_RESULTMSG       = '{$params_sb_sql['RESULTMSG']}',
        RESERVE_ID              = '{$params_sb_sql['RESERVE_ID']}',
        RESERVE_ORDERNO         = '{$params_sb_sql['RESERVE_ORDERNO']}',
        RESERVE_RECURRENCE_TYPE = '{$cookiepay_pg_subscribe_result['RESERVE_RECURRENCE_TYPE']}',
        RESERVE_PAY_DAY         = '".substr($params_sb_sql['NEXT_PAY_DATE'], -2)."',
        RESERVE_NOW_PAY_CNT     = '{$params_sb_sql['PAY_CNT']}',
        RESERVE_START_PAY_CNT   = '{$cookiepay_pg_subscribe_result['RESERVE_START_PAY_CNT']}',
        RESERVE_LAST_PAY_CNT    = '{$cookiepay_pg_subscribe_result['RESERVE_LAST_PAY_CNT']}',
        RESERVE_NEXT_PAY_DATE   = '{$params_sb_sql['NEXT_PAY_DATE']}',
        RESERVE_RETURN_URL      = '',
        ETC1                    = '{$params_sb_sql['ETC1']}',
        ETC2                    = '{$params_sb_sql['ETC2']}',
        ETC3                    = '{$params_sb_sql['ETC3']}',
        ETC4                    = '{$params_sb_sql['ETC4']}',                
        ETC5                    = '{$params_sb_sql['ETC5']}',
        PGNAME                  = '{$default['de_pg_service']}',
        pay_type                = '9',
        pay_status              = '{$pay_status_str}'
    ";

    @cookiepay_payment_log("[통지] ".COOKIEPAY_PG_SUBSCRIBE_RESULT." insert Query : ", $sql_pg_result_insert, 3);

    $result4 = sql_query($sql_pg_result_insert, false);

    # 정기(구독) 등록고객 테이블 업데이트
    $sql_subscribe_userlist = "
        UPDATE " . COOKIEPAY_PG_SUBSCRIBE_USERLIST . "
        SET RESERVE_NOW_PAY_CNT = '{$params_sb_sql['PAY_CNT']}',
            RESERVE_LAST_PAY_RESULTCODE = '{$params_sb_sql['RESULTCODE']}',
            RESERVE_LAST_PAY_RESULTMSG = '{$params_sb_sql['RESULTMSG']}'
        WHERE RESERVE_ID = '{$params_sb_sql['RESERVE_ID']}'
        LIMIT 1
    ";
    sql_query($sql_subscribe_userlist);

    exit;
}
# 정기(구독) 반복결제 결제내역 입력(E)

@cookiepay_payment_log("[통지]결제결과 저장 성공1", $cookiepay, 3);

$cookiepay['ACCEPT_NO'] = isset($cookiepay['ACCEPT_NO']) && !empty($cookiepay['ACCEPT_NO']) ? $cookiepay['ACCEPT_NO'] : '';
$cookiepay['TID'] = isset($cookiepay['TID']) && !empty($cookiepay['TID']) ? $cookiepay['TID'] : '';
$cookiepay['ORDERNO'] = isset($cookiepay['ORDERNO']) && !empty($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : '';

$resultMode = null;

// s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
// PAY_METHOD 는 위 세 줄과 달리 isset 처리가 없어, 키 없는 통지에서 notice 가 났다.
$cookiepay['PAY_METHOD'] = isset($cookiepay['PAY_METHOD']) ? $cookiepay['PAY_METHOD'] : '';
// e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026

// s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
// 통지가 실패를 말해도 아래 블록이 RESULTCODE 를 '0000' 으로 덮어써 가맹점
// 기록에는 성공으로 남고 있었다. 다만 RESULTCODE 가 비어 오는 통지가 있을 수
// 있으므로 "비어 있음" 은 실패로 보지 않는다. '0000' 이 아닌 값이 명시된
// 경우에만 실패로 보고, 결제실패(pay_status=3)로 기록한 뒤 종료한다.
// 주문 생성·갱신 경로로는 들어가지 않는다.
$cpNotiCode = isset($cookiepay['RESULTCODE']) ? trim((string) $cookiepay['RESULTCODE']) : '';
$cpNotiMsg  = isset($cookiepay['RESULTMSG'])  ? (string) $cookiepay['RESULTMSG']  : '';

if ($cpNotiCode !== '' && $cpNotiCode !== '0000') {
    @cookiepay_payment_log("[통지]결제실패 통지 수신 ({$cpNotiCode})", json_encode($cookiepay, JSON_UNESCAPED_UNICODE), 3);

    if (!empty($cookiepay['ORDERNO'])) {
        $cpFailOrderNo = sql_real_escape_string((string) $cookiepay['ORDERNO']);

        $cpFailSet = array(
            "RESULTCODE='" . sql_real_escape_string($cpNotiCode) . "'",
            "RESULTMSG='"  . sql_real_escape_string($cpNotiMsg)  . "'",
            "TID='"        . sql_real_escape_string((string) $cookiepay['TID']) . "'",
            "pay_status=3",
        );
        if (isset($cookiepay['AMOUNT']) && $cookiepay['AMOUNT'] !== '') {
            $cpFailSet[] = "AMOUNT='" . sql_real_escape_string((string) $cookiepay['AMOUNT']) . "'";
        }
        // 응답전문에는 pay_type 이 없으므로 ETC3 으로 주고받는다(아래 저장 블록과 동일).
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) {
            $cpFailSet[] = "pay_type='" . sql_real_escape_string((string) $cookiepay['ETC3']) . "'";
        }

        $cpFailRow = sql_fetch(" SELECT `id` FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cpFailOrderNo}' ORDER BY `id` DESC LIMIT 1 ");

        if (isset($cpFailRow['id']) && !empty($cpFailRow['id'])) {
            $cpFailSql = " UPDATE ".COOKIEPAY_PG_RESULT." SET ".implode(",", $cpFailSet)
                       . " WHERE `id`='" . sql_real_escape_string((string) $cpFailRow['id']) . "' ";
        } else {
            $cpFailSql = " INSERT INTO ".COOKIEPAY_PG_RESULT." SET ORDERNO='{$cpFailOrderNo}', "
                       . "PGNAME='" . sql_real_escape_string((string) $default['de_pg_service']) . "', "
                       . implode(",", $cpFailSet) . " ";
        }

        if (sql_query($cpFailSql, false)) {
            @cookiepay_payment_log("[통지]결제실패 저장 성공", $cpFailSql, 3);
        } else {
            @cookiepay_payment_log("[통지]결제실패 저장 실패", $cpFailSql, 3);
        }
    }

    exit;
}

// 계좌이체·가상계좌는 승인번호 자체가 없다. 예전에는 승인번호 자리에
// "11111111" 을 채워 아래 if 의 !empty() 관문을 통과시켰고, 그 가짜 값이
// cookiepay_pg_result.ACCEPTNO 와 g5_shop_order.od_app_no 까지 저장됐다.
// 관문용 표식($cpNoApproval)과 저장값을 분리해, 없는 승인번호는 빈 값으로 둔다.
$cpNoApproval = ($cookiepay['ACCEPT_NO'] == "00000000" || $cookiepay['PAY_METHOD'] == "VACCOUNT");
if ($cpNoApproval) {
    $cookiepay['ACCEPT_NO'] = '';
}
// e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026


// s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
// 승인번호가 없는 거래(계좌이체·가상계좌)도 여기서 걸러지면 안 된다.
if(($cpNoApproval || !empty($cookiepay['ACCEPT_NO'])) && !empty($cookiepay['TID']) && !empty($cookiepay['ORDERNO'])) {
// e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    $pgResult = sql_fetch("
        SELECT *
        FROM " . COOKIEPAY_PG_RESULT . "
        WHERE ORDERNO = '{$params_sb_sql['ORDERNO']}'
        ORDER BY `id` DESC
        LIMIT 1
    ");
    $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';

    // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    // 무조건 '0000'/'성공' 으로 덮어쓰던 자리. 실패 통지는 위에서 이미 걸러졌고,
    // 값이 비어 오는 통지만 성공으로 간주한다.
    $cookiepay['RESULTCODE'] = ($cpNotiCode !== '') ? $cpNotiCode : '0000';
    $cookiepay['RESULTMSG']  = ($cpNotiMsg  !== '') ? $cpNotiMsg  : '성공';
    // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
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
                $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
            }
        }
        $values['PGNAME'] = "'{$default['de_pg_service']}'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $values['pay_type'] = "'" . sql_real_escape_string((string) $cookiepay['ETC3']) . "'";
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
                $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
            }
        }
        $set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $set['pay_type'] = "pay_type='" . sql_real_escape_string((string) $cookiepay['ETC3']) . "'";
        }
        $set['pay_status'] = "pay_status=1";
        $setStr = implode(",", $set);

        $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";

        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("[통지]결제결과 저장 성공2", $sql, 3);
            $resultMode = 'update';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패2", $sql, 3);
        }
    }

    // s: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
    // pay_status 가 이미 1 이면 위 두 분기를 모두 건너뛰어 $resultMode 가 null 로
    // 남고 아래 검증 블록도 돌지 않는다. 의도된 중복 통지 방어지만 로그가 전혀
    // 남지 않아, 운영에서 "왜 여기서 멈췄는지" 알 수 없었다.
    if ($payStatus == 1) {
        @cookiepay_payment_log(
            "[통지]이미 처리된 통지 — 무시",
            "ORDERNO ".$cookiepay['ORDERNO']." / pay_status=1",
            3
        );
    }
    // e: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027

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

            $pgVerify = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_VERIFY." WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "' ORDER BY `id` DESC LIMIT 1");
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
                        // s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
                        // paycert 응답도 외부에서 들어온 값이다. 바로 위 결제결과
                        // INSERT 는 이스케이프하는데 여기만 빠져 있었다.
                        $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                        // e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
                    }
                }
                $valueStr = implode(",", $values);

                $sql = " INSERT INTO ".COOKIEPAY_PG_VERIFY." ({$columnStr}) VALUES ({$valueStr}) ";
                $res = sql_query($sql, false);
            } else {
                $set = [];
                foreach ($verify as $key => $val) {
                    if (in_array($key, $pgVerifyColumns)) {
                        // s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
                        // WHERE 절만 이스케이프하고 SET 절 값은 그대로였다.
                        $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
                        // e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
                    }
                }
                $setStr = implode(",", $set);
                $sql = "UPDATE ".COOKIEPAY_PG_VERIFY." SET {$setStr} WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
                $res = sql_query($sql, false);
            }

            if ($res) {
                @cookiepay_payment_log("[통지]결제검증결과 저장 성공3", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제검증결과 저장 실패3", $sql, 3);
            }

            if($verify['RESULTCODE'] == '0000') {
                
                // s: cookiepay-plugin v1.2
                $exists_sql = "select od_id from {$g5['g5_shop_order_table']} where od_id = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
                $exists_order = sql_fetch($exists_sql);
                if (!isset($exists_order['od_id']))
                {
                    $sql = "select * from ".COOKIEPAY_SHOP_ORDER." where od_id = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
                    $order_data = sql_fetch($sql);
                    if ($order_data)
                    {
                        $sql = "SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
                        $pg_data = sql_fetch($sql);
                        
                        $i_price = $order_data['od_price'] + $order_data['od_send_cost'] + $order_data['od_send_cost2'] - $order_data['od_temp_point'] - $order_data['od_send_coupon'];
                        $od_receipt_time = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3 \\4:\\5:\\6", $pg_data['ACCEPTDATE']);
                        // s: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
                        // 이 경로(신규 주문 생성)에는 금액 대조가 없었다. $pg_data['AMOUNT'] 는
                        // 통지 본문에서 온 값이라, 싼 결제의 거래번호를 재사용하면서 통지 금액만
                        // 부풀리면 그대로 반영된다. 기존 주문 갱신 경로에는 이미 같은 검증이 있다.
                        //
                        // 주문 총액과의 일치는 요구하지 않는다(require_full=false).
                        // 이 경로는 od_misu 가 남으면 '주문' 상태로 두는, 부분 입금이 정상인
                        // 경로이기 때문이다. ORDERNO 일치와 통지-검증 금액 대조는 그대로 한다.
                        // 이 경로는 일반 카드 통지에서도 지나므로 가드 파일이 아직
                        // 로드되지 않았을 수 있다(위 require 들은 다른 분기 안에 있다).
                        require_once G5_PATH."/cookiepay/cookiepay.guard.php";

                        $guardNew = cookiepay_guard_verified_amount(
                            $verify, $cookiepay, $cookiepay['ORDERNO'], $i_price, false
                        );

                        if (!$guardNew['ok']) {
                            @cookiepay_payment_log(
                                "[통지] 신규주문 금액 검증 차단 ({$guardNew['code']})",
                                $guardNew['msg']." / ORDERNO ".$cookiepay['ORDERNO']." / 주문금액 ".$i_price,
                                3
                            );

                            if (in_array($guardNew['code'], array('NOTI_TAMPERED', 'ORDERNO_MISMATCH'), true)) {
                                sql_query("
                                    UPDATE ".COOKIEPAY_PG_RESULT."
                                    SET pay_status = 3
                                    WHERE ORDERNO = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
                                ", false);
                                @cookiepay_payment_log("[통지] 위조 통지로 오염된 결제기록 무효화", "ORDERNO ".$cookiepay['ORDERNO']." / pay_status=3", 3);
                            }

                            exit;
                        }

                        // 받은 금액은 통지가 아니라 검증 응답 값을 쓴다.
                        $od_misu = $i_price - $guardNew['amount'];
                        // e: cookiepay-plugin > 통지 입력정리 > v1.8 > 261026
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
                                    where od_id = '" . sql_real_escape_string((string) $cookiepay['ETC5']) . "'
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

                    // s: cookiepay-plugin > 보안검토 항목10 > v1.3 > 261014
                    // 거래번호의 실재 여부만 확인하고 금액은 통지 본문에서 가져오면,
                    // 싼 상품을 실제로 결제해 얻은 거래번호를 더 비싼 주문의 통지에
                    // 재사용해 차액만큼 덜 내고 상품을 받을 수 있다.
                    // 결제완료로 기록할 금액은 검증 응답(paycert)의 값을 쓰고, 주문 금액과 대조한다.
                    require_once G5_PATH."/cookiepay/cookiepay.guard.php";

                    $guardOrder = sql_fetch("
                        SELECT od_id, od_misu, od_receipt_price
                        FROM {$g5['g5_shop_order_table']}
                        WHERE od_id = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
                        LIMIT 1
                    ");

                    // 주문이 받아야 할 총액 = 미수금 + 이미 받은 금액
                    $guardOrderPrice = (int) $guardOrder['od_misu'] + (int) $guardOrder['od_receipt_price'];

                    $guardRet = cookiepay_guard_verified_amount($verify, $cookiepay, $cookiepay['ORDERNO'], $guardOrderPrice);

                    if (!$guardRet['ok']) {
                        @cookiepay_payment_log("[통지] 결제금액 검증 차단 ({$guardRet['code']})", $guardRet['msg']." / 주문금액 {$guardOrderPrice}", 3);

                        // 위 574~640행은 검증보다 먼저 cookiepay_pg_result 를 pay_status=1 로
                        // 갱신한다. 위조 통지를 여기서 막아도 결제 기록은 이미 오염된 뒤라,
                        // 그 기록으로 주문서를 제출하면 항목 7 검증을 통과할 수 있다.
                        // 위조가 분명한 경우에는 결제 기록을 실패로 내려 쓰지 못하게 한다.
                        // VERIFY_FAILED 는 일시적 통신 장애일 수 있으므로 건드리지 않는다.
                        if (in_array($guardRet['code'], array('NOTI_TAMPERED', 'ORDERNO_MISMATCH', 'AMOUNT_MISMATCH'), true)) {
                            sql_query("
                                UPDATE ".COOKIEPAY_PG_RESULT."
                                SET pay_status = 3
                                WHERE ORDERNO = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
                            ", false);
                            @cookiepay_payment_log("[통지] 위조 통지로 오염된 결제기록 무효화", "ORDERNO ".$cookiepay['ORDERNO']." / pay_status=3", 3);
                        }

                        exit;
                    }

                    $guardAmount = $guardRet['amount'];
                    $guardTid    = isset($verify['TID']) ? $verify['TID'] : '';
                    // e: cookiepay-plugin > 보안검토 항목10 > v1.3 > 261014

                    if(!empty($cookiepay['ORDERNO'])) {
                        $sql_shop_order = "update {$g5['g5_shop_order_table']}
                                set od_receipt_price='" . sql_real_escape_string((string) $guardAmount) . "', od_status = '입금', od_tno='" . sql_real_escape_string((string) $guardTid) . "', od_misu=0, od_receipt_time=now()
                                where od_id = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
                                limit 1 ";
                        $result_shop_order = sql_query($sql_shop_order, false);

                        $sql_shop_cart = "update {$g5['g5_shop_cart_table']}         
                                set ct_status = '입금' 
                                where od_id = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
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

                // s: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
                // 결제기록은 검증 '전' 에 이미 pay_status=1 로 올라가 있다. 예전에는
                // 취소가 성공할 때만 2 로 되돌렸는데, 쿠키페이가 "그런 거래 없음"
                // (E008 등)이라 답하면 없는 거래라 취소도 실패한다. 결과적으로
                // 위조가 가장 명백한 경우에 결제성공 기록이 그대로 남았고,
                // 항목7 가드(pay_status=1 이면 주문완료 허용)를 그대로 통과했다.
                //
                // 쿠키페이가 코드를 돌려준 것은 "확인해 보니 없다" 는 답이므로
                // 결제실패(3)로 내린다. 응답 자체를 못 받은 통신 장애는 제외한다
                // (curl 실패 시 $verify 가 null 이라 코드가 빈 문자열이 된다).
                // 바로 아래 취소가 성공하면 2(취소)로 다시 덮어쓴다.
                $cpVerifyCode = isset($verify['RESULTCODE']) ? trim((string) $verify['RESULTCODE']) : '';

                if ($cpVerifyCode !== '' && $cpVerifyCode !== '9999') {
                    sql_query("
                        UPDATE ".COOKIEPAY_PG_RESULT."
                        SET pay_status = 3
                        WHERE ORDERNO = '" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'
                    ", false);
                    @cookiepay_payment_log(
                        "[통지]검증 실패 결제기록 무효화",
                        "ORDERNO ".$cookiepay['ORDERNO']." / paycert ".$cpVerifyCode." / pay_status=3",
                        3
                    );
                }
                // e: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027

                $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

                if ($ret['status'] === true) {
                    @cookiepay_payment_log("[통지]결제취소 성공5", $ret['data'], 3);
                    $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                } else {
                    @cookiepay_payment_log("[통지]결제취소 실패5", $ret['data'], 3);
                }

                $cancelArr = json_decode($ret['data'], true);

                // s: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
                // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
                // 관리자 취소 목록이 오염되고, 뒤에 오는 진짜 취소 통지가
                // 중복(orderno + cancel_date + cancel_amt)으로 오인될 수 있다.
                $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
                    !empty($cancelArr['cancel_tid'])
                    || !empty($cancelArr['cancel_code'])
                    || !empty($cancelArr['cancel_amt'])
                );

                if (!$cpCancelOk) {
                    @cookiepay_payment_log("[통지]취소 미성립 — 취소기록 생략6", "ORDERNO ".$cookiepay['ORDERNO'], 3);
                } else {
                $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('"
            . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_tid']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_code']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_msg']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_date']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_amt']) . "') ";
                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 성공6", $sql, 3);
                } else {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 실패6", $sql, 3);
                }
                }
                // e: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
            }
        } else {
            // 결제 검증 토큰 발행 실패시 결제 취소 처리
            @cookiepay_payment_log("[통지]결제 검증 토큰 발행 실패7", $resultJson, 3);
            
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("[통지]결제취소 성공7", $ret['data'], 3);
                $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
            } else {
                @cookiepay_payment_log("[통지]결제취소 실패7", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            // s: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
            // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
            // 관리자 취소 목록이 오염되고, 뒤에 오는 진짜 취소 통지가
            // 중복(orderno + cancel_date + cancel_amt)으로 오인될 수 있다.
            $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
                !empty($cancelArr['cancel_tid'])
                || !empty($cancelArr['cancel_code'])
                || !empty($cancelArr['cancel_amt'])
            );

            if (!$cpCancelOk) {
                @cookiepay_payment_log("[통지]취소 미성립 — 취소기록 생략8", "ORDERNO ".$cookiepay['ORDERNO'], 3);
            } else {
            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('"
            . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_tid']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_code']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_msg']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_date']) . "', '"
            . sql_real_escape_string((string) $cancelArr['cancel_amt']) . "') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("[통지]결제취소결과 저장 성공8", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제취소결과 저장 실패8", $sql, 3);
            }
            }
            // e: cookiepay-plugin > 통지 검증 실패 처리 > v2.1 > 261027
        }
    }
}