<?php
$sub_menu = "400900";
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 로그인 체크
if (empty($is_member)) {
    echo "<script language='javascript'> alert('잘못된 접근입니다.'); self.close(); </script> ";
    exit;
}

// s: cookiepay-plugin > 보안검토 항목8 > v1.3 > 261014
// 위쪽 로그인 체크는 "로그인했는가"만 본다. 주소의 order_no 를 바꾸면 다른 고객의
// 정기결제를 조회할 수 있고, 화면에는 이름·상품·금액과 BILLKEY 까지 출력된다.
// 조회 값을 쓰기 전에 그 정기결제가 로그인한 본인의 것인지 확인한다.
// (외부 입력을 쿼리에 그대로 넣던 부분도 함께 이스케이프한다)
$guardOrderNo = isset($_GET['order_no']) ? clean_xss_tags($_GET['order_no'], 1, 1) : '';
$guardOrderNoSql = sql_real_escape_string((string) $guardOrderNo);
// e: cookiepay-plugin > 보안검토 항목8 > v1.3 > 261014

$pss = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_RESULT." WHERE ORDERNO='{$guardOrderNoSql}' ORDER BY `id` DESC LIMIT 1 ");
$psu = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_SUBSCRIBE_USERLIST." WHERE RESERVE_ID='" . sql_real_escape_string((string) (isset($pss['RESERVE_ID']) ? $pss['RESERVE_ID'] : '')) . "' ORDER BY `id` DESC LIMIT 1 ");
$od = sql_fetch(" SELECT * FROM {$g5['g5_shop_order_table']} WHERE od_id='{$guardOrderNoSql}' ORDER BY `od_id` DESC LIMIT 1 ");

// s: cookiepay-plugin > 보안검토 항목8 > v1.3 > 261014
require_once G5_PATH."/cookiepay/cookiepay.guard.php";

// 소유자는 정기결제의 USERID 를 쓰되, 비어 있으면 원 주문의 회원 아이디로 본다.
$guardOwner = !empty($pss['USERID']) ? $pss['USERID'] : (isset($od['mb_id']) ? $od['mb_id'] : '');

$guardRet = cookiepay_guard_subscribe_owner(array('mb_id' => $guardOwner), $member['mb_id']);

if (!$guardRet['ok']) {
    @cookiepay_payment_log("정기(구독) 조회 차단 ({$guardRet['code']})", "요청자 {$member['mb_id']} / ORDERNO {$guardOrderNo}", 3);
    echo "<script language='javascript'> alert('잘못된 접근입니다.'); self.close(); </script> ";
    exit;
}
// e: cookiepay-plugin > 보안검토 항목8 > v1.3 > 261014

$pay_status_str = "정상";
if($psu['pay_status'] == "2") { 
    $pay_status_str = "<span style='color:red'>해지</span>";
}

?>

<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>쿠키페이 결제 ver.1.2</title>
</head>
<body>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://js.tosspayments.com/v1"></script>

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
        <span>정기(구독) 상세정보</span>
    </div>

    <div class="keyin-table pt-1rem">
        
    <table>
        <tbody>
            <tr>
                <th style="width:20%;padding:3px;">구독번호</th>
                <td style="width:30%;padding:3px;">
                    <?=substr($pss['RESERVE_ID'], 0, 10)?>
                </td>
                <th style="width:20%;padding:3px;">상태</th>
                <td style="width:30%;padding:3px;">
                    <?=$pay_status_str?>
                </td>
            </tr>
            <tr>
                <th style="width:20%;padding:3px;">신청일자</th>
                <td style="width:30%;padding:3px;">
                    <? echo substr($pss['PAY_DATE'], 0, 16); ?>
                </td>
                <th style="width:20%;padding:3px;">해지일자</th>
                <td style="width:30%;padding:3px;">
                    <? echo substr($psu['RESERVE_SCHEDULE_CANCEL_DATE'],0,16); ?>
                </td>                
            </tr>
            <tr>
                <th style="width:20%;padding:3px;">상품명</th>
                <td style="width:30%;padding:3px;">
                    <?php echo $pss['PRODUCT_NAME']; ?>
                </td>
                <th style="width:20%;padding:3px;">결제금액(1회)</th>
                <td style="width:30%;padding:3px;">
                    <?php echo number_format($pss['AMOUNT']); ?>
                </td>
            </tr>
            <tr>
                <th style="width:20%;padding:3px;">고객명</th>
                <td style="width:30%;padding:3px;">
                    <?php echo $od['od_name']; ?>
                </td>
                <th style="width:20%;padding:3px;">회차/약정회차</th>
                <td style="width:30%;padding:3px;">
                    <?php echo $pss['RESERVE_NOW_PAY_CNT'];?> / <?php echo $pss['RESERVE_LAST_PAY_CNT']; ?>
                </td>
            </tr>    

            <tr>
                <td colspan="4" class="pt-1rem text-center">

                <?php
                if(empty($psu['RESERVE_SCHEDULE_CANCEL_DATE'])) { 
                ?>
                    <button type="button" class="mr-1rem btn btn-payment" id="btn_pay_keyin">해지하기</button>
                <? } ?>
                    <button type="button" class="btn btn-cancel" onClick="self.close();">닫기</button>
                </td>
            </tr>
        </tbody>
    </table>

    </div>
    </form>
</div>


<script language='javascript'>

    $(document).on("click", ".btn-payment", function(event) {
        
        if (!confirm("정기(구독) 서비스를 해지 하시겠습니까?")) {
            return false;
        }

        $.ajax({
            type: "POST",
            url: "./cookiepay.subscribe.ajax.php",
            data : {
                reserveid: "<?=$pss['RESERVE_ID']?>",
                billkey : "<?=$pss['BILLKEY']?>",
            },
            cache: false,
            beforeSend : function(xhr) {
                xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
            },
            success: function(result){
                //$("#orderform").html(result);
                const obj = JSON.parse(result);
                
                if(obj.RESULTCODE == "0000") { 
                    alert('해지 처리되었습니다.');
                    opener.location.reload();
                    self.close();
                    return;
                } else {
                    alert(obj.RESULTMSG);
                    return;
                }
            
            }

        });




    });

	function set_quota(f, count) {
		var quota = document.payform.LAST_PAY_CNT;
		if(f.checked)
		{
			quota.setAttribute('readonly',"");
			quota.value = "0";
		}else{
			quota.removeAttribute('readonly');
			quota.value = count;
		}
	}
</script>    
</body>
</html>