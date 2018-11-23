<?php
/**
 * 用户页
 * @author lujinbo
 * @date 2016-06-02
 */
class controller_index extends controller_base
{
	public function __construct()
	{
        $filter_config  = array('excludes' =>'' , '','notLoginOnFail'=>'','serverInnerAddress'=>PASSPORT_HOST);
        $this->SSOClient = lib_SSOClient::getInstance($filter_config);
		parent::__construct();
        $this->_is_login();// 验证是否登录
	}

    /**
     * 用户首页
     * @author lujinbo
     * @date   2017-06-05
     */
    public function index()
    {
        $this->display('index');
    }

    /**
     * 退出
     * @author lujinbo
     */
    public function logout()
    {
        $this->SSOClient->logout();
    }

    /**
    * 验证是否登录
    * @param
    * @author  lujinbo
    * @date    2017-05-27
    * @return
    */
    protected function _is_login()
    {
        if(!isset($_SESSION['nicke_name']))
        {
            $login_url = $this->get_loginurl();
            header('Location:'.$login_url);
        }

        return $this->uid = $this->_cur_user_id;
    }

    //获取要跳转的登录的url
    protected function get_loginurl()
    {
        $back_url = urlencode(WWW_SERVICE_HOST.substr($_SERVER['REQUEST_URI'],1));
        $login_url = PASSPORT_HOST.'xuemao/login/index.php?back_url='.$back_url.'&source=xuemao';

        return $login_url;
    }
}
