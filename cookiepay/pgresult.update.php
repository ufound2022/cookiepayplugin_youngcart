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

// 가상계좌 발급은 아직 입금 전이라 pgresult.php 가 pay_status 를 0 으로 남긴다.
// 여기서 막으면 정상 가상계좌 주문이 전부 실패하므로 '주문'(미결제) 상태로 통과시킨다.
// 위조된 발급 기록으로 미결제 주문이 생기는 것은 무통장 주문과 같은 수준이라 피해가 없고,
// 실제 입금 통지는 result.noti.php 의 항목 10 검증이 따로 막는다.
$guardVaccount = (is_array($cookiepayPgResult)
    && (int) $cookiepayPgResult['pay_status'] === 0
    && !empty($cookiepayPgResult['ACCOUNTNO'])
    && !empty($cookiepayPgResult['CARDNAME']));

if ($guardVaccount) {
    // 진단용 기록. 발급 시점의 paycert 응답코드가 무엇인지 로그로 남긴다.
    $guardVerifyRow = sql_fetch("
        select RESULTCODE
        from ".COOKIEPAY_PG_VERIFY."
        where ORDERNO = '" . sql_real_escape_string((string) $cookiepayPgResult['ORDERNO']) . "'
        order by `id` desc
        limit 1
    ");
    $guardVerifyCode = isset($guardVerifyRow['RESULTCODE']) ? $guardVerifyRow['RESULTCODE'] : '(없음)';
    @cookiepay_payment_log("결제기록 검증", "가상계좌 발급건 통과 / ORDERNO ".$postOrderno." / paycert RESULTCODE ".$guardVerifyCode, 3);
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

$guardRet = cookiepay_guard_pay_record($cookiepayPgResult, $guardOrderPrice, $guardSessionOrderno, $guardTidUsed, $guardVaccount);

if (!$guardRet['ok']) {
    @cookiepay_payment_log("결제기록 검증 차단 ({$guardRet['code']})", $guardRet['msg']." / ORDERNO ".$postOrderno, 3);
    if(function_exists('add_order_post_log')) add_order_post_log($guardRet['msg']);

    // alert() 를 URL 없이 부르면 history.go(-1) 로 돌아가는데, 돌아간 pgresult.php?mode=after
    // 가 주문서를 자동 제출해 다시 이곳으로 들어온다. 무한 알림 루프가 되므로 갈 곳을 지정한다.
    alert($guardRet['msg'], G5_SHOP_URL.'/cart.php');
    exit;
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
