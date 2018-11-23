<?php
class controller_api extends controller_base
{
    private $_module_token;
    public function __construct()
    {
        parent::__construct();
        $this->_module_token = new module_token();

    }

    public function validate_token()
    {
        $vt = trim(lib_context::request('vt', lib_context::T_STRING));
        if(!empty($vt))
        {   
            $user_info = $this->_module_token->validate_token($vt);
			$user['user_info'] = $user_info;
			
            $this->_json_return($this->_succ,$user);
            
        }
        $this->_json_return($this->_fail,$user);
    }
}    