<?php
namespace Admin\Controller;
use Think\Page;
use Admin\Controller\HomeController;
use Plug\rongcloud\RongCloud;

class IndexController extends HomeController
{
    /**
     * [index 后台首页]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-01
     * @return    [type]     [description]
     */
    public function index(){
        //服务器信息
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
        $hostname=$_SERVER['SERVER_NAME'];
        $port=$_SERVER['SERVER_PORT']==''?80:$_SERVER['SERVER_PORT'];
        $version=php_uname();
        $this->assign('host',$host);
        $this->assign('hostname',$hostname);
        $this->assign('port',$port);
        $this->assign('version',$version);
        $this->display();
    }
    /**
     * [modpwd 修改密码]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-17
     * @return    [type]     [description]
     */
    public function modpwd()
    {
      
    }  
}