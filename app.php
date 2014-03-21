<?php

/**
 * @descrition 批量检测是否可以正常打开
 * @date 2014.03.17
 * @version 0.0.1
 */

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
define('DS', DIRECTORY_SEPARATOR);
define('START_TIME', microtime(true));
define('ROOT_DIR', dirname(__FILE__).DS);
define('DOMAIN_DIR', ROOT_DIR.'domains'.DS);
define('LOG_DIR', ROOT_DIR.'logs'.DS);
define('LIB_DIR', ROOT_DIR.'libs'.DS);
//重试次数,默认为10次.
define('RETRY_TIMES', 10);

require LIB_DIR.'functions.php';
require LIB_DIR.'RollingCurl.php';

checkEnv();

if (!($domain_files = readTheDir(DOMAIN_DIR))) {
    showError('目录[ '.DOMAIN_DIR.' ]还没有放域名.');
}

$domains = array();
$final_fail_domains = array();
$fail_domains = array();
$retry_domains = array();

foreach ($domain_files as $domain_file) {
    $domains = array_merge($domains, getFileContents($domain_file, true, true));
}
if (!($domains = array_unique($domains))) {
    showError('目录[ '.DOMAIN_DIR.' ]还没有放域名.');
}
$domain_nums = count($domains);

cliMsg('总共有 '.$domain_nums.' 个域名');

$count = 0;
$rc = new RollingCurl('RequestCallback');
$rc->window_size = 100;
$rc->options = array(
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => false,
    CURLOPT_VERBOSE => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_ENCODING => '',
    CURLOPT_NOBODY => true,
);
foreach ($domains as $domain) {
    $rc->add(new RollingCurlRequest(realUrl($domain)));
}
$rc->execute();

if ($fail_domains) {    
    cliMsg(PHP_EOL.PHP_EOL.'有'.count($fail_domains).'个不正常的域名,需要多次测试.'.PHP_EOL);
    do {        
        $rc = new RollingCurl('retryRequestCallback');
        $rc->window_size = 100;
        $rc->options = array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_ENCODING => '',
            CURLOPT_NOBODY => true,
        );
        foreach ($fail_domains as $domain) {
            $rc->add(new RollingCurlRequest(realUrl($domain)));
        }
        $rc->execute();

        if (empty($fail_domains)) {
            break;
        }
    } while (true);
}


cliMsg(PHP_EOL.PHP_EOL.'所有工作完成, 总用时: '.(microtime(true) - START_TIME).'秒.'.PHP_EOL);
$fail_domain_nums = count($final_fail_domains);
cliMsg('正常域名:   '.($domain_nums - $fail_domain_nums).'个');
cliMsg('不正常域名: '.$fail_domain_nums.'个');

if ($final_fail_domains) {
    $log_file = LOG_DIR.date('Y-m-d-H-i-s').'.log';
    cliMsg('日志已保存在: '.$log_file);
    file_put_contents($log_file, implode(PHP_EOL, $final_fail_domains));
}
