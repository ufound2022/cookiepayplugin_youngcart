<?php
include_once('../shop/_common.php');

// PG 결제 결과 영카트 변수에 매칭

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

// $cookiepayApi = cookiepay_get_api_account_info($default, 3);

$pay_type = '';
$tno = '';
$amount = '';
$app_time = '';
$app_no = '';
$bank_name = $bankname = '';
$depositor = '';
$account = '';
$commid = '';
$mobile_no = '';
$card_name = '';
$escw_yn = '';
$od_other_pay_type = '';
$cash_yn = '';
$cash_authno = '';
$cash_tr_code = '';

$postOrderno = isset($_POST['ORDERNO']) ? clean_xss_tags($_POST['ORDERNO'], 1, 1) : '';

// s: cookiepay-plugin v1.2
if (!$postOrderno && isset($_POST['od_id']))
{
    $postOrderno = isset($_POST['od_id']) ? clean_xss_tags($_POST['od_id'], 1, 1) : '';
}
// e: cookiepay-plugin v1.2

$sql = " select * from ".COOKIEPAY_PG_RESULT." where ORDERNO='" . sql_real_escape_string((string) $postOrderno) . "' ";
$cookiepayPgResult = sql_fetch($sql);

// s: cookiepay-plugin > 보안검토 항목7 > v1.3 > 261014
// cookiepay.pay.php 의 try_pay 모드는 로그인 없이 호출할 수 있고, 브라우저가 보낸
// 금액·거래번호를 pay_status=0(결제 시도)으로 저장한다. 위 조회는 pay_status 를 보지
// 않으므로 결제한 적 없는 시도 기록이 그대로 결제 내용이 된다. 거래번호 재사용도 막지 않는다.
// 검증이 끝난(pay_status=1) 기록만, 세션의 주문번호와 일치할 때만, 한 번만 인정한다.
require_once G5_PATH."/cookiepay/cookiepay.guard.php";

$guardSessionOrderno = isset($_SESSION['ss_order_id']) ? $_SESSION['ss_order_id'] : get_session('ss_order_id');

// 가상계좌는 발급 시점에 아직 입금 전이라 pgresult.php 가 pay_status 를 0 으로 남긴다.
// 이 경우는 쿠키페이 검증(cookiepay_pg_verify) 성공 기록이 있는지로 진짜 발급건을 가린다.
// 검증 기록은 서버에서 paycert 를 호출해야만 생기므로 외부에서 위조할 수 없다.
if (is_array($cookiepayPgResult)
    && (int) $cookiepayPgResult['pay_status'] === 0
    && !empty($cookiepayPgResult['ACCOUNTNO'])
    && !empty($cookiepayPgResult['CARDNAME'])) {

    $guardVerifyRow = sql_fetch("
        select RESULTCODE
        from ".COOKIEPAY_PG_VERIFY."
        where ORDERNO = '" . sql_real_escape_string((string) $cookiepayPgResult['ORDERNO']) . "'
        order by `id` desc
        limit 1
    ");

    if (isset($guardVerifyRow['RESULTCODE']) && (string) $guardVerifyRow['RESULTCODE'] === '0000') {
        // 검증을 통과한 가상계좌 발급건이므로 검증 완료로 본다.
        $cookiepayPgResult['pay_status'] = 1;
        @cookiepay_payment_log("결제기록 검증", "가상계좌 발급건 검증 통과 / ORDERNO ".$postOrderno, 3);
    }
}

// 이 거래번호가 다른 주문에 이미 쓰였는지 확인한다(거래번호 재사용 차단).
// 같은 주문을 다시 제출한 경우는 재사용이 아니므로 현재 주문번호는 제외한다.
$guardTidUsed = 0;
if (is_array($cookiepayPgResult) && !empty($cookiepayPgResult['TID'])) {
    $guardTidRow = sql_fetch("
        select count(*) as cnt
        from {$g5['g5_shop_order_table']}
        where od_tno = '" . sql_real_escape_string((string) $cookiepayPgResult['TID']) . "'
          and od_id != '" . sql_real_escape_string((string) $guardSessionOrderno) . "'
    ");
    $guardTidUsed = (int) $guardTidRow['cnt'];
}

// 주문 총액은 호출부(orderformupdate.php)가 계산해 둔 값을 쓴다.
$guardOrderPrice = isset($order_price) ? (int) $order_price : 0;

$guardRet = cookiepay_guard_pay_record($cookiepayPgResult, $guardOrderPrice, $guardSessionOrderno, $guardTidUsed);

if (!$guardRet['ok']) {
    @cookiepay_payment_log("결제기록 검증 차단 ({$guardRet['code']})", $guardRet['msg']." / ORDERNO ".$postOrderno, 3);
    if(function_exists('add_order_post_log')) add_order_post_log($guardRet['msg']);
    alert($guardRet['msg']);
}
// e: cookiepay-plugin > 보안검토 항목7 > v1.3 > 261014

if ($cookiepayPgResult) {
    $pay_type = $cookiepayPgResult['PAYMETHOD']; // paymethod

    $tno = $cookiepayPgResult['TID']; // 거래 고유 번호
    $amount = $cookiepayPgResult['AMOUNT']; // 실제 거래 금액
    $app_time = $cookiepayPgResult['ACCEPTDATE']; // 승인 시간
    $app_no = $cookiepayPgResult['ACCEPTNO']; // 승인 번호
    $bank_name = $bankname = $cookiepayPgResult['CARDNAME']; // 은행명
    $depositor = $cookiepayPgResult['RECEIVERNAME']; // 입금할 계좌 예금주
    $account = $cookiepayPgResult['ACCOUNTNO']; // 입금할 계좌 번호
    $commid = ''; // 통신사 코드
    $mobile_no = ''; // 휴대폰 번호
    $card_name = $cookiepayPgResult['CARDCODE']; // 은행코드
    $escw_yn = ''; // 에스크로 여부
    $od_other_pay_type = ''; // 간편결제유형

    $cash_yn = ''; // 현금영수증 등록여부
    $cash_authno = ''; // 현금 영수증 승인 번호
    $cash_tr_code = ''; // 현금영수증 등록구분

    $reserve_id = $cookiepayPgResult['RESERVE_ID'];
}
