<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

include_once(G5_PATH."/cookiepay/cookiepay.constants.php");

// Deprecated - PG명(EX: COOKIEPAY_TS)으로 쿠키페이 연동 아이디와 키를 리턴
// function cookiepay_get_api_accountByPg($default, $params) {
//     $pg = strtolower($params);
//     $ret = [
//         'api_id'  => '', 
//         'api_key' => ''
//     ];
//     $ret['api_id'] = $default["de_{$pg}_cookiepay_id"];
//     $ret['api_key'] = $default["de_{$pg}_cookiepay_key"];
//     return $ret;
// }

// Deprecated - PG명(EX: COOKIEPAY_TS)으로 쿠키페이 연동 수기결제 아이디와 키를 리턴
// function cookiepay_get_api_accountByPg_keyin($default, $params) {
//     $pg = strtolower($params);
//     $ret = [
//         'api_id'  => '', 
//         'api_key' => ''
//     ];
//     $ret['api_id'] = $default["de_{$pg}_cookiepay_id_keyin"];
//     $ret['api_key'] = $default["de_{$pg}_cookiepay_key_keyin"];
//     return $ret;
// }

// Deprecated - 사용 설정된 PG의 쿠키페이 연동 아이디와 키를 리턴
// function cookiepay_get_api_account($params) {
//     $pg = strtolower($params['de_pg_service']);
//     $ret = [
//         'api_id'  => '', 
//         'api_key' => ''
//     ];
//     $ret['api_id'] = $params["de_{$pg}_cookiepay_id"];
//     $ret['api_key'] = $params["de_{$pg}_cookiepay_key"];
//     return $ret;
// }

// Deprecated - 사용 설정된 PG의 쿠키페이 수기결제 연동 아이디와 키를 리턴
// function cookiepay_get_api_account_keyin($params) {
//     $pg = strtolower($params['de_pg_service']);
//     $ret = [
//         'api_id'  => '', 
//         'api_key' => ''
//     ];
//     $ret['api_id'] = $params["de_{$pg}_cookiepay_id_keyin"];
//     $ret['api_key'] = $params["de_{$pg}_cookiepay_key_keyin"];
//     return $ret;
// }

// 결제타입에 따른 쿠키페이 연동 아이디와 키를 리턴
// $pay_type: 1=수기결제, 3=신용카드인증, 5=해외달러결제, 7=해외원화결제
function cookiepay_get_api_account_info($default, $pay_type=3) {
    $pg = strtolower($default['de_pg_service']);

    $ret = [
        'api_id'  => '', 
        'api_key' => ''
    ];
    
	if ($pay_type == 1) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_keyin"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_keyin"];
	} else if ($pay_type == 5) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_global_dollar"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_global_dollar"];
	} else if ($pay_type == 7) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_global_won"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_global_won"];
	} else {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key"];
	}

    return $ret;
}

// 결제타입, PG명(EX: COOKIEPAY_TS)으로 쿠키페이 연동 아이디와 키를 리턴
// $pay_type: 1=수기결제, 3=신용카드인증, 5=해외달러결제, 7=해외원화결제
function cookiepay_get_api_account_info_by_pg($default, $pg, $pay_type=3) {
    $pg = strtolower($pg);

    $ret = [
        'api_id'  => '', 
        'api_key' => ''
    ];

	if ($pay_type == 1) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_keyin"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_keyin"];
	} else if ($pay_type == 5) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_global_dollar"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_global_dollar"];
	} else if ($pay_type == 7) {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id_global_won"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key_global_won"];
	} else {
		$ret['api_id'] = $default["de_{$pg}_cookiepay_id"];
    	$ret['api_key'] = $default["de_{$pg}_cookiepay_key"];
	}

    return $ret;
}

// 결제 로그 기록 ($start_end = 0:시작과 끝 기록 안 함. 1: 시작 기록, 2: 끝 기록, 3: 시작과 끝 모두 기록)
function cookiepay_payment_log($title="", $msg="", $start_end=0) {

	$path = $_SERVER['DOCUMENT_ROOT']."/../logs/".date("Ym");

	if(!is_dir($path)) {
		mkdir($path, 0707, true);
	}

	$path .= "/payment-".date("Ymd").".log";
	$file = fopen($path, "a");

	$log_msg = "";

	if ($start_end == 1 || $start_end == 3) {
		$log_msg .= "----- [".date("Y-m-d H:i:s")."] -----\r\n";
	}

	if ($title) {
		$log_msg .= "({$title}) \r\n";
	}
	
	$log_msg .= "{$msg} \r\n";

	if ($start_end == 2 || $start_end == 3) {
		$log_msg .= "-------------------------------------\r\n";
	}

	fwrite($file, $log_msg);

	fclose($file);
}

// 결제 취소 처리
// $apiId: 쿠키페이 연동 아이디
// $apiKey: 쿠키페이 연동 키
// $tid: 결제 고유 번호
// $bank: 환불계좌 은행코드(가상계좌시 필수)
// $account_no: 환불계좌번호(가상계좌시 필수)
// $account_name: 환불계좌 예금주명(가상계좌시 필수)
function cookipay_cancel_payment($apiId, $apiKey, $tid, $bank='', $account_no='', $account_name='') {
	$ret = [
		'status' => false,
		'msg' => '결제 취소 실패',
		'data' => ''
	];

	$tokenheaders = array(); 
	array_push($tokenheaders, "content-type: application/json; charset=utf-8");

	$token_url = COOKIEPAY_TOKEN_URL;

	$token_request_data = array(
		'pay2_id' => $apiId,
		'pay2_key'=> $apiKey,
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
		array_push($headers, "ApiKey: ".$apiKey);

		$cookiepayments_url = COOKIEPAY_CANCEL_URL;

		$request_data_array = array(
			'tid' => $tid,
			'reason' => '고객취소',
			'bank' => $bank,
			'account_no' => $account_no,
			'account_name' => $account_name
		);

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
			$ret['status'] = true;
			$ret['msg'] = "결제 취소 성공";
			$ret['data'] = $response;
		} else {
			$ret['msg'] = $result_array['cancel_msg'];
		}
	} else {
		$ret['msg'] = "결제 취소 토큰 발급 실패";
	}

	return $ret;
}
