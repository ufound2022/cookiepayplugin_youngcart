<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 주문정보 처리 및 결제창 팝업

require_once G5_PATH."/cookiepay/cookiepay.lib.php";
?>

<script>
function pay(settle_method) {
    if (settle_method == "수기결제") {
        var popupPos = "left=0, top=0, width=650, height=550";
    } else {
        if ("<?php echo $default['de_pg_service']; ?>" == "COOKIEPAY_TS") {
            var popupPos = "left=0, top=0, width=800, height=670";
        } else {
            var popupPos = "left=0, top=0, width=500, height=150";
        }
    }
    var pt = document.querySelector("#PAY_TYPE").value;
    var pgWin1 = window.open(`<?php echo COOKIEPAY_URL; ?>/cookiepay.pgwin.php?pm=${settle_method}&pt=${pt}`, "pgWin1", popupPos);

    if(!pgWin1 || pgWin1.closed || typeof pgWin1.closed=='undefined') { 
        alert("팝업이 차단되어 있습니다.\n팝업 차단 해제 후 다시 시도해 주세요.");
    }
}
</script>
