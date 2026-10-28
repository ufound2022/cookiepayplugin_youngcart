<?php
include_once('../shop/_common.php');

// PG 결제 결과 후 리턴 받아 처리

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

$mode = isset($_GET['mode']) ? clean_xss_tags($_GET['mode'], 1, 1) : '';

// s: cookiepay-plugin v1.2
$type = isset($_GET['type']) ? clean_xss_tags($_GET['type'], 1, 1) : '';
// e: cookiepay-plugin v1.2

// s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
// 이 줄은 mode 분기보다 위라 모든 요청에서 실행된다. 예전에는 $_GET['od_id'] 를
// 아무 처리 없이 보간해, 로그인 없이 회원·주문 테이블은 물론 연동 시크릿키가
// 든 설정 테이블까지 UNION 으로 끌어낼 수 있었다.
$pgOdIdRaw = isset($_GET['od_id']) ? (string) $_GET['od_id'] : '';
$pgOdId    = preg_match('/^[0-9]{10,24}$/', $pgOdIdRaw) ? $pgOdIdRaw : '';
$pgResult_ = $pgOdId === '' ? array() : sql_fetch("
    SELECT * FROM {$g5['g5_shop_order_table']}
    WHERE od_id='" . sql_real_escape_string($pgOdId) . "'
    ORDER BY `od_id` DESC LIMIT 1
");
// e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027

// s: cookiepay-plugin v1.2 > 20240412
if(!empty($_GET['ACCOUNTNO']) && !empty($_GET['RECEIVERNAME'])) {
    $order_ok_alert_msg = "(가상계좌)주문 접수가 완료되었습니다.\\n\\n입금은행, 계좌번호를 확인후 입금하여 주시기 바랍니다.";
} else { 
    $order_ok_alert_msg = "결제가 완료되었습니다.";
}
// e: cookiepay-plugin v1.2 > 20240412

if ($mode == "after") {
    $resCode = isset($_GET['RESULTCODE']) ? clean_xss_tags($_GET['RESULTCODE'], 1, 1) : '';
    $resMsg = isset($_GET['RESULTMSG']) ? clean_xss_tags($_GET['RESULTMSG'], 1, 1) : '';
    
    // s: cookiepay-plugin v1.2
    if ($resCode == '0000') {
        if ($type == 'keyin') {
            echo "
                <script>
                    alert('{$order_ok_alert_msg}');
                    opener.document.forderform.submit();
                    self.close();
                </script>
                ";
        }
        else if ($default['de_pg_service'] == 'COOKIEPAY_KW' || $default['de_pg_service'] == 'COOKIEPAY_TS' || $default['de_pg_service'] == 'COOKIEPAY_AL') {

            // s: cookiepay-plugin v1.2 > 240322
            // 노티우선 업데이트시 > 주문완료페이지로 이동
            if(!empty($pgResult_['od_id'])) { 
                // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
                echo "<script language='javascript'> alert('{$order_ok_alert_msg}'); location.href = '/shop/orderinquiryview.php?od_id=" . urlencode($pgResult_['od_id']) . "'; </script>";
                // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
                exit;
            }
            // e: cookiepay-plugin v1.2 > 240322


            echo "
                <form name='form' action='/shop/orderformupdate.php' method='POST' >
                    <input type='hidden' name='od_id' value='".cookiepay_h($pgOdId)."'>
                </form>
                <script>
                    alert('{$order_ok_alert_msg}');
                    document.form.submit();
                </script>
                ";
        }
        else {

            // 노티우선 업데이트시 > 주문완료페이지로 이동
            if(!empty($pgResult_['od_id'])) { 

                echo "
                <form name='form' action='/shop/orderinquiryview.php' method='GET' >
                    <input type='hidden' name='od_id' value='".cookiepay_h($pgResult_['od_id'])."'>
                </form>
                <script>
                    alert('결제가 성공했습니다.');
                    window.opener.name = 'cookiepay';
                    document.form.target = 'cookiepay';
                    document.form.submit();
                    self.close();
                </script>
                ";

            }
                        
            echo "
                <form name='form' action='/shop/orderformupdate.php' method='POST' >
                    <input type='hidden' name='od_id' value='".cookiepay_h($pgOdId)."'>
                </form>
                <script>
                    alert('결제가 성공했습니다.');
                    window.opener.name = 'cookiepay';
                    document.form.target = 'cookiepay';
                    document.form.submit();
                    self.close();
                </script>
                ";
        }
    }
    else {
        if ($type == 'keyin') {
            echo '
                <script>
                    alert("결제가 실패했습니다.\n오류코드: " + '.cookiepay_js($resCode).' + "\n오류메시지: " + '.cookiepay_js($resMsg).');
                    opener.location.reload();
                    self.close();
                </script>
                ';
        }
        else {
            echo '
                <script>
                    alert("결제가 실패했습니다.\n오류코드: " + '.cookiepay_js($resCode).' + "\n오류메시지: " + '.cookiepay_js($resMsg).');
                    location.href = "/shop";
                </script>
                ';
        }
    }
    // e: cookiepay-plugin v1.2
    
    exit;
}

// $cookiepay = $_REQUEST;
$cookiepay = array();
foreach ($_REQUEST as $key => $value) {
    $cookiepay[$key] = clean_xss_tags($value, 1, 1);
}

require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

if (!isset($cookiepay['RESULTCODE'])) {
    // 결제 실패 로그 기록 - 응답 결과 없음
    @cookiepay_payment_log("응답 결과 없음", "", 3);

    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE=&RESULTMSG=";</script>';
    exit;
}

if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) {
    $cookiepayApi = cookiepay_get_api_account_info($default, $cookiepay['ETC3']);
} else {
    $cookiepayApi = cookiepay_get_api_account_info($default, 3);
}

// @cookiepay_payment_log("통지 테스트", json_encode($cookiepay), 3);
// exit;

if ($cookiepay['RESULTCODE'] == '0000') {
    // 결제 성공 로그 기록
    @cookiepay_payment_log("결제 성공", json_encode($cookiepay), 1);

    $payStatus = '';
    if (!empty($cookiepay['ORDERNO'])) {
        $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "' ORDER BY `id` DESC LIMIT 1");
        $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';
    }

    // 결제 결과 테이블에 저장
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
        $values['PGNAME'] = "'" . sql_real_escape_string((string) $default['de_pg_service']) . "'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $values['pay_type'] = "'" . sql_real_escape_string((string) $cookiepay['ETC3']) . "'";
        }
        $values['pay_status'] = 1;
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("결제결과 저장 성공1", $sql, 3);
        } else {
            @cookiepay_payment_log("결제결과 저장 실패1", $sql, 3);
        }
    } else if ($payStatus != 1) {
        // update
        $set = [];
        foreach ($cookiepay as $key => $val) {
            if (in_array($key, $pgResultColumns)) {
                $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
            }
        }
        $set['PGNAME'] = "PGNAME='" . sql_real_escape_string((string) $default['de_pg_service']) . "'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $set['pay_type'] = "pay_type='" . sql_real_escape_string((string) $cookiepay['ETC3']) . "'";
        }
        $set['pay_status'] = "pay_status=1";

        // s: cookiepay-plugin v1.2.1 > 240412
        # 가상계좌 결제요청이라면 > pay_status : 0 처리 
        if(!empty($cookiepay['CARDNAME']) && !empty($cookiepay['ACCOUNTNO'])) { 
            $set['pay_status'] = "pay_status=0";
        }
        // e: cookiepay-plugin v1.2.1 > 240412
                
        $setStr = implode(",", $set);

        $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";

        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("결제결과 저장 성공2", $sql, 3);
        } else {
            @cookiepay_payment_log("결제결과 저장 실패2", $sql, 3);
        }
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
                    $values[$key] = "'" . sql_real_escape_string((string) $val) . "'";
                }
            }
            $valueStr = implode(",", $values);

            $sql = " INSERT INTO ".COOKIEPAY_PG_VERIFY." ({$columnStr}) VALUES ({$valueStr}) ";
            $res = sql_query($sql, false);
        } else {
            $set = [];
            foreach ($verify as $key => $val) {
                if (in_array($key, $pgVerifyColumns)) {
                    $set[$key] = "{$key}='" . sql_real_escape_string((string) $val) . "'";
                }
            }
            $setStr = implode(",", $set);
            $sql = "UPDATE ".COOKIEPAY_PG_VERIFY." SET {$setStr} WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'";
            $res = sql_query($sql, false);
        }

        if ($res) {
            @cookiepay_payment_log("결제검증결과 저장 성공3", $sql, 3);
        } else {
            @cookiepay_payment_log("결제검증결과 저장 실패3", $sql, 3);
        }

        if($verify['RESULTCODE'] == '0000') {
            // 결제 검증 성공
            @cookiepay_payment_log("결제검증 성공4", $response, 3);
        } else {
            // 결제 검증 실패시 결제 취소 처리
            @cookiepay_payment_log("결제검증 실패4", $response, 3);
        
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("결제취소 성공5", $ret['data'], 3);
                $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
            } else {
                @cookiepay_payment_log("결제취소 실패5", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
            // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
            // cookipay_cancel_payment() 는 성공일 때만 data 를 채운다(lib 229행).
            $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
                !empty($cancelArr['cancel_tid'])
                || !empty($cancelArr['cancel_code'])
                || !empty($cancelArr['cancel_amt'])
            );

            if (!$cpCancelOk) {
                @cookiepay_payment_log("취소 미성립 — 취소기록 생략6", "ORDERNO ".$cookiepay['ORDERNO'], 3);
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
                @cookiepay_payment_log("결제취소결과 저장 성공6", $sql, 3);
            } else {
                @cookiepay_payment_log("결제취소결과 저장 실패6", $sql, 3);
            }
            }
            // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
        }
    } else {
        // 결제 검증 토큰 발행 실패시 결제 취소 처리
        @cookiepay_payment_log("결제 검증 토큰 발행 실패7", $resultJson, 3);
        
        $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

        if ($ret['status'] === true) {
            @cookiepay_payment_log("결제취소 성공8", $ret['data'], 3);
            $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='" . sql_real_escape_string((string) $cookiepay['ORDERNO']) . "'", false);
        } else {
            @cookiepay_payment_log("결제취소 실패8", $ret['data'], 3);
        }

        $cancelArr = json_decode($ret['data'], true);

        // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
        // 취소가 성립하지 않았는데도 값이 전부 빈 행을 넣고 있었다.
        // cookipay_cancel_payment() 는 성공일 때만 data 를 채운다(lib 229행).
        $cpCancelOk = ($ret['status'] === true) && is_array($cancelArr) && (
            !empty($cancelArr['cancel_tid'])
            || !empty($cancelArr['cancel_code'])
            || !empty($cancelArr['cancel_amt'])
        );

        if (!$cpCancelOk) {
            @cookiepay_payment_log("취소 미성립 — 취소기록 생략9", "ORDERNO ".$cookiepay['ORDERNO'], 3);
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
            @cookiepay_payment_log("결제취소결과 저장 성공9", $sql, 3);
        } else {
            @cookiepay_payment_log("결제취소결과 저장 실패9", $sql, 3);
        }
        }
        // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
    }

} else {
    // 결제 실패 로그 기록
    @cookiepay_payment_log("결제 실패", json_encode($cookiepay), 3);

    // s: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027
    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.urlencode($cookiepay['RESULTCODE']).'&RESULTMSG='.urlencode($cookiepay['RESULTMSG']).'";</script>';
    // e: cookiepay-plugin > 보안점검 후속 > v2.2 > 261027

    exit;
}

// s: cookiepay-plugin v1.2 > 20240412 update
echo '<script>location.href = "./pgresult.php?mode=after'
   . '&RESULTCODE='.urlencode($cookiepay['RESULTCODE'])
   . '&RESULTMSG='.urlencode($cookiepay['RESULTMSG'])
   . '&od_id='.urlencode($cookiepay['ORDERNO'])
   . '&ACCOUNTNO='.urlencode($cookiepay['ACCOUNTNO'])
   . '&RECEIVERNAME='.urlencode($cookiepay['RECEIVERNAME'])
   . '";</script>';
// e: cookiepay-plugin v1.2 > 20240412 update

exit;
