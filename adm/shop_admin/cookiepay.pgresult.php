<?php
$sub_menu = '400900';
include_once('./_common.php');
include_once(G5_EDITOR_LIB);
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

auth_check_menu($auth, $sub_menu, "r");

$tab = isset($_GET['t']) ? clean_xss_tags($_GET['t'], 1, 1) : '';
if ($tab == 's') {
    $g5['title'] = '결제내역 - 결제성공';
} else if ($tab == 'c') {
    $g5['title'] = '결제내역 - 결제취소';
} else {
    $g5['title'] = '결제내역 - 전체내역';
}

include_once(G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

$data = [];
$success = [];
$cancel = [];
$cancelOrderno = [];

$fr_date = isset($_GET['fr_date']) ? clean_xss_tags($_GET['fr_date'], 1, 1) : '';
$fr_date = (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fr_date)) ? $fr_date : date("Y-m-01", strtotime("-1 month"));
$to_date = isset($_GET['to_date']) ? clean_xss_tags($_GET['to_date'], 1, 1) : '';
$to_date = (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $to_date)) ? $to_date : date("Y-m-d");

$from = date("Ymd", strtotime($fr_date))."00000000";
$to = date("Ymd", strtotime($to_date))."99999999";

$sql = "SELECT (SELECT SUM(cancel_amt) FROM cookiepay_pg_cancel WHERE orderno=R.ORDERNO) AS CANCEL_SUM_AMOUNT, C.cancel_tid, C.cancel_code, C.cancel_date, C.cancel_amt, R.*, V.PAYMETHOD, V.TID AS verify_tid, V.BUYERNAME, V.BUYEREMAIL, V.PRODUCTNAME, V.PRODUCTCODE, V.BUYERID, V.CARDCODE AS verify_cardcode FROM cookiepay_pg_cancel AS C LEFT JOIN cookiepay_pg_result AS R ON C.orderno=R.ORDERNO LEFT JOIN cookiepay_pg_verify AS V ON C.orderno=V.ORDERNO WHERE C.cancel_code='0000' AND R.RESULTCODE='0000' AND V.RESULTCODE='0000' AND C.cancel_date BETWEEN '{$from}' AND '{$to}' ORDER BY C.cancel_date DESC, C.id DESC";
$res = sql_query($sql);
for($i=0; $row=sql_fetch_array($res); $i++) {
    $cancelOrderno[$row['ORDERNO']] = $row['CANCEL_SUM_AMOUNT'];

    $cancel[$i]['cookiepayPg'] = '';
    $cancel[$i]['apiId'] = '';
    $cancel[$i]['apiKey'] = '';
    $cancel[$i]['status'] = '';
    $cancel[$i]['btnPgCancel'] = '';
    $cancel[$i]['payMethod'] = '';
    $cancel[$i]['orderno'] = $row['ORDERNO'];
    $cancel[$i]['TID'] = $row['verify_tid'];
    $cancel[$i]['amount'] = 0;
    $cancel[$i]['cancelAmount'] = isset($row['cancel_amt']) && !empty($row['cancel_amt']) ? $row['cancel_amt'] : 0;
    $cancel[$i]['CANCEL_SUM_AMOUNT'] = isset($row['CANCEL_SUM_AMOUNT']) && !empty($row['CANCEL_SUM_AMOUNT']) ? $row['CANCEL_SUM_AMOUNT'] : 0;
    $cancel[$i]['ACCEPTDATE'] = $row['ACCEPTDATE'];
    $cancel[$i]['ACCEPTNO'] = $row['ACCEPTNO'];
    $cancel[$i]['CANCELDATE'] = $row['cancel_date'];
    $cancel[$i]['BUYERNAME'] = $row['BUYERNAME'];
    $cancel[$i]['BUYERID'] = $row['BUYERID'];
    $cancel[$i]['PRODUCTNAME'] = $row['PRODUCTNAME'];

    $pgUpper = strtoupper($row['PGNAME']);
    $pgLower = strtolower($pgUpper);
    $cancel[$i]['cookiepayPg'] = mb_substr(COOKIEPAY_PG[$pgUpper], 0, 2);
    $cancel[$i]['cookiepayPg'] = $cancel[$i]['cookiepayPg'] == '페이' ? '페누' : $cancel[$i]['cookiepayPg'];

    $cancel[$i]['apiId'] = $default["de_{$pgLower}_cookiepay_id"];
    $cancel[$i]['apiKey'] = $default["de_{$pgLower}_cookiepay_key"];
    
    switch ($row['PAYMETHOD']) {
        case 'CARD':
            $cancel[$i]['payMethod'] = '카드';
            break;
        case 'BANK':
            $cancel[$i]['payMethod'] = '계좌이체';
            break;
        case 'VACCT':
            $cancel[$i]['payMethod'] = '가상계좌';
            break;
        case 'MOBILE':
            $cancel[$i]['payMethod'] = '휴대폰';
            break;
        case 'CARD_SUGI':
            $cancel[$i]['payMethod'] = '수기결제';
            $cancel[$i]['apiId'] = $default["de_{$pgLower}_cookiepay_id_keyin"];
            $cancel[$i]['apiKey'] = $default["de_{$pgLower}_cookiepay_key_keyin"];
            break;
        default:
            $cancel[$i]['payMethod'] = $row['PAYMETHOD'];
    }

    $cancel[$i]['status'] = '<span style="color:red;">취소</span>';
}

$sql = "SELECT R.*, V.PAYMETHOD, V.TID AS verify_tid, V.BUYERNAME, V.BUYEREMAIL, V.PRODUCTNAME, V.PRODUCTCODE, V.BUYERID, V.CARDCODE AS verify_cardcode FROM cookiepay_pg_result AS R LEFT JOIN cookiepay_pg_verify AS V ON R.ORDERNO=V.ORDERNO WHERE R.RESULTCODE='0000' AND R.ACCEPTDATE BETWEEN '{$from}' AND '{$to}'  ORDER BY R.ACCEPTDATE DESC, R.id DESC";
$res = sql_query($sql);
for($i=0; $row=sql_fetch_array($res); $i++) {
    $success[$i]['cookiepayPg'] = '';
    $success[$i]['apiId'] = '';
    $success[$i]['apiKey'] = '';
    $success[$i]['status'] = '';
    $success[$i]['btnPgCancel'] = '';
    $success[$i]['payMethod'] = '';
    $success[$i]['orderno'] = $row['ORDERNO'];
    $success[$i]['TID'] = $row['verify_tid'];
    $success[$i]['amount'] = isset($row['AMOUNT']) && !empty($row['AMOUNT']) ? $row['AMOUNT'] : 0;
    $success[$i]['cancelAmount'] = 0;
    $success[$i]['CANCEL_SUM_AMOUNT'] = isset($row['CANCEL_SUM_AMOUNT']) && !empty($row['CANCEL_SUM_AMOUNT']) ? $row['CANCEL_SUM_AMOUNT'] : 0;
    $success[$i]['ACCEPTDATE'] = $row['ACCEPTDATE'];
    $success[$i]['ACCEPTNO'] = $row['ACCEPTNO'];
    $success[$i]['CANCELDATE'] = "";
    $success[$i]['BUYERNAME'] = $row['BUYERNAME'];
    $success[$i]['BUYERID'] = $row['BUYERID'];
    $success[$i]['PRODUCTNAME'] = $row['PRODUCTNAME'];

    $pgUpper = strtoupper($row['PGNAME']);
    $pgLower = strtolower($pgUpper);
    $success[$i]['cookiepayPg'] = mb_substr(COOKIEPAY_PG[$pgUpper], 0, 2);
    $success[$i]['cookiepayPg'] = $success[$i]['cookiepayPg'] == '페이' ? '페누' : $success[$i]['cookiepayPg'];

    $cookiepayApi = cookiepay_get_api_account_info_by_pg($default, $pgLower, 3); // 신용카드
    // $success[$i]['apiId'] = $default["de_{$pgLower}_cookiepay_id"];
    // $success[$i]['apiKey'] = $default["de_{$pgLower}_cookiepay_key"];
    
    switch ($row['PAYMETHOD']) {
        case 'CARD':
            $success[$i]['payMethod'] = '카드';
            break;
        case 'BANK':
            $success[$i]['payMethod'] = '계좌이체';
            break;
        case 'VACCT':
            $success[$i]['payMethod'] = '가상계좌';
            break;
        case 'MOBILE':
            $success[$i]['payMethod'] = '휴대폰';
            break;
        case 'CARD_SUGI':
            $success[$i]['payMethod'] = '수기결제';
            $cookiepayApi = cookiepay_get_api_account_info_by_pg($default, $pgLower, 1); // 수기결제
            // $success[$i]['apiId'] = $default["de_{$pgLower}_cookiepay_id_keyin"];
            // $success[$i]['apiKey'] = $default["de_{$pgLower}_cookiepay_key_keyin"];
            break;
        default:
            $success[$i]['payMethod'] = $row['PAYMETHOD'];
    }

    $success[$i]['pay_type'] = isset($row['pay_type']) && !empty($row['pay_type']) ? $row['pay_type'] : '';
    if (!empty($success[$i]['pay_type'])) {
        $cookiepayApi = cookiepay_get_api_account_info_by_pg($default, $pgLower, $success[$i]['pay_type']); // 해외원화/달러인 경우
    }

    $success[$i]['apiId'] = $cookiepayApi['api_id'];
    $success[$i]['apiKey'] = $cookiepayApi['api_key'];

    $success[$i]['status'] = '<span style="color:blue;">승인</span>';

    // $now = strtotime("now");
    // $ableDate = strtotime("+1 day", strtotime($row['ACCEPTDATE']));
    // if ($now <= $ableDate && (isset($cancelOrderno[$row['ORDERNO']]) && $cancelOrderno[$row['ORDERNO']] < $success[$i]['amount'])) {
    if (!isset($cancelOrderno[$row['ORDERNO']]) || (isset($cancelOrderno[$row['ORDERNO']]) && $cancelOrderno[$row['ORDERNO']] < $success[$i]['amount'])) {
        $success[$i]['btnPgCancel'] = '<button type="button" id="btn_'.$row['ORDERNO'].'" class="btn-pg-cancel" data-orderno="'.$row['ORDERNO'].'" data-apiid="'.$success[$i]['apiId'].'" data-apikey="'.$success[$i]['apiKey'].'" data-tid="'.$success[$i]['TID'].'" data-bank="'.$row['CARDNAME'].'" data-accountno="'.$row['ACCOUNTNO'].'" data-accountname="'.$row['RECEIVERNAME'].'">결제취소</button>';
    }
}

if ($tab == 's') {
    $data = $success;
} else if ($tab == 'c') {
    $data = $cancel;
} else {
    $data = array_merge($cancel, $success);
}
?>

<style>
.dataTables_wrapper .dataTables_filter {float: left !important;text-align: left !important;margin-bottom: 6px;}
.dataTables_wrapper .dataTables_length {float: right !important;}
.dataTables_wrapper .dataTables_length select {height: 28px;}
</style>

<link rel="stylesheet" href="<?php echo COOKIEPAY_URL; ?>/datatable/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo COOKIEPAY_URL ?>/modal/jquery.modal.min.css" />

<div class="btn_fixed_top">
    <a href=" <?php echo G5_SHOP_URL; ?>" class="btn btn_02">쇼핑몰</a>
    <!-- <input type="submit" value="확인" class="btn_submit btn" accesskey="s"> -->
</div>
<div>
    <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="btn btn_02">전체내역</a>
    <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>?t=s" class="btn btn_03">결제성공</a>
    <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>?t=c" class="btn btn_01">결제취소</a>
</div>
<form class="local_sch03 local_sch">
    <input type="hidden" name="t" value="<?php echo $tab; ?>">
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
            <th>PG</th>
            <th>주문번호</th>
            <th>승인일시</th>
            <th>취소일시</th>
            <th>승인번호</th>
            <th>결제금액</th>
            <th>취소금액</th>
            <th>결제상태</th>
            <th>고객명</th>
            <th>고객ID</th>
            <th>상품명</th>
            <th>결제수단</th>
            <th>전표출력</th>
            <th>승인취소</th>
        </tr>
    </thead>
    <tbody>
<?php
if (count($data) == 0) {
    echo '<tr>
            <td colspan="14">결제내역이 없습니다.</td>
        </tr>';
} else {
    foreach ($data as $val) {
?>
        <tr>
            <td><?php echo $val['cookiepayPg']; ?></td>
            <td>
                <a href="/adm/shop_admin/orderlist.php?token=<?php echo $token; ?>&doc=&sort1=od_id&sort2=desc&page=1&save_search=<?php echo $val['orderno']; ?>&sel_field=od_id&search=<?php echo $val['orderno']; ?>" target="_blank" style="color:blue;">
                <?php echo $val['orderno']; ?>
                </a>
            </td>
            <td><?php echo !empty($val['ACCEPTDATE']) ? date("Y-m-d H:i:s", strtotime($val['ACCEPTDATE'])) : ''; ?></td>
            <td><?php echo !empty($val['CANCELDATE']) ? date("Y-m-d H:i:s", strtotime($val['CANCELDATE'])) : ''; ?></td>
            <td><?php echo $val['ACCEPTNO']; ?></td>
            <td><?php echo !empty($val['amount']) ? number_format($val['amount']) : ''; ?></td>
            <td><?php echo !empty($val['cancelAmount']) ? number_format($val['cancelAmount']) : ''; ?></td>
            <td><?php echo $val['status']; ?></td>
            <td><?php echo $val['BUYERNAME']; ?></td>
            <td><?php echo $val['BUYERID']; ?></td>
            <td><?php echo mb_strlen($val['PRODUCTNAME']) > 20 ? mb_substr($val['PRODUCTNAME'], 0, 20)."…" : $val['PRODUCTNAME']; ?></td>
            <td><?php echo $val['payMethod']; ?></td>
            <td>
            <?php if ($val['cookiepayPg'] == '페누') { ?>
                <button type="button" onclick="receiptPaynuri('<?php echo $default['de_cookiepay_pn_cookiepay_pgid_keyin']; ?>', '<?php echo $val['TID']; ?>')">전표출력</button>
            <?php } else { ?>
                <button type="button" onclick="receipt('<?php echo $val['TID']; ?>')">전표출력</button>
            <?php } ?>
            </td>
            <td><?php echo $val['btnPgCancel']; ?></td>
        </tr>
<?php
    }
}
?>
    </tbody>
</table>
</div>

<div id="pg-cancel-modal" class="tbl_head01 tbl_wrap" style="display:none;">
    <input type="hidden" name="orderno" id="orderno">
    <input type="hidden" name="api_id" id="api_id">
    <input type="hidden" name="api_key" id="api_key">
    <input type="hidden" name="tid" id="tid">
    <input type="hidden" name="bank" id="bank">
    <input type="hidden" name="accountno" id="accountno">
    <input type="hidden" name="accountname" id="accountname">
    <div>
        <?php echo help("결제취소는 신용카드 결제, 계좌이체에 한해서만 취소 가능합니다."); ?>
    </div>
    <table>
        <colgroup>
            <col width="30%;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th colspan="2" style="text-align:center;padding:10px;">
                    <h1>결제 취소</h1>
                </th>
            </tr>
            <tr>
                <th>결제금액</th>
                <td style="padding:10px;"><span id="pay_amount"></span></td>
            </tr>
            <tr>
                <th>취소 가능 금액</th>
                <td style="padding:10px;"><span id="cancel_able_amount"></span></td>
            </tr>
            <tr>
                <th>취소할 금액</th>
                <td style="padding-left:10px;">
                    <input class="frm_input" type="text" name="cancel_amount" id="cancel_amount" value="" style="padding-left:4px;">
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding:10px;text-align:center;">
                    <button id="btn-modal-submit" class="btn_submit" type="button" style="padding:4px;border:none;margin-right:15px;width:100px;">결제 취소</button>
                    <button id="btn-modal-close" type="button" style="padding:4px;border:none;width:100px;">닫기</button>
                </td>
            </tr>
        </thead>
    </table>
</div>

<script src="<?php echo COOKIEPAY_URL ?>/datatable/jquery.dataTables.min.js"></script>
<script src="<?php echo COOKIEPAY_URL ?>/modal/jquery.modal.min.js"></script>
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
            order: [[2, 'desc']],
        });
    }
});

$(document).on("click", ".btn-pg-cancel", function(event) {
    var orderno = $(this).data("orderno");
    var api_id = $(this).data("apiid");
    var api_key = $(this).data("apikey");
    var tid = $(this).data("tid");
    var bank = $(this).data("bank");
    var accountno = $(this).data("accountno");
    var accountname = $(this).data("accountname");

    $("#orderno").val(orderno);
    $("#api_id").val(api_id);
    $("#api_key").val(api_key);
    $("#tid").val(tid);
    $("#bank").val(bank);
    $("#accountno").val(accountno);
    $("#accountname").val(accountname);

    $.ajax({
        type: "POST",
        url: "./cookiepay.ajax.php",
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

            var pay_amount = data.result.payAmount;
            var cancel_able_amount = data.result.cancelAbleAmount;
            var cancel_amount = data.result.cancelAbleAmount;

            $("#pay_amount").text(pay_amount);
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
    var amount = $("#cancel_amount").val();
    var orderno = $("#orderno").val();
    var api_id = $("#api_id").val();
    var api_key = $("#api_key").val();
    var tid = $("#tid").val();
    var bank = $("#bank").val();
    var accountno = $("#accountno").val();
    var accountname = $("#accountname").val();

    if (amount.length > 0) {
        var cancel_able_amount = $("#cancel_able_amount").text();
        cancel_able_amount = cancel_able_amount * 1;
        if (amount <= 0) {
            alert("취소할 금액을 확인해 주세요.");
            $("#cancel_amount").focus();
            return false;
        }
        if (amount > cancel_able_amount) {
            alert("취소 가능 금액보다 많은 금액은 취소할 수 없습니다.\n다시 확인해 주세요.");
            $("#cancel_amount").focus();
            return false;
        }
    }
    
    if (!confirm("결제를 취소하시겠습니까?")) {
        return false;
    }

    $.ajax({
        type: "POST",
        url: "./cookiepay.ajax.php",
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

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
