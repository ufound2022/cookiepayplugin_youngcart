<?php
$sub_menu = '400951';
include_once('./_common.php');
include_once(G5_EDITOR_LIB);
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

auth_check_menu($auth, $sub_menu, "r");

$tab = isset($_GET['t']) ? clean_xss_tags($_GET['t'], 1, 1) : '';
if ($tab == 's') {
    $g5['title'] = '정기(구독)등록고객 - 정상';
} else if ($tab == 'c') {
    $g5['title'] = '정기(구독)등록고객 - 해지';
} else {
    $g5['title'] = '정기(구독)등록고객 - 전체내역';
}

include_once(G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

$fr_date = isset($_GET['fr_date']) ? clean_xss_tags($_GET['fr_date'], 1, 1) : '';
$fr_date = (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fr_date)) ? $fr_date : date("Y-m-01", strtotime("-1 month"));
$to_date = isset($_GET['to_date']) ? clean_xss_tags($_GET['to_date'], 1, 1) : '';
$to_date = (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $to_date)) ? $to_date : date("Y-m-d");

$from = date("Ymd", strtotime($fr_date))."00000000";
$to = date("Ymd", strtotime($to_date))."99999999";

# 정기(구독) 해지 상태 확인 > SubQuery
if(!empty($_GET['t'])) { 
    $pay_status_sql = " AND pay_status = '".$_GET['t']."' ";
}

if($_GET['fr_date'] && $_GET['to_date']) {
    $fr_data_s = date('Ymd', strtotime($_GET['fr_date']));
    $to_date_s = date('Ymd', strtotime($_GET['to_date']));

    $date_sql = " AND A.ACCEPTDATE >= '".$fr_data_s."000000'";
    $date_sql .= " AND A.ACCEPTDATE <= '".$to_date_s."235959' ";

    $sql = "select * from cookiepay_pg_subscribe_userlist A, g5_shop_order B where A.ORDERNO=B.od_id AND A.RESERVE_ORDERNO != '' AND A.TID != '' {$date_sql} {$pay_status_sql} order by A.id desc limit 1000";
} else { 
    $sql = "select * from cookiepay_pg_subscribe_userlist A, g5_shop_order B where A.ORDERNO=B.od_id AND A.RESERVE_ORDERNO != '' AND A.TID != '' {$pay_status_sql} order by A.id desc limit 1000";
}
//echo "sql :".$sql."<br>";
//$res = sql_query($sql);
$result = sql_query($sql);

?>

<style>
.dataTables_wrapper .dataTables_filter {float: left !important;text-align: left !important;margin-bottom: 6px;}
.dataTables_wrapper .dataTables_length {float: right !important;}
.dataTables_wrapper .dataTables_length select {height: 28px;}
</style>

<link rel="stylesheet" href="/cookiepay/datatable/jquery.dataTables.min.css">
<link rel="stylesheet" href="/cookiepay/modal/jquery.modal.min.css" />

<div class="btn_fixed_top">
    <a href=" https://asp.iroholdings.co.kr/shop" class="btn btn_02">쇼핑몰</a>
    <!-- <input type="submit" value="확인" class="btn_submit btn" accesskey="s"> -->
</div>
<div>
    <a href="/adm/shop_admin/cookiepay.subscribe.customer.php" class="btn btn_02">전체내역</a>
    <a href="/adm/shop_admin/cookiepay.subscribe.customer.php?t=1" class="btn btn_03">정상</a>
    <a href="/adm/shop_admin/cookiepay.subscribe.customer.php?t=2" class="btn btn_01">해지</a>
</div>
<form class="local_sch03 local_sch">
    <input type="hidden" name="t" value="">
<div class="sch_last">
    <strong>결제일</strong>
    <input type="text" id="fr_date"  name="fr_date" value="<?php echo $fr_date; ?>" class="frm_input" size="10" maxlength="10"> ~
    <input type="text" id="to_date"  name="to_date" value="<?php echo $to_date; ?>" class="frm_input" size="10" maxlength="10">
    <button type="button" onclick="javascript:set_date('오늘');">오늘</button>
    <button type="button" onclick="javascript:set_date('어제');">어제</button>
    <button type="button" onclick="javascript:set_date('이번주');">이번주</button>
    <button type="button" onclick="javascript:set_date('이번달');">이번달</button>
    <button type="button" onclick="javascript:set_date('지난주');">지난주</button>
    <button type="button" onclick="javascript:set_date('지난달');">지난달</button>
    <!-- <button type="button" onclick="javascript:set_date('전체');">전체</button> -->
    <input type="submit" value="검색" class="btn_submit">
</div>
</form>

<div class="tbl_head01 tbl_wrap">
<table id="dataTable">
    <thead>
        <tr>
            <th style="text-align:center">PG</th>
            <th style="text-align:center">등록일시</th>
            <th style="text-align:center">최근결제일</th>
            <th style="text-align:center">해지일시</th>
            <th style="text-align:center">구독번호</th>
            <th style="text-align:center">고객명</th>
            <th style="text-align:center">휴대폰번호</th>
            <th style="text-align:center">상품명</th>
            <th style="text-align:center">상품금액(1회)</th>
            <th style="text-align:center">승인번호</th>
            <th style="text-align:center">약정일/회차(총회차)</th>
            <th style="text-align:center">상태</th>
            <th style="text-align:center">카드</th>
            <th style="text-align:center">메모</th>
            <th style="text-align:center">결제여부</th>
            <th style="text-align:center">구독정보</th>
            <th style="text-align:center">주문내역</th>
            <th style="text-align:center">전표출력</th>
        </tr>
    </thead>
    <tbody>

    <?php

        $data = array();
        
        for($i=0; $row=sql_fetch_array($result); $i++) {

            $success[$i]['RESULTCODE']  = $row['RESULTCODE'];
            $success[$i]['RESULTMSG']   = $row['RESULTMSG'];
            $success[$i]['PRODUCT_NAME'] = $row['PRODUCT_NAME'];
            $success[$i]['ACCEPTNO'] = $row['ACCEPTNO'];
            $success[$i]['ACCEPTDATE'] = $row['ACCEPTDATE'];
            $success[$i]['od_name'] = $row['od_name'];
            $success[$i]['od_hp'] = $row['od_hp'];
            $success[$i]['RESERVE_PAY_DAY'] = $row['RESERVE_PAY_DAY'];
            $success[$i]['RESERVE_NOW_PAY_CNT'] = $row['RESERVE_NOW_PAY_CNT'];
            $success[$i]['RESERVE_LAST_PAY_CNT'] = $row['RESERVE_LAST_PAY_CNT'];
            $success[$i]['ORDERNO'] = $row['ORDERNO'];
            $success[$i]['RESERVE_ID'] = $row['RESERVE_ID'];
            $success[$i]['AMOUNT'] = $row['AMOUNT'];
            $success[$i]['TID'] = $row['TID'];
            $success[$i]['PGNAME'] = $row['PGNAME'];
            $success[$i]['pay_status'] = $row['pay_status'];

            $success[$i]['RESERVE_SCHEDULE_CANCEL_DATE'] = $row['RESERVE_SCHEDULE_CANCEL_DATE'];
            $success[$i]['RESERVE_LAST_PAY_DATE'] = $row['RESERVE_LAST_PAY_DATE'];
            $success[$i]['RESERVE_LAST_PAY_RESULTCODE'] = $row['RESERVE_LAST_PAY_RESULTCODE'];
            $success[$i]['RESERVE_LAST_PAY_RESULTMSG'] = $row['RESERVE_LAST_PAY_RESULTMSG'];
            $success[$i]['btnPayInfo'] = '<button type="button" id="btn_'.$row['ORDERNO'].'" class="btn-pg-cancel" data-orderno="'.$row['ORDERNO'].'" data-paystatus="'.$row['pay_status'].'" data-reservenowpaycnt="'.$row['RESERVE_NOW_PAY_CNT'].'" data-reservelastpaycnt="'.$row['RESERVE_LAST_PAY_CNT'].'" data-ordername="'.$row['od_name'].'" data-amountinfo="'.number_format($row['AMOUNT']).'" data-acceptdate="'.date('Y-m-d H:i:s', strtotime($row['ACCEPTDATE'])).'" data-productname="'.$row['PRODUCT_NAME'].'" data-reserveid="'.$row['RESERVE_ID'].'" data-schedulecanceldate="'.$row['RESERVE_SCHEDULE_CANCEL_DATE'].'">&nbsp;보기&nbsp;</button>';

        }

        $data = $success;

        if (count($data) == 0) {
            echo '<tr><td colspan="14">정기(구독) 등록 고객이 없습니다.</td></tr>';
        } else {
            
            //echo "결제내역 리스트 출력(S) <br>";
            foreach ($data as $val) {

                $pay_status = "";
                if($val['RESERVE_LAST_PAY_RESULTCODE'] == "0000") { 
                    $pay_status = "결제완료";
                } else {
                    $pay_status = "결제실패";
                }
            
                $pg_name_info = array(
                    "COOKIEPAY_TS"=>"토스페이",
                    "COOKIEPAY_KI"=>"이지페이",
                    "COOKIEPAY_KW"=>"키움페이",
                    "COOKIEPAY_DN"=>"다날",
                    "COOKIEPAY_AL"=>"모빌페이",
                    "COOKIEPAY_WP"=>"웰컴페이",
                    "COOKIEPAY_PN"=>"페이누리",
                );
            
                $pg_name_str = $pg_name_info[$val['PGNAME']];

                $pay_status_str = "정상";
                if($val['pay_status'] == "2") { 
                    $pay_status_str = "<span style='color:red'>해지</span>";
                }

    ?>
                <tr>
                    <td><?=$pg_name_str?></td>
                    <td>
                        <a href="/adm/shop_admin/orderlist.php?token=&doc=&sort1=od_id&sort2=desc&page=1&save_search=<?=$val['ORDERNO']?>&sel_field=od_id&search=<?=$val['ORDERNO']?>" target="_blank" style="color:blue;"><?=substr(date('Y-m-d H:i', strtotime($val['ACCEPTDATE'])),0, 10)?><br><?=substr(date('Y-m-d H:i', strtotime($val['ACCEPTDATE'])),11, 9)?></a>
                    </td>
                    <td>
                        <?=substr($val['RESERVE_LAST_PAY_DATE'],0, 10)?><br>
                        <?=substr($val['RESERVE_LAST_PAY_DATE'],11, 9)?>                        
                    </td>
                    <td>
                        <?=substr($val['RESERVE_SCHEDULE_CANCEL_DATE'],0, 10)?><br>
                        <?=substr($val['RESERVE_SCHEDULE_CANCEL_DATE'],11, 9)?>
                    </td>
                    <td><?=substr($val['RESERVE_ID'], 0, 10)."..."?></td>
                    <td><?=$val['od_name']?></td>
                    <td><?=$val['od_hp']?></td>
                    <td><?=$val['PRODUCT_NAME']?></td>
                    <td><?=number_format($val['AMOUNT'])?></td>
                    <td><?=$val['ACCEPTNO']?></td>
                    <td><span style="color:black;"><?=$val['RESERVE_PAY_DAY']?>일 / <?=$val['RESERVE_NOW_PAY_CNT']?> (<?=$val['RESERVE_LAST_PAY_CNT']?>)</span></td>
                    <td><?=$pay_status_str?></td>
                    <td></td>
                    <td></td>
                    <td><?=$pay_status?></td>
                    <td><?php echo $val['btnPayInfo']; ?></td>
                    <td>
                        <a href="/adm/shop_admin/orderlist.php?token=&doc=&sort1=od_id&sort2=desc&page=1&save_search=<?=$val['ORDERNO']?>&sel_field=od_id&search=<?=$val['ORDERNO']?>" target="_blank" style="color:blue;">
                            <button type="button">&nbsp;보기&nbsp;</button>
                        </a>
                    </td>
                    <td>
                        <button type="button" onclick="receipt('<?=$val['TID']?>')">전표출력</button>
                    </td>
                </tr>

    <? 
            }
        }

    ?>
        <!-- 반복(S) -->

        <!-- 반복(E) -->
    </tbody>
</table>
</div>

<div id="pg-cancel-modal" class="tbl_head02 tbl_wrap" style="display:none;">
    <input type="hidden" name="orderno" id="orderno">
    <div>
        <span class="frm_info">카드정보 및 약정일 변경을 원하시는 경우 해지 후 다시 주문해 주시기 바랍니다.</span>
    </div>
    <table>
        <colgroup>
            <col width="20%;">
            <col>
        </colgroup>
        <thead>
            <tr style="line-height:25px;">
                <th colspan="4" style="text-align:center;padding:10px;">
                    <h1>정기(구독) 상세정보</h1>
                </th>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">구독번호</th>
                <td style="padding:10px;"><span id="reserveid"></span></td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">상태</th>
                <td style="padding:10px;"><span id="paystatus"></span></td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">신청일자</th>
                <td style="padding-left:10px;">
                    <span id="acceptdate"></span>
                </td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;;">해지일자</th>
                <td style="padding-left:10px;">
                    <span id="schedule_cancel_date"></span>
                </td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">상품명</th>
                <td style="padding-left:10px;">
                    <span id="productname"></span>
                </td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">결제금액(1회)</th>
                <td style="padding-left:10px;">
                    <span id="amountinfo"></span>
                </td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">고객명</th>
                <td style="padding-left:10px;">
                    <span id="ordername"></span>
                </td>
            </tr>
            <tr style="line-height:25px;">
                <th style="text-align:center;">회차/약정회차</th>
                <td style="padding-left:10px;">
                    <span id="order_reserve_info"></span>
                </td>
            </tr>                                  
            <tr style="line-height:25px;">
                <td colspan="4" style="padding:10px;text-align:center;">
                    <button id="btn-modal-submit" class="btn_submit" type="button" style="padding:4px;border:none;margin-right:15px;width:100px;">해지하기</button>
                    <button id="btn-modal-close" type="button" style="padding:4px;border:none;width:100px;">닫기</button>
                </td>
            </tr>
        </thead>
    </table>
</div>

<script src="/cookiepay/datatable/jquery.dataTables.min.js"></script>
<script src="/cookiepay/modal/jquery.modal.min.js"></script>
<script>

$(function(){
    $("#fr_date, #to_date").datepicker({
        changeMonth: true, 
        changeYear: true, 
        dateFormat: "yy-mm-dd", 
        showButtonPanel: true, 
        yearRange: "c-99:c+99", 
        maxDate: "+0d" 
    });

    var dataTableKo = {
        "decimal" : "",
        "emptyTable" : "데이터가 없습니다.",
        "info" : "_START_ - _END_ (총 _TOTAL_ 건)",
        "infoEmpty" : "0건",
        "infoFiltered" : "(전체 _MAX_ 명 중 검색결과)",
        "infoPostFix" : "",
        "thousands" : ",",
        "lengthMenu" : "_MENU_ 개씩 보기",
        "loadingRecords" : "로딩중...",
        "processing" : "처리중...",
        "search" : "검색 : ",
        "zeroRecords" : "검색된 데이터가 없습니다.",
        "paginate" : {
            "first" : "첫 페이지",
            "last" : "마지막 페이지",
            "next" : "다음",
            "previous" : "이전"
        },
        "aria" : {
            "sortAscending" : " :  오름차순 정렬",
            "sortDescending" : " :  내림차순 정렬"
        }
    };

    var dataCount = "<?php echo count($data); ?>";
    if (dataCount > 0) {
        var table = $('#dataTable').DataTable({
            language: dataTableKo,
            order: [[1, 'desc']],
        });
    }
});

$(document).on("click", ".btn-pg-cancel", function(event) {
    var orderno = $(this).data("orderno");

    var reserveid = $(this).data("reserveid");
    var productname = $(this).data("productname");
    var acceptdate = $(this).data("acceptdate");
    var amountinfo = $(this).data("amountinfo");
    var ordername = $(this).data("ordername");
    var reservenowpaycnt = $(this).data("reservenowpaycnt");
    var reservelastpaycnt = $(this).data("reservelastpaycnt");
    var schedulecanceldate = $(this).data("schedulecanceldate");
    var paystatus = $(this).data("paystatus");
    var paystatus_str = "";

    if(paystatus == "1") { 
        paystatus_str = "정상";
    } else 
    if(paystatus == "2") { 
        paystatus_str = "해지";
        $("#btn-modal-submit").hide();
    }

    $("#orderno").val(orderno);

    // 주문내역 > 보기 테이블 상세에 내용기제(S)
    $("#reserveid").text(reserveid);
    $("#productname").text(productname);
    $("#acceptdate").text(acceptdate);
    $("#amountinfo").text(amountinfo);
    $("#ordername").text(ordername);
    $("#order_reserve_info").text(reservenowpaycnt+" / "+reservelastpaycnt);
    $("#schedule_cancel_date").text(schedulecanceldate);
    $("#paystatus").text(paystatus_str);
    // 주문내역 > 보기 테이블 상세에 내용기제(E)

    $.ajax({
        type: "POST",
        url: "./cookiepay.ajax_subscribe.php",
        cache: false,
        async: false,
        data: {
            mode: "get",
            orderno: orderno
        },
        dataType: "json",
        success: function(data) {
            if(data.error) {
                alert(data.error);
                return false;
            }

            var payAmount = data.result.payAmount;
            var cancel_able_amount = data.result.cancelAbleAmount;
            var cancel_amount = data.result.cancelAbleAmount;

            $("#cancel_able_amount").text(cancel_able_amount);
            $("#cancel_amount").val(cancel_amount);
        
            $("#pg-cancel-modal").modal({
                fadeDuration: 250
            });
        }
    });

    return false;
});

$(document).on("click", "#btn-modal-submit", function(event) {

    var reserveid = $("#reserveid").text();
    
    if (!confirm("정기(구독) 서비스를 해지 하시겠습니까?")) {
        return false;
    }

	$.ajax({
		type: "POST",
		url: "./cookiepay.subscribe.ajax.php",
		data : {
			reserveid: reserveid,
		},
		cache: false,
		beforeSend : function(xhr) {
			xhr.setRequestHeader("ApiKey", '');
			xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		},
		success: function(result){
			//$("#orderform").html(result);
			const obj = JSON.parse(result);
			
            if(obj.RESULTCODE == "0000") { 
                alert('해지 처리되었습니다.');
                location.reload();
                return;
            } else {
                alert(obj.RESULTMSG);
                location.reload();
                return;
            }
		
		}

	});

    /*
    $.ajax({
        type: "POST",
        url: "https://sandbox.cookiepayments.com/payAuth/token ",
        cache: false,
        async: false,
        data: {
            mode: "cancel",
            orderno: orderno,
            api_id: api_id,
            api_key: api_key,
            tid: tid,
            bank: bank,
            accountno: accountno,
            accountname: accountname,
            amount: amount
        },
        dataType: "json",
        success: function(data) {
            // console.log(data);

            if(data.error) {
                alert(data.error);
                return false;
            }

            cancelModalClose();
            location.reload();
        }
    });
    */


});

$(document).on("click", "#btn-modal-close", function(event) {
    cancelModalClose();
    return false;
});

function cancelModalClose() {
    $("#orderno").val();
    $("#api_id").val();
    $("#api_key").val();
    $("#tid").val();
    $("#bank").val();
    $("#accountno").val();
    $("#accountname").val();

    $.modal.close();
}

function base64_encode(str) {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
        return String.fromCharCode('0x' + p1);
    }));
}

function receipt(tid) {
    tid = base64_encode(tid);
    window.open(
        "<?php echo COOKIEPAY_RECEIPT_URL ?>?tid="+tid,
        "cookiepayments Receipt",
        "width=468,height=750"
    );
}

function receiptPaynuri(pgid, tid) {
    window.open(
        `https://pg.paynuri.com/receipt/view_receipt.do?TRANSACTIONID=${pgid}_${tid}_MNUL_1`,
        "cookiepayments Receipt",
        "width=468,height=750"
    );
}

function set_date(today)
{
    <?php
    $date_term = date('w', G5_SERVER_TIME);
    $week_term = $date_term + 7;
    $last_term = strtotime(date('Y-m-01', G5_SERVER_TIME));
    ?>
    if (today == "오늘") {
        document.getElementById("fr_date").value = "<?php echo G5_TIME_YMD; ?>";
        document.getElementById("to_date").value = "<?php echo G5_TIME_YMD; ?>";
    } else if (today == "어제") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME - 86400); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME - 86400); ?>";
    } else if (today == "이번주") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', strtotime('-'.$date_term.' days', G5_SERVER_TIME)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME); ?>";
    } else if (today == "이번달") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-01', G5_SERVER_TIME); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', G5_SERVER_TIME); ?>";
    } else if (today == "지난주") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-d', strtotime('-'.$week_term.' days', G5_SERVER_TIME)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-d', strtotime('-'.($week_term - 6).' days', G5_SERVER_TIME)); ?>";
    } else if (today == "지난달") {
        document.getElementById("fr_date").value = "<?php echo date('Y-m-01', strtotime('-1 Month', $last_term)); ?>";
        document.getElementById("to_date").value = "<?php echo date('Y-m-t', strtotime('-1 Month', $last_term)); ?>";
    } else if (today == "전체") {
        document.getElementById("fr_date").value = "";
        document.getElementById("to_date").value = "";
    }
}
</script>


<noscript>
    <p>
        귀하께서 사용하시는 브라우저는 현재 <strong>자바스크립트를 사용하지 않음</strong>으로 설정되어 있습니다.<br>
        <strong>자바스크립트를 사용하지 않음</strong>으로 설정하신 경우는 수정이나 삭제시 별도의 경고창이 나오지 않으므로 이점 주의하시기 바랍니다.
    </p>
</noscript>

</div>
<footer id="ft">
    <p>
        Copyright &copy; asp.iroholdings.co.kr. All rights reserved. Version 5.6.10<br>
        <button type="button" class="scroll_top"><span class="top_img"></span><span class="top_txt">TOP</span></button>
    </p>
</footer>
</div>

</div>

<script>
    $(".scroll_top").click(function() {
        $("body,html").animate({
            scrollTop: 0
        }, 400);
    })
</script>

<!-- <p>실행시간 : 0.018120050430298 -->

<script src="https://asp.iroholdings.co.kr/adm/admin.js?ver=2304171"></script>
<script src="https://asp.iroholdings.co.kr/js/jquery.anchorScroll.js?ver=2304171"></script>
<script>
    $(function() {

        var admin_head_height = $("#hd_top").height() + $("#container_title").height() + 5;

        $("a[href^='#']").anchorScroll({
            scrollSpeed: 0, // scroll speed
            offsetTop: admin_head_height, // offset for fixed top bars (defaults to 0)
            onScroll: function() {
                // callback on scroll start
            },
            scrollEnd: function() {
                // callback on scroll end
            }
        });

        var hide_menu = false;
        var mouse_event = false;
        var oldX = oldY = 0;

        $(document).mousemove(function(e) {
            if (oldX == 0) {
                oldX = e.pageX;
                oldY = e.pageY;
            }

            if (oldX != e.pageX || oldY != e.pageY) {
                mouse_event = true;
            }
        });

        // 주메뉴
        var $gnb = $(".gnb_1dli > a");
        $gnb.mouseover(function() {
            if (mouse_event) {
                $(".gnb_1dli").removeClass("gnb_1dli_over gnb_1dli_over2 gnb_1dli_on");
                $(this).parent().addClass("gnb_1dli_over gnb_1dli_on");
                menu_rearrange($(this).parent());
                hide_menu = false;
            }
        });

        $gnb.mouseout(function() {
            hide_menu = true;
        });

        $(".gnb_2dli").mouseover(function() {
            hide_menu = false;
        });

        $(".gnb_2dli").mouseout(function() {
            hide_menu = true;
        });

        $gnb.focusin(function() {
            $(".gnb_1dli").removeClass("gnb_1dli_over gnb_1dli_over2 gnb_1dli_on");
            $(this).parent().addClass("gnb_1dli_over gnb_1dli_on");
            menu_rearrange($(this).parent());
            hide_menu = false;
        });

        $gnb.focusout(function() {
            hide_menu = true;
        });

        $(".gnb_2da").focusin(function() {
            $(".gnb_1dli").removeClass("gnb_1dli_over gnb_1dli_over2 gnb_1dli_on");
            var $gnb_li = $(this).closest(".gnb_1dli").addClass("gnb_1dli_over gnb_1dli_on");
            menu_rearrange($(this).closest(".gnb_1dli"));
            hide_menu = false;
        });

        $(".gnb_2da").focusout(function() {
            hide_menu = true;
        });

        $('#gnb_1dul>li').bind('mouseleave', function() {
            submenu_hide();
        });

        $(document).bind('click focusin', function() {
            if (hide_menu) {
                submenu_hide();
            }
        });

        // 폰트 리사이즈 쿠키있으면 실행
        var font_resize_act = get_cookie("ck_font_resize_act");
        if (font_resize_act != "") {
            font_resize("container", font_resize_act);
        }
    });

    function submenu_hide() {
        $(".gnb_1dli").removeClass("gnb_1dli_over gnb_1dli_over2 gnb_1dli_on");
    }

    function menu_rearrange(el) {
        var width = $("#gnb_1dul").width();
        var left = w1 = w2 = 0;
        var idx = $(".gnb_1dli").index(el);

        for (i = 0; i <= idx; i++) {
            w1 = $(".gnb_1dli:eq(" + i + ")").outerWidth();
            w2 = $(".gnb_2dli > a:eq(" + i + ")").outerWidth(true);

            if ((left + w2) > width) {
                el.removeClass("gnb_1dli_over").addClass("gnb_1dli_over2");
            }

            left += w1;
        }
    }
</script>


<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');