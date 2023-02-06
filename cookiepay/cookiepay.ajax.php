<?php
include_once('../shop/_common.php');

require_once G5_PATH."/cookiepay/cookiepay.lib.php";

require_once G5_PATH."/cookiepay/cookiepay.migrate.php";

$data = [];
$data['error'] = '';

$mode = $_POST['mode'];

// 
if ($mode == "") {
    
}

echo json_encode($data);
exit;
