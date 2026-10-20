<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 결제 폼 데이터

// 세션 임시 저장 테이블 체크 후 없으면 생성
if (!sql_query("DESCRIBE `" . COOKIEPAY_SESSION . "`", false)) {
    $sql = "
        CREATE TABLE `" . COOKIEPAY_SESSION . "` (
            `id` INT(11) NOT NULL AUTO_INCREMENT
                COMMENT '고유번호',

            `json_sess` LONGTEXT NOT NULL
                COMMENT 'json_encode 세션'
                COLLATE 'utf8mb4_general_ci',

            `token_hash` CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NULL DEFAULT NULL
                COMMENT '토큰 SHA-256 해시',

            `orderno` VARCHAR(50) NOT NULL
                COMMENT '주문번호'
                COLLATE 'utf8mb4_general_ci',

            `expires_at` DATETIME
                NULL DEFAULT NULL
                COMMENT '만료일',
            
            `created_at` DATETIME NOT NULL
                COMMENT '생성일',

            PRIMARY KEY (`id`) USING BTREE,

            CONSTRAINT `json_sess`
                CHECK (json_valid(`json_sess`))
        )
        COMMENT='쿠키페이 플러그인용 세션 임시 저장'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
    ";

    sql_query($sql, true);
}

// token_hash 컬럼이 없으면 추가
$column = sql_fetch("
    SHOW COLUMNS
    FROM `" . COOKIEPAY_SESSION . "`
    WHERE Field = 'token_hash'
");

if (empty($column['Field'])) {
    $sql = "
        ALTER TABLE `" . COOKIEPAY_SESSION . "`
        ADD COLUMN `token_hash` CHAR(64)
            CHARACTER SET ascii
            COLLATE ascii_bin
            NULL DEFAULT NULL
            COMMENT 'ETC1 토큰 SHA-256 해시'
    ";

    sql_query($sql, true);
}

// orderno 컬럼이 없으면 추가
$column = sql_fetch("
    SHOW COLUMNS
    FROM `" . COOKIEPAY_SESSION . "`
    WHERE Field = 'orderno'
");

if (empty($column['Field'])) {
    $sql = "
        ALTER TABLE `" . COOKIEPAY_SESSION . "`
        ADD COLUMN `orderno` VARCHAR(50) NOT NULL
            COLLATE 'utf8mb4_general_ci'
            COMMENT '주문번호'
    ";

    sql_query($sql, true);
}

// expires_at 컬럼이 없으면 추가
$column = sql_fetch("
    SHOW COLUMNS
    FROM `" . COOKIEPAY_SESSION . "`
    WHERE Field = 'expires_at'
");

if (empty($column['Field'])) {
    $sql = "
        ALTER TABLE `" . COOKIEPAY_SESSION . "`
        ADD COLUMN `expires_at` DATETIME
            NULL DEFAULT NULL
            COMMENT '만료일'
    ";

    sql_query($sql, true);
}

// s: cookiepay-plugin > 보안검토 항목2 > v1.6 > 261020
// created_ip 컬럼이 없으면 추가. 세션 복원을 만든 접속에 묶기 위한 값이다.
$column = sql_fetch("
    SHOW COLUMNS
    FROM `" . COOKIEPAY_SESSION . "`
    WHERE Field = 'created_ip'
");

if (empty($column['Field'])) {
    $sql = "
        ALTER TABLE `" . COOKIEPAY_SESSION . "`
        ADD COLUMN `created_ip` VARCHAR(45)
            NULL DEFAULT NULL
            COMMENT '세션을 만든 접속 IP'
    ";

    sql_query($sql, true);
}
// e: cookiepay-plugin > 보안검토 항목2 > v1.6 > 261020

// PG에서 리턴 후 세션이 풀리는 경우를 대비해 세션을 json으로 DB에 넣고 pk를 etc1로 전달 받음
$jsonSess = json_encode($_SESSION);
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$sql = "
    INSERT INTO " . COOKIEPAY_SESSION . "
        (token_hash, orderno, json_sess, expires_at, created_at, created_ip)
    VALUES
        ('{$tokenHash}', '{$od_id}', '{$jsonSess}', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(),
         '" . sql_real_escape_string((string) $_SERVER['REMOTE_ADDR']) . "')
";
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
    <input type="hidden" name="HOMEURL" id="HOMEURL" placeholder="결제 완료 후 리다이렉트 url" value="<?php echo COOKIEPAY_RETURN_URL; ?>?od_id=<?php echo $od_id; ?>">
    <input type="hidden" name="CANCELURL" id="CANCELURL" value="<?php echo G5_URL;?>/shop/orderform.php?sw_direct=1&subscription=">
    <input type="hidden" name="PAY_TYPE" id="PAY_TYPE" value="">
    <input type="hidden" name="ENG_FLAG" id="ENG_FLAG" value="">
    <input type="hidden" name="ETC1" id="ETC1" placeholder="사용자 추가필드 1" value="<?= $token ?>">
    <input type="hidden" name="ETC2" id="ETC2" placeholder="사용자 추가필드 2" value="">
    <input type="hidden" name="ETC3" id="ETC3" placeholder="사용자 추가필드 3" value="">
    <input type="hidden" name="ETC4" id="ETC4" placeholder="사용자 추가필드 4" value="">
    <input type="hidden" name="ETC5" id="ETC5" placeholder="사용자 추가필드 5" value="">
    <input type="hidden" name="od_settle_case2" id="od_settle_case2">


<div id="display_pay_button" class="btn_confirm">
    <span id="show_req_btn"><input type="button" name="submitChecked" onClick="pay_approval();" value="결제등록요청" class="btn_submit"></span>
    <span id="show_pay_btn" style="display:none;"><input type="button" onClick="forderform_check();" value="주문하기" class="btn_submit"></span>
    <a href="<?php echo G5_SHOP_URL; ?>" class="btn_cancel">취소</a>
</div>

<?php
// 무통장 입금만 사용할 때는 주문하기 버튼 보이게
if(!($default['de_iche_use'] || $default['de_vbank_use'] || $default['de_hp_use'] || $default['de_card_use'])) {
?>
<script>
document.getElementById("show_req_btn").style.display = "none";
document.getElementById("show_pay_btn").style.display = "";
</script>
<?php } ?>

<script>
if (document.querySelector('input[name="od_settle_case"]')) {
    document.querySelectorAll('input[name="od_settle_case"]').forEach((elem) => {
        elem.addEventListener("change", function(event) {
            var item = event.target.value;
            document.querySelector('#ETC2').value = item;
        });
    });
}
</script>
