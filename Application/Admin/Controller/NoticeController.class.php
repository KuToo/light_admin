<?php
namespace Admin\Controller;
use Think\Page;
use Admin\Controller\PrivilegeController;
use Plug\rongcloud\RongCloud;

class NoticeController extends PrivilegeController
{
    public $error_code='';
    public $error_msg='';
    /**
     * [index 公告列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-01
     * @return    [type]     [description]
     */
    public function index(){
        $cur_page=I('p',1);//当前页
        $this->assign('p',$cur_page);
        $num         = 10;
        $group       = M('notice'); // 实例化group对象
        $count      = $group->where(['type'=>1])->count();// 查询满足要求的总记录数
        $page       = new Page($count,$num);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $group->where(['type'=>1])->order('pub_time desc')->limit($page->firstRow.','.$page->listRows)->select();
        foreach ($list as $k => &$v) {
            $v['pub_time']=date('Y-m-d H:i:s',$v['pub_time']);
            $v['content']=htmlspecialchars_decode($v['content']);
        }   
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }
    /**
     * [pubNotice 发布公告页面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function pubNotice(){
        $this->display();
    }
    /**
     * [doPubNotice 发布公告]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-12
     * @return    [type]     [description]
     */
    public function doPubNotice(){
        $uid=session('adminInfo.admin_id');
        $data=I('post.');
        $title=$data['title'];
        $sort=$data['order'];
        $content=htmlspecialchars_decode($data['editorValue']);
        if(empty($title)){
            $this->error_code='100101';
            $this->error_msg='公告标题不能为空';
        }elseif(intval($sort) >0){
            $this->error_code='100102';
            $this->error_msg='公告排序不能为空';
        }elseif(empty($content)){
            $this->error_code='100100';
            $this->error_msg='公告内容不能为空';
        }else{
            //TODO:验证身份
            //存表
            M()->startTrans();
            $notice=['type'=>1,'publisher'=>$uid,'title'=>$title,'content'=>$content,'order'=>$sort,'pub_time'=>time()];
            $res=M('notice')->add($notice);
            if($res>0){
                //荣云发送广播
                $config=get_rong_key_secret();
                $rongcloud= new RongCloud($config['key'],$config['secret']);
                $rcontent=json_encode([
                            "content"=>[
                                "id"=>$res,
                                "title"=>$title,
                                "message"=>$content
                            ],
                            "extra"=>''
                        ]);
                $res1=$rongcloud->message()->broadcast('a1','sys:publishNotice',$rcontent);//以超级管理员身份发送广播消息
                $res1=json_decode($res1,true);
                if($res1['code']==200){
                    M()->commit();
                    $this->error_code='0'; 
                    $this->error_msg='公告发布成功';
                }else{
                    M()->rollback();
                    $this->error_code='100102'; 
                    $this->error_msg='公告发布失败';
                }
            }else{
                M()->rollback();
                $this->error_code='100102';
                $this->error_msg='公告发布失败';
            }
        }
        if($this->error_code==='0'){
            $this->success($this->error_msg,U('/Admin/Notice/index'));
        }else{
            $this->error($this->error_msg);
        }
    }
    /**
     * [editNotice 编辑公告]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function editNotice()
    {
        $p=I('get.p');
        $this->assign('p',$p);
        $id=I('get.id');
        $notice=M('notice')->where(['id'=>$id])->find();
        $notice['content']=htmlspecialchars_decode($notice['content']);
        $this->assign('notice',$notice);
        $this->display();
    }
    /**
     * [saveNotice 保存 编辑公告]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function saveNotice()
    {
        $post=I('post.');
        $id=$post['id'];
        $title=$post['title'];
        $order=$post['order'];
        $content=htmlspecialchars_decode($post['editorValue']);
        if(empty($id) || empty($title) || $order==='' || empty($content)){
            $this->error('数据不能为空');
        }else{
            $data=['content'=>$content,'title'=>$title,'order'=>$order];
            $res=M('notice')->where(['id'=>$id])->save($data);
            if($res===false){
                $this->error('编辑失败');
            }else{
                $this->error('编辑成功',U('/Admin/Notice/index',array('p'=>$p)));
            }
        }
    }
    /**
     * [delNotice 删除公告]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function delNotice()
    {
        $id=I('post.id',0);
        if($id==0){
            $code='1';
            $msg='数据有误';
        }else{
            $res1=M('notice')->where(['id'=>$id])->delete();
            if($res1==false){
                $code='1';
                $msg='删除公告失败';
            }else{
                $code='0';
                $msg='删除公告成功';
            }
        }
        $this->ajaxReturn(['code'=>$code,'msg'=>$msg]);
    }
    /**
     * [delNotice 更改公告状态]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function modNoticeStatus()
    {
        $status=I('post.status');
        $id=I('post.id');
        $status1= $status == 1 ? 2 : 1 ;
        $res=M('notice')->where(['id'=>$id])->save(['status'=>$status1]);
        $text= $status1 == 1 ? '成功启用' : '成功禁用' ;
        if($res===false){
            $this->ajaxReturn(['code'=>1,'msg'=>$text]);
        }else{
            $this->ajaxReturn(['code'=>0,'msg'=>$text]);
        }
    }
}