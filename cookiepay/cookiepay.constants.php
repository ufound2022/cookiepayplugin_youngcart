<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
if (!defined('G5_USE_SHOP') || !G5_USE_SHOP) return;

if (!defined('COOKIEPAY_USE_HTTPS')) {
    define('COOKIEPAY_USE_HTTPS', true); // https 사용시 true
}

// s: cookiepay-plugin > 통지 서명 검증 > v2.0 > 261026
// 통지 서명(HMAC) 검증 단계.
//
//   'off'     검사하지 않는다
//   'log'     검사하되 차단하지 않고 로그만 남긴다
//   'enforce' 불일치를 401 로 차단한다. 배포 기본값
//
// 설치 직후 통지가 전부 실패한다면 먼저 'log' 로 내려 원인을 확인한다.
// 차단되지 않으므로 결제 처리에는 영향이 없고 로그에 사유가 남는다.
//
//   NO_SIGNATURE        헤더가 도달하지 않았다. 쿠키페이에 해당 연동 계정과
//                       통지 종류에 서명이 적용됐는지 확인한다. 앞단의
//                       WAF·프록시가 X- 헤더를 걸러내는 환경에서도 이 코드가
//                       나온다 — 로그의 headers= 에 X-Cookiepay-* 가
//                       보이는지로 갈린다
//   SIGNATURE_MISMATCH  키가 다르다. 로그의 keyfp= 와 pairs= 로 대조한다
//   TIMESTAMP_SKEW      양쪽 서버 시각 차이가 ±300초를 넘는다
if (!defined('COOKIEPAY_NOTI_SIGN_MODE')) {
    define('COOKIEPAY_NOTI_SIGN_MODE', 'enforce');
}

// 전송 시각 허용 오차(초). 양쪽 서버 시각이 NTP 로 맞춰져 있어야 한다.
if (!defined('COOKIEPAY_NOTI_SIGN_TOLERANCE')) {
    define('COOKIEPAY_NOTI_SIGN_TOLERANCE', 300);
}
// e: cookiepay-plugin > 통지 서명 검증 > v2.0 > 261026

$cookiepayIsTestPayment = isset($default['de_card_test']) && !empty($default['de_card_test']) ? $default['de_card_test'] : 0;

// Tables
define('COOKIEPAY_SESSION', 'cookiepay_session'); // 세션 임시 저장
define('COOKIEPAY_PG_RESULT', 'cookiepay_pg_result'); // 결제 결과
define('COOKIEPAY_PG_VERIFY', 'cookiepay_pg_verify'); // 결제 검증 결과
define('COOKIEPAY_PG_CANCEL', 'cookiepay_pg_cancel'); // 결제 취소 결과

define('COOKIEPAY_PG_SUBSCRIBE_RESULT', 'cookiepay_pg_subscribe_result'); // 정기(구독)결제관리
define('COOKIEPAY_PG_SUBSCRIBE_USERLIST', 'cookiepay_pg_subscribe_userlist'); // 정기(구독)등록고객

// s: cookiepay-plugin v1.2
define('COOKIEPAY_SHOP_ORDER', 'cookiepay_shop_order'); // 주문정보 임시 저장
// e: cookiepay-plugin v1.2

// 쿠키페이 연동 PG
define('COOKIEPAY_PG', 
[
    "COOKIEPAY_TS" => "토스페이", 
    "COOKIEPAY_KI" => "이지페이", 
    "COOKIEPAY_KW" => "키움페이", 
    "COOKIEPAY_DN" => "다날", 
    "COOKIEPAY_AL" => "모빌페이", 
    "COOKIEPAY_WP" => "웰컴페이",
    "COOKIEPAY_PN" => "페이누리",
]);

$cookiepayProtocol = COOKIEPAY_USE_HTTPS === true ? "https://" : "http://";

$cookiepayReturnUrl = COOKIEPAY_USE_HTTPS === true ? str_replace("http://", "https://", G5_URL) : G5_URL;

if ($cookiepayIsTestPayment == 0) { // 실결제
    $cookiepayPaymentsUrl = "{$cookiepayProtocol}www.cookiepayments.com";
} else { // 테스트결제
    $cookiepayPaymentsUrl = "{$cookiepayProtocol}sandbox.cookiepayments.com";
}

// 쿠키페이 결제 응답 url
define('COOKIEPAY_RETURN_URL', $cookiepayReturnUrl."/cookiepay/pgresult.php");

// 쿠키페이 PG 가입신청 링크
define('COOKIEPAY_JOIN_URL', "https://www.cookiepayments.com/join/apply");

// 쿠키페이 플러그인 경로
define('COOKIEPAY_PATH', G5_PATH."/cookiepay");

// 쿠키페이 플러그인 url
define('COOKIEPAY_URL', "/cookiepay");

// 쿠키페이 실결제 url
define('COOKIEPAY_PAY_URL', "{$cookiepayPaymentsUrl}/pay/ready");

// 쿠키페이 테스트결제 url
define('COOKIEPAY_TESTPAY_URL', "{$cookiepayPaymentsUrl}/pay/ready");

// 쿠키페이 수기결제 실결제 url
define('COOKIEPAY_KEYIN_URL', "{$cookiepayPaymentsUrl}/keyin/payment");

// 쿠키페이 수기결제 테스트결제 url
define('COOKIEPAY_TESTKEYIN_URL', "{$cookiepayPaymentsUrl}/keyin/payment");

// 쿠키페이 토큰 발행 url
define('COOKIEPAY_TOKEN_URL', "{$cookiepayPaymentsUrl}/payAuth/token");

// 쿠키페이 결제 검증 url
define('COOKIEPAY_VERIFY_URL', "{$cookiepayPaymentsUrl}/api/paycert");

// 쿠키페이 결제 취소 url
define('COOKIEPAY_CANCEL_URL', "{$cookiepayPaymentsUrl}/api/cancel");

// 쿠키페이 전표 출력 url
define('COOKIEPAY_RECEIPT_URL', "{$cookiepayPaymentsUrl}/api/receipt");

// 쿠키페이 결제 내역 url
define('COOKIEPAY_SEARCH_URL', "{$cookiepayPaymentsUrl}/api/paysearch");

// 쿠키페이 정기(구독) 반복결제 재결제
define('COOKIEPAY_SCHEDULE_REQUEST_PAYMENT', "{$cookiepayPaymentsUrl}/Subscribe/recurrence_request_payment");

## 영카트 플러그인 > 정기(구독) > S

// 쿠키페이 비인증정기 실결제 url
define('COOKIEPAY_SUBSCRIBE_URL', "{$cookiepayPaymentsUrl}/Subscribe/billkeygen");

// 쿠키페이 비인증정기 테스트결제 url
define('COOKIEPAY_TESTSUBSCRIBE_URL', "{$cookiepayPaymentsUrl}/Subscribe/billkeygen");

// 쿠키페이 정기(구독) 반복결제 해지
define('COOKIEPAY_SCHEDULE_CANCEL_URL', "{$cookiepayPaymentsUrl}/Subscribe/recurrence_schedule_cancel");

// 쿠키페이 정기(구독) 빌링키 폐기
define('COOKIEPAY_BILLKEY_DISPOSE_URL', "{$cookiepayPaymentsUrl}/Subscribe/billingkeycancel");

// 암호화 전문 복호화 하기
define('COOKIEPAY_EDI_DECRYPT_URL', "{$cookiepayPaymentsUrl}/EdiAuth/cookiepay_edi_decrypt");

## 영카트 플러그인 > 정기(구독) > E

