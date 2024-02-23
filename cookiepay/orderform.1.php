<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 주문정보 처리 및 결제창 팝업

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

// s: cookiepay-plugin v1.2
// 회원정보
$member = get_member($_SESSION['ss_mb_id']);

if (get_session("ss_direct")) {
    $tmp_cart_id = get_session('ss_cart_direct');
}
else {
    $tmp_cart_id = get_session('ss_cart_id');
}

if ($_SESSION['ss_direct']) {
    $tmp_cart_id = $_SESSION['ss_cart_direct'];
}
else {
    $tmp_cart_id = $_SESSION['ss_cart_id'];
}

$sql = " select SUM(IF(io_type = 1, (io_price * ct_qty), ((ct_price + io_price) * ct_qty))) as od_price,
              COUNT(distinct it_id) as cart_count
            from {$g5['g5_shop_cart_table']} where od_id = '$tmp_cart_id' and ct_select = '1' ";
$row = sql_fetch($sql);
$tot_ct_price = $row['od_price'];
$cart_count = $row['cart_count'];
// e: cookiepay-plugin v1.2
?>

<!-- // s: cookiepay-plugin v1.2 -->
<script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
<script src="https://js.tosspayments.com/v1"></script>
<?php
if ($default['de_pg_service'] == 'COOKIEPAY_PN') {
?>
<script src="https://www.cookiepayments.com/js/paynuri.js"></script>
<script src="https://pg.paynuri.com/js/jquery-ui-1.11.4.base/jquery-ui.min.js"></script>
<script src="https://pg.paynuri.com/js/jquery-dateFormat.min.js"></script>
<?php
}
?>
<script>
function pay_cookiepay(payType) {
    if (payType == "수기결제") {
        var tryOrderParams = {
            mode : 'try_order',
            od_id : $("input[name=ORDERNO]").val(),
            mb_id : '<?=$member['mb_id']?>',
            od_name : $("input[name=od_name]").val(),
            od_email : $("input[name=od_email]").val(),
            od_tel : $("input[name=od_tel]").val(),
            od_hp : $("input[name=od_hp]").val(),
            od_zip : $("input[name=od_zip]").val(),
            od_addr1 : $("input[name=od_addr1]").val(),
            od_addr2 : $("input[name=od_addr2]").val(),
            od_addr3 : $("input[name=od_addr3]").val(),
            od_addr_jibeon : $("input[name=od_addr_jibeon]").val(),
            od_deposit_name : $("input[name=od_deposit_name]").val(),
            od_b_name : $("input[name=od_b_name]").val(),
            od_b_tel : $("input[name=od_b_tel]").val(),
            od_b_hp : $("input[name=od_b_hp]").val(),
            od_b_zip : $("input[name=od_b_zip]").val(),
            od_b_addr1 : $("input[name=od_b_addr1]").val(),
            od_b_addr2 : $("input[name=od_b_addr2]").val(),
            od_b_addr3 : $("input[name=od_b_addr3]").val(),
            od_b_addr_jibeon : $("input[name=od_b_addr_jibeon]").val(),
            od_memo : $("input[name=od_memo]").val(),
            od_price : $("input[name=od_price]").val(),
            org_od_price : $("input[name=org_od_price]").val(),
            od_send_cost : $("input[name=od_send_cost]").val(),
            od_send_cost2 : $("input[name=od_send_cost2]").val(),
            od_coupon : $("input[name=od_coupon]").val(),
            od_send_coupon : $("input[name=od_send_coupon]").val(),
            od_goods_name : $("input[name=od_goods_name]").val(),
            od_temp_point : $("input[name=od_temp_point]").val(),
            od_hope_date : $("input[name=od_hope_date]").val(),
            ad_default : $("input[name=ad_default]").val(),
            it_id : $("input[name=it_id]").val(),
            cp_id : $("input[name=cp_id]").val(),
            od_cp_id : $("input[name=od_cp_id]").val(),
            sc_cp_id : $("input[name=sc_cp_id]").val(),
            od_pwd : $("input[name=od_pwd]").val(),
            comm_tax_mny : $("input[name=comm_tax_mny]").val(),
            comm_vat_mny : $("input[name=comm_vat_mny]").val(),
            comm_free_mny : $("input[name=comm_free_mny]").val(),
            ad_subject : $("input[name=ad_subject]").val(),
            od_settle_case : $("input[name=od_settle_case]:checked").val(),
            item_coupon : $("input[name=item_coupon]").val(),
        };
        
        $.ajax({
            type: "POST",
            url: "<?php echo COOKIEPAY_URL ?>/cookiepay.pay.php",
            data : tryOrderParams,
            cache: false,
            contentType : "application/x-www-form-urlencoded",
            success: function(result) {
                try {
                    const obj = JSON.parse(result);
                    console.log(obj.RTN_MSG);
                } catch(e) {
                    <?php if ($default['de_pg_service'] == 'COOKIEPAY_PN') { ?>
                    $("#dialog_payment").remove();
                    <?php } ?>
                    const newDiv = document.createElement('div');
                    newDiv.setAttribute("id","cookiepayform");
                    document.body.appendChild(newDiv);
                    $("#cookiepayform").html(result);
                }
            },
        });
        
        var popupPos = "left=0, top=0, width=650, height=550";
        var pt = document.querySelector("#PAY_TYPE").value;
        var pgWin1 = window.open(`<?php echo COOKIEPAY_URL; ?>/cookiepay.pgwin.php?pm=${payType}&pt=${pt}`, "pgWin1", popupPos);
        
        if(!pgWin1 || pgWin1.closed || typeof pgWin1.closed=='undefined') { 
            alert("팝업이 차단되어 있습니다.\n팝업 차단 해제 후 다시 시도해 주세요.");
        }
    }
    else {
        <?php
        $cookiepayApi = cookiepay_get_api_account_info($default, 3);
        ?>
        var payMethod = '';
        switch (payType) {
            case '간편결제':
            case '카카오페이':
                // 키움만 가능. 키움이 아니라면 CARD로 설정
                if ("<?php echo $default['de_pg_service']; ?>" == "COOKIEPAY_KW") {
                    payMethod = 'KAKAOPAY';
                } else {
                    payMethod = 'CARD';
                }
                break;
            case '계좌이체':
                payMethod = 'BANK';
                break;
            case '가상계좌':
                payMethod = 'VACCT';
                break;
            case '휴대폰':
                payMethod = 'MOBILE';
                break;
            default:
                payMethod = 'CARD';
        }
        
        var isTest = "<?php echo $default['de_card_test']; ?>";
        if (isTest == '0') {
            var url = "<?php echo COOKIEPAY_PAY_URL; ?>"; // 실결제
        } else {
            var url = "<?php echo COOKIEPAY_TESTPAY_URL; ?>"; // 테스트결제
        }
        
        if (document.getElementById("BUYERPHONE").value == '') {
            if ($("input[name=od_b_hp]").val()) {
                var buyerphone = $("input[name=od_b_hp]").val();
            }
            else {
                var buyerphone = $("input[name=od_b_tel]").val();
            }
        }
        else {
            var buyerphone = document.getElementById("BUYERPHONE").value;
        }
        
        var params = {
            API_ID: "<?php echo $cookiepayApi['api_id']; ?>",
            ORDERNO: document.getElementById("ORDERNO").value, //주문번호 (필수)
            PRODUCTNAME: document.getElementById("PRODUCTNAME").value, //상품명 (필수)
            AMOUNT: document.getElementById("AMOUNT").value, //결제 금액 (필수)
            BUYERNAME: document.getElementById("BUYERNAME").value, //고객명 (필수)
            BUYEREMAIL: document.getElementById("BUYEREMAIL").value, //고객 e-mail (필수)
            PAYMETHOD: payMethod, //결제 수단 (선택)
            PRODUCTCODE: document.getElementById("PRODUCTCODE").value, //상품 코드 (선택)
            BUYERID: document.getElementById("BUYERID").value, //고객 아이디 (선택)
            BUYERADDRESS: document.getElementById("BUYERADDRESS").value, //고객 주소 (선택)
            BUYERPHONE : buyerphone, //고객 휴대폰번호 (선택, 웰컴페이는 필수)
            RETURNURL: document.getElementById("RETURNURL").value, //결제 완료 후 리다이렉트 url (필수)
            CANCELURL : document.getElementById("CANCELURL").value,
            PAY_TYPE : document.getElementById("PAY_TYPE").value,
            ENG_FLAG : document.getElementById("ENG_FLAG").value,
            ETC1 : document.getElementById("ETC1").value, //사용자 추가필드1 (선택)
            ETC2 : document.getElementById("ETC2").value, //사용자 추가필드2 (선택)
            ETC3 : document.getElementById("ETC3").value, //사용자 추가필드3 (선택)
            ETC4 : document.getElementById("ETC4").value, //사용자 추가필드4 (선택)
            ETC5 : document.getElementById("ETC5").value, //사용자 추가필드5 (선택)
        };
        var tryPayParams = params;
        tryPayParams['mode'] = "try_pay";
        
        var tryOrderParams = {
            mode : 'try_order',
            od_id : $("input[name=ORDERNO]").val(),
            mb_id : '<?=$member['mb_id']?>',
            od_name : $("input[name=od_name]").val(),
            od_email : $("input[name=od_email]").val(),
            od_tel : $("input[name=od_tel]").val(),
            od_hp : $("input[name=od_hp]").val(),
            od_zip : $("input[name=od_zip]").val(),
            od_addr1 : $("input[name=od_addr1]").val(),
            od_addr2 : $("input[name=od_addr2]").val(),
            od_addr3 : $("input[name=od_addr3]").val(),
            od_addr_jibeon : $("input[name=od_addr_jibeon]").val(),
            od_deposit_name : $("input[name=od_deposit_name]").val(),
            od_b_name : $("input[name=od_b_name]").val(),
            od_b_tel : $("input[name=od_b_tel]").val(),
            od_b_hp : $("input[name=od_b_hp]").val(),
            od_b_zip : $("input[name=od_b_zip]").val(),
            od_b_addr1 : $("input[name=od_b_addr1]").val(),
            od_b_addr2 : $("input[name=od_b_addr2]").val(),
            od_b_addr3 : $("input[name=od_b_addr3]").val(),
            od_b_addr_jibeon : $("input[name=od_b_addr_jibeon]").val(),
            od_memo : $("input[name=od_memo]").val(),
            od_cart_count : '<?=$cart_count?>',
            od_cart_price : '<?=$tot_ct_price?>',
            od_cart_coupon : '0',
            od_send_cost : $("input[name=od_send_cost]").val(),
            od_send_cost2 : $("input[name=od_send_cost2]").val(),
            od_send_coupon : $("input[name=od_send_coupon]").val(),
            od_receipt_price : '0',
            od_cancel_price : '0',
            od_receipt_point : '0',
            od_refund_price : '0',
            od_bank_account : '',
            od_receipt_time : '0000-00-00 00:00:00',
            od_coupon : $("input[name=od_coupon]").val(),
            od_misu : '0',
            od_shop_memo : '',
            od_mod_history : '',
            od_status : '',
            od_hope_date : $("input[name=od_hope_date]").val(),
            od_settle_case : $("input[name=od_settle_case]:checked").val(),
            od_other_pay_type : '',
            od_test : '<?=$default['de_card_test']?>',
            od_mobile : '0',
            od_pg : '',
            od_tno : '',
            od_app_no : '',
            od_escrow : '0',
            od_tax_flag : '<?=$default['de_tax_flag_use']?>',
            od_tax_mny : '0',
            od_vat_mny : '0',
            od_free_mny : '0',
            od_delivery_company : '0',
            od_invoice : '',
            od_invoice_time : '0000-00-00 00:00:00',
            od_cash : '',
            od_cash_no : '',
            od_cash_info : '',
            od_time : '<?=G5_TIME_YMDHIS?>',
            od_pwd : $("input[name=od_pwd]").val(),
            od_ip : '<?=$REMOTE_ADDR?>',
            od_price : $("input[name=od_price]").val(),
            org_od_price : $("input[name=org_od_price]").val(),
            od_goods_name : $("input[name=od_goods_name]").val(),
            od_temp_point : $("input[name=od_temp_point]").val(),
            ad_default : $("input[name=ad_default]").val(),
            it_id : $("input[name=it_id]").val(),
            cp_id : $("input[name=cp_id]").val(),
            od_cp_id : $("input[name=od_cp_id]").val(),
            sc_cp_id : $("input[name=sc_cp_id]").val(),
            comm_tax_mny : $("input[name=comm_tax_mny]").val(),
            comm_vat_mny : $("input[name=comm_vat_mny]").val(),
            comm_free_mny : $("input[name=comm_free_mny]").val(),
            ad_subject : $("input[name=ad_subject]").val(),
            item_coupon : $("input[name=item_coupon]").val(),
        };
        
        $.ajax({
            type: "POST",
            url: "<?php echo COOKIEPAY_URL ?>/cookiepay.pay.php",
            data : tryPayParams,
            cache: false,
            contentType : "application/x-www-form-urlencoded",
            success: function(tryPayRes) {
                $.ajax({
                    type: "POST",
                    url: "<?php echo COOKIEPAY_URL ?>/cookiepay.pay.php",
                    data : tryOrderParams,
                    cache: false,
                    contentType : "application/x-www-form-urlencoded",
                    success: function(tryOrderRes) {
                        $.ajax({
                            type: "POST",
                            url: url,
                            data : params,
                            cache: false,
                            contentType : "application/x-www-form-urlencoded",
                            beforeSend : function(xhr) {
                                xhr.setRequestHeader("ApiKey", "<?php echo $cookiepayApi['api_key']; ?>");
                            },
                            success: function(result) {
                                try {
                                    const obj = JSON.parse(result);
                                    console.log(obj.RTN_MSG);
                                } catch(e) {
                                    <?php if ($default['de_pg_service'] == 'COOKIEPAY_PN') { ?>
                                    $("#dialog_payment").remove();
                                    <?php } ?>
                                    const newDiv = document.createElement('div');
                                    newDiv.setAttribute("id","cookiepayform");
                                    document.body.appendChild(newDiv);
                                    $("#cookiepayform").html(result);
                                }
                            },
                        });
                    },
                });
            },
        });
    }
}
</script>
<!-- // e: cookiepay-plugin v1.2 -->
