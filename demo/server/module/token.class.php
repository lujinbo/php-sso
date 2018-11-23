<?php
class module_token extends module_base
{
    CONST LOGIN_TOKEN_PREFIX = 'SSO_USER_TOKEN:';

    /*
     * 生成token
     */
    public function create_token()
    {
        $vt = str_replace('.','',uniqid(ip2long($_SERVER['REMOTE_ADDR']).$_SERVER['REMOTE_PORT'].getmypid().mt_rand(), true));
        $domain = '';
        
		setcookie("passport_vt",$vt,time()+3600*24*7,'/',"", false, true);
        return $vt;
    }

    /*
     * 写TOKEN对应用户
     */
    public function add_token($login_user,$vt)
    {

        if(empty($vt) || empty($login_user))
        {
            return false;
        }
        $cache_time = 3600*24*7;
        $cache_key = self::LOGIN_TOKEN_PREFIX.$vt;
		$login_user = SEncode($login_user);
        $this->_redis->set($cache_key, serialize($login_user));
        $this->_redis->setTimeout($cache_key, $cache_time);        
        return true;
    }

    /*
     * 验证token
     */
    public  function validate_token($vt)
    {
        if(empty($vt))
        {
            return false;
        }
        
		$cache_key = self::LOGIN_TOKEN_PREFIX.$vt;
        $token = unserialize($this->_redis->get($cache_key));
		//取出并删除
		//$this->_lib_redis->delete($cache_key);
        return !empty($token) ? $token : $token['login_user'];
    }

    /*
     * 验证token
     */
    public  function delete_token($vt)
    {
        if(empty($vt))
        {
            return false;
        }

        $cache_key = self::LOGIN_TOKEN_PREFIX.$vt;

        if($this->_redis->delete($cache_key))
        {
            return true;
        }
        else
        {
            return false;
        }

    }
}    
