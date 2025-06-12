<?php
$sub_menu = "400900";
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 로그인 체크
if (empty($is_member)) {
    exit;
}

//$cookiepay = json_decode(file_get_contents('php://input'), true);
$reserveid = $_POST['reserveid'];
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

   # 빌링키 폐기(S)
    $cookiepayments_url_dispose = COOKIEPAY_BILLKEY_DISPOSE_URL;

    $request_data_array_dispose = array(
                        'API_ID' => "{$cookiepayApi['api_id']}",
                        'BILLKEY' => "{$billkey}",
    );

    $cookiepayments_json_dispose = json_encode($request_data_array_dispose, TRUE);

    $ch = curl_init(); // curl 초기화

    curl_setopt($ch,CURLOPT_URL, $cookiepayments_url_dispose);
    curl_setopt($ch,CURLOPT_POST, false);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepayments_json_dispose);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response_dispose = curl_exec($ch);
    curl_close($ch);


    $result_dispose_decode_array = json_decode($response_dispose, true);
    # 빌링키 폐기(E)

    $cookiepayments_url = COOKIEPAY_SCHEDULE_CANCEL_URL;
    
    $request_data_array = array(
                            'API_ID' => "{$cookiepayApi['api_id']}",
                            'RESERVE_ID' => "{$reserveid}",
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

    # 암호화 전문 복호화하기(S)

    /*
        string(203) "{ "RESULTCODE": "0000", "RESULTMSG": "성공", "ENC_DATA": "4cH9f5+UybM2fog8K7DrGLBF9SC+6KUPARsdxnYwD/h/hF5FgM64Ah0uISQQibQI/GdPRZhOGpC30OMyf/eloVTeBgMqtH5Sl6bT2LoWvcn39KjfvPZrtaP/gjb8OS+W" }"
    */

    if($result_decode_array['RESULTCODE'] == "E103") { 

        $result_array = array();
        $result_array['RESULTCODE'] = $result_decode_array['RESULTCODE'];
        $result_array['RESULTMSG'] = $result_decode_array['RESULTMSG'];

        $sql = "update ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." 
            set RESERVE_SCHEDULE_CANCEL_DATE = '".substr($result_array['RESULTMSG'],0,19)."', pay_status='2' 
        where RESERVE_ID = '".$_POST['reserveid']."' limit 1 ";
        sql_query($sql);

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
    if($result_array['RESULTCODE'] == "0000") { 
        // RESERVE_SCHEDULE_CANCEL_DATE > 날짜 업데이트
        // pay_status > 필드값 2로 업데이트

        $sql = "update ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." 
                    set RESERVE_SCHEDULE_CANCEL_DATE = '".date('Y-m-d H:i:s')."', pay_status='2' 
                where RESERVE_ID = '".$_POST['reserveid']."' limit 1 ";
        sql_query($sql);
    }


    echo $response;
    exit;

    # 암호화 전문 복호화하기 (E)
   
    
}

?>

