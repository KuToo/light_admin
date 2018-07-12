<?php
namespace Admin\Controller;
use Think\Page;
// use Admin\Controller\AdminController;
use Think\Controller;
use Plug\rongcloud\RongCloud;
use Plug\redis\Predis;

class GroupController extends Controller
{
    /**
     * [index 管理员列表]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-01
     * @return    [type]     [description]
     */
    public function index(){
        $num         = 10;
        $group       = M('group'); // 实例化group对象
        $count      = $group->count();// 查询满足要求的总记录数
        $page       = new Page($count,$num);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $group->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
        foreach ($list as $k => &$v) {
            $v['ctime']=date('Y-m-d H:i:s',$v['ctime']);
            $v['status']= $v['status']==1 ? '正常' : '解散' ;
        }
        $cur_page=I('p',1);//当前页
        $this->assign('p',$cur_page);
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }
    /**
     * [users 群成员]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function users()
    {
        $gid=I('get.id');
        $num         = 10;
        $group_user       = M('group_user'); // 实例化group对象
        $count      = $group_user->where(['gid'=>$gid])->count();// 查询满足要求的总记录数
        $page       = new Page($count,$num);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show       = $page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list=M('group_user')
            ->alias('gu')
            ->join('lc_user u ON u.id=gu.uid')
            ->join('lc_user u1 ON u1.id=gu.yid')
            ->where(['gid'=>$gid])
            ->order('type desc,ctime desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->field('gu.uid,u.user_icon,u.user_nick as real_nick,gu.yid,u1.user_nick as ynick,gu.user_nick,gu.user_status,gu.admin_status,gu.type,gu.ctime,gu.quit_mark')
            ->select();
        $status_arr=['待确认','同意','拒绝'];
        $type_arr=['1'=>'普通群成员','2'=>'群管理员','3'=>'群主','4'=>'黑名单'];
        foreach ($list as $k => &$v) {
            $v['ctime']=date('Y-m-d H:i:s',$v['ctime']);
            $v['user_status']= $status_arr[$v['user_status']] ;
            $v['admin_status']= $status_arr[$v['admin_status']] ;
            $v['type']= $type_arr[$v['type']] ;
        }
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('count',$count);// 赋值分页输出
        $this->display();
    }
    /**
     * [addGroup 添加群组 页面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     */
    public function addGroup()
    {
        $group_icon = randIcon();
        $this->assign('group_icon',$group_icon);
        $this->display();
    }
    /**
     * [toAddGroup 创建群]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-03-16
     * @return    [type]     [description]
     */
    public function toAddGroup()
    {
        $data=I('post.');

        //数据验证
        if(empty($data['group_name'])){
            $this->error('群名称不能为空');die;
        }else{
            //新建管理员
            $user=$this->addUserByAdmin(1);
            $maxnum=M('group')->order("ABS(group_num) desc")->getField('group_num');
            M()->startTrans();
            $res1=M('group')->add(['group_name'=>$data['group_name'],'maxnum'=>$data['maxnum'],'gag'=>$data['gag'],'group_num'=>$maxnum+1,'ctime'=>time(),'master'=>$user['id'],'group_introduction'=>$data['group_introduction'],'group_icon'=>$data['group_icon']]);
            $res2=M('group_user')->add(['uid'=>$user['id'],'gid'=>$res1,'user_nick'=>$user['user_nick'],'ctime'=>time(),'user_status'=>1,'admin_status'=>1,'type'=>3,'yid'=>$user['id']]);
            //初始化算账数据
            //1.增加赔率数据
            $base_odds=M('odds')->where(['status'=>1])->select();
            $oddslist=array();
            foreach ($base_odds as $v) {
                $oddslist[]=['odds_id'=>$v['id'],'odds'=>$v['default_odds'],'status'=>1,'gid'=>$res1,'min'=>$v['default_min'],'max'=>$v['default_max']];
            }
            $insertOdds = M('group_odds')->addAll($oddslist);
            //2.增加回本
            $base_back=M('backs')->where(['status'=>1])->select();
            $backlist=array();
            foreach ($base_back as $v) {
                $backlist[]=['bid'=>$v['id'],'status'=>$v['default'],'gid'=>$res1];
            }
            $insertBack = M('group_back')->addAll($backlist);
            //3.增加关键词数据
            $base_keyword=M('keywords')->where(['status'=>1])->field('id,default_words')->select();
            $keywordlist=array();
            foreach ($base_keyword as $v) {
                $keywordlist[]=['kid'=>$v['id'],'status'=>1,'gid'=>$res1,'keywords'=>$v['default_words']];
            }
            $insertKeyword= M('group_keywords')->addAll($keywordlist);
            //4.增加封盘消息提醒
            $base_notice=M('bill_notice')->where(['status'=>1])->field('id,default_notice,time')->select();
            $noticelist=array();
            foreach ($base_notice as $v) {
                $noticelist[]=['nid'=>$v['id'],'status'=>1,'gid'=>$res1,'notice'=>$v['default_notice'],'time'=>$v['time']];
            }
            $insertNotice= M('group_bill_notice')->addAll($noticelist);
            //5.增加账单设置
            $base_setting=M('bill_setting')->where(['status'=>1])->field('id,default_value,default_status')->select();
            $settinglist=array();
            foreach ($base_setting as $v) {
                $settinglist[]=['sid'=>$v['id'],'status'=>1,'gid'=>$res1,'value'=>$v['default_value'],'status'=>$v['default_status']];
            }
            $insertSetting= M('group_bill_setting')->addAll($settinglist);
            //6.增加超忽视设置
            $base_exceed=M('exceed_ignore')->field('id,step,exceed,cal_bet,odds,type')->select();
//            var_dump($base_exceed);die;
            $exceedlist=array();
            foreach ($base_exceed as $v) {
                $exceedlist[]=['eid'=>$v['id'],'step'=>$v['step'],'exceed'=>$v['exceed'],'cal_bet'=>$v['cal_bet'],'odds'=>$v['odds'],'type'=>$v['type'],'gid'=>$res1];
            }

            $insertExceed= M('group_exceed_ignore')->addAll($exceedlist);

            if($res1 <=0 || $res2<=0 || $insertOdds===false || $insertBack===false || $insertKeyword===false || $insertNotice===false || $insertSetting===false || $insertExceed===false){
                M()->rollback();
                $this->error('创建群失败');die;
            }else{
                $config=get_rong_key_secret();
                $rongcloud=new RongCloud($config['key'],$config['secret']);
                $res=$rongcloud->group()->create($user['id'],$res1,$data['group_name']);
                $res=json_decode($res,true);
                if($res['code']==200){
                    M()->commit();
                    $this->success('创建群成功',U('/Admin/Group/index'));die;
                }else{
                    M()->rollback();
                    $this->error('创建群失败');die;
                }
            }
        }
    }
    /**
     * [editGroup 编辑群组]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function editGroup()
    {
        $id=I('get.id');
        $group=M('group')->where(['id'=>$id])->find();
        $this->assign('group',$group);
        $this->display();
    }
    /**
     * [editGroup 保存群组]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-18
     * @return    [type]     [description]
     */
    public function saveGroup()
    {
        $post=I('post.');
    }

    /**
     * 后台增加用户
     * @param $type 1 添加管理员 3 添加假人
     * @param $gid
     * @param string $nick
     */
    public function addUserByAdmin($type,$nick='')
    {
        //1.添加用户
        $max_user_name=M('user')->order("ABS(user_name) desc")->getField('user_name');
        $icon = randIcon();
        $user_name=$max_user_name+1;
        if($type==1){
            $user_nick='robot';
            $user_type=1;
        }else{
            $user_nick=$nick;
            $user_type=3;//假人
        }
        $res1=M('user')->add(['user_name'=>$user_name,'user_nick'=>$user_nick,'user_icon'=>$icon,'is_admin'=>$user_type,'password'=>md5('123456'),'user_tel'=>'0','reg_time'=>time(),'last_login_time'=>time()]);//user表
        $res2=M('user_detail')->add(['uid'=>$res1]);//user_detail表
        if($res1<=0 || $res2<=0){
            return false;
        }
        //在融云中增加用户
        $token=get_rongcloud_token($res1);
        $res=M('user')->where(['id'=>$res1])->save(['ry_token'=>$token]);
        if($res===false){
            M('user')->where(['id'=>$res1])->delete();
            M('user_detail')->where(['uid'=>$res1])->delete();
            return false;
        }
        $user=M('user')->where(['id'=>$res1])->find();
        return $user;
    }

    /**
     * @param $uid 用户id
     * @param $gid 群组ID
     * @param $type 群组身份
     */
    public function addUserToGroup($uid,$gid,$type)
    {
        $user=M('user')->where(['id'=>$uid])->find();
        $group=M('group')->where(['id'=>$gid])->find();
        if(empty($user) || empty($group) || !in_array($type,[1,2])){
            return false;
        }
        $group_user=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->find();
        if(empty($group_user)){
            M()->startTrans();
            $res=M('group_user')->add(['uid'=>$uid,'gid'=>$gid,'user_nick'=>$user['user_nick'],'ctime'=>time(),'yid'=>$group['master'],'user_status'=>1,'admin_status'=>1,'type'=>$type]);
            $rongcloud=$this->rongcloudCoon();
            $res2=$rongcloud->group()->join($uid,$gid,$group['group_name']);
            $res2=json_decode($res2,true);
            if($res<=0 || $res2['code']!=200){
                M()->rooback();
                return false;
            }else{
                M()->commit();
                return $res;
            }
        }else{
            $res=M('group_user')->where(['id'=>$group_user['id']])->save(['user_nick'=>$user['user_nick'],'ctime'=>time(),'yid'=>$group['master'],'is_quit'=>2,'quit_time'=>0,'money'=>0,'level_status'=>1,'user_status'=>1,'admin_status'=>1,'type'=>$type]);
            if($res===false){
                return false;
            }else{
                return $group_user['id'];
            }
        }
    }

    /**
     * 添加假人
     */
    public function addFaker()
    {
        $gid=I('post.gid');
        $name=I('post.name');
        $faker=$this->addUserByAdmin(3,$name);
        if($faker){
            $res=$this->addUserToGroup($faker['id'],$gid,1);
            if($res){
                $faker['id']=$res;
                $this->ajaxReturn(['code'=>'0','msg'=>'添加假人成功','data'=>$faker]);
            }else{
                $this->ajaxReturn(['code'=>'100000','msg'=>'添加假人失败']);
            }
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'添加假人失败']);
        }
    }

//    public function editFaker()
//    {
//        I()
//    }
    public function delFaker()
    {
        $id=I('post.id');
        $group_user=M('group_user')->where(['id'=>$id])->find();
        if(empty($group_user)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $res=M('group_user')->where(['id'=>$id])->save(['user_status'=>0,'admin_status'=>0,'is_quit'=>'1','quit_time'=>time()]);
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'删除假人失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'删除假人成功']);
        }
    }

    /**
     * 随机一条投注消息
     */
    public function randMsg($gid)
    {
        //获取所有的关键词
        $keywords1=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['k.status'=>1,'k.type'=>1,'gid'=>$gid])->field('k.id,k.type,gk.keywords,k.name')->select();
        $keywords2=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['k.status'=>1,'k.type'=>2,'k.id'=>['neq',17],'gid'=>$gid])->field('k.id,k.type,gk.keywords,k.name')->select();
        $tema=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['k.id'=>17,'gid'=>$gid])->field('k.id,k.type,gk.keywords,k.name')->find();
        $touzhu=array_column($keywords2,'keywords');
        $money_arr=[20,50,100,150,200,250,300,400,500,600];
        $money_arr_count=count($money_arr);
        $touzhu_arr=array();
        foreach ($touzhu as $v){
            $arr=explode('|',$v);
            if(empty($touzhu_arr)){
                $touzhu_arr=$arr;
            }elseif(!empty($arr)){
                $touzhu_arr=array_merge($touzhu_arr,$arr);
            }
        }
        foreach($keywords2 as $v){
            $money=$money_arr[mt_rand(0,$money_arr_count-1)];
            $keyword=explode('|',$v['keywords']);
            //去除关键词部分
            $keyword_count=count($keyword);
            $keyword_part=$keyword[mt_rand(0,$keyword_count-1)];
            $msg=$money.$keyword_part;
            Predis::getInstance()->lPush('faker_msg',$msg);
        }
        foreach($keywords1 as $v){
            $money=$money_arr[mt_rand(0,$money_arr_count-1)];
            $keyword=explode('|',$v['keywords']);
            //去除关键词部分
            $keyword_count=count($keyword);
            $keyword_part=$keyword[rand(0,$keyword_count-1)];
            if($v['id']==1) {//梭哈
                $touzhu1=$touzhu_arr[mt_rand(0,count($touzhu_arr))];
                $msg=$keyword_part.$touzhu1;
            }elseif($v['id']==3 || $v['id']==22){//需要三部分 加注 100 大
                $touzhu1=$touzhu_arr[mt_rand(0,count($touzhu_arr))];
                $msg=$keyword_part.$money.$touzhu1;
            }elseif($v['id']==4 || $v['id']==5 ){//需要两部分 查100 回100
                $msg=$keyword_part.$money;
            }else{//需要一部分 历史
                $msg=$keyword_part;
            }
            Predis::getInstance()->lPush('faker_msg',$msg);
        }
        //特码
        $tema_keywords=explode('|',$tema['keywords']);
        $num=mt_rand(1,27);
        $tema_money=$money_arr[mt_rand(0,$money_arr_count-1)];
        $tema_setting=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>3,'gid'=>$gid])->getField('gbs.status');
        if($tema_setting==1){//特码分数
            $msg=$num.$tema_keywords[mt_rand(0,count($tema_keywords)-1)].$tema_money;
            Predis::getInstance()->lPush('faker_msg',$msg);
        }else{
            $msg=$tema_money.$tema_keywords[mt_rand(0,count($tema_keywords)-1)].$num;
            Predis::getInstance()->lPush('faker_msg',$msg);
        }
    }
    /**
     * 发送群组消息
     */
    public function sendMessage()
    {
        $uid=I('post.uid');
        $gid=I('post.gid');
        $group_money=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->getField('money');
        $money_arr=[20,50,100,150,200,250,300,400,500,600];
        $money_arr_count=count($money_arr);
        if($group_money>=5000){
            $msg='回3000';
        }else{
            $msg_count=Predis::getInstance()->lLen('faker_msg');
            if($msg_count<=0){
                $this->randMsg($gid);
            }
            $msg=Predis::getInstance()->rPop('faker_msg');
            preg_match('/\d{1,}/',$msg,$match);
            if(!empty($match) && $group_money<$match[0]){
                $msg='查'.$money_arr[mt_rand(0,$money_arr_count-1)];
                ;
            }
        }
        if(empty($gid) || empty($uid) ||  empty($msg)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $content=json_encode(['content'=>$msg]);
        $rongcloud=$this->rongcloudCoon();
        $res= $rongcloud->message()->publishGroup($uid,$gid,'RC:TxtMsg',$content);
        $res=json_decode($res,true);
        if($res['code']==200){
            $this->ajaxReturn(['code'=>'0','msg'=>'发送成功']);
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'发送失败']);
        }
    }

    /**
     * 连接融云
     * @return RongCloud
     */
    private function rongcloudCoon()
    {
        $rong_config=get_rong_key_secret();
        $rongcloud= new RongCloud($rong_config['key'],$rong_config['secret']);
        return $rongcloud;
    }
}