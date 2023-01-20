<?php
$sub_menu = '400900';
include_once('./_common.php');
include_once(G5_EDITOR_LIB);
include_once(G5_PATH."/cookiepay/cookiepay.lib.php");

auth_check_menu($auth, $sub_menu, "r");

$g5['title'] = '결제내역';
include_once (G5_ADMIN_PATH.'/admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

$fr_date = (isset($_GET['fr_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['fr_date'])) ? $_GET['fr_date'] : '2023-01-01';
$to_date = (isset($_GET['to_date']) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['to_date'])) ? $_GET['to_date'] : date("Y-m-d");

$data = [];
foreach (COOKIEPAY_PG as $key => $val) {
    $pg = strtolower($key);
    if (!empty($default["de_{$pg}_cookiepay_id"]) && $default["de_{$pg}_cookiepay_key"]) {
        // api account
        $cookiepayApi = [];
        $cookiepayApi['api_id'] = $default["de_{$pg}_cookiepay_id"];
        $cookiepayApi['api_key'] = $default["de_{$pg}_cookiepay_key"];

        // s: 쿠키페이 결제내역 조회
        $tokenheaders = array(); 
        array_push($tokenheaders, "content-type: application/json; charset=utf-8");

        $token_request_data = array(
            'pay2_id' => $cookiepayApi['api_id'],
            'pay2_key'=> $cookiepayApi['api_key'],
        );

        $req_json = json_encode($token_request_data, TRUE);

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, COOKIEPAY_TOKEN_URL);
        curl_setopt($ch,CURLOPT_POST, false);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $req_json);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
        curl_setopt($ch,CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $tokenheaders);
        $resJson = curl_exec($ch);
        curl_close($ch);
        $resArr = json_decode($resJson,TRUE);

        if($resArr['RTN_CD'] == '0000'){
            $headers = array(); 
            array_push($headers, "content-type: application/json; charset=utf-8");
            array_push($headers, "TOKEN: ".$resArr['TOKEN']);

            $request_data_array = array(
                'API_ID' => $cookiepayApi['api_id'],
                'STD_DT' => $fr_date,
                'END_DT' => $to_date,
            );

            $cookiepayments_json = json_encode($request_data_array, TRUE);

            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, COOKIEPAY_SEARCH_URL);
            curl_setopt($ch,CURLOPT_POST, false);
            curl_setopt($ch,CURLOPT_POSTFIELDS, $cookiepayments_json);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
            curl_setopt($ch,CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            foreach($result as $index => $value) {
                $result[$index]['pg'] = $pg; // pg사
                $result[$index]['api_id'] = $cookiepayApi['api_id']; // 연동아이디
                $result[$index]['api_key'] = $cookiepayApi['api_key']; // 연동키
            }
        }
        // e: 쿠키페이 결제내역 조회

        $data = array_merge($data, $result);
    }
}

// s: 주문내역에 있는 건만 추려냄 - 연동아이디와 키가 정해진 후에는 이 코드블록은 필요없음
$sql = " SELECT * FROM {$g5['g5_shop_order_table']} ORDER BY od_id ASC ";
$res = sql_query($sql);

$ordernoList = [];
for($i=0; $row=sql_fetch_array($res); $i++) {
    array_push($ordernoList, $row['od_id']);
}

if (count($ordernoList) > 0) {
    foreach ($data as $key => $val) {
        if (!in_array($val['ORDERNO'], $ordernoList)) {
            unset($data[$key]);
        }
    }
}
// e: 주문내역에 있는 건만 추려냄
?>

<link rel="stylesheet" href="<?php echo COOKIEPAY_URL; ?>/datatable/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?php echo COOKIEPAY_URL ?>/modal/jquery.modal.min.css" />

<div class="btn_fixed_top">
    <a href=" <?php echo G5_SHOP_URL; ?>" class="btn btn_02">쇼핑몰</a>
    <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
</div>
<form class="local_sch03 local_sch">
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
    <button type="button" onclick="javascript:set_date('전체');">전체</button>
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
        $amount = $val['AMOUNT'];
        $cancelAmount = 0;
        $cancelSumAmount = !empty($val['CANCEL_SUM_AMOUNT']) ? $val['CANCEL_SUM_AMOUNT'] : 0;
        $status = '';

        $btnPgCancel = '';

        // PG사
        $pgKey = strtoupper($val['pg']);
        $cookiepayPg = mb_substr(COOKIEPAY_PG[$pgKey], 0, 2);
        
        // 결제수단
        switch ($val['PAYMETHOD']) {
            case 'CARD':
                $payMethod = '카드';
                break;
            case 'BANK':
                $payMethod = '계좌이체';
                break;
            case 'VACCT':
                $payMethod = '가상계좌';
                break;
            case 'MOBILE':
                $payMethod = '휴대폰';
                break;
            case 'CARD_SUGI':
                $payMethod = '수기결제';
                break;
            default:
                $payMethod = $val['PAYMETHOD'];
        }

        // 결제상태: 승인
        if ($val['RESULTCODE'] == '0000' && !empty($val['ACCEPTDATE']) && empty($val['CANCELDATE'])) {
            $status = '<span style="color:blue;">승인</span>';

            $now = strtotime("now");
            $ableDate = strtotime("+1 day", strtotime($val['ACCEPTDATE']));
            if ($now <= $ableDate) {
                $btnPgCancel = '<button type="button" id="btn_'.$val['ORDERNO'].'" class="btn-pg-cancel" data-orderno="'.$val['ORDERNO'].'" data-apiid="'.$val['api_id'].'" data-apikey="'.$val['api_key'].'" data-tid="'.$val['TID'].'" data-bank="'.$val['CARDNAME'].'" data-accountno="'.$val['ACCOUNTNO'].'" data-accountname="'.$val['RECEIVERNAME'].'">결제취소</button>';
            }
        }

        // 결제상태: 취소
        if (!empty($val['CANCELDATE'])) {
            $amount = 0;
            $cancelAmount = $val['AMOUNT'] - $cancelSumAmount;
            
            $status = '<span style="color:red;">취소</span>';

            $btnPgCancel = '';
        }
?>
        <tr>
            <td><?php echo $cookiepayPg; ?></td>
            <td>
                <a href="/adm/shop_admin/orderlist.php?token=<?php echo $token; ?>&doc=&sort1=od_id&sort2=desc&page=1&save_search=<?php echo $val['ORDERNO']; ?>&sel_field=od_id&search=<?php echo $val['ORDERNO']; ?>" target="_blank" style="color:blue;">
                <?php echo $val['ORDERNO']; ?>
                </a>
            </td>
            <td><?php echo !empty($val['ACCEPTDATE']) ? date("Y-m-d H:i:s", strtotime($val['ACCEPTDATE'])) : ''; ?></td>
            <td><?php echo !empty($val['CANCELDATE']) ? date("Y-m-d H:i:s", strtotime($val['CANCELDATE'])) : ''; ?></td>
            <td><?php echo $val['ACCEPTNO']; ?></td>
            <td><?php echo !empty($amount) ? number_format($amount) : ''; ?></td>
            <td><?php echo !empty($cancelAmount) ? number_format($cancelAmount) : ''; ?></td>
            <td><?php echo $status; ?></td>
            <td><?php echo $val['BUYERNAME']; ?></td>
            <td><?php echo $val['BUYERID']; ?></td>
            <td><?php echo mb_strlen($val['PRODUCTNAME']) > 20 ? mb_substr($val['PRODUCTNAME'], 0, 20)."…" : $val['PRODUCTNAME']; ?></td>
            <td><?php echo $payMethod; ?></td>
            <td><button type="button" onclick="receipt('<?php echo $val['TID']; ?>')">전표출력</button></td>
            <td><?php echo $btnPgCancel; ?></td>
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
    var tid = base64_encode(tid);
    window.open(
        "<?php echo COOKIEPAY_RECEIPT_URL ?>?tid="+tid,
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
