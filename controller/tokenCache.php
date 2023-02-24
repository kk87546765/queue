<?php


$data = $_REQUEST;

if (!$data['access_token']) {
    die('error');
}

$txt = dirname(dirname(__FILE__)) . "/log/tokenCache.txt";

$res = file_put_contents($txt,$data['access_token']);

if ($res) {
    echo "ok";
} else {
    echo 'error';
}