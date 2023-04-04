<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 결제 폼 데이터

// 세션 임시 저장 테이블 체크 후 없으면 생성
if(!sql_query(" DESCRIBE ".COOKIEPAY_SESSION." ", false)) {
    $sql = " CREATE TABLE `".COOKIEPAY_SESSION."` (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
                `json_sess` LONGTEXT NOT NULL COMMENT 'json_encode 세션' COLLATE 'utf8mb4_general_ci',
                `created_at` DATETIME NOT NULL COMMENT '생성일',
                PRIMARY KEY (`id`) USING BTREE,
                CONSTRAINT `json_sess` CHECK (json_valid(`json_sess`))
            )
            COMMENT='쿠키페이 플러그인용 세션 임시 저장'
            COLLATE='utf8mb4_general_ci'
            ENGINE=InnoDB ";
    sql_query($sql, true);
}

// PG에서 리턴 후 세션이 풀리는 경우를 대비해 세션을 json으로 DB에 넣고 pk를 etc1로 전달 받음
$jsonSess = json_encode($_SESSION);
$sql = " INSERT INTO ".COOKIEPAY_SESSION." (json_sess, created_at) VALUES ('{$jsonSess}', NOW()) ";
$result = sql_query($sql, false);
$insertId = 0;
if ($result) {
    $insertId = sql_insert_id();
}
?>

    <input type="hidden" name="ORDERNO" id="ORDERNO" placeholder="주문번호" value="<?php echo $od_id; ?>">
    <input type="hidden" name="PRODUCTNAME" id="PRODUCTNAME" placeholder="상품명" value="<?php echo mb_substr(str_replace("&", "", $goods), 0, 40); ?>">
    <input type="hidden" name="AMOUNT" id="AMOUNT" placeholder="금액" value="<?php echo ($tot_sell_price + $send_cost); ?>">
    <input type="hidden" name="BUYERNAME" id="BUYERNAME" placeholder="고객명" value="<?php echo isset($member['mb_name']) ? get_text($member['mb_name']) : ''; ?>">
    <input type="hidden" name="BUYEREMAIL" id="BUYEREMAIL" placeholder="고객 e-mail" value="<?php echo isset($member['mb_email']) ? get_text($member['mb_email']) : ''; ?>">
    <input type="hidden" name="PAYMETHOD" id="PAYMETHOD" placeholder="결제수단" value="CARD">
    <input type="hidden" name="PRODUCTCODE" id="PRODUCTCODE" placeholder="상품 코드" value="">
    <input type="hidden" name="BUYERID" id="BUYERID" placeholder="고객 ID" value="<?php echo isset($member['mb_id']) ? get_text($member['mb_id']) : ''; ?>">
    <input type="hidden" name="BUYERADDRESS" id="BUYERADDRESS" placeholder="고객 주소" value="">
    <input type="hidden" name="BUYERPHONE" id="BUYERPHONE" placeholder="고객 휴대폰번호" value="<?php echo str_replace("-", "", get_text($member['mb_hp'])); ?>">
    <input type="hidden" name="RETURNURL" id="RETURNURL" placeholder="결제 완료 후 리다이렉트 url" value="<?php echo COOKIEPAY_RETURN_URL; ?>?od_id=<?php echo $od_id; ?>">
    <input type="hidden" name="CANCELURL" id="CANCELURL" value="">
    <input type="hidden" name="PAY_TYPE" id="PAY_TYPE" value="">
    <input type="hidden" name="ENG_FLAG" id="ENG_FLAG" value="">
    <input type="hidden" name="ETC1" id="ETC1" placeholder="사용자 추가필드 1" value="<?php echo $insertId; ?>">
    <input type="hidden" name="ETC2" id="ETC2" placeholder="사용자 추가필드 2" value="">
    <input type="hidden" name="ETC3" id="ETC3" placeholder="사용자 추가필드 3" value="">
    <input type="hidden" name="ETC4" id="ETC4" placeholder="사용자 추가필드 4" value="">
    <input type="hidden" name="ETC5" id="ETC5" placeholder="사용자 추가필드 5" value="">
