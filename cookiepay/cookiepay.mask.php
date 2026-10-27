<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * 결제 로그에 남는 민감정보를 가린다.
 *
 * cookiepay_payment_log() 가 기록하는 내용에는 통지 전문·SQL 전문·PG 응답이
 * 그대로 들어간다. 그 안에 빌링키, 카드번호, 카드 비밀번호, 연동 시크릿 키,
 * 발급 토큰이 평문으로 섞여 있었다. 로그는 운영 디버깅의 유일한 수단이라
 * 끄거나 줄이는 대신, 값만 가리고 형태는 남긴다.
 *
 * 호출 지점이 40곳이 넘으므로 각 호출부가 아니라 로그 함수 한 곳에서 처리한다
 * (cookiepay.lib.php 의 cookiepay_payment_log 참고).
 *
 * 가리는 방식 두 가지:
 *   - 자격증명(키·비밀번호·토큰): 전부 가린다. 뒷자리도 남기지 않는다
 *   - 식별값(빌링키·카드번호): 뒤 4자만 남긴다. 장애 추적에 필요하다
 */

if (!function_exists('cookiepay_mask_value')) {
    /**
     * 뒤 $keep 자만 남기고 가린다.
     */
    function cookiepay_mask_value($value, $keep = 4)
    {
        $value = (string) $value;
        $len = strlen($value);

        if ($len === 0) {
            return '';
        }

        if ($len <= $keep) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - $keep) . substr($value, -$keep);
    }
}

if (!function_exists('cookiepay_mask_log')) {
    /**
     * 로그 한 줄에서 민감한 값을 가린다.
     *
     * JSON(`"KEY":"값"`), SQL(`KEY = '값'`), 헤더(`KEY: 값`),
     * 평문(`KEY 값`) 네 가지 표기를 모두 처리한다.
     */
    function cookiepay_mask_log($text)
    {
        if (!is_scalar($text)) {
            return '(배열/객체 — 기록 생략)';
        }

        $text = (string) $text;

        if ($text === '') {
            return $text;
        }

        // 전부 가릴 값 — 이 값이 유출되면 그 자체로 결제를 일으킬 수 있다
        $secret = array('pay2_key', 'api_key', 'apikey', 'CARDPWD', 'CARDAUTH', 'TOKEN');

        // 뒤 4자만 남길 값 — 어느 건인지 분간은 되어야 추적이 가능하다
        $partial = array('BILLKEY', 'CARDNO');

        foreach ($secret as $key) {
            $k = preg_quote($key, '/');
            $text = preg_replace('/(["\']' . $k . '["\']\s*:\s*")[^"]*(")/i', '${1}***${2}', $text);
            $text = preg_replace('/\b(' . $k . '\s*=\s*\')[^\']*(\')/i',      '${1}***${2}', $text);
            $text = preg_replace('/\b(' . $k . '\s*:\s*)[^\s,"\'}\]]+/i',     '${1}***',     $text);
            $text = preg_replace('/\b(' . $k . '\s+)[^\s,"\'}\]]+/i',         '${1}***',     $text);
        }

        foreach ($partial as $key) {
            $k = preg_quote($key, '/');
            $cb = function ($m) { return $m[1] . cookiepay_mask_value($m[2]) . (isset($m[3]) ? $m[3] : ''); };

            $text = preg_replace_callback('/(["\']' . $k . '["\']\s*:\s*")([^"]*)(")/i', $cb, $text);
            $text = preg_replace_callback('/\b(' . $k . '\s*=\s*\')([^\']*)(\')/i',      $cb, $text);
            $text = preg_replace_callback('/\b(' . $k . '\s*:\s*)([^\s,"\'}\]]+)/i',     $cb, $text);
            $text = preg_replace_callback('/\b(' . $k . '\s+)([^\s,"\'}\]]+)/i',         $cb, $text);
        }

        return $text;
    }
}
