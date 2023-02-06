<?php
include_once('../shop/_common.php');

// PG 결제 결과 후 리턴 받아 처리

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

$mode = $_GET['mode'] ?? '';
if ($mode == "after") {
    $resCode = $_GET['RESULTCODE'] ?? '';
    $resMsg = $_GET['RESULTMSG'] ?? '';

    if ($resCode == '0000') {
        echo '<script>
            alert("결제가 성공했습니다.");
            opener.document.forderform.submit();
            self.close();
        </script>';
    } else {
        echo '<script>
            alert("결제가 실패했습니다.\n오류코드: '.$resCode.'\n오류메시지: '.$resMsg.'");
            self.close();
        </script>';
    }
    
    exit;
}

$cookiepayApi = cookiepay_get_api_account($default);

$cookiepay = $_REQUEST;

require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

if (!isset($cookiepay['RESULTCODE'])) {
    // 결제 실패 로그 기록 - 응답 결과 없음
    @cookiepay_payment_log("응답 결과 없음", "", 3);

    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE=&RESULTMSG=";</script>';
    exit;
}

// 결제 결과 테이블에 저장
$set = [];
foreach ($cookiepay as $key => $val) {
    if (in_array($key, $pgResultColumns)) {
        $set[$key] = "{$key}='{$val}'";
    }
}
$set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'"; // pg사 추가
$setStr = implode(",", $set);

$sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='{$cookiepay['ORDERNO']}'";

$res = sql_query($sql, false);
if ($res) {
    @cookiepay_payment_log("결제결과 저장 성공", $sql, 3);
} else {
    @cookiepay_payment_log("결제결과 저장 실패", $sql, 3);
}

if ($cookiepay['RESULTCODE'] == '0000') {
    // 결제 성공 로그 기록
    @cookiepay_payment_log("결재 성공", json_encode($cookiepay), 1);

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

        // 결제 검증 결과 테이블에 저장
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
        if ($res) {
            @cookiepay_payment_log("결제검증결과 저장 성공", $sql, 3);
        } else {
            @cookiepay_payment_log("결제검증결과 저장 실패", $sql, 3);
        }

        if($verify['RESULTCODE'] == '0000') {
            // 결제 검증 성공
            @cookiepay_payment_log("결제검증 성공", $response, 3);
        } else {
            // 결제 검증 실패시 결제 취소 처리
            @cookiepay_payment_log("결제검증 실패", $response, 3);
        
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("결제취소 성공", $ret['data'], 3);
            } else {
                @cookiepay_payment_log("결제취소 실패", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("결제취소결과 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("결제취소결과 저장 실패", $sql, 3);
            }
        }
    } else {
        // 결제 검증 토큰 발행 실패시 결제 취소 처리
        @cookiepay_payment_log("결제 검증 토큰 발행 실패", $resultJson, 3);
        
        $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

        if ($ret['status'] === true) {
            @cookiepay_payment_log("결제취소 성공", $ret['data'], 3);
        } else {
            @cookiepay_payment_log("결제취소 실패", $ret['data'], 3);
        }

        $cancelArr = json_decode($ret['data'], true);

        $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("결제취소결과 저장 성공", $sql, 3);
        } else {
            @cookiepay_payment_log("결제취소결과 저장 실패", $sql, 3);
        }
    }

} else {
    // 결제 실패 로그 기록
    @cookiepay_payment_log("결재 실패", json_encode($cookiepay), 3);

    echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.$cookiepay['RESULTCODE'].'&RESULTMSG='.$cookiepay['RESULTMSG'].'";</script>';

    exit;
}

echo '<script>location.href = "./pgresult.php?mode=after&RESULTCODE='.$cookiepay['RESULTCODE'].'&RESULTMSG='.$cookiepay['RESULTMSG'].'";</script>';

exit;
