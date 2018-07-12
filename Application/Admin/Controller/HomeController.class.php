<?php
namespace Admin\Controller;
use Think\Controller;

class HomeController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!session('admin_id')) {
            //请先登录
            $this->error('请先登录',U('/Admin/Login/login'));
        }
    }
}