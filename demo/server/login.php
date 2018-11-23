<?php
/**
 * 用户登录
 * @author lujinbo
 * @date 2016-07-30
 */
class controller_login extends controller_base
{
    private $_fw_module_users;
    public function __construct()
    {
        parent::__construct();
        $this->_module_login = new module_login();
        $this->_module_token = new module_token();
        $this->_module_users = new module_users();
    }

    /**
     * 全站统一登陆页面
     * @author lujinbo
     */
    public function index()
    {
        
        $back_url  = trim(lib_context::request('back_url',lib_context::T_STRING,XUEMAO_SERVICE_HOST));
		$source = trim(lib_context::request('source', lib_context::T_STRING,'index'));
		$back_url = empty($back_url) ? XUEMAO_SERVICE_HOST : $back_url;
		$source = empty($source) ? 'index' : $source;

        $login_res = $this->_module_login->is_login();
        //已登陆直接返回令牌
        if($login_res['result'] == $this->_succ)
        {
            return $this->validate_success($_REQUEST['back_url'],$_COOKIE['passport_vt']);
        }
		
        $this->assign('back_url',           $back_url);
        $this->assign('origin_url',         $origin_url);
        $this->assign('source',             $source);
        $this->display('user/login');
    }


    /**
     * 全站统一登陆页面action
     * @author lujinbo
     */
    public function account_login_do()
    {
        try
        {
            if(lib_context::is_post())
            {
                $user_name = trim(lib_context::post('user_name', lib_context::T_STRING));
                $pass_word = trim(lib_context::post('passwd',    lib_context::T_STRING));
                $back_url  = trim(lib_context::post('back_url',  lib_context::T_STRING));
                $source = trim(lib_context::post('source', lib_context::T_STRING,'index'));

                $data = array(
                    'user_name' => $user_name,
                    'passwd' => $pass_word,
                    'business'=>'xxx',
                );
                $login_num_res = $this->_module_login->get_login_num($data['user_name']);


                if ($login_num_res['use_num'] < 1) {
                    throw new lib_base_exception('登录次数操作限制，请与管理员联系！');
                }

                if (empty($data['user_name'])) {
                    throw new lib_base_exception('请输入账号');
                }
                if (empty($data['passwd'])) {
                    throw new lib_base_exception('请输入密码');
                }

                //验证验证码
                $show_yzm = $this->_module_login->is_show_yzm($user_name);
                if ($show_yzm) {
                    $code = lib_context::post('code', lib_context::T_STRING);
                    if ($code == '') {
                        throw new lib_base_exception('请输入验证码');
                    }

                    $lib_verifycode = new lib_verifycode('account_login');
                    $check_res = $lib_verifycode->check_code($code);

                    if (!$check_res) {
                        throw new lib_base_exception('验证码错误');
                    }
                }

                if(check_email($data['user_name']))
                {
                    $account_type = 'email';
                    //1：手机号码，2：邮箱用户，3：QQ账号，4：微信账号，5：微博
                    $login_type_number = 2;
                }
                elseif(check_mobile($data['user_name']))
                {
                    $account_type = 'mobile';
                    $login_type_number = 1;

                }
                else
                {
                    throw new lib_base_exception('账号类型应为手机号或者邮箱');
                }

                //验证账号信息
                $check_user_info = $this->_module_users->check_user_info($account_type,$data['user_name']);
                if(!$check_user_info)
                {
                    throw new lib_base_exception('账号不存在');
                }

                if($check_user_info['role'] != 1)
                {
                    throw new lib_base_exception('账号非学生端用户');
                }

                if($check_user_info['status'] == 0)
                {
                    throw new lib_base_exception('账号已被停用');
                }

                //密码检查
                if($check_user_info['pass_word'] != md5('#!*_xxx_*!#'.$pass_word) )
                {
                    throw new lib_base_exception('密码错误');
                }

                //开始登陆
                $vt = $this->_module_login->userLogin($check_user_info['id'],$login_type_number,$source);
                //登陆结果验证
                if($vt)
                {

                    $json_data['back_url'] = self::goback_url($back_url,$vt);//获取成功回跳地址带vt参数
                    $json_data['vt'] = $vt;
                    $this->_json_return(1,$json_data);
                }
                else
                {
                    throw new lib_base_exception('账号和密码不匹配，请重新输入');
                }
            }

        }
        catch(lib_base_exception $e)
        {
            $login_fail_num = $this->_module_login->get_login_fail($user_name);
            $data = array('login_fail_num'=> $login_fail_num);
            $this->_json_return(0,$data,$e->getMessage());
        }
    }


    /*
     * 验证成功回跳URL
     */
    public function validate_success($back_url,$vt)
    {
        if(self::goback_url($back_url,$vt))
        {
            header("Location:".self::goback_url($back_url,$vt));
        }
    }

    /*
     * 验证成功回跳URL
     */
    public static function goback_url($back_url,$vt)
    {
		if(strpos(BASE_URL, 'dev') !== false)
		{
			$back_url = empty($back_url) ? 'http://dev.www.test.com/' : $back_url;
		}
		elseif(strpos(BASE_URL, 'bch') !== false)
		{
			$back_url = empty($back_url) ? 'http://www.bch.test.com/' : $back_url;
		}
		else
		{
			$back_url = empty($back_url) ? 'http://www.test.com/' : $back_url;
		}

        if(!empty($back_url))
        {
			if(strpos($back_url,'__vt_param__') !== false)
			{
				$origin_url_arr = explode('?',$back_url);
				$origin_url = $origin_url_arr[0];
				$arr = parse_url($back_url);
				$arr_query = convertUrlQuery($arr['query']);
				unset($arr_query['__vt_param__']);
				if($arr_query)
				{
					$back_url = $origin_url.'?'.getUrlQuery($arr_query);
				}
				else
				{
					$back_url = $origin_url;
				}
			}

            if (strpos($back_url,'?') !== false) {
                $back_url.='&__vt_param__='.$vt;
            }else{
                $back_url.='?__vt_param__='.$vt;
            }
			return $back_url;
        }
        return '';
    }
}
