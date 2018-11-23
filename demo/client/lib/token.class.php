<?php
/*
 * token 管理类
 * @author lujinbo
 */
class lib_token
{
    public static $_serverInnerAddress = '';
    CONST CACHE_EXPIRE = 'APPLICATION_CACHE_USER:';
    /**
     * token验证
     */
    public static function validate($vt)
    {
		if($vt != $_COOKIE['vt'])
		{
			return array();
		}

        $user = self::remoteValidate($vt);

        return $user;
    } 


    /*
     * 远程验证
     */
    public static function remoteValidate($vt)
    {
        $url = self::$_serverInnerAddress."/api/validate_token.php?vt=". $vt;
        $user = json_decode(self::_curl_post($url),true);
        if($user['result'] == 'succ')
        {            
			$user['data'] = SDecode($user['data']['user_info'],XUEMAO_ENCRYPT_KEY);
            //self::cacheUser($vt,$user['data']);
            return $user['data'];
        }
        return false;
    }

    /*
     * 远程验证成功后将信息写入本地缓存
     */
    public static function cacheUser($vt,$user)
    {
        $lib_base_redis = new lib_base_redis();

        if($user)
        {
            $token_user['user'] = $user;
            $token_user['last_access_time'] = time();

            $cache_key = self::CACHE_EXPIRE.$vt; 
			$cache_time = 3600*24*7;
            $lib_base_redis->set($cache_key,serialize($token_user));
            $lib_base_redis->setTimeout($cache_key,$cache_time);
        }
    }

    /**
     * 用户退出时失效对应缓存
     * 
     * @param vt
     */
    public static function invalidate($vt) {
        // 从本地缓存移除
        $lib_base_redis = new lib_base_redis();
        $cache_key = self::CACHE_EXPIRE.$vt;
		$lib_base_redis->delete($cache_key);
		//setcookie('vt','',time()-3600,'/',BASE_URL);
		setcookie('vt','',time()-3600,'/');
		setcookie('newUserInfo','',time()-3600,'/');
        return true;
    }


    /**
     * 提交POST请求，curl方法
     * @param string  $url     请求url地址
     * @param mixed   $data   POST数据,数组或类似id=1&k1=v1
     * @param array   $header   头信息
     * @param int    $timeout   超时时间
     * @param int    $port    端口号
     * @return string           请求结果,
     *                          如果出错,返回结果为array('error'=>'','result'=>''),
     *                          未出错，返回结果为array('result'=>''),
     */
    public static function _curl_post($url, $data = array(), $header = array(), $timeout = 5, $port = 80)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $cookie = '';
        foreach ($_COOKIE as $key => $val) {
            $cookie .= $key.'='.$val.";";
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        //curl_setopt($ch, CURLOPT_PORT, $port);
        !empty ($header) && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        if (0 != curl_errno($ch)) {
            $result  = "Error:\n" . curl_error($ch);
        }
        curl_close($ch);
        
        return $result;
    }

}
