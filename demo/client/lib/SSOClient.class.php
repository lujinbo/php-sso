<?php
/*
 * sso客户端类
 * @author lujinbo
 */
class lib_SSOClient
{
	public static $_instance= null;
    private $_excludes;//不需要拦截的请求
    private $_serverBaseUrl = PASSPORT_HOST; // 服务端公网访问地址
    private $_serverInnerAddress; // 服务端系统间通信用内网地址
    private $_notLoginOnFail; // 当授权失败时是否让浏览器跳转到服务端登录页

    private function __construct($filter_config)
    {
        $this->_excludes = $filter_config['excludes']; 
        $this->_notLoginOnFail = $filter_config['notLoginOnFail'];

        //设置内网服务通讯地址
        lib_token::$_serverInnerAddress = $filter_config['serverInnerAddress'];
		$this->__init();
    }

	public static function getInstance($filter_config)
    {
         if (is_null ( self::$_instance ) || isset ( self::$_instance )) {
            self::$_instance = new self ($filter_config);
        }
        return self::$_instance;
    }

	public function __init()
    {
        if(isset($_COOKIE['vt']))
        {
            //验证token
            $user = lib_token::validate($_COOKIE['vt']);
            if ($user) {                
                // 将user存放session，供业务系统使用
                $this->holdUser($user);
            } else {
                // 删除无效的VT cookie
                setcookie("vt",'',time()-3600, "/","", false, true);
				$this->logout();
            }
        }
        else
        {
            $vtParam = $this->pasreVtParam(); // 从请求中获取vt参数
            if ($vtParam != null) {
                // 让浏览器向本链接发起一次重定向，此过程去除vtParam，将vt写入cookie
                $this->redirectToSelf($vtParam);
            }
        }
    }

    /*
     * 拦截请求
     */
    public function doFilter()
    {
        if(isset($_COOKIE['vt']))
        {
            //验证token
            $user = lib_token::validate($_COOKIE['vt']);
            if ($user) {                
                // 将user存放session，供业务系统使用
                $this->holdUser($user);
               
            } else {
                //删除无效的VT cookie
                setcookie("vt",'',time()-3600, "/","", false, true);
				$this->logout();
                //引导浏览器重定向到服务端执行登录校验
                $this->loginCheck();
            }
        }
        else
        {
            $vtParam = $this->pasreVtParam(); // 从请求中获取vt参数
            if ($vtParam == null) {
                // 请求中中没有vtParam，引导浏览器重定向到服务端执行登录校验
                $this->loginCheck();
            } else if (strlen($vtParam) == 0) {
                // 有vtParam，但内容为空，表示到服务端loginCheck后，得到的结果是未登录
                echo "403";
            } else {
                // 让浏览器向本链接发起一次重定向，此过程去除vtParam，将vt写入cookie
                $this->redirectToSelf($vtParam);
            }
        }
    }
    
    private function holdUser($user)
    {
        $this->_redis = new lib_base_redis();
		if(!$this->_redis->exists('session_user:'.session_id()))
		{
            $oauth_platform = isset($user['oauth_platform']) ? $user['oauth_platform'] : '';
            if(!isset($_SESSION)) session_start();
            $_SESSION['user_id'] = $user['user_id'];
			$_SESSION['nicke_name'] = $user['nicke_name']; 
			$_SESSION['role'] = $user['role'];
			$_SESSION['login_type'] = $user['login_type'];
			$_SESSION['oauth_platform'] = $oauth_platform;
            $_SESSION['header_img'] = $user['header_img'];
            $_SESSION['login_type_number'] = $user['login_type_number'];
			$_SESSION['login_user_name'] = isset($user['login_user_name'])?$user['login_user_name']:'';
			$header_img = '';
			if($user['header_img'])
			{
			  $header_img = explode("@", $user['header_img']);
			  $header_img = $header_img['0'].'?x-oss-process=image/circle,r_200';  
			}
			$_SESSION['header_img_alias'] = $header_img;
			
		}

    }

    // 从参数中获取服务端传来的vt后，执行一个到本链接的重定向，将vt写入cookie
    // 重定向后再发来的请求就存在有效vt参数了
    private function redirectToSelf($vtParam)
    {
        $paramname = "__vt_param__=";
		$back_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		//本地种cookie 
		setcookie("vt",$vtParam,time()+3600*24*7,'/',"", false, true);
		$location = $this->_serverBaseUrl . "xuemao/login/index.php?back_url=".urlencode($back_url);
        header("Location:".$location); 
    }

    // 从请求参数中解析vt
    private function pasreVtParam() 
    {
        $paramname = "__vt_param__=";
        $qstr = $_SERVER["QUERY_STRING"];
        if ($qstr == null) {
            return false;
        }
       
        if(strpos($qstr,$paramname) !== false ){
            return substr($qstr,(strpos($qstr,$paramname)+strlen($paramname)));
        }else{
            return null;
        }
    }

    // 引导浏览器重定向到服务端执行登录校验
    private function loginCheck()
    {
        $back_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        $qstr = $_SERVER['QUERY_STRING'];
        if($qstr)
        {
            $paramname = "__vt_param__=";
            if (strpos($qstr,$paramname) !== false) 
            {
                $new_qstr = explode('&',$qstr);
                foreach ($new_qstr as $k=>$v) {
                    if(strpos($v, $paramname) !==false)
                    {
                        unset($new_qstr[$k]);
                    }
                }
                $new_qstr = implode('&',$new_qstr);
            }

            $back_url .='?'.$new_qstr;
        }

        $location = $this->_serverBaseUrl . "xuemao/login/index.php?back_url=".urlencode($back_url);
        header("Location:".$location);
    }

    /*
     * 统一退出
     */
    public function logout()
    {

        header("Access-Control-Allow-Origin: ".PASSPORT_HOST);
        header("Access-Control-Allow-Methods","GET,POST,PUT,OPTIONS");
        header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Origin');
        header("Access-Control-Allow-Credentials: true ");
        lib_token::invalidate($_COOKIE['vt']);
        if(!isset($_SESSION)) session_start();
		$_SESSION['user_id'] = '';
        $_SESSION['nicke_name'] = ''; 
        $_SESSION['role'] = '';
        $_SESSION['login_type'] = '';
        $_SESSION['header_img'] = '';
		if(isset($_SESSION)){
            session_destroy();
            setcookie('PHPSESSID','',-1,'/');

        }
    }
}
