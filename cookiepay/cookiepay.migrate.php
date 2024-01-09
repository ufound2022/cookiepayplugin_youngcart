<?php
include_once('../shop/_common.php');

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

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

//@sql_query(" ALTER TABLE `".COOKIEPAY_PG_RESULT."` ADD COLUMN IF NOT EXISTS `pay_type` VARCHAR(2) NOT NULL COMMENT '결제타입. 1:수기결제,3:신용카드인증,5:해외달러결제,7:해외원화결제' COLLATE 'utf8_general_ci' AFTER `PGNAME` ", true);
//@sql_query(" ALTER TABLE `".COOKIEPAY_PG_RESULT."` ADD COLUMN IF NOT EXISTS `pay_status` TINYINT NOT NULL DEFAULT 0 COMMENT '결제상태. 0:결제시도,1:결제성공,2:결제취소,3:결제실패,4:가상계좌' AFTER `pay_type` ", true);


// s: cookiepay-plugin v1.2
$sql = "
SELECT COLUMN_NAME
FROM information_schema.`COLUMNS`
WHERE information_schema.`COLUMNS`.TABLE_SCHEMA = '".G5_MYSQL_DB."'
AND TABLE_NAME='".COOKIEPAY_PG_RESULT."'
AND information_schema.`COLUMNS`.COLUMN_NAME = 'pay_type';
";
$res = sql_fetch($sql);
if (!isset($res['COLUMN_NAME']))
{
    @sql_query(" ALTER TABLE `".COOKIEPAY_PG_RESULT."` ADD COLUMN `pay_type` VARCHAR(2) NOT NULL COMMENT '결제타입. 1:수기결제,3:신용카드인증,5:해외달러결제,7:해외원화결제' COLLATE 'utf8mb4_general_ci' AFTER `PGNAME` ", true);
}

$sql = "
SELECT COLUMN_NAME
FROM information_schema.`COLUMNS`
WHERE information_schema.`COLUMNS`.TABLE_SCHEMA = '".G5_MYSQL_DB."'
AND TABLE_NAME='".COOKIEPAY_PG_RESULT."'
AND information_schema.`COLUMNS`.COLUMN_NAME = 'pay_status';
";
$res = sql_fetch($sql);
if (!isset($res['COLUMN_NAME']))
{
    @sql_query(" ALTER TABLE `".COOKIEPAY_PG_RESULT."` ADD COLUMN `pay_status` TINYINT NOT NULL DEFAULT 0 COMMENT '결제상태. 0:결제시도,1:결제성공,2:결제취소,3:결제실패,4:가상계좌' AFTER `pay_type` ", true);
}

$sql = "
SELECT COLUMN_TYPE
FROM information_schema.`COLUMNS`
WHERE information_schema.`COLUMNS`.TABLE_SCHEMA = '".G5_MYSQL_DB."'
AND TABLE_NAME='".COOKIEPAY_PG_RESULT."'
AND information_schema.`COLUMNS`.COLUMN_NAME = 'TID'
";
$res = sql_fetch($sql);
if (isset($res))
{
    if ($res['COLUMN_TYPE'] != 'varchar(100)')
    {
        @sql_query(" ALTER TABLE `".COOKIEPAY_PG_RESULT."` CHANGE COLUMN `TID` `TID` VARCHAR(100) NOT NULL COMMENT '거래고유번호. pg사 결제 거래고유번호 (전표출력 및 결제취소에 사용됩니다)' COLLATE 'utf8mb4_general_ci' AFTER `AMOUNT` ", true);
    }
}
// e: cookiepay-plugin v1.2


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
    'PGNAME',
    'pay_type',
    'pay_status'
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

// s: cookiepay-plugin v1.2
// 주문정보 임시 저장 테이블 없으면 생성
if(!sql_query(" DESCRIBE ".COOKIEPAY_SHOP_ORDER." ", false)) {
    $sql = " CREATE TABLE `".COOKIEPAY_SHOP_ORDER."` (
                `od_id` BIGINT(20) UNSIGNED NOT NULL,
                `mb_id` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_name` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_email` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_tel` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_hp` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_zip1` CHAR(3) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_zip2` CHAR(3) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_addr1` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_addr2` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_addr3` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_addr_jibeon` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_deposit_name` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_name` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_tel` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_hp` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_zip1` CHAR(3) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_zip2` CHAR(3) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_addr1` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_addr2` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_addr3` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_b_addr_jibeon` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_memo` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
                `od_cart_count` INT(11) NOT NULL DEFAULT '0',
                `od_cart_price` INT(11) NOT NULL DEFAULT '0',
                `od_cart_coupon` INT(11) NOT NULL DEFAULT '0',
                `od_send_cost` INT(11) NOT NULL DEFAULT '0',
                `od_send_cost2` INT(11) NOT NULL DEFAULT '0',
                `od_send_coupon` INT(11) NOT NULL DEFAULT '0',
                `od_receipt_price` INT(11) NOT NULL DEFAULT '0',
                `od_cancel_price` INT(11) NOT NULL DEFAULT '0',
                `od_receipt_point` INT(11) NOT NULL DEFAULT '0',
                `od_refund_price` INT(11) NOT NULL DEFAULT '0',
                `od_bank_account` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_receipt_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `od_coupon` INT(11) NOT NULL DEFAULT '0',
                `od_misu` INT(11) NOT NULL DEFAULT '0',
                `od_shop_memo` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
                `od_mod_history` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
                `od_status` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_hope_date` DATE NOT NULL DEFAULT '0000-00-00',
                `od_settle_case` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_other_pay_type` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_test` TINYINT(4) NOT NULL DEFAULT '0',
                `od_mobile` TINYINT(4) NOT NULL DEFAULT '0',
                `od_pg` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_tno` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_app_no` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_escrow` TINYINT(4) NOT NULL DEFAULT '0',
                `od_casseqno` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_tax_flag` TINYINT(4) NOT NULL DEFAULT '0',
                `od_tax_mny` INT(11) NOT NULL DEFAULT '0',
                `od_vat_mny` INT(11) NOT NULL DEFAULT '0',
                `od_free_mny` INT(11) NOT NULL DEFAULT '0',
                `od_delivery_company` VARCHAR(255) NOT NULL DEFAULT '0' COLLATE 'utf8mb3_general_ci',
                `od_invoice` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_invoice_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `od_cash` TINYINT(4) NOT NULL,
                `od_cash_no` VARCHAR(255) NOT NULL COLLATE 'utf8mb3_general_ci',
                `od_cash_info` TEXT NOT NULL COLLATE 'utf8mb3_general_ci',
                `od_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `od_pwd` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_ip` VARCHAR(25) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_price` INT(11) NOT NULL DEFAULT '0',
                `org_od_price` INT(11) NOT NULL DEFAULT '0',
                `od_goods_name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_temp_point` INT(11) NOT NULL DEFAULT '0',
                `ad_default` INT(11) NOT NULL DEFAULT '0',
                `it_id` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `cp_id` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `od_cp_id` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `sc_cp_id` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `comm_tax_mny` INT(11) NOT NULL DEFAULT '0',
                `comm_vat_mny` INT(11) NOT NULL DEFAULT '0',
                `comm_free_mny` INT(11) NOT NULL DEFAULT '0',
                `ad_subject` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb3_general_ci',
                `item_coupon` INT(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`od_id`) USING BTREE,
                INDEX `index2` (`mb_id`) USING BTREE
            )
            COMMENT='주문정보 임시 저장'
            COLLATE='utf8mb4_general_ci'
            ENGINE=InnoDB ";
    sql_query($sql, true);
}
// e: cookiepay-plugin v1.2
