<?php
include_once('../shop/_common.php');

// PG 결제창 팝업 및 주문화면

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

$cookiepayApi = cookiepay_get_api_account($default);
?>

<script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
<script src="https://js.tosspayments.com/v1"></script>
<script>
$(function(){
    pay();
});

function pay() {
    var payMethod = '';
    switch ("<?php echo $_GET['pm']; ?>") {
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

    var params = {
        API_ID: "<?php echo $cookiepayApi['api_id']; ?>",
        ORDERNO: opener.document.getElementById("ORDERNO").value, //주문번호 (필수)
        PRODUCTNAME: opener.document.getElementById("PRODUCTNAME").value, //상품명 (필수)
        AMOUNT: opener.document.getElementById("AMOUNT").value, //결제 금액 (필수)
        BUYERNAME: opener.document.getElementById("BUYERNAME").value, //고객명 (필수)
        BUYEREMAIL: opener.document.getElementById("BUYEREMAIL").value, //고객 e-mail (필수)
        PAYMETHOD: payMethod, //결제 수단 (선택)
        PRODUCTCODE: opener.document.getElementById("PRODUCTCODE").value, //상품 코드 (선택)
        BUYERID: opener.document.getElementById("BUYERID").value, //고객 아이디 (선택)
        BUYERADDRESS: opener.document.getElementById("BUYERADDRESS").value, //고객 주소 (선택)
        BUYERPHONE : opener.document.getElementById("BUYERPHONE").value, //고객 휴대폰번호 (선택, 웰컴페이는 필수)
        RETURNURL: opener.document.getElementById("RETURNURL").value, //결제 완료 후 리다이렉트 url (필수)
        CANCELURL : opener.document.getElementById("CANCELURL").value,
        ETC1 : opener.document.getElementById("ETC1").value, //사용자 추가필드1 (선택)
        ETC2 : opener.document.getElementById("ETC2").value, //사용자 추가필드2 (선택)
        ETC3 : opener.document.getElementById("ETC3").value, //사용자 추가필드3 (선택)
        ETC4 : opener.document.getElementById("ETC4").value, //사용자 추가필드4 (선택)
        ETC5 : opener.document.getElementById("ETC5").value, //사용자 추가필드5 (선택)
    };

    var tryPayParams = params;
    tryPayParams['mode'] = "try_pay";

    $.ajax({
        type: "POST",
        url: "<?php echo COOKIEPAY_URL ?>/cookiepay.pay.php",
        data : tryPayParams,
        cache: false,
        contentType : "application/x-www-form-urlencoded",
        success: function(tryPayRes) {
            
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
                        const newDiv = document.createElement('div');
                        newDiv.setAttribute("id","cookiepayform");
                        document.body.appendChild(newDiv);
                        $("#cookiepayform").html(result);
                    }
                },
            });

        },
    });
}
</script>
