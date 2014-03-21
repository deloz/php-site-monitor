<?php

function realUrl($domain) {
    return 'http://'.str_ireplace(array('http://'), '', $domain);
}
function cliMsg($msg = '') {
    echo convert2gbk($msg), PHP_EOL;
}

function checkEnv() {
    mkdirp(DOMAIN_DIR);
    mkdirp(LOG_DIR);
    function_exists('curl_init') or showError('curl扩展没有开启,请检查php.ini配置');
}

function mkdirp($dir) {
    is_dir($dir) or mkdir($dir, 0777, true);
}

function retryRequestCallback($response, $info, $request) {    
    global $retry_domains, $final_fail_domains, $fail_domains;

    $domain = str_ireplace(array('http://'), '', $request->url);

    if (isset($retry_domains[$domain])) {
        $retry_domains[$domain]++;
    } else {
        $retry_domains[$domain] = 1;
    }


    $ip = gethostbyname($domain);
    cliMsg('^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^'.PHP_EOL.'域名:'.str_pad($domain, 20, ' ', STR_PAD_RIGHT).'已经重试'.$retry_domains[$domain].'次. 最多重试'.RETRY_TIMES.'次');
    if (!in_array($info['http_code'], array(200, 301))) {
        cliMsg('域名:'.str_pad($domain, 20, ' ', STR_PAD_RIGHT).'不正常,用时: '.$info['total_time'].'秒,http状态为: '.$info['http_code'].' ,IP: '.$ip.',稍候重试.');
        if ($retry_domains[$domain] < RETRY_TIMES) {
            in_array($domain, $fail_domains) or $fail_domains[] = $domain;
        } else {
            $final_fail_domains[] = str_pad($domain, 30, ' ', STR_PAD_RIGHT).str_pad($info['http_code'], 5, ' ', STR_PAD_RIGHT).$ip;
            if (false !== ($key = array_search($domain, $fail_domains))) {
                unset($fail_domains[$key]);
            }
        }
    } else {
        cliMsg('域名:'.str_pad($domain, 20, ' ', STR_PAD_RIGHT).'  正常,用时: '.$info['total_time'].'秒,http状态为: '.$info['http_code'].' ,IP: '.$ip);
        if (false !== ($key = array_search($domain, $fail_domains))) {
            unset($fail_domains[$key]);
        }
    }
    
    cliMsg('剩余'.count($fail_domains).'个域名要重试'.PHP_EOL.'^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^'.PHP_EOL.PHP_EOL);
}

function RequestCallback($response, $info, $request) {
    global $count, $domain_nums, $fail_domains;

    $count++;
    cliMsg('第'.$count.'个, 剩余'.($domain_nums - $count).'个');

    
    $domain = str_ireplace(array('http://'), '', $request->url);
    $ip = gethostbyname($domain);

    if (!in_array($info['http_code'], array(200, 301))) {
        cliMsg('域名:'.str_pad($domain, 20, ' ', STR_PAD_RIGHT).'不正常,用时: '.$info['total_time'].'秒,http状态为: '.$info['http_code'].' ,IP: '.$ip.',稍候重试.');
        $fail_domains[] = $domain;
    } else {
        cliMsg('域名:'.str_pad($domain, 20, ' ', STR_PAD_RIGHT).'  正常,用时: '.$info['total_time'].'秒,http状态为: '.$info['http_code'].' .IP: '.$ip);
    }
    
    cliMsg();
}

function detectEncoding($str) {
    return mb_detect_encoding($str, array('UTF-8', 'CP936', 'BIG5', 'ASCII'));
}

function convert2gbk($str) {
    return convertEncoding($str, 'CP936');
}

function convert2utf8($str) {
    return convertEncoding($str, 'UTF-8');
}

function convertEncoding($str, $to_encoding) {
    if ($to_encoding !== ($encoding = detectEncoding($str))) {
        $str = mb_convert_encoding($str, $to_encoding, $encoding);
    }

    return $str;
}

function readTheDir($dir) {
    $files = array();
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($dir)), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($objects as $file_info) {
        if (in_array($file_info->getFileName(), array('.', '..'))) {
            continue;
        }

        if ($file_info->isFile()) {
            $files[] = $file_info->getPathName();
        }
    }

    return $files;
}

function showError($msg) {
    die(convert2gbk($msg).PHP_EOL.PHP_EOL);
}

function getFileContents($file_name, $as_array = false, $remove_repeat = false) {
    file_exists($file_name) or showError('文件[ '.$file_name.' ]不存在');

    if (!$as_array) {
        return trim(file_get_contents($file_name));
    }

    $result = array_map('convert2utf8', file($file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

    return $remove_repeat ? array_unique($result) : $result;
}