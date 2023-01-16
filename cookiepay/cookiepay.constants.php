<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
if (!defined('G5_USE_SHOP') || !G5_USE_SHOP) return;

if (!defined('COOKIEPAY_USE_HTTPS')) {
    define('COOKIEPAY_USE_HTTPS', true); // https 사용시 true
}

// Tables
define('COOKIEPAY_SESSION', 'cookiepay_session'); // 세션 임시 저장
define('COOKIEPAY_PG_RESULT', 'cookiepay_pg_result'); // 결제 결과
define('COOKIEPAY_PG_VERIFY', 'cookiepay_pg_verify'); // 결제 검증 결과
define('COOKIEPAY_PG_CANCEL', 'cookiepay_pg_cancel'); // 결제 취소 결과

// 쿠키페이 연동 PG
define('COOKIEPAY_PG', 
[
    "COOKIEPAY_TS" => "토스페이", 
    "COOKIEPAY_KI" => "이지페이", 
    "COOKIEPAY_KW" => "키움페이", 
    "COOKIEPAY_DN" => "다날", 
    "COOKIEPAY_AL" => "모빌페이", 
    "COOKIEPAY_WP" => "웰컴페이"
]);

$cookiepayProtocol = COOKIEPAY_USE_HTTPS === true ? "https://" : "http://";

// 쿠키페이 PG 가입신청 링크
define('COOKIEPAY_JOIN_URL', "{$cookiepayProtocol}www.cookiepayments.com/join/apply");

// 쿠키페이 실결제 url
define('COOKIEPAY_PAY_URL', "{$cookiepayProtocol}www.cookiepayments.com/pay/ready");

// 쿠키페이 테스트결제 url
define('COOKIEPAY_TESTPAY_URL', "{$cookiepayProtocol}cookiepay.iroholdings.co.kr/pay/ready");

// 쿠키페이 결제 응답 url
$cookiepayReturnUrl = COOKIEPAY_USE_HTTPS === true ? str_replace("http://", "https://", G5_URL) : G5_URL;
define('COOKIEPAY_RETURN_URL', $cookiepayReturnUrl."/cookiepay/pgresult.php");

// 쿠키페이 토큰 발행 url
define('COOKIEPAY_TOKEN_URL', "{$cookiepayProtocol}www.cookiepayments.com/payAuth/token");

// 쿠키페이 결제 검증 url
define('COOKIEPAY_VERIFY_URL', "{$cookiepayProtocol}www.cookiepayments.com/api/paycert");

// 쿠키페이 결제 취소 url
define('COOKIEPAY_CANCEL_URL', "{$cookiepayProtocol}www.cookiepayments.com/api/cancel");

// 쿠키페이 전표 출력 url
define('COOKIEPAY_RECEIPT_URL', "{$cookiepayProtocol}www.cookiepayments.com/api/receipt");

// 쿠키페이 결제 내역 url
define('COOKIEPAY_SEARCH_URL', "{$cookiepayProtocol}www.cookiepayments.com/api/paysearch");

// 쿠키페이 플러그인 경로
define('COOKIEPAY_PATH', G5_PATH."/cookiepay");

// 쿠키페이 플러그인 url
define('COOKIEPAY_URL', "/cookiepay");
