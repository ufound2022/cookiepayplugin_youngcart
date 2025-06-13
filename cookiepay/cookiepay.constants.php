<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
if (!defined('G5_USE_SHOP') || !G5_USE_SHOP) return;

if (!defined('COOKIEPAY_USE_HTTPS')) {
    define('COOKIEPAY_USE_HTTPS', true); // https 사용시 true
}

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

