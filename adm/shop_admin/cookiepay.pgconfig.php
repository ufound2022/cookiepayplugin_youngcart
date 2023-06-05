<?php exit;
$sub_menu = '990100';
include_once('./_common.php');
include_once(G5_EDITOR_LIB);
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

auth_check_menu($auth, $sub_menu, "r");

$g5['title'] = '쿠키페이설정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

$pgCodes = array_keys(COOKIEPAY_PG);
?>

<style>
.bg-skyblue {background-color:skyblue;}
input, select {padding-left:6px;}
</style>

<form name="pgconfig" id="pgconfig" action="./cookiepay.configformupdate.php" method="post">
<input type="hidden" name="token" value="">

<section id ="anc_scf_payment">
    <h2 class="h2_frm">쿠키페이설정</h2>
    
    <?php echo help("❗ 결제대행사와 연동정보를 제외한 설정은 \"<strong>쇼핑몰관리 > 쇼핑몰설정 > 결제설정</strong>\"의 설정이 적용됩니다."); ?>

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>결제설정 입력</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">
                <label for="de_pg_service">결제대행사</label><br>
                <a href="<?php echo COOKIEPAY_JOIN_URL ?>" target="_blank" class="kg_btn">쿠키페이 신청하기</a>
            </th>
            <td>
                <?php echo help('쇼핑몰에서 사용할 결제대행사를 선택합니다.'); ?>
                <ul class="de_pg_tab">
                <?php
                // 선택된 결제대행사가 없을 경우 초기 선택 지정
                $i = 0;
                foreach (COOKIEPAY_PG as $pgCode => $pgName) {
                    $checked = "";
                
                    if ($default['de_pg_service'] == $pgCode) {
                        $checked = "checked";
                    }
                    
                    echo '<label style="margin-right:15px;"><input type="radio" name="de_pg_service" id="de_pg_service_'.$i.'" value="'.$pgCode.'" '.$checked.'> '.$pgName.'</label>';

                    $i++;
                }
                ?>
                </ul>
            </td>
        </tr>

        <?php
        $i = 0;
        foreach (COOKIEPAY_PG as $pgCode => $pgName) { 
            $pgCodeLower = strtolower($pgCode);
        ?>
        <tr class="pg_info_fld <?php echo $pgCodeLower; ?>_info_fld" id="<?php echo $pgCodeLower; ?>_info_anchor">
            <th scope="row">
                <label for="de_<?php echo $pgCodeLower; ?>_cookiepay_id"><?php echo $pgName; ?> 연동 아이디</label>
            </th>
            <td>
                <?php echo help("쿠키페이에서 발급 받으신 연동 아이디를 입력합니다."); ?>
                <input type="text" name="de_<?php echo $pgCodeLower; ?>_cookiepay_id" value="<?php echo get_sanitize_input($default["de_{$pgCodeLower}_cookiepay_id"]); ?>" id="de_<?php echo $pgCodeLower; ?>_cookiepay_id" class="frm_input code_input" size="50" maxlength="50">
            </td>
        </tr>
        <tr class="pg_info_fld <?php echo $pgCodeLower; ?>_info_fld">
            <th scope="row"><label for="de_<?php echo $pgCodeLower; ?>_cookiepay_key"><?php echo $pgName; ?> 연동 시크릿키</label></th>
            <td>
                <?php echo help("쿠키페이에서 발급 받으신 연동 시크릿키를 입력합니다."); ?>
                <input type="text" name="de_<?php echo $pgCodeLower; ?>_cookiepay_key" value="<?php echo get_sanitize_input($default["de_{$pgCodeLower}_cookiepay_key"]); ?>" id="de_<?php echo $pgCodeLower; ?>_cookiepay_key" class="frm_input" size="67" maxlength="50">
            </td>
        </tr>
        <?php
            $i++;
        }
        ?>
        </tbody>
        </table>
    </div>
</section>

<div class="btn_fixed_top">
    <a href=" <?php echo G5_SHOP_URL; ?>" class="btn btn_02">쇼핑몰</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
</div>

</form>

<script>
$(function() {
    idKeySection();
    onSubmitConfirm();
    choicePG();
});

var onSubmitConfirm = function(){
    $('#pgconfig').on("submit", function(e){
        var usePgValue = $('input[name="de_pg_service"]:checked').val();
        if (!usePgValue) {
            alert("결제대행사를 선택해 주세요.");
            return false;
        }
        var usePg = usePgValue.toLowerCase();
        var usePgId = $(`#de_${usePg}_cookiepay_id`);
        var usePgKey = $(`#de_${usePg}_cookiepay_key`);

        if (usePgId.val().length < 1 || usePgKey.val().length < 1) {
            alert("연동 아이디와 연동 시크릿키를 입력해 주세요.");
            usePgId.focus();
            return false;
        }
        
        if (!confirm("설정을 저장 하시겠습니까?")){
            return false;
        }
        return true;
    });
}

// 연동아이디/시크릿키 화면처리
var idKeySection = function() {
    var usePgValue = $('input[name="de_pg_service"]:checked').val();
    var usePg = usePgValue.toLowerCase();
    if (!usePgValue) {
        $(".pg_info_fld").css("display", "");
    } else {
        $(".pg_info_fld").css("display", "none");
        $(`.${usePg}_info_fld`).css("display", "");
    }
}

// 결제대행사 클릭시 처리
var choicePG = function(){
    $('input[name="de_pg_service"]').on("change", function(e){
        var usePgValue = $('input[name="de_pg_service"]:checked').val();
        var usePg = usePgValue.toLowerCase();
        $(".pg_info_fld").css("display", "none");
        $(`.${usePg}_info_fld`).css("display", "");
    });
};
</script>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
