<?php
include_once('../shop/_common.php');

// PG 결제 결과 후 리턴 받아 처리

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

$cookiepayApi = cookiepay_get_api_account($default);

$cookiepay = $_REQUEST;

// 결제 결과 테이블 없으면 생성
if(!sql_query(" DESCRIBE ".COOKIEPAY_PG_RESULT." ", false)) {
    $sql = " CREATE TABLE `".COOKIEPAY_PG_RESULT."` (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
                `RESULTCODE` VARCHAR(4) NULL DEFAULT NULL COMMENT '결과코드. pg사 응답 코드 (성공시 0000, 그외 에러)' COLLATE 'utf8mb4_general_ci',
                `RESULTMSG` VARCHAR(100) NULL DEFAULT NULL COMMENT '결과 메세지. pg사 응답 메시지 (성공 또는 오류 메세지)' COLLATE 'utf8mb4_general_ci',
                `ORDERNO` VARCHAR(50) NOT NULL COMMENT '주문번호' COLLATE 'utf8mb4_general_ci',
                `AMOUNT` VARCHAR(10) NULL DEFAULT NULL COMMENT '결제된금액' COLLATE 'utf8mb4_general_ci',
                `TID` VARCHAR(20) NOT NULL COMMENT '거래고유번호. pg사 결제 거래고유번호 (전표출력 및 결제취소에 사용됩니다)' COLLATE 'utf8mb4_general_ci',
                `ACCEPTDATE` VARCHAR(20) NULL DEFAULT NULL COMMENT '승인일시. pg사 결제 승인일시' COLLATE 'utf8mb4_general_ci',
                `ACCEPTNO` VARCHAR(10) NULL DEFAULT NULL COMMENT '승인번호. pg사 결제 승인번호' COLLATE 'utf8mb4_general_ci',
                `CASH_BILL_NO` VARCHAR(10) NULL DEFAULT NULL COMMENT '현금영수증일련번호. 가상계좌 및 계좌이체 시 현금영수증 일련번호' COLLATE 'utf8mb4_general_ci',
                `CARDNAME` VARCHAR(10) NULL DEFAULT NULL COMMENT '입금할 은행명. 가상계좌 및 계좌이체 시 입금할 은행명' COLLATE 'utf8mb4_general_ci',
                `ACCOUNTNO` VARCHAR(10) NULL DEFAULT NULL COMMENT '입금할 계좌번호. 가상계좌 시 입금할 계좌번호' COLLATE 'utf8mb4_general_ci',
                `RECEIVERNAME` VARCHAR(10) NULL DEFAULT NULL COMMENT '입금할 예금주. 가상계좌 시 입금할 예금주' COLLATE 'utf8mb4_general_ci',
                `DEPOSITENDDATE` VARCHAR(10) NULL DEFAULT NULL COMMENT '입금마감일. 가상계좌 시 입금마감일' COLLATE 'utf8mb4_general_ci',
                `CARDCODE` VARCHAR(10) NULL DEFAULT NULL COMMENT '입금할 은행코드. 가상계좌 시 입금할 은행코드' COLLATE 'utf8mb4_general_ci',
                `QUOTA` VARCHAR(2) NULL DEFAULT NULL COMMENT '카드 할부결제시 할부기간 (00:일시불, 01:1개월)' COLLATE 'utf8mb4_general_ci',
                `ETC1` VARCHAR(100) NULL DEFAULT NULL COMMENT '사용자 추가 필드1. 결제 요청시 입력한 값' COLLATE 'utf8mb4_general_ci',
                `ETC2` VARCHAR(100) NULL DEFAULT NULL COMMENT '사용자 추가 필드2. 결제 요청시 입력한 값' COLLATE 'utf8mb4_general_ci',
                `ETC3` VARCHAR(100) NULL DEFAULT NULL COMMENT '사용자 추가 필드3. 결제 요청시 입력한 값' COLLATE 'utf8mb4_general_ci',
                `ETC4` VARCHAR(100) NULL DEFAULT NULL COMMENT '사용자 추가 필드4. 결제 요청시 입력한 값' COLLATE 'utf8mb4_general_ci',
                `ETC5` VARCHAR(100) NULL DEFAULT NULL COMMENT '사용자 추가 필드5. 결제 요청시 입력한 값' COLLATE 'utf8mb4_general_ci',
                `PGNAME` VARCHAR(30) NULL DEFAULT NULL COMMENT 'PG사' COLLATE 'utf8mb4_general_ci',
                PRIMARY KEY (`id`) USING BTREE,
                INDEX `ORDERNO` (`ORDERNO`) USING BTREE,
                INDEX `TID` (`TID`) USING BTREE
            )
            COMMENT='쿠키페이 플러그인 결제 응답 결과'
            COLLATE='utf8mb4_general_ci'
            ENGINE=InnoDB ";
    sql_query($sql, true);
}

$pgResultColumns = [
    'RESULTCODE',
    'RESULTMSG',
    'ORDERNO',
    'AMOUNT',
    'TID',
    'ACCEPTDATE',
    'ACCEPTNO',
    'CASH_BILL_NO',
    'CARDNAME',
    'ACCOUNTNO',
    'RECEIVERNAME',
    'DEPOSITENDDATE',
    'CARDCODE',
    'QUOTA',
    'ETC1',
    'ETC2',
    'ETC3',
    'ETC4',
    'ETC5',
    'PGNAME'
];

// 결제 검증 테이블 없으면 생성
if(!sql_query(" DESCRIBE ".COOKIEPAY_PG_VERIFY." ", false)) {
    $sql = " CREATE TABLE `".COOKIEPAY_PG_VERIFY."` (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
                `RESULTCODE` VARCHAR(4) NULL DEFAULT NULL COMMENT 'PG 사 응답코드. 응답 코드 (성공시 0000, 그외 에러)' COLLATE 'utf8mb4_general_ci',
                `RESULTMSG` VARCHAR(100) NULL DEFAULT NULL COMMENT 'PG 사 응답메시지. 응답 메시지 (성공 또는 오류 메세지)' COLLATE 'utf8mb4_general_ci',
                `ORDERNO` VARCHAR(50) NOT NULL COMMENT '주문번호. 결제한 주문번호' COLLATE 'utf8mb4_general_ci',
                `AMOUNT` VARCHAR(10) NULL DEFAULT NULL COMMENT '결제 된 금액. 문자 및 특수문자 허용 불가' COLLATE 'utf8mb4_general_ci',
                `BUYERNAME` VARCHAR(20) NULL DEFAULT NULL COMMENT '고객명' COLLATE 'utf8mb4_general_ci',
                `BUYEREMAIL` VARCHAR(50) NULL DEFAULT NULL COMMENT '고객 e-mail' COLLATE 'utf8mb4_general_ci',
                `PRODUCTNAME` VARCHAR(40) NULL DEFAULT NULL COMMENT '상품명. & 문자 포함시 오류 발생' COLLATE 'utf8mb4_general_ci',
                `PRODUCTCODE` VARCHAR(10) NULL DEFAULT NULL COMMENT '상품코드' COLLATE 'utf8mb4_general_ci',
                `PAYMETHOD` VARCHAR(20) NOT NULL COMMENT '결제수단. CARD(카드), KAKAOPAY(카카오페이), BANK(계좌이체), VACCT(가상계좌), MOBILE(휴대폰)' COLLATE 'utf8mb4_general_ci',
                `BUYERID` VARCHAR(20) NULL DEFAULT NULL COMMENT '고객 ID' COLLATE 'utf8mb4_general_ci',
                `TID` VARCHAR(50) NOT NULL COMMENT 'PG 거래 고유번호. PG사 결제 거래고유번호 (전표출력 및 결제취소에 사용됩니다)' COLLATE 'utf8mb4_general_ci',
                `ACCEPTNO` VARCHAR(10) NULL DEFAULT NULL COMMENT '승인번호. PG사 결제 승인번호' COLLATE 'utf8mb4_general_ci',
                `ACCEPTDATE` VARCHAR(20) NULL DEFAULT NULL COMMENT '승인일시. PG사 결제 승인일시' COLLATE 'utf8mb4_general_ci',
                `CANCELDATE` VARCHAR(20) NULL DEFAULT NULL COMMENT '취소날짜. 결제 취소한 경우 취소일시' COLLATE 'utf8mb4_general_ci',
                `CANCELMSG` VARCHAR(50) NULL DEFAULT NULL COMMENT '취소메시지. 결제 취소한 경우 취소메세지' COLLATE 'utf8mb4_general_ci',
                `ACCOUNTNO` VARCHAR(50) NULL DEFAULT NULL COMMENT '가상계좌번호. 가상계좌 이용 시 리턴 값' COLLATE 'utf8mb4_general_ci',
                `RECEIVERNAME` VARCHAR(50) NULL DEFAULT NULL COMMENT '예금주성명. 가상계좌 이용 시 리턴 값' COLLATE 'utf8mb4_general_ci',
                `DEPOSITENDDATE` VARCHAR(50) NULL DEFAULT NULL COMMENT '계좌사용만료일. 가상계좌 이용 시 리턴 값' COLLATE 'utf8mb4_general_ci',
                `CARDNAME` VARCHAR(50) NULL DEFAULT NULL COMMENT '은행명. 가상계좌 이용 시 리턴 값' COLLATE 'utf8mb4_general_ci',
                `CARDCODE` VARCHAR(50) NULL DEFAULT NULL COMMENT '은행코드. 가상계좌 이용 시 리턴 값' COLLATE 'utf8mb4_general_ci',
                PRIMARY KEY (`id`) USING BTREE,
                INDEX `ORDERNO` (`ORDERNO`) USING BTREE,
                INDEX `PAYMETHOD` (`PAYMETHOD`) USING BTREE,
                INDEX `TID` (`TID`) USING BTREE
            )
            COMMENT='쿠키페이 플러그인 결제 검증 결과'
            COLLATE='utf8mb4_general_ci'
            ENGINE=InnoDB ";
    sql_query($sql, true);
}

$pgVerifyColumns = [
    'RESULTCODE',
    'RESULTMSG',
    'ORDERNO',
    'AMOUNT',
    'BUYERNAME',
    'BUYEREMAIL',
    'PRODUCTNAME',
    'PRODUCTCODE',
    'PAYMETHOD',
    'BUYERID',
    'TID',
    'ACCEPTNO',
    'ACCEPTDATE',
    'CANCELDATE',
    'CANCELMSG',
    'ACCOUNTNO',
    'RECEIVERNAME',
    'DEPOSITENDDATE',
    'CARDNAME',
    'CARDCODE'
];

// 결제 취소 테이블 없으면 생성
if(!sql_query(" DESCRIBE ".COOKIEPAY_PG_CANCEL." ", false)) {
    $sql = " CREATE TABLE `".COOKIEPAY_PG_CANCEL."` (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
                `orderno` VARCHAR(50) NOT NULL COMMENT '주문번호' COLLATE 'utf8mb4_general_ci',
                `cancel_tid` VARCHAR(50) NOT NULL COMMENT 'PG사 취소 고유 거래번호' COLLATE 'utf8mb4_general_ci',
                `cancel_code` VARCHAR(4) NOT NULL COMMENT '취소 응답코드. 정상 : 0000, 그외 에러' COLLATE 'utf8mb4_general_ci',
                `cancel_msg` VARCHAR(100) NOT NULL COMMENT '취소 응답메시지' COLLATE 'utf8mb4_general_ci',
                `cancel_date` VARCHAR(50) NOT NULL COMMENT '취소날짜' COLLATE 'utf8mb4_general_ci',
                `cancel_amt` VARCHAR(10) NOT NULL COMMENT '취소 된 금액' COLLATE 'utf8mb4_general_ci',
                PRIMARY KEY (`id`) USING BTREE,
                INDEX `cancel_tid` (`cancel_tid`) USING BTREE,
                INDEX `orderno` (`orderno`) USING BTREE
            )
            COMMENT='쿠키페이 플러그인 결제 취소 결과'
            COLLATE='utf8mb4_general_ci'
            ENGINE=InnoDB ";
    sql_query($sql, true);
}

$pgCancelColumns = [
    'cancel_tid',
    'cancel_code',
    'cancel_msg',
    'cancel_date',
    'cancel_amt'
];

if (!isset($cookiepay['RESULTCODE'])) {
    // 결제 실패 로그 기록 - 응답 결과 없음
    @cookiepay_payment_log("응답 결과 없음", "", 3);

    echo '<script>
            alert("결제에 실패했습니다.");
            self.close();
        </script>';
    exit;
}

// 결제 결과 테이블에 저장
// column 쿼리 처리
$columnStr = implode(",", $pgResultColumns);

// values 쿼리 처리
$values = [];
foreach ($pgResultColumns as $val) {
    $values[$val] = "''";
}
foreach ($cookiepay as $key => $val) {
    if (array_key_exists($key, $values)) {
        $values[$key] = "'{$val}'";
    }
}

$values['PGNAME'] = "'{$default['de_pg_service']}'"; // pg사 추가

$valueStr = implode(",", $values);

$sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
// $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." (RESULTCODE, RESULTMSG, ORDERNO, AMOUNT, TID, ACCEPTDATE, ACCEPTNO, CASH_BILL_NO, CARDNAME, ACCOUNTNO, RECEIVERNAME, DEPOSITENDDATE, CARDCODE, ETC1, ETC2, ETC3, ETC4, ETC5, PGNAME) VALUES ('{$cookiepay['RESULTCODE']}', '{$cookiepay['RESULTMSG']}', '{$cookiepay['ORDERNO']}', '{$cookiepay['AMOUNT']}', '{$cookiepay['TID']}', '{$cookiepay['ACCEPTDATE']}', '{$cookiepay['ACCEPTNO']}', '{$cookiepay['CASH_BILL_NO']}', '{$cookiepay['CARDNAME']}', '{$cookiepay['ACCOUNTNO']}', '{$cookiepay['RECEIVERNAME']}', '{$cookiepay['DEPOSITENDDATE']}', '{$cookiepay['CARDCODE']}', '{$cookiepay['ETC1']}', '{$cookiepay['ETC2']}', '{$cookiepay['ETC3']}', '{$cookiepay['ETC4']}', '{$cookiepay['ETC5']}', '{$cookiepay['PGNAME']}') ";
$res = sql_query($sql, false);
if ($res) {
    @cookiepay_payment_log("결제결과 저장 성공", $sql, 3);
} else {
    @cookiepay_payment_log("결제결과 저장 실패", $sql, 3);
}

if ($cookiepay['RESULTCODE'] == '0000') {
    // 결제 성공 로그 기록
    @cookiepay_payment_log("결재 성공", json_encode($cookiepay), 1);

    // 결제 검증
    $headers = array(
        'Content-Type: application/json; charset=utf-8',
    );

    $token_url = COOKIEPAY_TOKEN_URL;

    $request_data = array(
        'pay2_id' => $cookiepayApi['api_id'],
        'pay2_key'=> $cookiepayApi['api_key'],
    );

    $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resultJson = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($resultJson, true);

    if($result['RTN_CD'] == '0000') {

        $paycert_url = COOKIEPAY_VERIFY_URL;

        $headers = array(
            'content-type: application/json; charset=utf-8',
            'TOKEN: ' . $result['TOKEN'],
        );

        $request_data = array(
            'tid' => $cookiepay['TID'],
        );

        $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $paycert_url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $verify = json_decode($response, true);

        // 결제 검증 결과 테이블에 저장
        // column 쿼리 처리
        $columnStr = implode(",", $pgVerifyColumns);

        // values 쿼리 처리
        $values = [];
        foreach ($pgVerifyColumns as $val) {
            $values[$val] = "''";
        }
        foreach ($verify as $key => $val) {
            if (array_key_exists($key, $values)) {
                $values[$key] = "'{$val}'";
            }
        }
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_VERIFY." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("결제검증결과 저장 성공", $sql, 3);
        } else {
            @cookiepay_payment_log("결제검증결과 저장 실패", $sql, 3);
        }

        if($verify['RESULTCODE'] == '0000') {
            // 결제 검증 성공
            @cookiepay_payment_log("결제검증 성공", $response, 3);
        } else {
            // 결제 검증 실패시 결제 취소 처리
            @cookiepay_payment_log("결제검증 실패", $response, 3);
        
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("결제취소 성공", $ret['data'], 3);
            } else {
                @cookiepay_payment_log("결제취소 실패", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("결제취소결과 저장 성공", $sql, 3);
            } else {
                @cookiepay_payment_log("결제취소결과 저장 실패", $sql, 3);
            }
        }
    } else {
        // 결제 검증 토큰 발행 실패시 결제 취소 처리
        @cookiepay_payment_log("결제 검증 토큰 발행 실패", $resultJson, 3);
        
        $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

        if ($ret['status'] === true) {
            @cookiepay_payment_log("결제취소 성공", $ret['data'], 3);
        } else {
            @cookiepay_payment_log("결제취소 실패", $ret['data'], 3);
        }

        $cancelArr = json_decode($ret['data'], true);

        $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("결제취소결과 저장 성공", $sql, 3);
        } else {
            @cookiepay_payment_log("결제취소결과 저장 실패", $sql, 3);
        }
    }

} else {
    // 결제 실패 로그 기록
    @cookiepay_payment_log("결재 실패", json_encode($cookiepay), 3);

    echo '<script>
        alert("['.$cookiepay['RESULTCODE'].'] '.$cookiepay['RESULTMSG'].'");
        self.close();
    </script>';

    exit;
}

echo '<script>
        alert("['.$cookiepay['RESULTCODE'].'] '.$cookiepay['RESULTMSG'].'");
        opener.document.forderform.submit();
        self.close();
    </script>';

exit;
