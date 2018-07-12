<?php 
namespace Admin\Controller;
// use Admin\Controller\PrivilegeController;
use Think\Controller;
use Plug\rongcloud\RongCloud;
/**
* 后台管理员控制器
*/
class AdminController extends Controller
{ 
    /**
     * [index 管理员列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function admins()
    {
        $admins=M('user')->where(['status'=>1,'user_type'=>2])->field('id,user_name,user_nick,user_icon,reg_time,user_tel,ry_token')->select();
        $count=count($admins);
        $this->assign('count',$count);
        $this->assign('admins',$admins);
        $this->display();
    }
    /**
     * [index 管理员详情]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function admin()
    {
        $uid=I('uid');
        $user=M('user')
                ->alias('u')
                ->join('lc_user_detail ud ON ud.uid=u.id')
                ->where(['u.id'=>$uid])
                ->field('u.id,u.user_name,u.user_nick,u.user_icon')
                ->find();
        $this->assign('admin',$user);
        $this->display();
    }
    /**
     * [index 添加管理员 页面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function addAdmin()
    {
        $user_icon = randIcon();
        $this->assign('user_icon',$user_icon);
        $this->display();
    }
    /**
     * [index 添加管理员 逻辑]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function toAddAdmin()
    {
        $post=I('post.');
        //验证数据
        if(empty($post['adminName']) || empty($post['password']) || empty($post['password2']) || empty($post['phone'])){
            $this->error('数据有误');die;
        }
        if($post['password'] != $post['password2']){
            $this->error('两次密码不一致');die;
        }
        $user_name=M('user')->field("max('user_name')")->find();
        var_dump($user_name);die;
        $data=['user_nick'=>$post['adminName'],'user_icon'=>$post['user_icon'],'password'=>md5($post['password']),'user_tel'=>$post['phone'],'reg_time'=>time(),'user_type'=>2];
        M()->startTrans();
        $res1=M('user')->add($data);
        $res2=M('user_detail')->add(['uid'=>$res1]);
        if($res1>0 || $res2>0){
            //融云注册账号
            $key_secret=get_rong_key_secret();
            $rong_cloud=new RongCloud($key_secret['key'],$key_secret['secret']);
            $token_json=$rong_cloud->user()->getToken($res1,$post['adminName'],$post['user_icon']);
            $token_array=json_decode($token_json,true);
            if ($token_array['code']!=200) {
                M()->rollback();
                $this->error('增加管理员失败');
            }else{
                M()->commit();
                $token=$token_array['token'];
                $token_str=array('ry_token'=>$token);
                M('user')->where(['id'=>$res1])->save($token_str);
                $this->success('增加管理员成功',U('/Admin/Admin/admins'));
            }
        }else{
            $this->error('增加管理员失败');
        }
    }
    /**
     * [index 管理员列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function editAdmin()
    {
        $id=I('get.id');
        $admin=M('admin')
            ->alias('a')
            ->join('lc_admin_role ar ON ar.admin_id=a.id')
            ->where(['a.id'=>$id])
            ->field('a.id,a.name,a.tel,ar.role_id')
            ->find();
        $this->assign('admin',$admin);
        $this->display();
    }
    /**
     * [index 管理员列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function saveAdmin()
    {
        $this->display();
    }
    /**
     * [index 管理员列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function delAdmin()
    {
        $id=I('post.id',0);
        if($id==0){
            $code='1';
            $msg='数据有误';
        }else{
            if($id==1){
                $code='1';
                $msg='不能删除超级管理员';
            }else{ 
                M()->startTrans();
                $res1=M('admin')->where(['id'=>$id])->delete();
                $res2=M('admin_role')->where(['admin_id'=>$id])->delete();
                if($res1==false || $res2==false){
                    M()->rollback();
                    $code='1';
                    $msg='删除管理员失败';
                }else{
                    M()->commit();
                    $code='0';
                    $msg='删除管理员成功';
                }
            }
        }
        $this->ajaxReturn(['code'=>$code,'msg'=>$msg]);
    }
    /**
     * [modRoleStatus 改变管理员状态 1-正常 2-禁用]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function modAdminStatus()
    {
        $id=I('post.id');
        if($id==1){
            $this->ajaxReturn(['code'=>1,'msg'=>'不能禁用超级管理员']);
        }else{ 
            $status=I('post.status');
            $status1= $status == 1 ? 2 : 1 ;
            $res=M('admin')->where(['id'=>$id])->save(['status'=>$status1]);
            $text= $status1 == 1 ? '成功启用' : '成功禁用' ;
            if($res===false){
                $this->ajaxReturn(['code'=>1,'msg'=>$text]);
            }else{
                $this->ajaxReturn(['code'=>0,'msg'=>$text]);
            }
        }
    }
    /**
     * [modPwd 修改密码页面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function modPwd()
    {
        $uid=empty(I('get.uid')) ? session('adminInfo.admin_id') : I('get.uid') ;
        if(session('adminInfo.admin_id')!=1 && $uid !=session('adminInfo.admin_id')){
            $this->error('没有权限');
        }else{
            if(session('adminInfo.admin_id')==1 && $uid != 1){
                $type=2;
            }else{
                $type=1;
            }
            $admin=M('admin')->where(['id'=>$uid])->getField('name');
            $this->assign('name',$admin);
            $this->assign('type',$type);
            $this->display();
        }
    }
    /**
     * [savePwd 保存修改密码]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function savePwd()
    {
        //验证身份（只允许超级管理员/自己 修改密码）
        $post=I('post.');
        $admin_id=empty($post['uid']) ? session('adminInfo.admin_id') : $post['uid'] ;
        if(session('adminInfo.admin_id')!=1 && $admin_id !=session('adminInfo.admin_id')){
            $this->error('没有权限');
        }else{ 
            if(empty($post['oldpassword']) || empty($post['newpassword']) || empty($post['newpassword2'])){
                $this->error('密码不能为空');
            }else{
                if($post['newpassword'] != $post['newpassword2']){
                    $this->error('两次密码不一致');
                }
                if(session('adminInfo.admin_id')==1 && $admin_id != 1){
                    $pwd=M('admin')->where(['id'=>$admin_id])->getField('password');
                    if(md5($post['oldpassword']) != $pwd){
                        $this->error('旧密码不正确');
                    }
                }
                $res=M('admin')->where(['id'=>$admin_id])->save(['password'=>md5($post['password'])]);
                if($res===false){
                    $this->error('密码修改失败');
                }else{
                    $this->error('密码修改成功');
                }
            }
        }
    }
}