## PHP SSO单点登录流程
整个实现流程参考流程图，由于涉及代码太多不便上传，只上传了部分代码，只保留了基本架构，如果项目中需要，则需要自己提炼。

### 代码运行过程：

#### server端有局部会话令牌情况：
用户访问client/user.php 时，验证是否登录，未登录则跳转是 server/login.php  login.php中 首先验证本地是否有局部会话的令牌，如果有则将局部会话保存的vt令牌重定向到用户浏览器，用户浏览器将vt令牌传给client端，client 远程验证vt的有效性（server/api.php），成功则返回用户信息，client端创建局部会话，并将受限资源返回给用户浏览器

#### server端没有局部会话令牌情况：
用户访问client/user.php 时，验证是否登录，未登录则跳转是 server/login.php  login.php中 首先验证本地是否有局部会话的令牌，本地没有会话令牌，显示登录界面，用户输入完账号密码，验证通过后创建局部会话，创建vt令牌，server端携带令牌重定向到用户浏览器，用户浏览器将vt令牌传给client端，client 远程验证vt的有效性（server/api.php），成功则返回用户信息，client端创建局部会话，并将受限资源返回给用户浏览器

![desc](https://github.com/lujinbo/php-sso/blob/master/resource/sso.png?raw=true) 
