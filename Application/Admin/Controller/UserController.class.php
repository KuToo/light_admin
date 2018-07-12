<?php
namespace Admin\Controller;
use Think\Page;
use Admin\Controller\PrivilegeController;
use Admin\Controller\HomeController;
use Plug\rongcloud\RongCloud;

class UserController extends HomeController
{
    /**
     * [index 用户列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-01
     * @return    [type]     [description]
     */
    public function index(){
        $num=12;
        $user = M('user'); // 实例化User对象
        $count      = $user->count();// 查询满足要求的总记录数
        $page       = new Page($count,$num);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $user->order('reg_time desc')->limit($page->firstRow.','.$page->listRows)->select();
        foreach ($list as $k => &$v) {
            $res=M('user_detail')
                ->alias('ud')
                ->join('lc_area a1 ON a1.id=ud.province')
                ->join('lc_area a2 ON a2.id=ud.city')
                ->where(['uid'=>$v['id']])
                ->field('ud.sex,a1.area as pro,a2.area as city')
                ->find();
            $v['reg_time']=date('Y-m-d H:i:s',$v['reg_time']);
            $v['sex']=$res==1?'男':'女';
            $v['addr']=$res['pro'].'/'.$res['city'];
        }
        $cur_page=I('p',1);//当前页
        $this->assign('p',$cur_page);
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('count',$count);// 赋值分页输出
        $this->display();
    }
    /**
     * [user 用户详情]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function user()
    {
        $uid=I('post.id',0);
        $user_info=M('user')
            ->alias('u')
            ->join('lc_user_detail ud ON ud.uid=u.id')
            ->alias('lc_area a1 ON a1.id=ud.province')
            ->alias('lc_area a2 ON a2.id=ud.city')
            ->where(['u.id'=>$uid])
            ->field('u.user_name,u.user_nick,user_tel,a1.area as province,a2.area as city,ud.sex')
            ->find();
        $this->ajaxReturn($user_info);
    }
    /**
     * [addUser 增加用户 页面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     */
    public function addUser()
    {
        $province=M('area')->where(['level'=>1])->field('id,area')->select();
        $user_icon=randIcon();
        $this->assign('province',$province);
        $this->assign('user_icon',$user_icon);
        $this->display();
    }
    /**
     * [toAddUser 增加用户 逻辑]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function toAddUser()
    {
        $post=I('post.');
        if(empty($post['user_icon']) || empty($post['tel']) || empty($post['sex']) || empty($post['province']) || empty($post['city'])){
            $this->error('数据不能为空');
        }else{
            if(!preg_match('/^1[34578]\d{9}$/', $post['tel'])){
                $this->error('手机号码不合法');
            }else{
                $user_name=M('user')->order('id desc')->getField('user_name');
                $user_name=$user_name + 1 ;
                $pass=md5('666666');
                M()->startTrans();
                $res1=M('user')->add(['user_icon'=>$post['user_icon'],'user_nick'=>$post['user_nick'],'user_tel'=>$post['tel'],'user_name'=>$user_name,'password'=>$pass]);
                $res2=M('user_detail')->add(['sex'=>$post['sex'],'province'=>$post['province'],'city'=>$post['city'],'des'=>$post['des']]);
                if($res1 >0 && $res2 >0){
                    M()->commit();
                    //在融云上产生新用户（生成tokesn）
                    $token=get_rongcloud_token($res);
                    $res3=M('user')->where(['id'=>$res])->save(['ry_token'=>$token]);
                    $this->success('添加用户成功',U('/Admin/User/index'));
                }else{
                    M()->rollback();
                    $this->error('添加用户失败');
                }
            }
        }
    }
    /**
     * [editUser 编辑用户]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function editUser()
    {
        $uid=I('get.id');
        $user=M('user')
            ->alias('u')
            ->join('lc_user_detail ud ON ud.uid=u.id')
            ->alias('lc_area a1 ON a1.id=ud.province')
            ->alias('lc_area a2 ON a2.id=ud.city')
            ->where(['u.id'=>$uid])
            ->field('u.id,u.user_nick,u.user_icon,u.user_tel,user_level,ud.sex,ud.province,ud.city,ud.des')
            ->find();
        $this->assign('user',$user);
        $province=M('area')->where(['level'=>1])->field('id,area')->select();
        $this->assign('province',$province);
        $this->display();
    }
    /**
     * [saveUser 保存用户]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    function saveUser()
    {
        $post=I('post.');
        if(empty($post['id']) || empty($post['user_icon']) || empty($post['tel']) || empty($post['sex']) || empty($post['province']) || empty($post['city'])){
            $this->error('数据不能为空');
        }else{
            if(!preg_match('/^1[34578]\d{9}$/', $post['tel'])){
                $this->error('手机号码不合法');
            }else{
                M()->startTrans();
                $res1=M('user')->where(['id'=>$post['id']])->save(['user_icon'=>$post['user_icon'],'user_nick'=>$post['user_nick'],'user_tel'=>$post['tel']]);
                $res2=M('user_detail')->where(['uid'=>$post['id']])->save(['sex'=>$post['sex'],'province'=>$post['province'],'city'=>$post['city'],'des'=>$post['des']]);
                if($res1 ===false  || $res2 ===false){
                    M()->rollback();
                    $this->error('编辑用户失败');
                }else{
                    M()->commit();
                    $this->success('编辑用户成功',U('/Admin/User/index'));
                }
            }
        }

    }
    /**
     * [modUserStatus 改变用户状态]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-16
     * @return    [type]     [description]
     */
    public function modUserStatus()
    {
        $status=I('post.status');
        $id=I('post.id');
        $status1= $status == 1 ? 0 : 1 ;
        $res=M('user')->where(['id'=>$id])->save(['status'=>$status1]);
        $text= $status1 == 1 ? '成功启用' : '成功禁用' ;
        if($res===false){
            $this->ajaxReturn(['code'=>1,'msg'=>$text]);
        }else{
            $this->ajaxReturn(['code'=>0,'msg'=>$text]);
        }
    }
    /**
     * [modpwd 重置密码]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function modpwd()
    {
        $id=I('post.id');
        $default='123456';
        $res=M('user')->where(['id'=>$id])->save(['password'=>md5($default)]);
        if($res===false){
            $this->ajaxReturn(['code'=>'1','msg'=>'重置密码失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'重置密码成功']);
        }
    }

    /**
     * 上下分弹窗
     */
    public function modScore()
    {
        $uid=I('post.uid');
        $mark=I('post.mark');
        $score=I('post.uid');
        $user=M('user_detail')->where(['uid'=>$uid])->field('user_name,user_nick,money')->find();
    }

    /**
     * 上下分逻辑处理
     */
    public function saveModScore()
    {
        $uid=I('post.uid');
        $mark=I('post.mark');
        $score=I('post.uid');
        $money=M('user_detail')->where(['uid'=>$uid])->getField('money');
        if($mark==2){//下分
            if($money<$score){
                $this->ajaxReturn(['code'=>'100000','余额不足']);
            }else{
                $res=M('user_detail')->where(['uid'=>$uid])->save(['money'=>$money-$score]);
                $msg='下分';
            }
        }else{//上分
            $res=M('user_detail')->where(['uid'=>$uid])->save(['money'=>$money+$score]);
            $msg='上分';
        }
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>$msg.'失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>$msg.'成功']);
        }
    }


}