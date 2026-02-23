<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if(empty($env)){
    die('arquivo env não localizado!');
}
foreach ($env as $key => $value) {
    define($key, $value);
}
