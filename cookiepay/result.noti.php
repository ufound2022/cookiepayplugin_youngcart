<?php
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 결제 결과 통지 수신 후 처리

$cookiepay = json_decode(file_get_contents('php://input'), true);
$cookiepay['ACCEPT_NO'] = $cookiepay['ACCEPT_NO'] ?? '';
$cookiepay['TID'] = $cookiepay['TID'] ?? '';
$cookiepay['ORDERNO'] = $cookiepay['ORDERNO'] ?? '';

$resultMode = null;

@cookiepay_payment_log("[통지]수신", json_encode($cookiepay), 3);

if(!empty($cookiepay['ACCEPT_NO']) && !empty($cookiepay['TID']) && !empty($cookiepay['ORDERNO'])) {
    $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
    $payStatus = $pgResult['pay_status'] ?? '';

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
            @cookiepay_payment_log("[통지]결제결과 저장 성공", $sql, 3);
            $resultMode = 'insert';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패", $sql, 3);
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
            @cookiepay_payment_log("[통지]결제결과 저장 성공", $sql, 3);
            $resultMode = 'update';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패", $sql, 3);
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
            $verifyId = $pgVerify['id'] ?? null;

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
                @cookiepay_payment_log("[통지]결제검증결과 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제검증결과 저장 실패", $sql, 3);
            }

            if($verify['RESULTCODE'] == '0000') {
                // 결제 검증 성공
                @cookiepay_payment_log("[통지]결제검증 성공", $response, 3);
                echo '<html>
                        <body>
                        <RESULT>SUCCESS</RESULT>
                        </body>
                      </html>';
            } else {
                // 결제 검증 실패시 결제 취소 처리
                @cookiepay_payment_log("[통지]결제검증 실패", $response, 3);
            
                $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

                if ($ret['status'] === true) {
                    @cookiepay_payment_log("[통지]결제취소 성공", $ret['data'], 3);
                    $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                } else {
                    @cookiepay_payment_log("[통지]결제취소 실패", $ret['data'], 3);
                }

                $cancelArr = json_decode($ret['data'], true);

                $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 성공", $sql, 3);
                } else {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 실패", $sql, 3);
                }
            }
        } else {
            // 결제 검증 토큰 발행 실패시 결제 취소 처리
            @cookiepay_payment_log("[통지]결제 검증 토큰 발행 실패", $resultJson, 3);
            
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("[통지]결제취소 성공", $ret['data'], 3);
                $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
            } else {
                @cookiepay_payment_log("[통지]결제취소 실패", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("[통지]결제취소결과 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제취소결과 저장 실패", $sql, 3);
            }
        }
    }
}