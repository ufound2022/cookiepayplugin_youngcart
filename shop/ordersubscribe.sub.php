<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

if (!defined("_ORDERINQUIRY_")) exit; // 개별 페이지 접근 불가

// 테마에 orderinquiry.sub.php 있으면 include
if(defined('G5_THEME_SHOP_PATH')) {
    $theme_inquiry_file = G5_THEME_SHOP_PATH.'/orderinquiry.sub.php';
    if(is_file($theme_inquiry_file)) {
        include_once($theme_inquiry_file);
        return;
        unset($theme_inquiry_file);
    }
}
?>
<style>
.dataTables_wrapper .dataTables_filter {float: left !important;text-align: left !important;margin-bottom: 6px;}
.dataTables_wrapper .dataTables_length {float: right !important;}
.dataTables_wrapper .dataTables_length select {height: 28px;}
</style>

<link rel="stylesheet" href="/cookiepay/datatable/jquery.dataTables.min.css">
<link rel="stylesheet" href="/cookiepay/modal/jquery.modal.min.css" />

<!-- 주문 내역 목록 시작 { -->
<?php if (!$limit) { ?>총 <?php echo $cnt; ?> 건<?php } ?>

<div class="tbl_head03 tbl_wrap">
    <table>
    <thead>
    <tr>
        <th scope="col" style="width:160px">구독번호</th>
        <th scope="col" style="width:140px">신청일자</th>
        <th scope="col" style="width:140px">해지일자</th>
        <th scope="col" style="width:150px">주문번호</th>
        <th scope="col" style="width:140px">주문일시</th>
        <th scope="col" style="width:160px">상품명</th>
        <th scope="col" style="width:140px">결제금액(1회)</th>
        <th scope="col" style="width:100px">회차/약정회차</th>
        <th scope="col" style="width:80px">결제상태</th>
        <th scope="col" style="width:80px">구독상태</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $sql = " select *
               from cookiepay_pg_subscribe_result
              where USERID = '{$member['mb_id']}'
              order by id desc
              $limit ";
    $result = sql_query($sql);
    for ($i=0; $row=sql_fetch_array($result); $i++)
    {

        #echo "SELECT * FROM cookiepay_pg_subscribe_userlist WHERE RESERVE_ID='{$row['RESERVE_ID']}' ORDER BY `id` DESC LIMIT 1 ";
        $psu = sql_fetch(" SELECT * FROM cookiepay_pg_subscribe_userlist WHERE RESERVE_ID='{$row['RESERVE_ID']}' ORDER BY `id` DESC LIMIT 1 ");
        $od = sql_fetch(" SELECT * FROM g5_shop_order WHERE RESERVE_ID='{$row['ORDERNO']}' ORDER BY `od_id` DESC LIMIT 1 ");

        $pay_substribe_status_str = "정상";
        if($psu['pay_status'] == "2") { 
            $pay_substribe_status_str = "<span style='color:red'>해지</span>";
        }

        $pay_status_str = "";
        if($row['pay_status'] == "1") { 
            $pay_status_str = "결제성공";
        } else 
        if($row['pay_status'] == "2") { 
            $pay_status_str = "<span style='color:red'>결제취소</font>";
        }
        //$uid = md5($row['od_id'].$row['od_time'].$row['od_ip']);

        switch($row['od_status']) {
            case '주문':
                $od_status = '<span class="status_01">입금확인중</span>';
                break;
            case '입금':
                $od_status = '<span class="status_02">입금완료</span>';
                break;
            case '준비':
                $od_status = '<span class="status_03">상품준비중</span>';
                break;
            case '배송':
                $od_status = '<span class="status_04">상품배송</span>';
                break;
            case '완료':
                $od_status = '<span class="status_05">배송완료</span>';
                break;
            default:
                $od_status = '<span class="status_06">주문취소</span>';
                break;
        }
    ?>

    <tr>
        <td>
            <a href="javascript:openNewWindow('<?=$row['ORDERNO']?>');"><?php echo substr($row['RESERVE_ID'], 0, 10); ?></a>
        </td>
        <td><? echo substr($psu['PAY_DATE'], 0, 16); ?></td>
        <td class="td_numbig"><? echo substr($psu['RESERVE_SCHEDULE_CANCEL_DATE'],0,16); ?></td>
        <td class="td_numbig text_right"><?php echo $row['ORDERNO']; ?></td>
        <td class="td_numbig text_right"><?php echo substr($row['PAY_DATE'], 0, 16); ?></td>
        <td class="td_numbig text_center"><?php echo $row['PRODUCT_NAME']; ?></td>
        <td class="td_numbig text_right"><?php echo number_format($row['AMOUNT']); ?></td>
        <td><?php echo $row['RESERVE_NOW_PAY_CNT'];?> / <?php echo $row['RESERVE_LAST_PAY_CNT'];?></td>
        <td><?=$pay_status_str?></td>
        <td><?=$pay_substribe_status_str?></td>
    </tr>

    <?php
    }

    if ($i == 0)
        echo '<tr><td colspan="7" class="empty_table">주문 내역이 없습니다.</td></tr>';
    ?>
    </tbody>
    </table>
</div>

<script language='javascript'>
function openNewWindow(order_no) {
    window.open('/cookiepay/cookiepay_subscribe_info.php?order_no='+order_no,'cookiepay_subscribe_info','width=630,height=380,top=200,left=500, scrollbars=no');
}

</script>