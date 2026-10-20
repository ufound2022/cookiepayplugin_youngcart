<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 쿠키페이 결제 검증(/payAuth/token → /api/paycert) 호출 헬퍼.
 *
 * 신규 파일이며 기존 파일/함수를 수정하지 않는다. result.noti.php 의 일반 결제
 * 경로에는 같은 호출이 인라인으로 이미 있지만, 정기(구독) 경로에는 없어서
 * 보안 검토 항목 6을 막으려면 재사용 가능한 형태가 필요했다.
 *
 * 판정은 하지 않는다. 쿠키페이 응답을 그대로 돌려주고, 판정은
 * cookiepay.guard.php 의 검증 함수가 맡는다.
 *
 * @param  string $apiId  쿠키페이 연동 아이디
 * @param  string $apiKey 쿠키페이 연동 키
 * @param  string $tid    조회할 거래 고유번호
 * @return array  paycert 응답 배열. 실패 시 RESULTCODE 가 '0000' 이 아니다.
 */
function cookiepay_verify_tid($apiId, $apiKey, $tid)
{
    if (empty($apiId) || empty($apiKey)) {
        return array('RESULTCODE' => '9999', 'RESULTMSG' => '연동 정보 없음');
    }

    if (empty($tid)) {
        return array('RESULTCODE' => '9999', 'RESULTMSG' => '거래번호 없음');
    }

    // 토큰 발급
    $req_json = json_encode(array(
        'pay2_id'  => $apiId,
        'pay2_key' => $apiKey,
    ), JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, COOKIEPAY_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('content-type: application/json; charset=utf-8'));
    $tokenResJson = curl_exec($ch);
    curl_close($ch);

    $tokenRes = json_decode($tokenResJson, true);

    if (!isset($tokenRes['RTN_CD']) || $tokenRes['RTN_CD'] != '0000') {
        @cookiepay_payment_log("[검증] 토큰 발급 실패", $tokenResJson, 3);
        return array('RESULTCODE' => '9999', 'RESULTMSG' => '토큰 발급 실패');
    }

    // 결제 검증
    $request_data = json_encode(array('tid' => $tid), JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, COOKIEPAY_VERIFY_URL);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'content-type: application/json; charset=utf-8',
        'ApiKey: ' . $apiKey,
        'TOKEN: ' . $tokenRes['TOKEN'],
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    @cookiepay_payment_log("[검증] paycert 응답 (tid: {$tid})", $response, 3);

    $verify = json_decode($response, true);

    if (!is_array($verify)) {
        return array('RESULTCODE' => '9999', 'RESULTMSG' => '검증 응답 없음');
    }

    return $verify;
}
