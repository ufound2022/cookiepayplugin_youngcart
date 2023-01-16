<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 주문정보 처리 및 결제창 팝업

require_once G5_PATH."/cookiepay/cookiepay.lib.php";
?>

<script>
function pay(settle_method) {
    if ("<?php echo $default['de_pg_service']; ?>" == "COOKIEPAY_TS") {
        var popupPos = "left=0, top=0, width=800, height=670";
    } else {
        var popupPos = "left=0, top=0, width=10, height=10";
    }
    var pgWin1 = window.open(`<?php echo COOKIEPAY_URL; ?>/cookiepay.pgwin.php?pm=${settle_method}`, "pgWin1", popupPos);
}
</script>
