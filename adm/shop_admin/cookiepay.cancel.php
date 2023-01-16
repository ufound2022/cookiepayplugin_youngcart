<?php
include_once('./_common.php');
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

$orderno = $od_id;

$cookiepayPgResultSql = "SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='$orderno' ORDER BY ACCEPTDATE DESC LIMIT 1";
$cookiepayPgResultRes = sql_fetch($cookiepayPgResultSql);
if (isset($cookiepayPgResultRes['PGNAME']) && !empty($cookiepayPgResultRes['PGNAME'])) {
    $cookiepayApi = cookiepay_get_api_accountByPg($default, $cookiepayPgResultRes['PGNAME']);
    $api_id = $cookiepayApi['api_id'];
    $api_key = $cookiepayApi['api_key'];

    $tid = $cookiepayPgResultRes['TID'];
    $bank = $cookiepayPgResultRes['CARDNAME'];
    $accountno = $cookiepayPgResultRes['ACCOUNTNO'];
    $accountname = $cookiepayPgResultRes['RECEIVERNAME'];
    // $amount = $cookiepayPgResultRes['AMOUNT'];

    $tokenheaders = array(); 
    array_push($tokenheaders, "content-type: application/json; charset=utf-8");

    $token_url = COOKIEPAY_TOKEN_URL;

    $token_request_data = array(
        'pay2_id' => $api_id,
        'pay2_key'=> $api_key,
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
    
    if($RES_ARR['RTN_CD'] == '0000'){
        $headers = array(); 
        array_push($headers, "content-type: application/json; charset=utf-8");
        array_push($headers, "TOKEN: ".$RES_ARR['TOKEN']);
        array_push($headers, "ApiKey: ".$api_key);

        $cookiepayments_url = COOKIEPAY_CANCEL_URL;

        $request_data_array = array(
            'tid' => $tid,
            'reason' => '고객취소',
            'bank' => $bank,
            'account_no' => $accountno,
            'account_name' => $accountname
        );

        // 부분 취소일 경우 취소 금액 처리(취소금액이 없으면 전체 취소로 처리됨)
        // if ($amount > 0) {
        //     $request_data_array['amount'] = $amount;
        // }

        $cookiepayments_json = json_encode($request_data_array, TRUE);

        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, $cookiepayments_url);
        curl_setopt($ch,CURLOPT_POST, false);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepayments_json);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
        curl_setopt($ch,CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        $result_array = json_decode($response,TRUE);

        if ($result_array['cancel_code'] == '0000') {
            $data['result'] = $result_array['cancel_code'];
            @cookiepay_payment_log("결제 취소 성공", $response, 3);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$orderno}', '{$result_array['cancel_tid']}', '{$result_array['cancel_code']}', '{$result_array['cancel_msg']}', '{$result_array['cancel_date']}', '{$result_array['cancel_amt']}') ";
            
            $res = sql_query($sql, false);

            if ($res) {
                @cookiepay_payment_log("결제 취소 결과 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("결제 취소 결과 저장 실패", $sql, 3);
            }

        } else {
            $data['error'] = "[{$result_array['cancel_code']}] {$result_array['cancel_msg']}";
            @cookiepay_payment_log("결제 취소 실패", $response, 3);
        }
    } else {
        $data['error'] = "결제 취소 토큰 발급 실패";
        @cookiepay_payment_log("결제 취소 토큰 발급 실패", "", 3);
    }    
}
