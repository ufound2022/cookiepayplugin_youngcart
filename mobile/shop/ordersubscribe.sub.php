<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

if (!defined("_ORDERINQUIRY_")) exit; // 개별 페이지 접근 불가

// 테마에 orderinquiry.sub.php 있으면 include
if(defined('G5_THEME_MSHOP_PATH')) {
    $theme_inquiry_file = G5_THEME_MSHOP_PATH.'/orderinquiry.sub.php';
    if(is_file($theme_inquiry_file)) {
        include_once($theme_inquiry_file);
        return;
        unset($theme_inquiry_file);
    }
}
?>

<?php if (!$limit) { ?>총 <?php echo $cnt; ?> 건<?php } ?>


<div id="sod_inquiry">
    <ul>
        <?php
        /*
        $sql = " select *,
                    (od_cart_coupon + od_coupon + od_send_coupon) as couponprice
                   from {$g5['g5_shop_order_table']}
                  where mb_id = '{$member['mb_id']}'
                  order by od_id desc
                  $limit ";
        */

        $sql = "select * from ".COOKIEPAY_PG_SUBSCRIBE_RESULT."
        where USERID='".$member['mb_id']."' order by id desc $limit 
        ";

        $result = sql_query($sql);
        for ($i=0; $row=sql_fetch_array($result); $i++)
        {

            // 주문상품
            $sql = " select it_name, ct_option
                        from {$g5['g5_shop_cart_table']}
                        where od_id = '{$row['ORDERNO']}'
                        order by io_type, ct_id
                        limit 1 ";
            $ct = sql_fetch($sql);
            $ct_name = get_text($ct['it_name']).' '.get_text($ct['ct_option']);

            $sql = " select count(*) as cnt
                        from {$g5['g5_shop_cart_table']}
                        where od_id = '{$row['od_id']}' ";
            $ct2 = sql_fetch($sql);
            if($ct2['cnt'] > 1)
                $ct_name .= ' 외 '.($ct2['cnt'] - 1).'건';

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

            $od_invoice = '';
            if($row['od_delivery_company'] && $row['od_invoice'])
                $od_invoice = '<span class="inv_inv"><i class="fa fa-truck" aria-hidden="true"></i> <strong>'.get_text($row['od_delivery_company']).'</strong> '.get_text($row['od_invoice']).'</span>';

            $uid = md5($row['od_id'].$row['od_time'].$row['od_ip']);



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

        <li>
            <div class="subscribe_no">
                <span class="idtime_time">구독번호 : <a href="javascript:openNewWindow('<?=$row['ORDERNO']?>');"><?php echo substr($row['RESERVE_ID'], 0, 10); ?></a></span>
            </div>                     
            <div class="inquiry_idtime">
                <?php
                /*
                <a href="<?php echo G5_SHOP_URL; ?>/orderinquiryview.php?od_id=<?php echo $row['od_id']; ?>&amp;uid=<?php echo $uid; ?>" class="idtime_link"><?php echo $row['od_id']; ?></a>
                <span class="idtime_time"><?php echo substr($row['od_time'],2,25); ?></span>
                */
                ?>
                <span class="idtime_time">주문일시 : <?php echo substr($row['PAY_DATE'], 0, 16); ?></span>
            </div>
            <div class="inquiry_idtime">
                <span class="idtime_time">해지일시 : <? echo substr($psu['RESERVE_SCHEDULE_CANCEL_DATE'],0,16); ?></span>
            </div>
            <div class="inquiry_idtime">
                <span class="idtime_time">주문번호 : <a href="<?php echo G5_SHOP_URL; ?>/orderinquiryview.php?od_id=<?php echo $row['ORDERNO']; ?>&amp;uid=<?php echo $uid; ?>"><?php echo $row['ORDERNO']; ?></a></span>
            </div>    
            <div class="inq_wr">
                <div class="inquiry_price">상품명 : <?php echo $row['PRODUCT_NAME']; ?></div>
            </div>
            <?php
            /*
            <div class="inquiry_name">
                <?php echo $ct_name; ?>
            </div>
            */
            ?>
            <div class="inq_wr">
                <div class="inquiry_price">
                    <?php // echo display_price($row['od_receipt_price']); ?>
                   금 액 : <?php echo number_format($row['AMOUNT']); ?>
                </div>
                <div class="inv_status"><?php echo $od_status; ?></div>
            </div>
            <div class="idtime_time">
                회차/약정상태 : <?php echo $row['RESERVE_NOW_PAY_CNT'];?> / <?php echo $row['RESERVE_LAST_PAY_CNT'];?>
            </div>        
            <div class="idtime_time">
                결제상태 : <?=$pay_status_str?>
            </div>   
            <div class="idtime_time">
                구독상태 : <?=$pay_substribe_status_str?>
            </div>   
            <div class="inquiry_inv">
                <?php echo $od_invoice; ?>
            </div>
        </li>

        <?php
        }

        if ($i == 0)
            echo '<li class="empty_list">주문 내역이 없습니다.</li>';
        ?>
    </ul>
</div>

<script language='javascript'>
function openNewWindow(order_no) {
    window.open('/cookiepay/cookiepay_subscribe_info.php?order_no='+order_no,'cookiepay_subscribe_info','width=450,height=380,top=200,left=500, scrollbars=no');
}

</script>
