<?php
include_once('../shop/_common.php');

// PG 결제창 팝업 및 주문화면

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

$payTypeCode = isset($_GET['pt']) ? clean_xss_tags($_GET['pt'], 1, 1) : 3;

$payType = isset($_GET['pm']) ? clean_xss_tags($_GET['pm'], 1, 1) : '';

if ($payType == "수기결제") {
    $payTypeCode = 1;
}

$cookiepayApi = cookiepay_get_api_account_info($default, $payTypeCode);
?>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>쿠키페이 결제 ver.1.2</title>
</head>
<body>

<script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
<script src="https://js.tosspayments.com/v1"></script>

<?php if ($default['de_pg_service'] == 'COOKIEPAY_PN') { ?>

<script src="https://www.cookiepayments.com/js/paynuri.js"></script>
<script src="https://pg.paynuri.com/js/jquery-ui-1.11.4.base/jquery-ui.min.js"></script>
<script src="https://pg.paynuri.com/js/jquery-dateFormat.min.js"></script>

<?php } ?>

<script>
$(function(){
    var browserName = checkBrowser();
    if (browserName != "Safari" && browserName != "other") {
        var pgWin2 = window.open("blank", "pgWin2", "left=-10000, top=0, width=10, height=10");

        if(!pgWin2 || pgWin2.closed || typeof pgWin2.closed=='undefined') { 
            alert("팝업이 차단되어 있습니다.\n팝업 차단 해제 후 다시 시도해 주세요.");
        } else {
            pgWin2.close();
        }
    }

    var payType = "<?php echo $payType; ?>";

    if (payType == "수기결제") {
        onlyNumber();
        needPassword();
        selectAllText();
        payKeyin();
    } else {
        pay_dist();
    }
});

function pay_dist() {
    var payType = "<?php echo $payType; ?>";
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

    var payTypeCode = opener.document.getElementById("PAY_TYPE").value;
    if (payTypeCode != 3) {
        // 
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
        PAY_TYPE : payTypeCode,
        ENG_FLAG : opener.document.getElementById("ENG_FLAG").value,
        ETC1 : opener.document.getElementById("ETC1").value, //사용자 추가필드1 (선택)
        ETC2 : opener.document.getElementById("ETC2").value, //사용자 추가필드2 (선택)
        ETC3 : opener.document.getElementById("ETC3").value, //사용자 추가필드3 (선택)
        ETC4 : opener.document.getElementById("ETC4").value, //사용자 추가필드4 (선택)
        ETC5 : opener.document.getElementById("ETC5").value, //사용자 추가필드5 (선택)
        ORDER_NO_CHECK : "N",
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
}

function onlyNumber(){
    $(".only-number").on("keyup", function(e){
        $this = $(this);
        var replacedVal = $this.val().replace(/\D/g, "");
        $this.val(replacedVal);

        var nextCount = $this.data("nextcount");
        var nextId = $this.data("nextid");

        if ($this.val().length > nextCount){
            $this.val($this.val().slice(0, nextCount));
        }
        
        if (nextCount !== undefined && nextId !== undefined) {
            if (nextCount == $this.val().length) {
                $(`#${nextId}`).focus();
            }
        }
    });
}

function needPassword(){
    $("#use_hanacard").on("change", function(){
        if ($(this).is(":checked")) {
            $("#birthday_tr").removeClass("d-none");
            $("#password_tr").removeClass("d-none");
        } else {
            $("#birthday_tr").addClass("d-none");
            $("#password_tr").addClass("d-none");
        }
    });
}

function selectAllText(){
    $("#CARDNO1, #CARDNO2, #CARDNO3, #CARDNO4, #EXPIREDT1, #EXPIREDT2").on("click focus", function(e){
        $(this).select();
    });
}

function payKeyin(){
    $("#btn_pay_keyin").on("click", function(e){
        e.preventDefault();
        var valid = true;
        $(".required").each(function(){
            var nextCount = $(this).data("nextcount");
            var thisId = $(this).attr("id");
            if (thisId == "CARDNO4") {
                nextCount = 3; // lotte card 대응
            }
            if ($(this).val().length < nextCount) {
                alert("[" + $(this).parent().parent().children("th").text() + "] 필수 입력 항목입니다.");
                $(this).focus();
                valid = false;
                return false;
            }
        });
        if (!valid) {
            return false;
        }
        if ($("#use_hanacard").is(":checked")) {
            if ($("#CARDAUTH").val().length < 6 || $("#CARDAUTH").val().length > 13) {
                alert("생년월일 (사업자등록번호)를 확인해 주세요.");
                $("#CARDAUTH").focus();
                return false;
            }
            if ($("#CARDPWD").val().length < 2) {
                alert("카드 비밀번호를 확인해 주세요.");
                $("#CARDPWD").focus();
                return false;
            }
        }
        
        <?php
        // s: cookiepay-plugin v1.2
        if ($default['de_pg_service'] == 'COOKIEPAY_TS') {
        ?>
            if ($("#CARDAUTH").val().length < 6 || $("#CARDAUTH").val().length > 13) {
                alert("생년월일 (사업자등록번호)를 확인해 주세요.");
                $("#CARDAUTH").focus();
                return false;
            }
        <?php
        }
        // e: cookiepay-plugin v1.2
        ?>
        
        if (!$("#agree").is(":checked")) {
            alert("[카드 소유주가 본 결제에 대해 동의하였음을 확인합니다]에 동의해 주세요.");
            $("#agree").focus();
            return false;
        }
        
        $("#btn_pay_keyin").text("결제 진행 중···").attr("disabled", true).addClass("btn-disabled");
        // $("#btn_pay_keyin").text("결제하기").attr("disabled", false).removeClass("btn-disabled");
        
        var cardNumber = $("#CARDNO1").val() + $("#CARDNO2").val() + $("#CARDNO3").val() + $("#CARDNO4").val();
        var cardExpireDt = $("#EXPIREDT1").val() + $("#EXPIREDT2").val();
        var useHanacard = $("input:checkbox[name='use_hanacard']:checked").val();
        if (useHanacard == 1) {
            $("input[name='HANACARD_USE']").val("Y");
        } else {
            $("input[name='HANACARD_USE']").val('');
        }
        
        $("input[name='ORDERNO']").val(opener.document.getElementById("ORDERNO").value);
        $("input[name='PRODUCTNAME']").val(opener.document.getElementById("PRODUCTNAME").value);
        $("input[name='AMOUNT']").val(opener.document.getElementById("AMOUNT").value);
        $("input[name='BUYERNAME']").val(opener.document.getElementById("BUYERNAME").value);
        $("input[name='BUYEREMAIL']").val(opener.document.getElementById("BUYEREMAIL").value);
        $("input[name='PRODUCTCODE']").val(opener.document.getElementById("PRODUCTCODE").value);
        $("input[name='BUYERID']").val(opener.document.getElementById("BUYERID").value);
        $("input[name='BUYERADDRESS']").val(opener.document.getElementById("BUYERADDRESS").value);
        $("input[name='BUYERPHONE']").val(opener.document.getElementById("BUYERPHONE").value);
        $("input[name='CARDNO']").val(cardNumber);
        $("input[name='EXPIREDT']").val(cardExpireDt);
        
        <!-- // s: cookiepay-plugin v1.2 -->
        $("input[name='CARDAUTH']").val($("#CARDAUTH").val());
        <!-- // e: cookiepay-plugin v1.2 -->
        
        $("input[name='ETC1']").val(opener.document.getElementById("ETC1").value);
        $("input[name='ETC2']").val(opener.document.getElementById("ETC2").value);
        $("input[name='ETC3']").val(opener.document.getElementById("ETC3").value);
        $("input[name='ETC4']").val(opener.document.getElementById("ETC4").value);
        $("input[name='ETC5']").val(opener.document.getElementById("ETC5").value);

        $("#payform").submit();
    });
}

function checkBrowser() { 
    var agent = window.navigator.userAgent.toLowerCase(); 
    var browserName; 
    switch (true) { 
        case agent.indexOf("edge") > -1:  
            browserName = "MS Edge"; // MS 엣지 
            break; 
        case agent.indexOf("edg/") > -1:  
            browserName = "Edge (chromium based)"; // 크롬 기반 엣지 
            break; 
        case agent.indexOf("opr") > -1 && !!window.opr:  
            browserName = "Opera"; // 오페라 
            break; 
        case agent.indexOf("chrome") > -1 && !!window.chrome:  
            browserName = "Chrome"; // 크롬 
            break; 
        case agent.indexOf("trident") > -1:  
            browserName = "MS IE"; // 익스플로러 
            break; 
        case agent.indexOf("firefox") > -1:  
            browserName = "Mozilla Firefox"; // 파이어 폭스 
            break; 
        case agent.indexOf("safari") > -1:  
            browserName = "Safari"; // 사파리 
            break; 
        default:  
            browserName = "other"; // 기타 
    }
    return browserName;
}
</script>

<?php
if ($payType == "수기결제") {
    $needPassword = false;

    $needPasswordPg = [
        "COOKIEPAY_KI", 
        "COOKIEPAY_KW", 
        "COOKIEPAY_AL", 
        "COOKIEPAY_WP",
        "COOKIEPAY_PN",
    ];

    if (in_array($default["de_pg_service"], $needPasswordPg)) {
        $needPassword = true;
    }
?>

<style>
.title-bg {
    background-color: #E5F0FF;
    color: #000;
    height: 3rem;
    line-height: 3rem;
    font-size: 1.4rem;
    font-weight: 600;
}
.frm_input {
    height: 2rem;
    line-height: 2rem;
    border: 1px solid #d0d3db;
    background: #fff;
    color: #000;
    vertical-align: middle;
    border-radius: 3px;
    padding: 5px;
    -webkit-box-shadow: inset 0 1px 1px rgb(0 0 0 / 8%);
    -moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
    box-shadow: inset 0 1px 1px rgb(0 0 0 / 8%);
}
.text-center {text-align: center;}
.w-25 {width: 24.2%;}
.w-50 {width: 49.4%;}
.w-100 {width: 100%;}
.mr-05rem {margin-right:.5rem;}
.mr-1rem {margin-right: 1rem;}
.pt-05rem {padding-top: .5rem;}
.pt-1rem {padding-top: 1rem;}
.pt-5rem {padding-top: 5rem;}
table {
    width: 100%;
    font-size: .85rem !important;
}
.keyin-table th {
    /* width: 10.4rem; */
    width: 7rem;
    height: 3rem;
    line-height: 2.4rem;
    text-align: center;
    background-color: #F6F6F6;
    padding-left: .6rem;
}
.btn {
    height: 50px;
    font-weight: bold;
    font-size: 1.25em;
    cursor: pointer;
    color: #fff;
    border-radius: 3px;
}
.btn-payment {
    width: 50%;
    border: 1px solid #3a8afd;
    background: #3a8afd;
}
.btn-cancel {
    width: 25%;
    border: 1px solid #ddd;
    background: #eee;
    color: #999;
}
.btn-disabled {
    background-color: skyblue;
    border: 1px solid lightblue;
}
.d-none {display: none;}
</style>
<div>
    <div class="text-center title-bg">
        <span>수기결제</span>
    </div>
    <form name="payform" id="payform" method="post" action="<?php echo COOKIEPAY_URL; ?>/cookiepay.pay.php">
    <input type="hidden" name="mode" value="keyin_pay">
    <input type="hidden" name="ORDERNO">
    <input type="hidden" name="PRODUCTNAME">
    <input type="hidden" name="AMOUNT">
    <input type="hidden" name="BUYERNAME">
    <input type="hidden" name="BUYEREMAIL">
    <input type="hidden" name="PRODUCTCODE">
    <input type="hidden" name="BUYERID">
    <input type="hidden" name="BUYERADDRESS">
    <input type="hidden" name="BUYERPHONE">
    <input type="hidden" name="CARDNO">
    <input type="hidden" name="EXPIREDT">
    
    <!-- // s: cookiepay-plugin v1.2 -->
    <input type="hidden" name="CARDAUTH">
    <!-- // e: cookiepay-plugin v1.2 -->
    
    <!-- <input type="hidden" name="CARDPWD"> -->
    <!-- <input type="hidden" name="QUOTA"> -->
    
    <?php if ($default['de_pg_service'] == 'COOKIEPAY_PN') { ?>
    <input type="hidden" name="TAXYN" value="Y">
    <?php } ?>

    <input type="hidden" name="HANACARD_USE">
    <input type="hidden" name="ETC1">
    <input type="hidden" name="ETC2">
    <input type="hidden" name="ETC3">
    <input type="hidden" name="ETC4">
    <input type="hidden" name="ETC5">
    <div class="keyin-table pt-1rem">
    <table>
        <tbody>
            <tr>
                <th>카드번호</th>
                <td>
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="4" data-nextid="CARDNO2" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" name="CARDNO1" id="CARDNO1" maxlength="4" placeholder="●●●●" autocomplete="off">
                </td>
                <td>
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="4" data-nextid="CARDNO3" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" name="CARDNO2" id="CARDNO2" maxlength="4" placeholder="●●●●" autocomplete="off">
                </td>
                <td>
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="4" data-nextid="CARDNO4" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" style="-webkit-text-security: disc;" name="CARDNO3" id="CARDNO3" maxlength="4" placeholder="●●●●" autocomplete="off">
                </td>
                <td>
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="4" data-nextid="EXPIREDT2" type="text" pattern="[0-9]*" inputmode="numeric" min="111" max="9999" style="-webkit-text-security: disc;" name="CARDNO4" id="CARDNO4" maxlength="4" placeholder="●●●●" autocomplete="off">
                </td>
            </tr>
            <tr>
                <th>유효기간</th>
                <td colspan="2">
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="2" data-nextid="EXPIREDT1" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" name="EXPIREDT2" id="EXPIREDT2" maxlength="2" placeholder="MM(월)" autocomplete="off">
                </td>
                <td colspan="2">
                    <input class="frm_input w-100 text-center only-number required" data-nextcount="2" data-nextid="QUOTA" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" name="EXPIREDT1" id="EXPIREDT1" maxlength="2" placeholder="YY(년)" autocomplete="off">
                </td>
            </tr>
            <tr>
                <th>할부개월</th>
                <td colspan="4">
                    <select class="frm_input w-100" name="QUOTA" id="QUOTA">
                        <option value="00">일시불</option>
                        <option value="02">2개월</option>
                        <option value="03">3개월</option>
                        <option value="04">4개월</option>
                        <option value="05">5개월</option>
                        <option value="06">6개월</option>
                        <option value="07">7개월</option>
                        <option value="08">8개월</option>
                        <option value="09">9개월</option>
                        <option value="10">10개월</option>
                        <option value="11">11개월</option>
                        <option value="12">12개월</option>
                    </select>
                </td>
            </tr>
            <tr class="<?php echo $needPassword ? '' : 'd-none' ?>">
                <th>결제카드</th>
                <td colspan="4">
                    <input type="checkbox" name="use_hanacard" id="use_hanacard" value="1"> 
                    <label for="use_hanacard"> <strong>[하나카드]</strong>로 결제하시는 경우 체크해 주세요.</label>
                </td>
            </tr>
            
            <!-- // s: cookiepay-plugin v1.2 -->
            <tr class="<?php if ($default['de_pg_service'] != 'COOKIEPAY_TS') { echo "d-none"; }?>" id="birthday_tr">
            <!-- // e: cookiepay-plugin v1.2 -->
            
                <th>생년월일</th>
                <td colspan="4">
                    <input class="frm_input w-100 text-center only-number" type="text" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" name="CARDAUTH" id="CARDAUTH" maxlength="13" placeholder="- 없이 숫자만 입력" autocomplete="off">
                    <br>
                    <small>개인(생년월일) : 761203 / 법인(사업자등록번호) : 1231212345</small>
                </td>
            </tr>
            <tr class="d-none" id="password_tr">
                <th>카드 비밀번호</th>
                <td colspan="4">
                    <input class="frm_input w-100 text-center only-number" type="text" name="CARDPWD" id="CARDPWD" pattern="[0-9]*" inputmode="numeric" min="1111" max="9999" style="-webkit-text-security: disc;" maxlength="2" placeholder="" autocomplete="new-password">
                    <br>
                    <small>카드비밀번호 앞 2자리</small>
                </td>
            </tr>
            <tr>
                <td colspan="5" class="pt-05rem text-center">
                    <input type="checkbox" name="agree" id="agree" value="1"> 
                    <label for="agree"> * 카드 소유주가 본 결제에 대해 동의하였음을 확인합니다.</label>
                </td>
            </tr>
            <tr>
                <td colspan="5" class="pt-1rem text-center">
                    <button type="button" class="mr-1rem btn btn-payment" id="btn_pay_keyin">결제하기</button>
                    <button type="button" class="btn btn-cancel" onClick="self.close();">취소</button>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
    </form>
</div>

<?php
} // end if ($payType == "수기결제")
else {
    if ($default['de_pg_service'] == 'COOKIEPAY_PN') {
?>
    <script>
        document.querySelector('body').style.margin='0px';
    </script>
<?
    } else {
?>
<div>
    <small>결제를 돕기 위해 생성된 창 입니다.</small>
    <h3>결제가 진행 중인 경우 이 창을 닫지 말아주세요.</h3>
    <small>결제를 중지한 경우에도 현재 창이 보이신다면 <button type="button" onClick="opener.location.reload();self.close();">닫기</button>를 눌러주세요.</small>
</div>

<?php
    }
}
?>

</body>
</html>