<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/23
 * Time: 16:42
 */
    function get_client_ip($type = 0) {
        $type       =  $type ? 1 : 0;
        static $ip  =   NULL;
        if ($ip !== NULL) return $ip[$type];
        //优先代理IP
        if (isset ($_SERVER['HTTP_X_REAL_IP'])){
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos) unset($arr[$pos]);
            $ip     =   trim($arr[0]);
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u",ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }



    //匹配ID地址
    function IpToLocation($userip){
        require_once(dirname(dirname(__FILE__)) . "/helper/IP.class.php");
        $regions = array (
            '北京' => '北京',
            '天津' => '天津',
            '河北' => '石家庄',
            '山西' => '太原',
            '内蒙古'=>'呼和浩特',
            '辽宁'=>'沈阳',
            '吉林'=>'长春',
            '黑龙江'=>'哈尔滨',
            '上海' => '上海',
            '江苏'=>'南京',
            '浙江'=>'杭州',
            '安徽'=>'合肥',
            '福建'=>'福州',
            '江西'=>'南昌',
            '山东'=>'济南',
            '河南'=> '郑州',
            '湖北' => '武汉',
            '湖南' => '长沙',
            '广东' => '广州',
            '广西' => '南宁',
            '海南' => '海口',
            '重庆' => '重庆',
            '四川' => '成都',
            '贵州' => '贵阳',
            '云南' => '昆明',
            '西藏' => '拉萨',
            '陕西' => '西安',
            '甘肃' => '兰州',
            '青海' => '西宁',
            '宁夏' => '银川',
            '新疆' => '乌鲁木齐',
            '香港' => '香港',
            '澳门' => '澳门',
            '台湾' => '台湾',
        );

        $ips = IP::find($userip);
        return array('country'=>(!empty($ips[2]) && $ips[2] != $ips[1] ? $ips[2] : (isset($regions[$ips[1]]) ? $regions[$ips[1]] : $ips[1])), 'area'=>$ips[1]);
    }


    function fetchUrl($url, $time=3, $http_code = false) {
        $curl_opt = array(
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => $time
        );
        $ch = curl_init();
        curl_setopt_array($ch, $curl_opt);
        $contents = curl_exec($ch);
        if ($http_code) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            unset($contents);
            return $http_code;
        }
        curl_close($ch);

        return $contents;
    }

    function postCurl($url, $data = '', $time = 120, $header=[])
    {
        $curl_opt = array(
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT => $time,
            CURLOPT_SSL_VERIFYPEER => 0, // 跳过证书检查
            CURLOPT_SSL_VERIFYHOST => 0  // 从证书中检查SSL加密算法是否存在
        );
        if($header) $curl_opt[CURLOPT_HTTPHEADER] = $header;

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opt);
        $contents = curl_exec($ch);
        curl_close($ch);

        return $contents;
    }

    function setLog($msg, $dir = '', $file = '') {
        $maxsize = 2 * 1024 * 1024;
        $base_dir = dirname(dirname(__FILE__)).'/log/';
        !empty($dir) && $base_dir .= $dir;

        if(!is_dir($base_dir)) {
            mkdir($base_dir, 0777, true);
        }

        empty($file) && $file = date('Ymd').'.log';

        $path = $base_dir.$file;
        //检测文件大小，默认超过2M则备份文件重新生成 2*1024*1024
        if(is_file($path) && $maxsize <= filesize($path) )
            rename($path,dirname($path).'/'.time().'-'.basename($path));

        error_log($msg, 3, $path);
    }

    function checkParam($key) {
        if (!$key) return '';
        $is_md5 = preg_match("/^[a-z0-9A-Z]{32}$/", $key);

        if ($is_md5){
            $res = strtoupper(trim($key));
        } else {
            $res = strtoupper(md5(trim($key)));
        }
        return $res;
    }

    //获取用户表索引值
    function userTabIndex($uname, $num = 255)
    {
        $uname = strtolower($uname);
        $c1 = substr($uname, 0, 1);
        $c2 = substr($uname, -1);
        $n = ord($c1) + ord($c2);
        $l = strlen($uname);
        $n += $l * $l;
        return $n % $num;
    }