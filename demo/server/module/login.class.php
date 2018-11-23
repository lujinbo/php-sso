<?php
class fw_module_login extends fw_module_base
{
    private $_fw_data_users;
    private $_login_fail_cache_key = 'login_fail_key:';
    private $_login_cookie_key = 'xuemao_login_fail';
    private $_login_num = 10;
    public $login_error_num = 3;//登录失败次数，出现验证码
    private static $binding_type = array('open_id','unionid');
    private $_return_user_info = array();

    public function __construct()
    {
        parent::__construct();
        $this->_fw_data_users = new fw_data_users();
        $this->_module_token = new module_token();

    }

    /**
     * 用户登录（非临时oauth用户）
     * @author lujinbo
     * @param $userId
     * @param $login_type_number
     * @return bool|mixed
     */
    public function userLogin($userId,$login_type_number,$source,$login_log_type=1)
    {
        //用户信息
        $userInfo = $this->_fw_data_users->fetch_by_id($userId);
        //1：手机号码，2：邮箱用户，3：QQ账号，4：微信账号，5：微博
        switch ($login_type_number){
            case 1:
                $login_user_name = $userInfo['mobile'];
                break;
            case 2:
                $login_user_name = $userInfo['email'];
                break;
            default:
                $login_user_name = $userInfo['mobile'] ? $userInfo['mobile'] : $userInfo['email'];
                break;
        }
        $login_user = [
            'user_id' => $userInfo['id'],
            'nicke_name'=> $userInfo['nicke_name'],
            'role'=> $userInfo['role'],
            'header_img'=>$userInfo['header_img'],
            'login_user_name' => $login_user_name,
            'login_type'=>'account',//
            'login_type_number'=>$login_type_number//授权登录
        ];

        $vt = $this->_module_token->create_token();
        if($vt) {
            $this->_module_token->add_token($login_user,$vt);
            //清除登录失败次数
            $this->del_login_fail($userInfo['user_name']);
            //增加登录日志
            $this->add_login_log($login_user['user_id'],$source,$login_log_type);
            //更新登录信息
            $update_data = ['last_login' => date('Y-m-d H:i:s')];
            $this->_fw_data_users->update_by_id($userInfo['id'], $update_data);
            return $vt;
        }
        return false;
    }


    /**
     * 退出登录
     * @return Ambigous <multitype:, multitype:mixed string >
     */
    public function logout()
    {
        $this->_module_token->delete_token($_COOKIE['vt']);
        setcookie('user_name', '', time()-3600, '/');
        setcookie('user_id', '', time()-3600, '/');
        setcookie('vt', '', time()-3600, '/');
        
        return $this->_formatreturndata(true);
    }
    
    
    /**
     * 验证是否登录
     * @return Ambigous <multitype:, multitype:mixed string >
     */
    public function is_login()
    {
        $login_user = $this->_module_token->validate_token($_COOKIE['passport_vt']);
        if($login_user)
        {
            return $this->_formatreturndata(true,$login_user);
        }
        return $this->_formatreturndata(false);
    }
        
    /**
     * 返回登录sig code
     * @param int $user_id
     * @param string $user_name
     */
    private function _get_sigcode($user_id, $user_name)
    {
        return md5($user_id.$user_name.SALES_KEY);
    }
    
    /**
     * 获取登录允许的次数
     * @return Ambigous <number, multitype:number , boolean, string>
     */
    public function get_login_num($username,$business ='xuemao')
    {
        $cache_key = $this->_login_fail_cache_key. $username.'_'.$business;
        $cache_time = 3600;
        $cache_value = unserialize($this->_redis->get($cache_key));
        if($cache_value)
        {
            $cache_value['use_num'] = $this->_login_num - $cache_value['fail_num'];
        }
        else
        {
            $cache_value = array('fail_num' => 0, 'use_num' => $this->_login_num);
        }
        
        return $cache_value;
    }
    
    /**
     * 获取当前操作用户登录失败次数
     */
    public function get_user_login_num()
    {
        $login_fail = lib_cookie::getcookie($this->_login_cookie_key);
        $data['fail_num'] = $login_fail ? $login_fail : 0;
        $data['use_num'] = $this->_login_num - $data['fail_num'];
        return $data;
    }
    
    /**
     * 更新登录失败次数
     * @param unknown $username
     */
    public function set_login_fail($username,$business='xuemao')
    {
        $cache_key = $this->_login_fail_cache_key.$username.'_'.$business;
        $cache_time = 1200;
        $cache_value = unserialize($this->_redis->get($cache_key));
        if($cache_value)
        {
            $cache_value['fail_num'] += 1;
        }
        else 
        {
            $cache_value = array('fail_num' => 1);
        }
        
        $this->_redis->set($cache_key, serialize($cache_value));
        $this->_redis->setTimeout($cache_key, $cache_time);
        
        //当前操作用户失败次数

        $login_fail = lib_cookie::getcookie($this->_login_cookie_key);
        if($login_fail)
        {
            lib_cookie::setcookie($this->_login_cookie_key, $login_fail+1, time()+$cache_time, '/');
        }
        else 
        {
            lib_cookie::setcookie($this->_login_cookie_key, 1, time()+$cache_time, '/');
        }
    }
    
	/**
     * 删除登录失败次数
     * @param unknown $username
     */
    public function del_login_fail($username,$business='xuemao')
    {
        $cache_key = $this->_login_fail_cache_key. $username.'_'.$business;
		$this->_redis->delete($cache_key);
    }

    /**
     * 获取登录失败次数
     * @param type $username
     */
    public function get_login_fail($username,$business ='xuemao') {
        $cache_key = $this->_login_fail_cache_key . $username.'_'.$business;
        $fail = unserialize($this->_redis->get($cache_key));
        if($fail && isset($fail['fail_num'])) {
            return $fail['fail_num'];
        }
        return 0;
    }
    
    /**
     * 是否显示验证码
     * @param type $user_name
     */
    public function is_show_yzm($user_name){
        return $this->get_login_fail($user_name) >= $this->login_error_num ? 1 : 0;
    }

}

