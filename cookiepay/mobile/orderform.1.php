<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 주문정보 처리 및 결제창 팝업

require_once G5_PATH."/cookiepay/cookiepay.lib.php";
?>

<form name="sm_form" method="POST" action="" accept-charset="euc-kr">
<input type="hidden" name="good_mny" value="<?php echo $tot_price; ?>" >
</form>

<script>
function pay(settle_method) {
    var h = screen.height;
    var w = screen.width;
    var popupPos = `left=0, top=0, width=${w}, height=${h}`;
    var pgWin1 = window.open(`<?php echo COOKIEPAY_URL; ?>/cookiepay.pgwin.php?pm=${settle_method}`, "pgWin1", popupPos);
}
</script>
