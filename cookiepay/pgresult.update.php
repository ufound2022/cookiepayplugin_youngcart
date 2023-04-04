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

$sql = " select * from ".COOKIEPAY_PG_RESULT." where ORDERNO='{$_POST['ORDERNO']}' ";
$cookiepayPgResult = sql_fetch($sql);

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
}
