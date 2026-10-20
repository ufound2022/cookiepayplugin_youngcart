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

    // 실패 통지는 주문을 만들지 않는다. 결제가 일어나지 않았으니 paycert 가 승인을
    // 확인해 줄 수도 없다. 그런데 이 실패 이력(pay_status=3)이 있어야 관리자 화면에
    // 재결제 버튼이 뜨므로, 기록까지 막으면 실패한 회차를 되살릴 방법이 없어진다.
    // 등록된 정기결제의 것인지는 위에서 이미 확인했으므로 기록만 허용한다.
    if (!isset($noti['RESULTCODE']) || (string) $noti['RESULTCODE'] !== '0000') {
        return cookiepay_guard_result(true, 'RECORD_ONLY', '실패 통지이므로 이력만 기록합니다.', array(
            'amount'      => 0,
            'record_only' => true,
        ));
    }

    // 여기부터는 성공을 주장하는 통지다. 주문이 만들어지므로 반드시 재확인한다.
    // 통지 본문의 RESULTCODE 가 아니라 쿠키페이 응답을 믿는다.
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
        'amount'      => (int) $verify['AMOUNT'],
        'record_only' => false,
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
 * 가상계좌 발급은 예외다. 발급 시점에는 아직 입금 전이라 결제가 일어나지 않았고
 * pgresult.php 가 pay_status 를 0 으로 남긴다. 이때 주문은 '주문'(미결제) 상태로
 * 만들어져야 하며, 막으면 정상 가상계좌 주문이 전부 실패한다.
 * 위조된 발급 기록으로 미결제 주문이 생기는 것은 무통장 주문과 같은 수준이라
 * 실질적 피해가 없고, 실제 입금 통지는 항목 10 검증이 따로 막는다.
 *
 * @param array      $pg_result       cookiepay_pg_result 한 행
 * @param int        $order_price     서버가 계산한 주문 총액
 * @param string     $session_orderno 방문자 세션의 주문번호(브라우저 입력값 아님)
 * @param int        $tid_used_count  이 TID 로 이미 완료된 주문 수
 * @param bool       $vaccount_issue  가상계좌 발급 단계인가(입금 전)
 */
function cookiepay_guard_pay_record($pg_result, $order_price, $session_orderno, $tid_used_count, $vaccount_issue = false)
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
    // 가상계좌 발급은 아직 입금 전이므로 이 조건에서 제외한다.
    if (!$vaccount_issue && (int) $pg_result['pay_status'] !== 1) {
        return cookiepay_guard_result(false, 'NOT_VERIFIED', '결제완료로 확인되지 않은 기록입니다.');
    }

    // 가상계좌 발급 단계에는 거래번호가 아직 없을 수 있다.
    if (!$vaccount_issue && empty($pg_result['TID'])) {
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
 * [취소 통지] 취소 통지를 반영해도 되는지 검증한다.
 *
 * 취소 통지는 결제 통지와 payload 모양이 전혀 다르다(소문자 키).
 *   {"noti_type":"cancel","paymethod":"CARD","orderno":"...",
 *    "cancel_amount":"1250","tid":"...","cancel_date":"20261019112952"}
 *
 * 통지 주소는 누구나 호출할 수 있으므로 통지만 믿고 반영하면 외부에서 아무 주문이나
 * 환불 기록을 남길 수 있다. 그래서 쿠키페이에 거래를 다시 묻는다.
 *
 * 다만 취소 여부 자체는 /api/paycert 로 확인할 수 없다. 이 API 는 TID 로 조회하는데
 * 결제건과 취소건의 TID 가 같고 조회가 오름차순이라 항상 첫 결제건이 돌아온다.
 * 그래서 취소된 거래도 CANCELDATE 가 빈 값이다(2026-10-19 실측 확인).
 * 따라서 paycert 는 "그 거래가 실재하고 이 주문의 것인가" 확인에만 쓰고,
 * 취소 사실은 통지와 TID 일치로 받아들인다.
 *
 * 남는 위험: 원 결제의 TID 를 아는 사람은 자기 주문에 가짜 환불 기록을 남길 수 있다.
 * TID 는 추측할 수 없고 본인 주문에서만 알 수 있으므로 남의 주문은 건드릴 수 없고,
 * 자기 주문에 환불 기록이 남는 것은 공격자에게 이득이 없다.
 * 근본 대책은 통지 주소의 발신 IP 제한이며 쿠키페이에 요청해야 한다.
 *
 * @param array $noti      취소 통지 본문(소문자 키 그대로)
 * @param array $verify    쿠키페이 /api/paycert 응답
 * @param array $pg_result cookiepay_pg_result 의 원 결제 기록
 * @param array $order     g5_shop_order 행(od_receipt_price, od_refund_price)
 */
function cookiepay_guard_cancel_noti($noti, $verify, $pg_result, $order)
{
    if (empty($noti['orderno'])) {
        return cookiepay_guard_result(false, 'NO_ORDERNO', '취소 통지에 주문번호가 없습니다.');
    }

    if (empty($noti['tid'])) {
        return cookiepay_guard_result(false, 'NO_TID', '취소 통지에 거래번호가 없습니다.');
    }

    $amount = (int) (isset($noti['cancel_amount']) ? $noti['cancel_amount'] : 0);
    if ($amount <= 0) {
        return cookiepay_guard_result(false, 'BAD_AMOUNT', '취소 금액이 올바르지 않습니다.');
    }

    if (empty($pg_result)) {
        return cookiepay_guard_result(false, 'NO_RECORD', '원 결제 기록이 없습니다.');
    }

    // 취소 통지가 가리키는 거래가 이 주문의 결제 거래와 같아야 한다.
    if ((string) $pg_result['TID'] !== (string) $noti['tid']) {
        return cookiepay_guard_result(false, 'TID_MISMATCH', '원 결제의 거래번호와 다릅니다.');
    }

    // 쿠키페이에 거래가 실재하는지 묻는다. CANCELDATE 는 보지 않는다(위 설명 참고).
    if (empty($verify) || empty($verify['ORDERNO'])) {
        return cookiepay_guard_result(false, 'VERIFY_FAILED', '쿠키페이에서 거래를 확인하지 못했습니다.');
    }

    // 그 거래가 이 주문의 것이어야 한다. 쿠키페이 쪽 기록으로 TID 와 주문번호를 묶는다.
    if ((string) $verify['ORDERNO'] !== (string) $noti['orderno']) {
        return cookiepay_guard_result(false, 'ORDERNO_MISMATCH', '검증 응답의 주문번호가 통지와 다릅니다.');
    }

    // 취소 금액은 실제 결제 금액을 넘을 수 없다. 우리 DB 가 아니라 쿠키페이 응답을 기준으로 본다.
    $verify_amount = (int) (isset($verify['AMOUNT']) ? $verify['AMOUNT'] : 0);
    if ($verify_amount > 0 && $amount > $verify_amount) {
        return cookiepay_guard_result(false, 'AMOUNT_OVER', '취소 금액이 결제 금액을 넘습니다.');
    }

    if (empty($order)) {
        return cookiepay_guard_result(false, 'NO_ORDER', '주문을 찾을 수 없습니다.');
    }

    $paid     = (int) $order['od_receipt_price'];
    $refunded = (int) $order['od_refund_price'];

    // 받은 돈보다 많이 환불될 수는 없다.
    if ($paid > 0 && ($refunded + $amount) > $paid) {
        return cookiepay_guard_result(false, 'AMOUNT_OVER', '취소 금액이 결제 금액을 넘습니다.');
    }

    // 결제 금액이 0 이면(미입금 가상계좌 등) 돌려줄 돈이 없으므로 전액 취소로 본다.
    $full = ($paid <= 0) || (($refunded + $amount) >= $paid);

    return cookiepay_guard_result(true, 'OK', '취소 통지 검증 통과', array(
        'amount'       => $amount,
        'refund_total' => $refunded + $amount,
        'full'         => $full,
    ));
}

/**
 * [항목 1] 외부 입력을 DB 컬럼 이름으로 쓰기 전에 걸러낸다.
 *
 * cookiepay.pay.php 의 try_order 모드는 전송된 필드의 이름을 그대로 컬럼 이름으로
 * 써서 INSERT 문을 만든다($keys[$key] = "{$key}"). 로그인도 필요 없으므로
 * 누구나 임의의 컬럼 이름이나 SQL 조각을 컬럼 자리에 넣을 수 있다.
 * 값은 이스케이프로 막을 수 있지만 컬럼 이름은 따옴표로 감쌀 수 없다.
 * 그래서 허용 목록에 있는 이름만 통과시킨다.
 *
 * @param array $data    외부에서 들어온 연관배열
 * @param array $allowed 허용할 키 목록(실제 테이블 컬럼명 + 가공용 가상 키)
 */
function cookiepay_guard_filter_columns($data, $allowed)
{
    if (empty($allowed) || !is_array($allowed)) {
        // 허용 목록을 못 구했으면 통과시키지 않는다. 열어 두는 쪽이 더 위험하다.
        return cookiepay_guard_result(false, 'NO_ALLOWLIST', '허용 컬럼 목록이 없습니다.', array(
            'data'     => array(),
            'rejected' => is_array($data) ? array_keys($data) : array(),
        ));
    }

    $allowedMap = array_flip($allowed);

    $filtered = array();
    $rejected = array();

    foreach ((array) $data as $key => $val) {
        if (isset($allowedMap[$key])) {
            $filtered[$key] = $val;
        } else {
            $rejected[] = $key;
        }
    }

    if (empty($filtered)) {
        return cookiepay_guard_result(false, 'NO_VALID_COLUMN', '저장할 수 있는 항목이 없습니다.', array(
            'data'     => array(),
            'rejected' => $rejected,
        ));
    }

    return cookiepay_guard_result(true, 'OK', '컬럼 허용 목록 통과', array(
        'data'     => $filtered,
        'rejected' => $rejected,
    ));
}

/**
 * [항목 11] 관리자 위조 방지 토큰 — 발급.
 *
 * 세션에 토큰이 없으면 만들고, 있으면 그대로 돌려준다(멱등).
 *
 * 영카트/그누보드의 get_admin_token() 을 쓰지 않는다. 그 함수는 호출할 때마다
 * 새 토큰을 발급해 세션에 덮어쓰므로, 확인하는 쪽에서 부르면 방금 만든 값과
 * 비교하게 되어 언제나 불일치한다(2026-10-19 확인). 앞서 admin.head.php 가
 * 입력칸을 채워 줄 것으로 본 가정도 틀렸다. 코어 동작에 기대지 않고 자체 키를 쓴다.
 */
function cookiepay_guard_admin_token()
{
    if (empty($_SESSION['cookiepay_admin_token'])) {
        $_SESSION['cookiepay_admin_token'] = md5(uniqid(mt_rand(), true));
    }

    return (string) $_SESSION['cookiepay_admin_token'];
}

/**
 * [항목 11] 관리자 위조 방지 토큰 — 확인.
 *
 * 발급 함수를 부르지 않는다. 확인하면서 토큰을 만들면 비교가 무의미해진다.
 */
function cookiepay_guard_admin_token_ok($token)
{
    if (empty($token) || empty($_SESSION['cookiepay_admin_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['cookiepay_admin_token'], (string) $token);
}

/**
 * [항목 11] 관리자 재결제 요청을 받아도 되는지 검증한다.
 *
 * cookiepay.subscribe.repay.ajax.php 는 권한 확인도 위조 방지 토큰 확인도 없이
 * 요청에 담긴 BILLKEY 로 고객의 저장된 카드를 결제한다. 관리자가 속아서 링크를
 * 열거나, 관리자 화면에 주입된 스크립트가 실행되면 그대로 결제가 일어난다.
 *
 * 빌링키는 요청에서 받지 않고 서버 조회 값을 돌려준다.
 *
 * @param string $auth_error auth_check_menu() 결과(빈 문자열이면 권한 있음)
 * @param bool   $token_ok   관리자 위조 방지 토큰이 유효한가
 * @param array  $subscribe  정기결제 행(BILLKEY, RESERVE_ID 포함)
 */
function cookiepay_guard_admin_repay($auth_error, $token_ok, $subscribe)
{
    if (!empty($auth_error)) {
        return cookiepay_guard_result(false, 'NO_PERMISSION', '권한이 없습니다.');
    }

    if ($token_ok !== true) {
        return cookiepay_guard_result(false, 'BAD_TOKEN', '토큰값이 올바르지 않습니다.');
    }

    if (empty($subscribe) || empty($subscribe['RESERVE_ID'])) {
        return cookiepay_guard_result(false, 'NO_SUBSCRIBE', '정기결제 정보가 없습니다.');
    }

    if (empty($subscribe['BILLKEY'])) {
        return cookiepay_guard_result(false, 'NO_BILLKEY', '등록된 빌링키가 없습니다.');
    }

    return cookiepay_guard_result(true, 'OK', '관리자 재결제 검증 통과', array(
        'billkey' => (string) $subscribe['BILLKEY'],
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
