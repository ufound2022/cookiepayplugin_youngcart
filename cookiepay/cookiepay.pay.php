<?php
include_once('../shop/_common.php');

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

$ret = [
    'status' => false,
    'data' => ''
];

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
        // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
        // try_pay 는 로그인 검사가 없는 경로다. clean_xss_tags 는 SQL 방어가 아니다.
        $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $orderno) . "' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
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
        // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
        $values['pay_status'] = "0";
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
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
    // s: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    // $odId 에 담아 놓고 미정의 $od_id 를 보고 있어 중복 주문 검사가 한 번도
    // 동작하지 않았다(cookiepay_subscribe.pay.php 도 같은 오타였다).
    if (!empty($odId))
    {
        $odResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_SHOP_ORDER." WHERE od_id='" . sql_real_escape_string((string) $odId) . "' LIMIT 1");
        $odResultId = isset($odResult['od_id']) && !empty($odResult['od_id']) ? $odResult['od_id'] : '';
    }
    // e: cookiepay-plugin > PG 지적사항 > v1.9 > 261026
    
    if (!empty($odId) && empty($odResultId))
    {
        // s: cookiepay-plugin > 보안검토 항목1 > v1.4 > 261014
        // 아래 반복문은 전송된 필드의 이름을 그대로 컬럼 이름으로 쓴다. 값은
        // 이스케이프할 수 있지만 컬럼 이름은 따옴표로 감쌀 수 없으므로, 실제
        // 테이블에 있는 컬럼 이름만 통과시킨다. 허용 목록은 테이블에서 직접 읽는다.
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
        // e: cookiepay-plugin > 보안검토 항목1 > v1.4 > 261014

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
                // s: cookiepay-plugin > 보안검토 항목1 > v1.4 > 261014
                $vals[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                // e: cookiepay-plugin > 보안검토 항목1 > v1.4 > 261014
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

// s: cookiepay-plugin > 시크릿키 노출 제거 > v1.7 > 261020
// 결제창 호출(/pay/ready)을 서버에서 중계한다.
// 예전에는 브라우저가 쿠키페이로 직접 요청하면서 ApiKey 헤더에 가맹점 시크릿 키를
// 실어 보냈고, 그 값이 페이지 소스에 그대로 찍혀 누구나 가져갈 수 있었다.
// 어느 키를 쓸지는 브라우저가 보낸 API_ID(비밀이 아니다)로 고른다.
// 설정에 등록된 아이디와 일치할 때만 중계하므로 동작은 이전과 같다.
if ($mode == "pay_ready") {
    @cookiepay_payment_log("결제창 중계 진입", "ORDERNO ".(isset($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : '')." / API_ID ".(isset($cookiepay['API_ID']) ? $cookiepay['API_ID'] : ''), 3);

    require_once G5_PATH."/cookiepay/cookiepay.guard.php";

    // 가드 파일이 옛 버전이면 아래 호출에서 치명적 오류가 나고 빈 응답이 나간다.
    // 그러면 호출부가 아무것도 표시하지 못해 원인을 알 수 없으므로 먼저 확인한다.
    if (!function_exists('cookiepay_guard_match_api_key')) {
        @cookiepay_payment_log("결제창 중계 불가", "cookiepay.guard.php 가 옛 버전입니다 (cookiepay_guard_match_api_key 없음)", 3);
        echo json_encode(array('RTN_CD' => '9999', 'RTN_MSG' => '결제 모듈 파일이 최신이 아닙니다.'));
        exit;
    }

    $readyParams = $cookiepay;
    unset($readyParams['mode']);

    $readyApiId = isset($readyParams['API_ID']) ? $readyParams['API_ID'] : '';

    // 설정에 등록된 연동 정보 전체(수기/일반/해외달러/해외원화/정기)
    $readyPairs = array();
    foreach (array(1, 3, 5, 7, 9) as $readyType) {
        $readyPairs[] = cookiepay_get_api_account_info($default, $readyType);
    }

    $readyRet = cookiepay_guard_match_api_key($readyPairs, $readyApiId);

    if (!$readyRet['ok']) {
        @cookiepay_payment_log("결제창 중계 차단 ({$readyRet['code']})", $readyRet['msg']." / API_ID ".$readyApiId, 3);
        echo json_encode(array('RTN_CD' => '9999', 'RTN_MSG' => $readyRet['msg']));
        exit;
    }

    $readyUrl = $default['de_card_test'] == '0' ? COOKIEPAY_PAY_URL : COOKIEPAY_TESTPAY_URL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $readyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($readyParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded",
    ));
    $readyRes = curl_exec($ch);
    $readyErr = curl_error($ch);
    curl_close($ch);

    if ($readyRes === false) {
        @cookiepay_payment_log("결제창 중계 실패", $readyErr." / ORDERNO ".(isset($readyParams['ORDERNO']) ? $readyParams['ORDERNO'] : ''), 3);
        echo json_encode(array('RTN_CD' => '9999', 'RTN_MSG' => '결제창을 열 수 없습니다.'));
        exit;
    }

    @cookiepay_payment_log("결제창 중계", "ORDERNO ".(isset($readyParams['ORDERNO']) ? $readyParams['ORDERNO'] : '')." / 응답 ".strlen($readyRes)."바이트", 3);

    // 응답은 결제창 HTML 이다. 호출부가 그대로 화면에 넣으므로 가공하지 않는다.
    echo $readyRes;
    exit;
}
// e: cookiepay-plugin > 시크릿키 노출 제거 > v1.7 > 261020

// 수기결제
if ($mode == "keyin_pay") {

    // s: cookiepay-plugin > 보안검토 항목9 > v1.3 > 261014
    // 이 모드는 로그인 확인이 없어 누구나 호출할 수 있었다. 카드번호·금액·주문번호를
    // 요청에서 그대로 받아 쇼핑몰 서버가 결제를 보내므로, 도난 카드번호의 유효성을
    // 쇼핑몰을 통해 확인하는 통로(카드 테스팅)가 된다.
    // 로그인한 본인의 결제 대기 주문에 대해서만, 횟수 제한 안에서만 허용한다.
    //
    // 주의: 이 시점에는 g5_shop_order 가 아직 없다. 결제가 성공해야 pgresult.php 가
    // opener 의 주문서를 제출하고 그때 주문이 만들어진다. 대기 주문은
    // cookiepay_shop_order(try_order 모드가 저장)에 있다.
    require_once G5_PATH."/cookiepay/cookiepay.guard.php";

    $guardMemberId = isset($member['mb_id']) ? $member['mb_id'] : '';
    $guardSessionOrderno = isset($_SESSION['ss_order_id']) ? $_SESSION['ss_order_id'] : get_session('ss_order_id');

    $guardPendingOrder = array();
    if (!empty($guardSessionOrderno)) {
        $guardPendingOrder = sql_fetch("
            SELECT od_id, mb_id, od_price, od_send_cost, od_send_cost2, od_temp_point, od_send_coupon
            FROM ".COOKIEPAY_SHOP_ORDER."
            WHERE od_id = '" . sql_real_escape_string((string) $guardSessionOrderno) . "'
            LIMIT 1
        ");
    }

    // 시도 횟수는 세션에 센다. 실패한 수기결제는 BUYERID·ACCEPTDATE 가 빈 값으로만
    // 저장되고(위 insert 는 토큰 응답만 채운다), 같은 주문의 재시도는 행을 추가하지도
    // 않으므로 cookiepay_pg_result 로는 시도를 셀 수 없다.
    $guardTryKey = 'cookiepay_keyin_try';
    $guardTryLog = (isset($_SESSION[$guardTryKey]) && is_array($_SESSION[$guardTryKey])) ? $_SESSION[$guardTryKey] : array();

    $guardNow = time();
    $guardRecent = array();
    foreach ($guardTryLog as $guardTryTime) {
        if (($guardNow - (int) $guardTryTime) < 600) { // 10분 안의 시도만 센다
            $guardRecent[] = (int) $guardTryTime;
        }
    }

    $guardRet = cookiepay_guard_keyin_request($guardMemberId, $guardSessionOrderno, $guardPendingOrder, $cookiepay, count($guardRecent));

    // 차단 여부와 무관하게 시도로 기록한다(차단된 시도도 횟수에 포함).
    $guardRecent[] = $guardNow;
    $_SESSION[$guardTryKey] = $guardRecent;

    if (!$guardRet['ok']) {
        @cookiepay_payment_log("수기결제 차단 ({$guardRet['code']})", $guardRet['msg']." / 요청자 ".$guardMemberId." / ORDERNO ".$cookiepay['ORDERNO'], 3);
        echo '<script>alert("'.$guardRet['msg'].'"); self.close();</script>';
        exit;
    }

    // 결제 금액은 요청값이 아니라 서버에 저장된 주문 금액을 쓴다.
    // 문자열로 넣는다. 정수로 두면 json_encode 가 따옴표 없이 보내고
    // 쿠키페이가 E007(필수 항목 AMOUNT 누락)로 거부한다. 다른 필드도 모두 문자열이다.
    $cookiepay['AMOUNT'] = (string) $guardRet['amount'];

    @cookiepay_payment_log("수기결제 검증 통과", "ORDERNO ".$cookiepay['ORDERNO']." / 금액 ".$cookiepay['AMOUNT'], 3);
    // e: cookiepay-plugin > 보안검토 항목9 > v1.3 > 261014

    $cookiepayApiKeyin = cookiepay_get_api_account_info($default, 1);

    $req_json = json_encode([
        'pay2_id' => $cookiepayApiKeyin['api_id'],
        'pay2_key'=> $cookiepayApiKeyin['api_key'],
    ], true);

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
    
    if ($tokenRes['RTN_CD'] == '0000') {
        
        $pgResultId = '';
        if (!empty($cookiepay['ORDERNO'])) {
            $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "' AND pay_status=0 ORDER BY `id` DESC LIMIT 1");
            $pgResultId = isset($pgResult['id']) && !empty($pgResult['id']) ? $pgResult['id'] : '';
        }

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
            $values['pay_type'] = "'1'";
            $values['pay_status'] = "0";
            $valueStr = implode(",", $values);

            $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("수기결제 시도 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("수기결제 시도 저장 실패", $sql, 3);
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

        $cookiepayments_url = $default['de_card_test'] == '0' ? COOKIEPAY_KEYIN_URL : COOKIEPAY_TESTKEYIN_URL;

        $request_data_array = [
            'API_ID'        => $cookiepayApiKeyin['api_id'],
            'ORDERNO'       => $cookiepay['ORDERNO'],
            'PRODUCTNAME'   => $cookiepay['PRODUCTNAME'],
            'AMOUNT'        => $cookiepay['AMOUNT'],
            'BUYERNAME'     => $cookiepay['BUYERNAME'],
            'BUYEREMAIL'    => $cookiepay['BUYEREMAIL'],
            'CARDNO'        => $cookiepay['CARDNO'],
            'EXPIREDT'      => $cookiepay['EXPIREDT'],
            'PRODUCTCODE'   => $cookiepay['PRODUCTCODE'],
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

        if ($keyinRes['RESULTCODE'] == '0000') {
            @cookiepay_payment_log("수기결제 성공", $keyinResJson, 1);

            $payStatus = '';
            if (!empty($keyinRes['ORDERNO'])) {
                $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $keyinRes['ORDERNO']) . "' ORDER BY `id` DESC LIMIT 1");
                $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';
            }

            if ($payStatus == '') {
                // insert
                $columnStr = implode(",", $pgResultColumns);
                $values = [];
                foreach ($pgResultColumns as $val) {
                    $values[$val] = "''";
                }
                foreach ($keyinRes as $key => $val) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $values['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $values['pay_type'] = "'1'";
                $values['pay_status'] = "1";
                $valueStr = implode(",", $values);

                $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("수기결제 결과 저장 성공1", $sql, 3);
                } else {
                    @cookiepay_payment_log("수기결제 결과 저장 실패1", $sql, 3);
                }
                // end insert
            } else if ($payStatus != 1) {
                // update
                $set = [];
                foreach ($keyinRes as $key => $val) {
                    if (in_array($key, $pgResultColumns)) {
                        $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
                    }
                }
                $set['PGNAME'] = "PGNAME='" . sql_real_escape_string((string) $default['de_pg_service']) . "'";
                $set['pay_type'] = "pay_type='1'";
                $set['pay_status'] = "pay_status=1";
                $setStr = implode(",", $set);

                $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='" . sql_real_escape_string((string) $keyinRes['ORDERNO']) . "'";

                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("수기결제 결과 저장 성공2", $sql, 3);
                } else {
                    @cookiepay_payment_log("수기결제 결과 저장 실패2", $sql, 3);
                }
                // end update
            }

            // 결제 검증
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
                    @cookiepay_payment_log("수기결제 검증결과 저장 성공3", $sql, 3);
                } else {
                    @cookiepay_payment_log("수기결제 검증결과 저장 실패3", $sql, 3);
                }

                if($verify['RESULTCODE'] == '0000') {
                    @cookiepay_payment_log("수기결제 검증 성공4", $verifyJson, 3);
                } else {
                    @cookiepay_payment_log("수기결제 검증 실패4", $verifyJson, 3);
                
                    $ret = cookipay_cancel_payment($cookiepayApiKeyin['api_id'], $cookiepayApiKeyin['api_key'], $keyinRes['TID']);
        
                    if ($ret['status'] === true) {
                        @cookiepay_payment_log("수기결제 취소 성공5", $ret['data'], 3);
                        $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                    } else {
                        @cookiepay_payment_log("수기결제 취소 실패5", $ret['data'], 3);
                    }
        
                    $cancelArr = json_decode($ret['data'], true);
        
                    // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
                    // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
                    $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
                        !empty($cancelArr['cancel_tid'])
                        || !empty($cancelArr['cancel_code'])
                        || !empty($cancelArr['cancel_amt'])
                    );

                    if (!$cpCancelOk) {
                        @cookiepay_payment_log("수기결제 취소 미성립 — 취소기록 생략6", "ORDERNO ".$cookiepay['ORDERNO'], 3);
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
                        @cookiepay_payment_log("수기결제 취소결과 저장 성공6", $sql, 3);
                    } else {
                        @cookiepay_payment_log("수기결제 취소결과 저장 실패6", $sql, 3);
                    }
                    }
                    // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
                }
            } else {
                @cookiepay_payment_log("수기결제 검증 토큰 발행 실패7", $resultJson, 3);
                
                $ret = cookipay_cancel_payment($cookiepayApiKeyin['api_id'], $cookiepayApiKeyin['api_key'], $keyinRes['TID']);
        
                if ($ret['status'] === true) {
                    @cookiepay_payment_log("수기결제 취소 성공8", $ret['data'], 3);
                    $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
                } else {
                    @cookiepay_payment_log("수기결제 취소 실패8", $ret['data'], 3);
                }
        
                $cancelArr = json_decode($ret['data'], true);
        
                // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
                // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
                $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
                    !empty($cancelArr['cancel_tid'])
                    || !empty($cancelArr['cancel_code'])
                    || !empty($cancelArr['cancel_amt'])
                );

                if (!$cpCancelOk) {
                    @cookiepay_payment_log("수기결제 취소 미성립 — 취소기록 생략9", "ORDERNO ".$cookiepay['ORDERNO'], 3);
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
                    @cookiepay_payment_log("수기결제 취소결과 저장 성공9", $sql, 3);
                } else {
                    @cookiepay_payment_log("수기결제 취소결과 저장 실패9", $sql, 3);
                }
                }
                // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
            }
        } else {
            @cookiepay_payment_log("수기결제 실패", json_encode($keyinResJson), 3);
        }
    } else {
        @cookiepay_payment_log("수기결제 토큰 발행 실패", $tokenResJson, 3);
    }
    
    // s: cookiepay-plugin v1.2
    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.$keyinRes['RESULTCODE'].'&RESULTMSG='.$keyinRes['RESULTMSG'].'&type=keyin";</script>';
    // e: cookiepay-plugin v1.2
    
    exit;
} // end if ($mode == "keyin_pay") 
