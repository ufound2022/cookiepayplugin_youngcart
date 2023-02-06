<?php exit;
$sub_menu = '990100';
include_once('./_common.php');
require_once G5_PATH."/cookiepay/cookiepay.lib.php";

check_demo();

auth_check_menu($auth, $sub_menu, "w");

check_admin_token();

if(!isset($default['de_cookiepay_al_cookiepay_id'])) {
	// 쿠키페이 PG사 연동 정보 컬럼 추가
	$sql = " ALTER TABLE `{$g5['g5_shop_default_table']}`
			ADD `de_cookiepay_al_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '모빌페이의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_pg_service`, 
			ADD `de_cookiepay_al_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '모빌페이의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_al_cookiepay_id`, 
			ADD `de_cookiepay_ts_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '토스페이의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_al_cookiepay_key`, 
			ADD `de_cookiepay_ts_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '토스페이의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ts_cookiepay_id`, 
			ADD `de_cookiepay_kw_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '키움페이의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ts_cookiepay_key`, 
			ADD `de_cookiepay_kw_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '키움페이의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_kw_cookiepay_id`, 
			ADD `de_cookiepay_dn_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '다날의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_kw_cookiepay_key`, 
			ADD `de_cookiepay_dn_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '다날의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_dn_cookiepay_id`, 
			ADD `de_cookiepay_ki_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '이지페이의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_dn_cookiepay_key`, 
			ADD `de_cookiepay_ki_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '이지페이의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ki_cookiepay_id`, 
			ADD `de_cookiepay_wp_cookiepay_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '웰컴페이의 쿠키페이 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ki_cookiepay_key`, 
			ADD `de_cookiepay_wp_cookiepay_key` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '웰컴페이의 쿠키페이 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_wp_cookiepay_id` ";
    sql_query($sql, true);
}

if(!isset($default['de_cookiepay_al_cookiepay_id_keyin'])) {
    // 쿠키페이 PG사 Keyin 결제 연동 정보 컬럼 추가
    $sql = " ALTER TABLE `{$g5['g5_shop_default_table']}`
                ADD `de_cookiepay_al_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '모빌페이의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_wp_cookiepay_key`, 
                ADD `de_cookiepay_al_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '모빌페이의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_al_cookiepay_id_keyin`, 
                ADD `de_cookiepay_ts_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '토스페이의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_al_cookiepay_key_keyin`, 
                ADD `de_cookiepay_ts_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '토스페이의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ts_cookiepay_id_keyin`, 
                ADD `de_cookiepay_kw_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '키움페이의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ts_cookiepay_key_keyin`, 
                ADD `de_cookiepay_kw_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '키움페이의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_kw_cookiepay_id_keyin`, 
                ADD `de_cookiepay_dn_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '다날의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_kw_cookiepay_key_keyin`, 
                ADD `de_cookiepay_dn_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '다날의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_dn_cookiepay_id_keyin`, 
                ADD `de_cookiepay_ki_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '이지페이의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_dn_cookiepay_key_keyin`, 
                ADD `de_cookiepay_ki_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '이지페이의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ki_cookiepay_id_keyin`, 
                ADD `de_cookiepay_wp_cookiepay_id_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '웰컴페이의 쿠키페이 수기결제 연동 아이디' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_ki_cookiepay_key_keyin`, 
                ADD `de_cookiepay_wp_cookiepay_key_keyin` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '웰컴페이의 쿠키페이 수기결제 연동 시크릿키' COLLATE 'utf8_general_ci' AFTER `de_cookiepay_wp_cookiepay_id_keyin` ";
    sql_query($sql, true);
}


$check_sanitize_keys = array(
'de_pg_service',                		  //결제대행사
'de_cookiepay_al_cookiepay_id',           //모빌페이의 쿠키페이 연동 아이디
'de_cookiepay_al_cookiepay_key',          //모빌페이의 쿠키페이 연동 시크릿키
'de_cookiepay_ts_cookiepay_id',           //토스페이의 쿠키페이 연동 아이디
'de_cookiepay_ts_cookiepay_key',          //토스페이의 쿠키페이 연동 시크릿키
'de_cookiepay_kw_cookiepay_id',           //키움페이의 쿠키페이 연동 아이디
'de_cookiepay_kw_cookiepay_key',          //키움페이의 쿠키페이 연동 시크릿키
'de_cookiepay_dn_cookiepay_id',           //다날의 쿠키페이 연동 아이디
'de_cookiepay_dn_cookiepay_key',          //다날의 쿠키페이 연동 시크릿키
'de_cookiepay_ki_cookiepay_id',           //이지페이의 쿠키페이 연동 아이디
'de_cookiepay_ki_cookiepay_key',          //이지페이의 쿠키페이 연동 시크릿키
'de_cookiepay_wp_cookiepay_id',           //웰컴페이의 쿠키페이 연동 아이디
'de_cookiepay_wp_cookiepay_key',          //웰컴페이의 쿠키페이 연동 시크릿키
'de_cookiepay_al_cookiepay_id_keyin',     //모빌페이의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_al_cookiepay_key_keyin',    //모빌페이의 쿠키페이 수기결제 연동 시크릿키
'de_cookiepay_ts_cookiepay_id_keyin',     //토스페이의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_ts_cookiepay_key_keyin',    //토스페이의 쿠키페이 수기결제 연동 시크릿키
'de_cookiepay_kw_cookiepay_id_keyin',     //키움페이의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_kw_cookiepay_key_keyin',    //키움페이의 쿠키페이 수기결제 연동 시크릿키
'de_cookiepay_dn_cookiepay_id_keyin',     //다날의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_dn_cookiepay_key_keyin',    //다날의 쿠키페이 수기결제 연동 시크릿키
'de_cookiepay_ki_cookiepay_id_keyin',     //이지페이의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_ki_cookiepay_key_keyin',    //이지페이의 쿠키페이 수기결제 연동 시크릿키
'de_cookiepay_wp_cookiepay_id_keyin',     //웰컴페이의 쿠키페이 수기결제 연동 아이디
'de_cookiepay_wp_cookiepay_key_keyin',    //웰컴페이의 쿠키페이 수기결제 연동 시크릿키
);

foreach( $check_sanitize_keys as $key ){
    $$key = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : '';
}

$updateSet = [];

foreach ($check_sanitize_keys as $column) {
    array_push($updateSet, "{$column}='{$$column}'");
}

$updateSetSql = implode(",", $updateSet);

$sql = " UPDATE {$g5['g5_shop_default_table']} SET {$updateSetSql} ";

sql_query($sql);

goto_url("./cookiepay.pgconfig.php");
