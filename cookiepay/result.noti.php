<?php
include_once('../shop/_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";
require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

// 결제 결과 통지 수신 후 처리

$cookiepay = json_decode(file_get_contents('php://input'), true);
$cookiepay['ACCEPT_NO'] = isset($cookiepay['ACCEPT_NO']) && !empty($cookiepay['ACCEPT_NO']) ? $cookiepay['ACCEPT_NO'] : '';
$cookiepay['TID'] = isset($cookiepay['TID']) && !empty($cookiepay['TID']) ? $cookiepay['TID'] : '';
$cookiepay['ORDERNO'] = isset($cookiepay['ORDERNO']) && !empty($cookiepay['ORDERNO']) ? $cookiepay['ORDERNO'] : '';

$resultMode = null;

@cookiepay_payment_log("[통지]수신", json_encode($cookiepay), 3);

if(!empty($cookiepay['ACCEPT_NO']) && !empty($cookiepay['TID']) && !empty($cookiepay['ORDERNO'])) {
    $pgResult = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
    $payStatus = isset($pgResult['pay_status']) && $pgResult['pay_status']>=0 ? $pgResult['pay_status'] : '';

    $cookiepay['RESULTCODE'] = '0000';
    $cookiepay['RESULTMSG'] = '성공';
    $cookiepay['ACCEPTDATE'] = $cookiepay['ACCEPT_DATE'];
    $cookiepay['ACCEPTNO'] = $cookiepay['ACCEPT_NO'];
    unset($cookiepay['ACCEPT_DATE']);
    unset($cookiepay['ACCEPT_NO']);

    if ($payStatus == '') {
        // insert
        $columnStr = implode(",", $pgResultColumns);
        $values = [];
        foreach ($pgResultColumns as $val) {
            $values[$val] = "''";
        }
        foreach ($cookiepay as $key => $val) {
            if (array_key_exists($key, $values)) {
                $values[$key] = "'{$val}'";
            }
        }
        $values['PGNAME'] = "'{$default['de_pg_service']}'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $values['pay_type'] = "'{$cookiepay['ETC3']}'";
        }
        $values['pay_status'] = 1;
        $valueStr = implode(",", $values);

        $sql = " INSERT INTO ".COOKIEPAY_PG_RESULT." ({$columnStr}) VALUES ({$valueStr}) ";
        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("[통지]결제결과 저장 성공1", $sql, 3);
            $resultMode = 'insert';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패1", $sql, 3);
        }
    } else if ($payStatus != 1) {
        // update
        $set = [];
        foreach ($cookiepay as $key => $val) {
            if (in_array($key, $pgResultColumns)) {
                $set[$key] = "{$key}='{$val}'";
            }
        }
        $set['PGNAME'] = "PGNAME='{$default['de_pg_service']}'"; // pg사 추가
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) { // 응답전문에는 pay_type이 없으므로 pay_type을 etc3에 추가해 보내고 받음
            $set['pay_type'] = "pay_type='{$cookiepay['ETC3']}'";
        }
        $set['pay_status'] = "pay_status=1";
        $setStr = implode(",", $set);

        $sql = "UPDATE ".COOKIEPAY_PG_RESULT." SET {$setStr} WHERE ORDERNO='{$cookiepay['ORDERNO']}'";

        $res = sql_query($sql, false);
        if ($res) {
            @cookiepay_payment_log("[통지]결제결과 저장 성공2", $sql, 3);
            $resultMode = 'update';
        } else {
            @cookiepay_payment_log("[통지]결제결과 저장 실패2", $sql, 3);
        }
    }

    if ($resultMode == 'insert' || $resultMode == 'update') {
        if (isset($cookiepay['ETC3']) && !empty($cookiepay['ETC3'])) {
            $cookiepayApi = cookiepay_get_api_account_info($default, $cookiepay['ETC3']);
        } else {
            $cookiepayApi = cookiepay_get_api_account_info($default, 3);
        }

        // 결제 검증
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
        );

        $token_url = COOKIEPAY_TOKEN_URL;

        $request_data = array(
            'pay2_id' => $cookiepayApi['api_id'],
            'pay2_key'=> $cookiepayApi['api_key'],
        );

        $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resultJson = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resultJson, true);

        if($result['RTN_CD'] == '0000') {

            $paycert_url = COOKIEPAY_VERIFY_URL;

            $headers = array(
                'content-type: application/json; charset=utf-8',
                'TOKEN: ' . $result['TOKEN'],
            );

            $request_data = array(
                'tid' => $cookiepay['TID'],
            );

            $request_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $paycert_url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);
            $verify = json_decode($response, true);

            $pgVerify = sql_fetch(" SELECT * FROM ".COOKIEPAY_PG_VERIFY." WHERE ORDERNO='{$cookiepay['ORDERNO']}' ORDER BY `id` DESC LIMIT 1");
            $verifyId = isset($pgVerify['id']) && !empty($pgVerify['id']) ? $pgVerify['id'] : null;

            // 결제 검증 결과 테이블에 저장
            if (is_null($verifyId)) {
                // column 쿼리 처리
                $columnStr = implode(",", $pgVerifyColumns);

                // values 쿼리 처리
                $values = [];
                foreach ($pgVerifyColumns as $val) {
                    $values[$val] = "''";
                }
                foreach ($verify as $key => $val) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = "'{$val}'";
                    }
                }
                $valueStr = implode(",", $values);

                $sql = " INSERT INTO ".COOKIEPAY_PG_VERIFY." ({$columnStr}) VALUES ({$valueStr}) ";
                $res = sql_query($sql, false);
            } else {
                $set = [];
                foreach ($verify as $key => $val) {
                    if (in_array($key, $pgVerifyColumns)) {
                        $set[$key] = "{$key}='{$val}'";
                    }
                }
                $setStr = implode(",", $set);
                $sql = "UPDATE ".COOKIEPAY_PG_VERIFY." SET {$setStr} WHERE ORDERNO='{$cookiepay['ORDERNO']}'";
                $res = sql_query($sql, false);
            }

            if ($res) {
                @cookiepay_payment_log("[통지]결제검증결과 저장 성공3", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제검증결과 저장 실패3", $sql, 3);
            }

            if($verify['RESULTCODE'] == '0000') {
                
                // s: cookiepay-plugin v1.2
                $exists_sql = "select od_id from {$g5['g5_shop_order_table']} where od_id = '{$cookiepay['ORDERNO']}'";
                $exists_order = sql_fetch($exists_sql);
                if (!isset($exists_order['od_id']))
                {
                    $sql = "select * from ".COOKIEPAY_SHOP_ORDER." where od_id = '{$cookiepay['ORDERNO']}'";
                    $order_data = sql_fetch($sql);
                    if ($order_data)
                    {
                        $sql = "SELECT * FROM ".COOKIEPAY_PG_RESULT." WHERE ORDERNO='{$cookiepay['ORDERNO']}'";
                        $pg_data = sql_fetch($sql);
                        
                        $i_price = $order_data['od_price'] + $order_data['od_send_cost'] + $order_data['od_send_cost2'] - $order_data['od_temp_point'] - $order_data['od_send_coupon'];
                        $od_receipt_time = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/", "\\1-\\2-\\3 \\4:\\5:\\6", $pg_data['ACCEPTDATE']);
                        $od_misu = $i_price - $pg_data['AMOUNT'];
                        if ($od_misu == 0)
                        {
                            $od_status = '입금';
                        }
                        else
                        {
                            $od_status = '주문';
                        }
                        
                        // 복합과세 금액
                        $od_tax_mny = round($i_price / 1.1);
                        $od_vat_mny = $i_price - $od_tax_mny;
                        $od_free_mny = 0;
                        if ($default['de_tax_flag_use'])
                        {
                            $od_tax_mny = $order_data['comm_tax_mny'] ? (int) $order_data['comm_tax_mny'] : 0;
                            $od_vat_mny = $order_data['comm_vat_mny'] ? (int) $order_data['comm_vat_mny'] : 0;
                            $od_free_mny = $order_data['comm_free_mny'] ? (int) $order_data['comm_free_mny'] : 0;
                        }
                        
                        $sql = " insert {$g5['g5_shop_order_table']}
                                    set od_id               = '{$order_data['od_id']}',
                                        mb_id               = '{$order_data['mb_id']}',
                                        od_name             = '{$order_data['od_name']}',
                                        od_email            = '{$order_data['od_email']}',
                                        od_tel              = '{$order_data['od_tel']}',
                                        od_hp               = '{$order_data['od_hp']}',
                                        od_zip1             = '{$order_data['od_zip1']}',
                                        od_zip2             = '{$order_data['od_zip2']}',
                                        od_addr1            = '{$order_data['od_addr1']}',
                                        od_addr2            = '{$order_data['od_addr2']}',
                                        od_addr3            = '{$order_data['od_addr3']}',
                                        od_addr_jibeon      = '{$order_data['od_addr_jibeon']}',
                                        od_deposit_name     = '{$order_data['od_deposit_name']}',
                                        od_b_name           = '{$order_data['od_b_name']}',
                                        od_b_tel            = '{$order_data['od_b_tel']}',
                                        od_b_hp             = '{$order_data['od_b_hp']}',
                                        od_b_zip1           = '{$order_data['od_b_zip1']}',
                                        od_b_zip2           = '{$order_data['od_b_zip2']}',
                                        od_b_addr1          = '{$order_data['od_b_addr1']}',
                                        od_b_addr2          = '{$order_data['od_b_addr2']}',
                                        od_b_addr3          = '{$order_data['od_b_addr3']}',
                                        od_b_addr_jibeon    = '{$order_data['od_b_addr_jibeon']}',
                                        od_memo             = '{$order_data['od_memo']}',
                                        od_cart_count       = '{$order_data['od_cart_count']}',
                                        od_cart_price       = '{$order_data['od_cart_price']}',
                                        od_cart_coupon      = '{$order_data['od_cart_coupon']}',
                                        od_send_cost        = '{$order_data['od_send_cost']}',
                                        od_send_cost2       = '{$order_data['od_send_cost2']}',
                                        od_send_coupon      = '{$order_data['od_send_coupon']}',
                                        od_receipt_price    = '{$pg_data['AMOUNT']}',
                                        od_cancel_price     = '{$order_data['od_cancel_price']}',
                                        od_receipt_point    = '{$order_data['od_temp_point']}',
                                        od_refund_price     = '{$order_data['od_refund_price']}',
                                        od_bank_account     = '{$pg_data['CARDCODE']}',
                                        od_receipt_time     = '{$od_receipt_time}',
                                        od_coupon           = '{$order_data['od_coupon']}',
                                        od_misu             = '{$od_misu}',
                                        od_shop_memo        = '{$order_data['od_shop_memo']}',
                                        od_mod_history      = '{$order_data['od_mod_history']}',
                                        od_status           = '{$od_status}',
                                        od_hope_date        = '{$order_data['od_hope_date']}',
                                        od_settle_case      = '{$order_data['od_settle_case']}',
                                        od_other_pay_type   = '{$order_data['od_other_pay_type']}',
                                        od_test             = '{$order_data['od_test']}',
                                        od_mobile           = '{$order_data['od_mobile']}',
                                        od_pg               = '{$pg_data['PGNAME']}',
                                        od_tno              = '{$pg_data['TID']}',
                                        od_app_no           = '{$pg_data['ACCEPTNO']}',
                                        od_escrow           = '{$order_data['od_escrow']}',
                                        od_casseqno         = '{$order_data['od_casseqno']}',
                                        od_tax_flag         = '{$order_data['od_tax_flag']}',
                                        od_tax_mny          = '{$od_tax_mny}',
                                        od_vat_mny          = '{$od_vat_mny}',
                                        od_free_mny         = '{$od_free_mny}',
                                        od_delivery_company = '{$order_data['od_delivery_company']}',
                                        od_invoice          = '{$order_data['od_invoice']}',
                                        od_invoice_time     = '{$order_data['od_invoice_time']}',
                                        od_cash             = '{$order_data['od_cash']}',
                                        od_cash_no          = '{$order_data['od_cash_no']}',
                                        od_cash_info        = '{$order_data['od_cash_info']}',
                                        od_time             = '".G5_TIME_YMDHIS."',
                                        od_pwd              = '{$order_data['od_pwd']}',
                                        od_ip               = '{$order_data['od_ip']}'
                                        ";
                        $result = sql_query($sql, false);
                        @cookiepay_payment_log("[통지]주문정보 저장", $sql, 3);

                        # s: cookiepay-plugin > 장바구니 업데이트 > v1.2.1
                        if(!empty($cookiepay['ETC5'])) { 
                            $cart_status      = '입금';

                            $sql_cart = "update {$g5['g5_shop_cart_table']}
                                    set od_id = '{$order_data['od_id']}',
                                        ct_status = '{$cart_status}'
                                    where od_id = '{$cookiepay['ETC5']}'
                                    and ct_select = '1' ";
                            //$result_cart = sql_query($sql_cart, false);  # e: cookiepay-plugin > 장바구니 업데이트(임시주석처리) > v1.2.1 
                            @cookiepay_payment_log("[통지]카트정보 업데이트", $sql_cart, 3);

                        }
                        # e: cookiepay-plugin > 장바구니 업데이트 > v1.2.1 
                                                
                    }
                }
                // e: cookiepay-plugin v1.2
                
                // 결제 검증 성공
                @cookiepay_payment_log("[통지]결제검증 성공4", $response, 3);
                echo '<html>
                        <body>
                        <RESULT>SUCCESS</RESULT>
                        </body>
                      </html>';
            } else {
                // 결제 검증 실패시 결제 취소 처리
                @cookiepay_payment_log("[통지]결제검증 실패4", $response, 3);
            
                $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

                if ($ret['status'] === true) {
                    @cookiepay_payment_log("[통지]결제취소 성공5", $ret['data'], 3);
                    $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
                } else {
                    @cookiepay_payment_log("[통지]결제취소 실패5", $ret['data'], 3);
                }

                $cancelArr = json_decode($ret['data'], true);

                $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
                $res = sql_query($sql, false);
                if ($res) {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 성공6", $sql, 3);
                } else {
                    @cookiepay_payment_log("[통지]결제취소결과 저장 실패6", $sql, 3);
                }
            }
        } else {
            // 결제 검증 토큰 발행 실패시 결제 취소 처리
            @cookiepay_payment_log("[통지]결제 검증 토큰 발행 실패7", $resultJson, 3);
            
            $ret = cookipay_cancel_payment($cookiepayApi['api_id'], $cookiepayApi['api_key'], $cookiepay['TID'], $cookiepay['CARDCODE'], $cookiepay['ACCOUNTNO'], $cookiepay['RECEIVERNAME']);

            if ($ret['status'] === true) {
                @cookiepay_payment_log("[통지]결제취소 성공7", $ret['data'], 3);
                $payStatusRes = sql_query("UPDATE ".COOKIEPAY_PG_RESULT." SET pay_status=2 WHERE ORDERNO='{$cookiepay['ORDERNO']}'", false);
            } else {
                @cookiepay_payment_log("[통지]결제취소 실패7", $ret['data'], 3);
            }

            $cancelArr = json_decode($ret['data'], true);

            $sql = " INSERT INTO ".COOKIEPAY_PG_CANCEL." (orderno, cancel_tid, cancel_code, cancel_msg, cancel_date, cancel_amt) VALUES ('{$cookiepay['ORDERNO']}', '{$cancelArr['cancel_tid']}', '{$cancelArr['cancel_code']}', '{$cancelArr['cancel_msg']}', '{$cancelArr['cancel_date']}', '{$cancelArr['cancel_amt']}') ";
            $res = sql_query($sql, false);
            if ($res) {
                @cookiepay_payment_log("[통지]결제취소결과 저장 성공8", $sql, 3);
            } else {
                @cookiepay_payment_log("[통지]결제취소결과 저장 실패8", $sql, 3);
            }
        }
    }
}