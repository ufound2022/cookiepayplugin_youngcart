<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 쿠키페이 보안 검토(2026-10-02) 항목 6~10에 대한 검증 함수 모음.
 *
 * 이 파일은 신규 파일이며 기존 파일/함수를 수정하지 않는다.
 * 모든 함수는 DB나 네트워크에 접근하지 않는 순수 함수로, 호출부가 조회해 온
 * 값을 받아 "허용/차단"만 판정한다. 단위 테스트 대상은 이 판정 로직이다.
 *
 * 반환 형식은 공통으로 다음과 같다.
 *   ['ok' => bool, 'code' => '식별코드', 'msg' => '사람이 읽는 사유']
 * 일부 함수는 'amount' 등 호출부가 신뢰하고 써야 할 값을 함께 돌려준다.
 */

if (!defined('COOKIEPAY_GUARD_KEYIN_MAX_TRY')) {
    define('COOKIEPAY_GUARD_KEYIN_MAX_TRY', 5); // 수기결제 시도 제한(동일 방문자 기준)
}

/**
 * 공통 반환 헬퍼.
 */
function cookiepay_guard_result($ok, $code, $msg, $extra = array())
{
    return array_merge(array('ok' => (bool) $ok, 'code' => $code, 'msg' => $msg), $extra);
}

/**
 * [항목 6] 정기(구독) 통지로 새 주문을 만들기 전 검증.
 *
 * 현재 result.noti.php 139행 분기는 통지 본문에 RESERVE_ORDERNO 가 있고
 * PAY_CNT >= 2 이기만 하면 원 주문을 복사해 od_status='입금' 주문을 만든다.
 * 이 경로에서는 쿠키페이에 결제 사실을 확인하지 않으므로, 위조 통지만으로
 * 결제완료 주문이 반복 생성된다.
 *
 * @param array $noti      수신한 통지 본문(RESERVE_ORDERNO, PAY_CNT, TID, AMOUNT, RESULTCODE)
 * @param array $verify    쿠키페이 /api/paycert 응답(RESULTCODE, TID, AMOUNT, ORDERNO)
 * @param array $subscribe cookiepay_pg_subscribe_userlist 등록 정보(RESERVE_ORDERNO, AMOUNT)
 */
function cookiepay_guard_subscribe_noti($noti, $verify, $subscribe)
{
    if (empty($noti['RESERVE_ORDERNO'])) {
        return cookiepay_guard_result(false, 'NO_RESERVE_ORDERNO', '정기결제 원 주문번호가 없습니다.');
    }

    if ((int) (isset($noti['PAY_CNT']) ? $noti['PAY_CNT'] : 0) < 2) {
        return cookiepay_guard_result(false, 'NOT_REPEAT_PAYMENT', '2회차 이상 결제가 아닙니다.');
    }

    if (empty($subscribe) || empty($subscribe['RESERVE_ORDERNO'])) {
        return cookiepay_guard_result(false, 'NO_SUBSCRIBE', '등록된 정기결제 정보가 없습니다.');
    }

    // 통지가 가리키는 원 주문이 실제 등록된 정기결제의 원 주문과 같아야 한다.
    if ((string) $subscribe['RESERVE_ORDERNO'] !== (string) $noti['RESERVE_ORDERNO']) {
        return cookiepay_guard_result(false, 'RESERVE_ORDERNO_MISMATCH', '등록된 정기결제의 원 주문번호와 다릅니다.');
    }

    // 핵심: 통지 본문의 RESULTCODE 가 아니라 쿠키페이 재확인 결과를 믿는다.
    if (empty($verify) || !isset($verify['RESULTCODE']) || (string) $verify['RESULTCODE'] !== '0000') {
        return cookiepay_guard_result(false, 'VERIFY_FAILED', '쿠키페이 결제 검증에 실패했습니다.');
    }

    if (empty($noti['TID']) || empty($verify['TID'])
        || (string) $verify['TID'] !== (string) $noti['TID']) {
        return cookiepay_guard_result(false, 'TID_MISMATCH', '검증 응답의 거래번호가 통지와 다릅니다.');
    }

    // 금액도 통지가 아니라 검증 응답 값으로 비교한다.
    if ((int) $verify['AMOUNT'] !== (int) $subscribe['AMOUNT']) {
        return cookiepay_guard_result(false, 'AMOUNT_MISMATCH', '검증 금액이 약정 정기결제 금액과 다릅니다.');
    }

    return cookiepay_guard_result(true, 'OK', '정기결제 통지 검증 통과', array(
        'amount' => (int) $verify['AMOUNT'],
    ));
}

/**
 * [항목 7] 결제 기록(cookiepay_pg_result)으로 주문을 완료해도 되는지 검증.
 *
 * 현재 pgresult.update.php 36행은 브라우저가 보낸 ORDERNO 로 기록을 찾고
 * pay_status 를 보지 않는다. cookiepay.pay.php 의 try_pay 모드가 비로그인
 * 상태에서 브라우저가 보낸 금액·거래번호로 pay_status=0 기록을 만들 수 있으므로,
 * 결제 없이 만든 기록이 그대로 결제 내용으로 쓰인다. 거래번호 재사용도 막지 않는다.
 *
 * @param array      $pg_result       cookiepay_pg_result 한 행
 * @param int        $order_price     서버가 계산한 주문 총액
 * @param string     $session_orderno 방문자 세션의 주문번호(브라우저 입력값 아님)
 * @param int        $tid_used_count  이 TID 로 이미 완료된 주문 수
 */
function cookiepay_guard_pay_record($pg_result, $order_price, $session_orderno, $tid_used_count)
{
    if (empty($pg_result)) {
        return cookiepay_guard_result(false, 'NO_RECORD', '결제 기록이 없습니다.');
    }

    if (empty($session_orderno)) {
        return cookiepay_guard_result(false, 'NO_SESSION_ORDERNO', '세션에 주문번호가 없습니다.');
    }

    // 주문번호는 브라우저가 보낸 값이 아니라 세션 값과 일치해야 한다.
    if ((string) $pg_result['ORDERNO'] !== (string) $session_orderno) {
        return cookiepay_guard_result(false, 'ORDERNO_MISMATCH', '세션의 주문번호와 결제 기록이 다릅니다.');
    }

    // 핵심: 검증까지 끝난(pay_status=1) 기록만 인정한다. 0=시도 는 결제가 아니다.
    if ((int) $pg_result['pay_status'] !== 1) {
        return cookiepay_guard_result(false, 'NOT_VERIFIED', '결제완료로 확인되지 않은 기록입니다.');
    }

    if (empty($pg_result['TID'])) {
        return cookiepay_guard_result(false, 'NO_TID', '거래번호가 없습니다.');
    }

    // 거래번호 하나로 주문 하나만 완료되게 한다.
    if ((int) $tid_used_count > 0) {
        return cookiepay_guard_result(false, 'TID_REUSED', '이미 사용된 거래번호입니다.');
    }

    if ((int) $pg_result['AMOUNT'] !== (int) $order_price) {
        return cookiepay_guard_result(false, 'AMOUNT_MISMATCH', '결제 금액과 주문 금액이 다릅니다.');
    }

    return cookiepay_guard_result(true, 'OK', '결제 기록 검증 통과', array(
        'amount' => (int) $pg_result['AMOUNT'],
        'tid'    => (string) $pg_result['TID'],
    ));
}

/**
 * [항목 8] 정기결제 조회/해지 요청자가 그 정기결제의 주인인지 검증.
 *
 * 현재 cookiepay_subscribe_info.php 8행과 cookiepay.subscribe.ajax.php 8행은
 * empty($is_member) 로 로그인 여부만 본다. 주소의 주문번호를 바꾸면 다른 고객의
 * 정기결제를 조회하거나 해지할 수 있고, 빌링키까지 화면에 출력된다.
 *
 * @param array  $subscribe 정기결제 행(mb_id 포함)
 * @param string $member_id 로그인한 회원 아이디(비회원이면 빈 값)
 */
function cookiepay_guard_subscribe_owner($subscribe, $member_id)
{
    if (empty($member_id)) {
        return cookiepay_guard_result(false, 'NOT_LOGGED_IN', '로그인이 필요합니다.');
    }

    if (empty($subscribe)) {
        return cookiepay_guard_result(false, 'NO_SUBSCRIBE', '정기결제 정보가 없습니다.');
    }

    // 소유자가 비어 있는 행은 누구의 것도 아니므로 열어 주지 않는다.
    if (empty($subscribe['mb_id'])) {
        return cookiepay_guard_result(false, 'NO_OWNER', '정기결제에 소유자가 없습니다.');
    }

    if ((string) $subscribe['mb_id'] !== (string) $member_id) {
        return cookiepay_guard_result(false, 'NOT_OWNER', '본인의 정기결제가 아닙니다.');
    }

    return cookiepay_guard_result(true, 'OK', '정기결제 소유자 확인 통과');
}

/**
 * [항목 9] 수기결제(키인) 요청을 받아도 되는지 검증.
 *
 * 현재 cookiepay.pay.php 의 keyin_pay 모드는 로그인 확인이 없고, 금액과
 * 주문번호를 요청 본문에서 그대로 받는다. 도난 카드번호의 유효성 확인
 * (카드 테스팅) 통로로 쓰일 수 있다.
 *
 * 수기결제는 g5_shop_order 가 만들어지기 전에 일어난다. 결제가 성공해야
 * pgresult.php 가 opener 의 주문서를 제출하고 그때 주문이 생성된다. 따라서 이 시점의
 * "결제 대기 주문"은 cookiepay_shop_order 에 임시 저장된 행이다.
 *
 * 가장 강한 방어는 요청의 ORDERNO 가 방문자 세션의 주문번호와 같은지 보는 것이다.
 * 세션 주문번호는 서버가 들고 있으므로 외부에서 지정할 수 없다.
 *
 * 금액은 요청값을 버리고 임시 주문의 금액을 돌려준다. 호출부는 반환된 amount 를 써야 한다.
 * (임시 주문의 값도 브라우저가 보낸 것이지만, 최종 금액은 주문 확정 단계에서
 *  서버가 계산한 주문 총액과 다시 대조된다. 항목 7 검증 참고)
 *
 * @param string $member_id       로그인한 회원 아이디
 * @param string $session_orderno 방문자 세션의 주문번호(ss_order_id)
 * @param array  $pending_order   cookiepay_shop_order 임시 주문 행
 * @param array  $request         요청 본문(ORDERNO, AMOUNT 등)
 * @param int    $recent_attempts 최근 시간창 안의 동일 방문자 수기결제 시도 횟수
 * @param int    $max_attempts    허용 시도 횟수
 */
function cookiepay_guard_keyin_request($member_id, $session_orderno, $pending_order, $request, $recent_attempts, $max_attempts = COOKIEPAY_GUARD_KEYIN_MAX_TRY)
{
    if (empty($member_id)) {
        return cookiepay_guard_result(false, 'NOT_LOGGED_IN', '로그인이 필요합니다.');
    }

    if ((int) $recent_attempts >= (int) $max_attempts) {
        return cookiepay_guard_result(false, 'TOO_MANY_ATTEMPTS', '수기결제 시도 횟수를 초과했습니다.');
    }

    if (empty($session_orderno)) {
        return cookiepay_guard_result(false, 'NO_SESSION_ORDERNO', '세션에 주문번호가 없습니다.');
    }

    // 핵심: 결제할 주문번호는 요청이 아니라 세션이 정한다.
    if (empty($request['ORDERNO']) || (string) $request['ORDERNO'] !== (string) $session_orderno) {
        return cookiepay_guard_result(false, 'ORDERNO_MISMATCH', '세션의 주문번호와 요청이 다릅니다.');
    }

    if (empty($pending_order) || empty($pending_order['od_id'])) {
        return cookiepay_guard_result(false, 'NO_ORDER', '결제 대기 주문이 없습니다.');
    }

    if ((string) $pending_order['od_id'] !== (string) $session_orderno) {
        return cookiepay_guard_result(false, 'ORDERNO_MISMATCH', '세션의 주문번호와 대기 주문이 다릅니다.');
    }

    if (empty($pending_order['mb_id']) || (string) $pending_order['mb_id'] !== (string) $member_id) {
        return cookiepay_guard_result(false, 'NOT_OWNER', '본인의 주문이 아닙니다.');
    }

    // 결제할 금액. result.noti.php 가 임시 주문에서 쓰는 공식과 같게 맞춘다.
    $payable = (int) $pending_order['od_price']
             + (int) $pending_order['od_send_cost']
             + (int) $pending_order['od_send_cost2']
             - (int) $pending_order['od_temp_point']
             - (int) $pending_order['od_send_coupon'];

    if ($payable <= 0) {
        return cookiepay_guard_result(false, 'NOTHING_TO_PAY', '결제할 금액이 없습니다.');
    }

    // 금액은 요청값이 아니라 서버에 저장된 주문 금액을 쓴다.
    return cookiepay_guard_result(true, 'OK', '수기결제 요청 검증 통과', array(
        'amount' => $payable,
    ));
}

/**
 * [항목 10] 결제 검증 후 주문에 기록할 금액을 결정한다.
 *
 * 현재 result.noti.php 는 /api/paycert 로 거래번호의 실재 여부는 확인하지만,
 * 이후 770행에서 od_receipt_price 를 통지 본문의 AMOUNT 로 넣는다. 실제 결제된
 * 적은 금액의 거래번호를 큰 주문의 통지에 재사용하면 차액만큼 덜 내고 상품을 받는다.
 *
 * @param array  $verify      쿠키페이 /api/paycert 응답(RESULTCODE, ORDERNO, AMOUNT)
 * @param array  $noti        수신한 통지 본문(ORDERNO, AMOUNT)
 * @param string $order_id    주문번호
 * @param int    $order_price 서버가 계산한 주문 총액
 */
function cookiepay_guard_verified_amount($verify, $noti, $order_id, $order_price)
{
    if (empty($verify) || !isset($verify['RESULTCODE']) || (string) $verify['RESULTCODE'] !== '0000') {
        return cookiepay_guard_result(false, 'VERIFY_FAILED', '쿠키페이 결제 검증에 실패했습니다.');
    }

    if (empty($verify['ORDERNO']) || (string) $verify['ORDERNO'] !== (string) $order_id) {
        return cookiepay_guard_result(false, 'ORDERNO_MISMATCH', '검증 응답의 주문번호가 주문과 다릅니다.');
    }

    // 통지 금액과 검증 금액이 다르면 통지가 조작된 것이다.
    if (isset($noti['AMOUNT']) && (int) $noti['AMOUNT'] !== (int) $verify['AMOUNT']) {
        return cookiepay_guard_result(false, 'NOTI_TAMPERED', '통지 금액이 검증 금액과 다릅니다.');
    }

    // 결제완료로 처리할 금액은 검증 응답 값이며, 주문 총액과 같아야 한다.
    if ((int) $verify['AMOUNT'] !== (int) $order_price) {
        return cookiepay_guard_result(false, 'AMOUNT_MISMATCH', '검증 금액이 주문 금액과 다릅니다.');
    }

    return cookiepay_guard_result(true, 'OK', '검증 금액 확인 통과', array(
        'amount' => (int) $verify['AMOUNT'],
    ));
}
