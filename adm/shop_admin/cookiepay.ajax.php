<?php
$sub_menu = "400900";
include_once('./_common.php');
include_once(G5_LIB_PATH.'/json.lib.php');
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

$data = [];
$data['error'] = '';

$data['error'] = auth_check_menu($auth, $sub_menu, 'w', true);
if($data['error']) {
    echo json_encode($data);
    exit;
}

$mode = clean_xss_tags($_POST['mode'], 1, 1);

// 결제, 취소내역 조회
if ($mode == "get") {
    $orderNo = clean_xss_tags($_POST['orderno'], 1, 1);

    $payAmount = 0; // 결제금액
    $cancelAmount = 0; // 취소금액
    $cancelAbleAmount = 0; // 취소가능금액

    $sql = " SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$orderNo}' AND RESULTCODE='0000' ORDER BY ACCEPTDATE DESC LIMIT 1 ";
    $res = sql_fetch($sql);
    if ($res) {
        $payAmount = $res['AMOUNT'];

        // 취소 내역 조회
        $sql2 = " SELECT COALESCE(SUM(cancel_amt), 0) AS cancel_amount FROM ".COOKIEPAY_PG_CANCEL." WHERE orderno='{$orderNo}' ";
        $res2 = sql_fetch($sql2);

        if (isset($res2['cancel_amount'])) {
            $cancelAmount = $res2['cancel_amount'];
            $cancelAbleAmount = $payAmount - $cancelAmount;
        } else {
            $cancelAmount = 0;
            $cancelAbleAmount = $payAmount;
        }
        
        $data['result'] = [
            "payAmount" => $payAmount,
            "cancelAmount" => $cancelAmount,
            "cancelAbleAmount" => $cancelAbleAmount
        ];
    } else {
        $data['result'] = [];
        $data['error'] = "결제 내역이 존재하지 않습니다.";
    }
}

// 취소 처리
if ($mode == "cancel") {
    
    $orderno = clean_xss_tags($_POST['orderno'], 1, 1);
    $api_id = clean_xss_tags($_POST['api_id'], 1, 1);
    $api_key = clean_xss_tags($_POST['api_key'], 1, 1);
    $tid = clean_xss_tags($_POST['tid'], 1, 1);
    $bank = clean_xss_tags($_POST['bank'], 1, 1);
    $accountno = clean_xss_tags($_POST['accountno'], 1, 1);
    $accountname = clean_xss_tags($_POST['accountname'], 1, 1);
    $amount = clean_xss_tags($_POST['amount'], 1, 1);

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
        if ($amount > 0) {
            $request_data_array['amount'] = $amount;
        }

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

            // s: 영카트 주문 정보 업데이트
            $updateSql = " update {$g5['g5_shop_order_table']} od_refund_price='{$result_array['cancel_amt']}' where od_id = '$orderno' ";
            sql_query($updateSql);
            // e: 영카트 주문 정보 업데이트

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

echo json_encode($data);
exit;
