<?php  
namespace Admin\Controller;
use Think\Controller;

class LoginController extends Controller
{
    /**
     * [login 后台登录]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-01
     * @return    [type]     [description]
     */
    public function login()
    {
        if(IS_POST){
            //登录逻辑处理
            $data = I('post.');
            $user = M('user')->where(['user_nick'=>$data['name'],'user_type'=>2])->field('id,status,password')->find();
            if(empty($user)){
                $code = '100004';
                $msg = '用户不存在！';
            }elseif($user['status'] != 1){
                $code = '100005';
                $msg = '您的账号禁止登录！';
            }elseif($user['password']!= md5($data['pass'])){
                $code = '100006';
                $msg = '密码不正确！';
            }elseif($data['img_code']!=session('img_code')){
                $code = '100007';
                $msg = '验证码不正确！';
            }else{
                $code = '0';
                $msg = '登录成功！';
                $res=M('user')->where(['id'=>$user['id']])->save(['last_login_time'=>time()]);
            }
            if($code==0){
                session('admin_id',$user['id']);
                $this->success('登录成功',U('/Admin/Index/index'));
            }else{
                $this->error($msg);
            }
            
        }else{
            //登录表单界面
            $this->display();
        }
    }
    function logout()
    {
        session('admin_uid',null);
        $this->success('退出成功',U('/Admin/Login/login'));
    }

    public function getCode()
    {
        $imagecode = new \Plug\yzm\ImageCode();  
        $imagecode->doimg();  
        session('img_code',$imagecode->getCode())  ;//验证码保存到SESSION中
    }
}
