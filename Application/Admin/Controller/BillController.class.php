<?php
namespace Admin\Controller;
use Think\Page;
use Admin\Controller\HomeController;
use Plug\redis\Predis;
use Plug\rongcloud\RongCloud;
use Admin\Controller\TimerController;
/**
 * 账单操作类
 */
class BillController extends HomeController
{
    public function index()
    {
        $rong_config=get_rong_key_secret();
        $this->assign('appkey',$rong_config['key']);
        //开奖频道配置
        $prize_channel=C('LOTTERY.BJ');
        $gid=I('id');
        $group=M('group')->where(['id'=>$gid])->find();
        $admin_info=M('user')->where(['id'=>$group['master']])->field('user_name,user_nick,reg_time,ry_token')->find();
        $this->assign('admin_info',$admin_info);
        if(empty($group)){
            $this->error('群信息有误');die;
        }
        //最新一期开奖信息
        $current_period=$this->getCurrentPeriod();
        //玩家列表
        $group_users=M('group_user')->where(['gid'=>$gid,'user_status'=>1,'admin_status'=>1,'is_quit'=>2])->select();

        if(!empty($group_users)){
            foreach ($group_users as $k=>$v) {
                $user_type=M('user')->where(['id'=>$v['uid']])->getField('is_admin');
                if($user_type==3){
                    unset($group_users[$k]);
                }else{

                    if($v['money']==0){//余额为0
                        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$v['uid'],'period'=>$current_period['period']+1])->find();
                        if(empty($bill) || $bill['total_bet']==0){//没有投注信息或者投注进额为0或者是假人投注
                            unset($group_users[$k]);
                        }
                    }
                }
            }
            foreach ($group_users as $key => &$value) {
                $value['user_name']=M('user')->where(['id'=>$value['uid']])->getField('user_name');
                $bill=M('bill')->where(['gid'=>$gid,'uid'=>$value['uid'],'period'=>$current_period['period']+1])->find();
                // var_dump(M()->_sql());
                if(!empty($bill)){
                    $value['des']=$bill['des'];
                    $value['score']=$bill['total_bet'];
                    $value['time']=$bill['time'];
                    $value['profit']=$bill['profit'];
                }else{
                    $value['des']='--';
                    $value['score']=0;
                    $value['time']=0;
                    $value['profit']=0;
                }
            }
            // die;
        }

        //开奖数据
        $periods=M('period')->where(['status1'=>1])->order('period desc')->limit(0,179)->select();
        if(!empty($periods)){
            foreach ($periods as &$valu) {
                $num1=$valu['num1'];
                $num2=$valu['num2'];
                $num3=$valu['num3'];
                $cal_res=$valu['cal_res'];
                $cal_des='';
                if($cal_res>13){
                 $cal_des .='大';
                }else{
                 $cal_des .='小';
                }
                if($cal_res%2==0){
                 $cal_des .='双';
                }else{
                 $cal_des .='单';
                }
                if($num1+1==$num2 && $num2+1==$num3){
                 $cal_des .=' 顺子';
                }
                if($num1==$num2 && $num2==$num3){
                 $cal_des .=' 豹子';
                }
                if( ($num1==$num2 && $num2!=$num3) || ($num1==$num3 && $num2!=$num3) || ($num2==$num3 && $num2!=$num1) ){
                    $cal_des .=' 对子';
                }
                $max=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid'=>$gid,'bs.id'=>1])->getField('gbs.value');
                $min=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid'=>$gid,'bs.id'=>2])->getField('gbs.value');

                if($cal_res>(27-$max) && $cal_res<27){
                    $cal_des .=' 极大';
                }
                if($cal_res>0 && $cal_res<$min){
                    $cal_des .=' 极小';
                }
                $profits=M('bill')->where(['gid'=>$gid,'period'=>$valu['period']])->getField('profit',true);
                if(empty($profits)){
                    $profit=0;
                }else{
                    $profit=array_sum($profits);
                }
                $valu['cal_des']=$cal_des;
                $valu['profit']=-$profit;
            }
        }
        //封盘设置1
        $closeNotice1=M('group_bill_notice')->alias('gbn')->join('lc_bill_notice bn ON bn.id=gbn.nid')->where(['gbn.gid'=>$gid,'bn.type'=>['in',[1,2]]])->field('gbn.id,gbn.time,gbn.status,gbn.notice,bn.type')->select();
        //封盘设置2
        $closeNotice2=M('group_bill_notice')->alias('gbn')->join('lc_bill_notice bn ON bn.id=gbn.nid')->where(['gbn.gid'=>$gid,'bn.type'=>3])->field('gbn.id,gbn.time,gbn.status,gbn.notice,bn.type')->select();
        //下注设置 关键词1
        $keywords1=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['k.status'=>1,'k.type'=>1,'gid'=>$gid])->field('gk.id,gk.keywords,k.name')->select();
        //下注设置 关键词2
        $keywords2=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['k.status'=>1,'k.type'=>2,'gid'=>$gid])->field('gk.id,gk.keywords,k.name')->select();
        //下注设置 特码选项设置
        $tema=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>3,'gid'=>$gid])->field('gbs.id,gbs.status,value,sid')->find();
        //下注设置 投注方式设置
        $billType=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>5,'gid'=>$gid])->field('gbs.id,gbs.status,value,sid')->find();
        //下注设置 极小范围
        $maxvalue=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>2,'gid'=>$gid])->field('gbs.id,gbs.status,value,sid')->find();
        //下注设置 极大范围
        $minvalue=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>1,'gid'=>$gid])->field('gbs.id,gbs.status,value,sid')->find();
        //赔率设置 赔率设置
        $odds=M('group_odds')
            ->alias('go')
            ->join('lc_odds o ON o.id=go.odds_id')
            ->field('go.id,o.name,o.status,go.odds,go.min,go.max')
            ->order('o.id')
            ->where(['go.gid'=>$gid])
            ->select();
        //赔率设置 回本设置
        $backs=M('group_back')
            ->alias('gb')
            ->join('lc_backs b ON b.id=gb.bid')
            ->where(['gb.gid'=>$gid])
            ->field('gb.id,b.name,gb.status')
            ->select();
        //赔率设置 1314回本设置
        $back1314_total=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['sid'=>4,'gid'=>$gid])->field('gbs.id,gbs.status,gbs.value')->find();
        $back1314=M('group_exceed_ignore')->alias('gei')->join('lc_exceed_ignore ei ON ei.id=gei.eid')->where(['ei.id'=>['in',[1,2,3]],'gei.gid'=>$gid])->select();
        //回水设置 返利情况
        $rebate_all=M('group_rebate')->alias('gr')->join('lc_rebate r ON r.id=gr.rid')->where(['gr.gid'=>$gid,'r.type'=>['in',[1,2]]])->field('gr.limit,r.name,gr.value,gr.id,r.type')->select();
        //回水
        $begin_time=strtotime(date('Y/m/d 0:0:0'));
        $end_time=strtotime(date('Y/m/d 0:0:0',strtotime('+1 day')));
        $this->assign('begin',date('Y/m/d 0:0:0'));
        $this->assign('end',date('Y/m/d 0:0:0',strtotime('+1 day')));
        $water=M('bill')->alias('b')->join('lc_user u ON u.id=b.uid')->where(['u.is_admin'=>2,'b.gid'=>$gid,'b.atime'=>[['egt',$begin_time],['lt',$end_time]]])->select();
        $user=array_unique(array_column($water,'uid'));
        $water1=array();
        if(!empty($user)) {
            foreach ($user as $val) {
                $group_user = M('group_user')->alias('gu')->join('lc_user u ON u.id=gu.uid')->where(['u.is_admin'=>2,'uid' => $val, 'gid' => $gid])->field('gu.user_nick,gu.yid,gu.uid,gid,gu.id')->find();
                $y_nick = M('group_user')->where(['uid' => $group_user['yid'], 'gid' => $gid])->getField('user_nick');
                $y_name = M('user')->where(['id' => $group_user['yid']])->getField('user_name');
                $user_name = M('user')->where(['id' => $val])->getField('user_name');
                $bet_money = 0;
                $group_money = 0;
                $profit = 0;
                $mark1 = 1;
                $mark2 = 1;
                foreach ($water as $v) {
                    if ($val == $v['uid']) {
                        $bet_money += $v['total_bet'];
                        $profit += $v['profit'];
                        $bet_arr = explode(';', trim($v['bet'], ';'));
                        foreach ($bet_arr as $v) {
                            $arr = explode(',', $v);
                            if (in_array($arr[0], [13, 14, 15, 16])) {
                                $group_money += $arr[1];
                            }
                        }
                        if ($v['is_return_profit'] == 2) {
                            $mark1 = 2;
                        }
                        if ($v['is_return_water'] == 2) {
                            $mark2 = 2;
                        }
                    }
                }
                $data['uid'] = $val;
                $data['user_nick'] = $group_user['user_nick'];
                $data['y_nick'] = $y_nick;
                $data['y_name'] = $y_name;
                $data['profit'] = $profit;
                $data['user_name'] = $user_name;
                $data['bet_money'] = $bet_money;
                $data['group_money'] = $group_money;
                $data['proporty'] = round($group_money / $bet_money * 100, 2);
                $data['profit_mark'] = $mark1;
                $data['water_mark'] = $mark2;
                $water1[$val] = $data;
            }
        }


        //操作日志
        $logs=M('group_logs')->where(['gid'=>$gid])->limit(0,25)->order('ctime desc')->select();
        if(!empty($logs)){
            foreach ($logs as &$va) {
                $va['user_nick']=M('user')->where(['id'=>$va['uid']])->getField('user_nick');
                $va['ctime']=date('Y-m-d H:i:s',$va['ctime']);
            }
        }

        //当前时间
        $hour=date('H');
        $minu=floor(date('i')/5)*5;
        $date=$hour.','.$minu;
        //今天平台盈利
        $total_profit=$this->totalProfit($gid);
        //是否封盘
        $is_close=M('period_group')->where(['gid'=>$gid,'period'=>$current_period['period']])->getField('status');
        if(empty($is_close)){
            $is_close=1;
        }
        //回水记录
        $water_records=M('water_record')->where(['gid'=>$gid])->limit(20)->select();
        $record_count=M('water_record')->where(['gid'=>$gid])->count();
        $page=ceil($record_count/20);
        if($page<=5){
            $info='';
            for ($i=1;$i<=$page;$i++){
                $info.='<a class="page" href="javascript:void(0);" onclick="waterRecord('.$i.')">'.$i.'</a>';
            }
        }else{
            $info='<a class="prev" href="javascript:void(0);" onclick="prevpage()">上一页</a><a class="page" href="javascript:void(0);" onclick="waterRecord(1)">1</a><a class="page" href="javascript:void(0);" onclick="waterRecord(2)">2</a>...<a class="page" href="javascript:void(0);" onclick="waterRecord('.($page-1).')">'.($page-1).'</a><a class="page" href="javascript:void(0);" onclick="waterRecord('.$page.')">'.$page.'</a><a class="next" href="javascript:void(0);" onclick="nextpage()")>下一页</a>';
        }

        if(!empty($water_records)){
            foreach($water_records as &$values){
                $values['oper']=M('group_user')->where(['gid'=>$gid,'uid'=>$values['oper']])->getField('user_nick');
                $values['user']=M('group_user')->where(['gid'=>$gid,'uid'=>$values['uid']])->getField('user_nick');
                $values['ctime']=date('Y-m-d H:i:s',$values['ctime']);
                $values['start']=date('Y-m-d H:i:s',$values['start']);
                $values['end']=date('Y-m-d H:i:s',$values['end']);
            }
        }
        $this->assign('water_record',$water_records);
        $this->assign('page_info',$info);
        $this->assign('cur_page',1);
        $this->assign('total_page',$page);
        //假人列表
        $fakers=M('group_user')->alias('gu')->join('lc_user u ON u.id=gu.uid')->where(['gu.gid'=>$gid,'gu.user_status'=>1,'gu.admin_status'=>1,'gu.is_quit'=>2,'is_admin'=>3])->field('gu.id,gu.uid,u.user_name,u.user_icon,gu.user_nick')->select();
        $this->assign('fakers',$fakers);
        //假人消息列表
        $faker_messages=M('faker_message')->where(['status'=>1])->field('id,content')->select();
        $this->assign('faker_messages',$faker_messages);

        $this->assign('group',$group);
        $this->assign('prize_channel',$prize_channel['ZH_NAME']);
        $this->assign('group_users',$group_users);
        $this->assign('periods',$periods);
        $this->assign('closeNotice1',$closeNotice1);
        $this->assign('closeNotice2',$closeNotice2);
        $this->assign('odds',$odds);
        $this->assign('keywords1',$keywords1);
        $this->assign('keywords2',$keywords2);
        $this->assign('tema',$tema);
        $this->assign('billType',$billType);
        $this->assign('maxvalue',$maxvalue);
        $this->assign('minvalue',$minvalue);
        $this->assign('backs',$backs);
        $this->assign('back1314_total',$back1314_total);
        $this->assign('back1314',$back1314);
        $this->assign('water',$water1);
        $this->assign('logs',$logs);
        $this->assign('current_period',$current_period);
        $this->assign('rebate',$rebate_all);
        $this->assign('date',$date);
        $this->assign('total_profit',$total_profit);
        $this->assign('is_close',$is_close);
        $this->display();
    }
    /**
     * [search 搜索玩家]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-01
     * @param     string     $value [description]
     * @return    [type]            [description]
     */
    public function search()
    {
        $num=I('post.search');
        $period=I('post.period');
        $gid=I('post.gid');
        $user=M('user')->where(['user_name'=>$num])->find();
        $where=array();
        if(!empty($user)){
            $where[]=['uid'=>$user['id'],'gid'=>$gid];
            $where[]=['user_nick'=>$num,'gid'=>$gid];
            $where['_logic']='OR';
        }else{
            $where=['user_nick'=>$num];
        }
        $group_user=M('group_user')->where($where)->field('user_nick,uid,money')->find();
        if(!empty($group_user)){
            $user_name=M('user')->where(['id'=>$group_user['uid']])->getField('user_name');
            $bill=M('bill')->where(['gid'=>$gid,'uid'=>$group_user['uid'],'period'=>$period])->find();
            if(empty($bill)){
                if($group_user['money']==0){
                    $group_user=array();
                }else{
                    $group_user['user_name']=$user_name;
                    $group_user['bet']=0;
                    $group_user['des']='--';
                    $group_user['time']=0;
                    $group_user['profit']=0;
                    $group_user['total_bet']=0;
                }
            }else{
                $group_user['user_name']=$user_name;
                $group_user['bet']=$bill['bet'];
                $group_user['des']=$bill['des'];
                $group_user['time']=$bill['time'];
                $group_user['profit']=$bill['profit'];
                $group_user['total_bet']=$bill['total_bet'];
            }
        }
        $this->ajaxReturn($group_user);
    }
    /**
     * [clearBet 清空下注]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-01
     * @return    [type]     [description]
     */
    public function clearBet()
    {
        $period=I('post.period');//期数
        $gid=I('post.gid');//群id
        $bills=M('bill')->where(['gid'=>$gid,'period'=>$period])->select();
        //返回积分
        M()->startTrans();
        if(!empty($bills)){
            foreach ($bills as $value) {
                $res1=M('bill')->where(['period'=>$period,'gid'=>$gid,'uid'=>$value['uid']])->delete();
                $money=M('group_user')->where(['gid'=>$gid,'uid'=>$value['uid']])->getField('money');
                $bet_arr=explode(';',trim($bill['bet'],';'));
                $total_bet=0;
                foreach ($bet_arr as $v) {
                    $arr=explode(',',$v);
                    $total_bet += $arr[1];
                }
                $res2=M('group_user')->where(['gid'=>$gid,'uid'=>$value['uid']])->save(['money'=>$money+$total_bet]);
                if($res1===false || $res2===false){
                    M()->rollback();
                    $this->ajaxReturn(['code'=>'100000','msg'=>'清空下注失败']);
                }
            }
        }
        M()->commit();
        $new_bills=M('bill')->where(['gid'=>$gid,'period'=>$period])->select();
        if(!empty($new_bills)){
            foreach ($new_bills as &$value) {
                $group_user=M('group_user')->where(['uid'=>$value['uid'],'gid'=>$value['gid']])->field('user_nick,money')->find();
                $value['user_nick']=$group_user['user_nick'];
                $value['money']=$group_user['money'];
                $value['user_name']=$user['user_name'];
                $bet_arr=explode(';',trim($value['bet'],';'));
                $total_bet=0;
                foreach ($bet_arr as$v) {
                    $arr=explode(',',$v);
                    $total_bet += $arr[1];
                }
                $value['total_bet']=$total_bet;
            }
        }
        $this->ajaxReturn(['code'=>'0','msg'=>'清空下注成功','data'=>$new_bills]);
    }
    /**
     * [closeBill 关闭算账]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-08
     * @return    [type]     [description]
     */
    public function closeBill()
    {
        $gid=I('post.gid');
        $status=I('post.status');
        $res=M('group')->where(['id'=>$gid])->save(['cal_status'=>$status]);
        $group=M('group')->where(['id'=>$gid])->find();
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'操作失败','data'=>$group]);
        }
        $this->ajaxReturn(['code'=>'0','msg'=>'操作成功','data'=>$group]);
    }
    /**
     * [modPeriodStatus 封盘/开盘]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-01
     * @return    [type]     [description]
     */
    public function modPeriodStatus()
    {
        $period=I('post.period');//期数
        $gid=I('post.gid');//群id
        $status=I('post.status');//状态
        $info=M('period_group')->where(['gid'=>$gid,'period'=>$period+1])->find(['status'=>$status]);
        if(empty($info)){
            $res1=M('period_group')->add(['gid'=>$gid,'period'=>$period+1,'status'=>$status]);
            $res = $res1>0 ? true : false;
        }else{
            $res=M('period_group')->where(['gid'=>$gid,'period'=>$period+1])->save(['status'=>$status]);
        }
        if($res===false){
            $msg= $status==1 ? '开盘失败' : '封盘失败' ;
            $this->ajaxReturn(['code'=>'100000','msg'=>$msg]);
        }else{
            $msg= $status==1 ? '开盘成功' : '封盘成功' ;
            $this->ajaxReturn(['code'=>'0','msg'=>$msg]);
        }
    }
    /**
     * [getBetUser 获取下注用户信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-12
     * @return    [type]     [description]
     */
    public function getBetUser()
    {
        $gid=I('post.gid');//群组id
        $uid=I('post.uid');//用户Id
        $period=I('post.period');//期数
        $data=array();
        $group_user=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->field('user_nick,money')->find();
        if(empty($group_user)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $data['user_name']=M('user')->where(['id'=>$uid])->getField('user_name');
        $data['user_nick']=$group_user['user_nick'];
        $data['money']=$group_user['money'];
        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->find();
        if(!empty($bill)){
            $data['des']=$bill['des'];
            $data['time']=$bill['time'];
            $data['score']=$bill['total_bet'];
            $data['profit']=$bill['profit'];
        }else{
            $data['des']='--';
            $data['time']=0;
            $data['score']=0;
            $data['profit']=0;
        }
        $this->ajaxReturn(['code'=>'0','msg'=>$data]);
    }
    /**
     * [matchKeyword 根据消息内容计算投注信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-08
     * @return    [type]     [description]
     */
    public function matchKeyword()
    {
        $msg=I('post.msg');//消息
        $gid=I('post.gid');//群组id
        $uid=I('post.uid');//用户Id
        $period=I('post.period');//期数
        //群成员信息
        $group_user=M('group_user')
            ->alias('gu')
            ->join('lc_user u ON u.id=gu.uid')
            ->join('lc_user_detail ud ON u.id=ud.uid')
            ->where(['gu.uid'=>$uid,'gu.gid'=>$gid])
            ->field('gu.id,u.is_admin,gu.money,gu.user_nick,gu.uid,u.user_name,ud.money as totalmoney')
            ->find();
        //关键词
        $keywords=M('group_keywords')
            ->alias('gk')
            ->join('lc_keywords k ON k.id=gk.kid')
            ->where(['gk.gid'=>$gid])
            ->order('k.level desc')
            ->field('gk.id,k.type,gk.kid,k.name,gk.keywords')
            ->select();
        //投注信息
        $bill=M('bill')->where(['period'=>$period,'uid'=>$uid,'gid'=>$gid])->find();
        // var_dump($msg);
        // var_dump($gid);
        // var_dump($uid);
        // var_dump($period);
        // var_dump($bill);die;
        //是否封盘
        $bill_notice=M('group_bill_notice')
            ->alias('gbn')
            ->join('lc_bill_notice bn ON gbn.nid=bn.id')
            ->where(['gbn.gid'=>$gid,'bn.type'=>1])
            ->field('gbn.status,gbn.notice')
            ->find();
        $is_close=M('period_group')->where(['gid'=>$gid,'period'=>$period])->getField('status');
//        var_dump(M()->_sql());
//        var_dump($is_close);die;
        if(empty($is_close)){
            $is_close=1;
        }
        if($is_close==2){
            $notice=M('group_bill_notice')->alias('gbn')->join('lc_bill_notice bn ON bn.id=gbn.nid')->where(['bn.type'=>3,'gid'=>$gid,'gbn.status'=>1])->getField('notice');
            $this->ajaxReturn(['code'=>'100001','msg'=>$notice,'type'=>2]);//已经封盘
        }
        //投注方式（是否累加）
        $bill_type=M('group_bill_setting')->where(['gid'=>$gid,'sid'=>5])->getField('status');
        //连接融云
        $rongcloud = $this->rongcloudCoon();
        $str='';
        $total_money=0;
        $mark=1;
        $bet_arr=array();
        $des_arr=array();
        foreach ($keywords as $value) {
            if($mark==2){
                break;//匹配到type为1的时候停止循环
            }
            $matches=array();
            $res=array();
            if(empty($msg)){
                break;
            }
            if(!empty($value['keywords'])){
                if($value['kid']==1){//梭哈
                    $bets=M('group_keywords')
                        ->alias('gk')
                        ->join('lc_keywords k ON k.id=gk.kid')
                        ->where(['gk.gid'=>$gid,'k.type'=>2])
                        ->field('gk.id,gk.kid,gk.keywords,k.name')
                        ->select();
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    $money=0;
                    foreach ($keyword as $val) {//循环梭哈的关键词
                        foreach ($bets as $v) {//循环投注信息
                            $arr=explode('|',trim($v['keywords'],'|'));
                            foreach ($arr as $ve) {
                                $res=preg_match('/('.$val.$ve.')/',$msg,$matches);
                                if(!empty($matches)){
                                    $str = $v['kid'].','.$group_user['money'].';';
                                    $des = $group_user['money'].$value['name'].$v['name'];
                                    if($group_user['money']<=0){
                                        $this->ajaxReturn(['code'=>"100001",'msg'=>'余额不足','type'=>2]);
                                    }
                                    M()->startTrans();
                                    $res1=M('group_user')->where(['id'=>$group_user['id']])->save(['money'=>0]);
                                    $info=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->find();
                                    if(!empty($info)){
                                        $bet=explode(';',trim($info['bet'],';'));
                                        foreach ($bet as $va) {
                                            $ex=explode(',',$va);
                                            if($ex[0]==$v['kid']);{
                                                $bet_info=str_replace($va, $ex[0].','.$ex[1]+$group_user['money'], $info['bet']);
                                                $des_info=str_replace($ex[1].$val['name'], $ex[1]+$group_user['money'].$val['name'], $info['des']);
                                                $res2=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->save(['bet'=>$bet_info,'des'=>$des_info]);
                                                if($res1===false || $res2===false){
                                                    M()->rollback();
                                                    $this->ajaxReturn(['code'=>"100000",'msg'=>'投注失败1']);
                                                }else{
                                                    M()->commit();
                                                    if($group_user['is_admin']==2){
                                                        $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des_info}  学分：0  ",'type'=>1]);
                                                    }else{
                                                        $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des_info}  学分：0  ",'type'=>2]);
                                                    }
                                                }
                                                break;
                                            }
                                        }
                                        $bet_info=$info['bet'].';'.$str;
                                        $des_info=$info['des'].$des;
                                        $res2=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->save(['bet'=>$bet_info,'des'=>$des_info]);
                                        if($res1===false || $res2===false){
                                            M()->rollback();
                                            $this->ajaxReturn(['code'=>"100000",'msg'=>'投注失败2']);
                                        }else{
                                            M()->commit();
                                            if($group_user['is_admin']==2){
                                                $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des_info}  学分：0  ",'type'=>1]);
                                            }else{
                                                $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des_info}  学分：0  ",'type'=>2]);
                                            }
                                        }
                                    }
                                    $res2=M('bill')->add(['gid'=>$gid,'uid'=>$uid,'period'=>$period,'bet'=>$str,'des'=>$des]);
                                    if($res1===false || $res2<=0){
                                        M()->rollback();
                                        $this->ajaxReturn(['code'=>"100000",'msg'=>'投注失败3']);
                                    }else{
                                        M()->commit();
                                        if($group_user['is_admin']==2){
                                            $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  学分：0  ",'type'=>1]);
                                        }else{
                                            $this->ajaxReturn(['code'=>"0",'msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  学分：0  ",'type'=>2]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }elseif($value['kid']==2){//取消下注
                    $keywords=explode('|',trim($value['keywords'],'|'));
                    $match=array();
                    foreach ($keywords as $val) {
                        preg_match('/('.$val.')/',$msg,$match);
                        if(!empty($match)){
                            $bill=M('bill')->where(['gid'=>$gid,'period'=>$period,'uid'=>$uid])->order('atime desc')->find();//查询本期最近一次下注
                            M()->startTrans();
                            $res1=M('bill')->where(['uid'=>$uid,'period'=>$period,'gid'=>$gid])->delete();//删除下注
                            $bill_money=0;
                            $bet=explode(';',trim($bill['bet'],','));
                            if(!empty($bet)){
                                foreach ($bet as $v) {
                                    $bet_array=explode(',',$v);
                                    $bill_money +=$bet_array[1];
                                }
                            }
                            $money1=$group_user['money']+$bill_money;
                            $res2=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money1]);
                            if($res1===false || $res2===false){
                                M()->rollback();
                                $this->ajaxReturn(['code'=>'100000','msg'=>'取消失败']);
                            }
                            M()->commit();
                            $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  取消下注  余分：{$money1}",'type'=>1]);
                        }
                    }
                }elseif($value['kid']==3){//改
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    $remark1=0;
                    foreach ($keyword as  $val) {
                        if($remark==1){
                            break;
                        }
                        $matches=array();
                        preg_match('/('.$val.')/',$msg,$matches);
                        if(!empty($matches)){
                            $msg=str_replace($matches[0],'',$msg);
                            $bet_arr=array();
                            $des_arr=array();
                            if(!empty($bill)){
                                $bill_bet1=explode(';',trim($bill['bet'],';'));
                                $money0=0;
                                foreach ($bill_bet1 as $v) {
                                    $arr0=explode(',',$v);
                                    $money0 +=$arr0[1];
                                }
                                $money10=$group_user['money']+$money0;
                                M()->startTrans();
                                $res10=M('bill')->where(['id'=>$bill['id']])->delete();
                                $res20=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money10]);
                                if($res10===false || $res20===false){
                                    M()->rollback();
                                    $this->ajaxReturn(['code'=>'100000','msg'=>'修改下注失败']);
                                }else{
                                    $group_user=M('group_user')
                                        ->alias('gu')
                                        ->join('lc_user u ON u.id=gu.uid')
                                        ->join('lc_user_detail ud ON u.id=ud.uid')
                                        ->where(['gu.uid'=>$uid,'gu.gid'=>$gid])
                                        ->field('gu.id,gu.money,gu.user_nick,gu.uid,u.user_name,ud.money as totalmoney')
                                        ->find();
                                    $bill=array();
                                    M()->commit();
                                }
                                $remark1=1;
                            }
                        }
                    }
                }elseif($value['kid']==4){//查/上分
                    $money1=0;
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    foreach ($keyword as $val) {
                        $matches=array();
                        $matches1=array();
                        preg_match_all('/(\d{1,}'.$val.')/',$msg,$matches);
                        if(!empty($matches[0])){
                            foreach ($matches[0] as $v) {
                                $money1+=intval($v);
                            }
                            break;
                        }
                        preg_match_all('/('.$val.'\d{1,})/',$msg,$matches1);
                        if(!empty($matches1[0])){
                            foreach ($matches1[0] as $v) {//匹配的数据
                                preg_match_all('/\d{1,}/',$v,$matches2);
                                $money1+=$matches2[0][0];
                            }
                            break;
                        }
                    }
                    if($money1>0){
                        $str = $value['kid'].','.$money1.';';
                        $des = $value['name'].$money1;
                        if($group_user['totalmoney']<$money1){
                            $this->ajaxReturn(['code'=>'100001','msg'=>'余额不足','type'=>2]);
                        }
                        $money2=$group_user['money']+$money1;
                        $bill=M('bill')->where(['period'=>$period,'gid'=>$gid,'uid'=>$uid])->find();
                        M()->startTrans();
                        $res20=M('user_detail')->where(['uid'=>$uid])->save(['money'=>$group_user['totalmoney']-$money1]);//修改用户总积分
                        $res21=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money2]);//修改用户群积分
                        $res22=M('group_score')->add(['gid'=>$gid,'uid'=>$uid,'score'=>$money1,'in_out'=>1,'ctime'=>time()]);//增加积分记录
                        if(empty($bill)){
                            $res23=M('bill')->add(['gid'=>$gid,'uid'=>$uid,'period'=>$period,'bet'=>$str,'des'=>'','ctime'=>time(),'atime'=>time(),'remain'=>$money2]);//增加账单记录
                            $res24=true;
                        }else{
                            $res23=1;
                            $arr20=explode(';',trim($bill['bet'],';'));
                            foreach ($arr20 as $va) {
                                $arr21=explode(',',$va);
                                if($arr21[0]==$value['kid']){
                                    $str=str_replace($arr20, $value['kid'].','.$arr21[1]+$money1, $bill['bet']);
                                }
                            }
                            $res24=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->save(['bet'=>$str,'atime'=>time(),'remain'=>$money2]);
                        }
                        if($res20===false || $res21===false ||$res22<=0 || $res23<=0 || $res24===false){
                            M()->rollback();
                            $this->ajaxReturn(['code'=>'100000','msg'=>'上分失败']);
                        }
                        M()->commit();
                        if($group_user['is_admin']==2){
                            $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  余分：{$money2}",'type'=>1]);
                        }else{
                            $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  余分：{$money2}",'type'=>2]);
                        }

                    }
                }elseif($value['kid']==5){//回(下分)
                    $money1=0;
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    foreach ($keyword as $val) {
                        preg_match_all('/(\d{1,}'.$val.')/',$msg,$matches);
                        if(!empty($matches)){
                            foreach ($matches[0] as $v) {
                                $money1+=intval($v);
                            }
                        }
                        preg_match_all('/('.$val.'\d{1,})/',$msg,$matches1);
                        if(!empty($matches1)){
                            foreach ($matches1[0] as $v) {//匹配的数据
                                preg_match_all('/\d{1,}/',$v,$matches2);
                                $money1+=$matches2[0][0];
                            }
                        }
                    }
                    if($money1>0){
                        $str = $value['kid'].','.$money1.';';
                        $des = $value['name'].$money1;
                        if($group_user['money']<$money1){
                            $this->ajaxReturn(['code'=>'100001','msg'=>'余额不足','type'=>2]);
                        }
                        $money2=$group_user['money']-$money1;
                        $bill=M('bill')->where(['period'=>$period,'gid'=>$gid,'uid'=>$uid])->find();
                        M()->startTrans();
                        $res20=M('user_detail')->where(['uid'=>$uid])->save(['money'=>$group_user['totalmoney']+$money1]);
                        $res21=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money2]);
                        $res22=M('group_score')->add(['gid'=>$gid,'uid'=>$uid,'score'=>$money1,'in_out'=>2,'ctime'=>time()]);
                        if(empty($bill)){
                            $res23=M('bill')->add(['gid'=>$gid,'uid'=>$uid,'period'=>$period,'bet'=>$str,'des'=>'','ctime'=>time(),'atime'=>time(),'remain'=>$money2]);
                            $res24=true;
                        }else{
                            $res23=1;
                            $arr20=explode(';',trim($bill['bet'],';'));
                            foreach ($arr20 as $va) {
                                $arr21=explode(',',$va);
                                if($arr21[0]==$value['kid']){
                                    $str=str_replace($arr20, $value['kid'].','.$arr21[1]+$money1, $bill['bet']);
                                }
                            }
                            $res24=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->save(['bet'=>$str,'atime'=>time(),'remain'=>$money2]);
                        }
                        if($res20===false || $res21===false || $res22<=0 || $res23<=0 || $res24===false){
                            M()->rollback();
                            $this->ajaxReturn(['code'=>'100000','msg'=>'下分失败']);
                        }
                        M()->commit();
                        $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  余分：{$money2}",'type'=>1]);
                    }
                }elseif($value['kid']==6){//账单数据
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    $matches=array();
                    foreach ($keyword as $v) {
                        preg_match('/('.$v.')/',$msg,$matches);
                        if(!empty($matches)){
                            $my_bill=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->find();
                            $my=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->find();
                            $user_name=M('user')->where(['id'=>$uid])->getField('user_name');
                            if(empty($my_bill)){
                                $my_msg='暂时没有攻击信息';
                            }else{
                                $my_msg=$my_bill['des'];
                            }
                            $this->ajaxReturn(['code'=>0,'msg'=>$my['user_nick'].'('.$user_name.') '.$my_msg.'  余分:'.$my['money']]);
                        }
                    }
                }elseif($value['kid']==7){//历史
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    $matches=array();
                    foreach ($keyword as $v) {
                        preg_match('/('.$v.')/',$msg,$matches);
                        if(!empty($matches)){
                            // $newperiod=predis::getInstance()->get('newest_bill_period');
                            // $tenperiod=predis::getInstance()->get('ten_bill_periods');
                            // if($newperiod==$period){
                            //     $tenperiod=str_replace(',', '  ' , $tenperiod);
                            //     $this->ajaxReturn(['code'=>'0',$tenperiod]);
                            // }
                            // $config=C('LOTTERY.CQ');
                            // $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=10&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
                            // $array = json_decode($data,true);

                            // $ten_num='';
                            // foreach ($array as $val) {
                            //     $num1=0;
                            //     $num2=0;
                            //     $num3=0;
                            //     $all_num=explode(',',$val['number']);//所有的号码
                            //     foreach ($all_num as $k=>$v) {
                            //         if($k>=0 && $k<7){
                            //             $num1+=$v;
                            //         }elseif($k>=7 && $k<14){
                            //             $num2+=$v;
                            //         }elseif($k>=14 && $k<21){
                            //             $num3+=$v;
                            //         }else{
                            //             break;
                            //         }
                            //     }
                            //     $num1=$num1%10;
                            //     $num2=$num2%10;
                            //     $num3=$num3%10;
                            //     $cal_res=$num1+$num2+$num3;
                            //     $ten_num .=$cal_res.',';
                            // }
                            // $ten_num=trim($ten_num,',');
                            // $newperiod=predis::getInstance()->set('newest_bill_period',$period);
                            // $tenperiod=predis::getInstance()->set('ten_bill_periods',$ten_num);
                            // $tenperiod1=str_replace(',', '  ' ,$ten_num);
                            // $this->ajaxReturn(['code'=>'0','msg'=>'历史  '.$tenperiod1]);
                            $ten=M('period')->order('period desc')->limit(0,10)->getField('cal_res',true);
                            $ten_res=implode(' ',$ten);
                            $this->ajaxReturn(['code'=>'0','msg'=>'历史  '.$ten_res]);
                        }
                    }
                }elseif($value['kid']==22){//加注
                    $bets=M('group_keywords')
                        ->alias('gk')
                        ->join('lc_keywords k ON k.id=gk.kid')
                        ->where(['gk.gid'=>$gid,'k.type'=>2])
                        ->field('gk.id,gk.kid,gk.keywords,k.name')
                        ->select();
                    $keyword=explode('|',trim($value['keywords'],'|'));
                    $money=0;
                    foreach ($keyword as $val) {//循环加注的关键词
                        foreach ($bets as $v) {//循环投注信息
                            $arr=explode('|',trim($v['keywords'],'|'));
                            foreach ($arr as $ve) {
//                                $preg='/('.$val.'\d{1,}'.$ve.')/';
                                preg_match('/('.$val.'\d{1,}'.$ve.')/',$msg,$matches);
                                if(!empty($matches)){
                                    preg_match('/\d{1,}/',$matches[0],$mat);
                                    $tou_money=$mat[0];
                                    $bet_arr[]=$v['kid'].','.$tou_money.',2';
                                    $des_arr[]=$tou_money.$v['name'];
                                    $msg=str_replace($matches[0],'',$msg);
                                    $total_money += $tou_money;
                                }
                            }
                        }
                    }
                }else{//下注
                    if($value['kid']==17){//特码
                        $keyword=explode('|',trim($value['keywords'],'|'));
                        foreach ($keyword as $val) {
                            $matches=array();
                            preg_match_all('/(\d{1,}'.$val.'\d{1,})/',$msg,$matches);
                            $setting=M('bill_setting')->where(['id'=>4])->find();//特码设置
                            if(!empty($matches)){
                                foreach ($matches[0] as $v) {
                                    if($setting['status']==1){
                                        $num=intval($v);
                                        $money=intval(preg_replace('/('.$num.$val.')/','',$v));
                                    }else{
                                        $money=intval($v);
                                        $num=intval(preg_replace('/('.$money.$val.')/','',$v));
                                    }
                                    if( $num>0 && $num<27){
                                        if($bill_type==1){
                                            $total_money += $money;
                                        }else{
                                            $has_bet=explode(';',trim($bill['bet'],';'));//已经投注的信息
                                            if(!empty($has_bet)){
                                                foreach($has_bet as $vel){
                                                    $has_bet_info=explode($vel,',');
                                                    if($has_bet_info[2]==$num){//已经存在
                                                        $add_money=$money-$has_bet_info[1];
                                                        $total_money+=$add_money;
                                                    }
                                                }
                                            }
                                        }
                                        $bet_arr[]=$value['kid'].','.$money.','.$num.',1';
                                        $des_arr[]=$money.$value['name'].'('.$num.')';
                                        $msg=str_replace($v,'',$msg);
                                    }
                                }
                            }
                        }
                    }else{
                        $res=$this->matchBetWords($msg,$value['keywords']);
                        $money=$res['money'];
                        $msg=$res['msg'];
                        if($money>0){
                            $odds=M('group_odds')->where(['gid'=>$gid,'odds_id'=>6])->find();
                            if($odds['min']>$money || $odds['max']<$money){//超出两最之外
                                $this->ajaxReturn(['code'=>'100000','msg'=>'单注金额过高或过低']);
                            }
                            if(!empty($bill)){
                                if($bill_type==1){
                                    $total_money += $money;
                                }else{
                                    $has_bet=explode(';',trim($bill['bet'],';'));//已经投注的信息
                                    if(!empty($has_bet)){
                                        foreach($has_bet as $vel){
                                            $has_bet_info=explode(',',$vel);
                                            if($has_bet_info[0]==$value['kid']){//已经存在
                                                $add_money=$money-$has_bet_info[1];
                                                $total_money+=$add_money;
                                            }
                                        }
                                    }else{
                                        $total_money += $money;
                                    }
                                }
                            }else{
                                $total_money += $money;
                            }
                            $bet_arr[]=$value['kid'].','.$money.',1';
                            $des_arr[]=$money.$value['name'];
                        }
                    }
                }
            }
        }
        if($total_money>$group_user['money']){
            $this->ajaxReturn(['code'=>'100001','msg'=>'剩余积分不足','type'=>2]);
        }
        M()->startTrans();

        if(!empty($bet_arr)){//匹配到关键词
            foreach ($bet_arr as $key=> $val) {//循环匹配到的投注信息
                $arr1=explode(',',$val);
                $type=$arr1[count($arr1)-1];
                if(!empty($bill)){//已经有投注信息
                    $remark=0;
                    $bill_bet=explode(';',trim($bill['bet'],';'));
                    foreach ($bill_bet as  $v) {//循环已经投注过的投注信息
                        $arr=explode(',',$v);
                        if($arr[0]==$arr1[0]){//已经存在该投注信息
                            if($bill_type==1){//累加投注
                                if($arr[0]==17){//特码
                                    if($arr1[2]==$arr[2]){
                                        $remark=1;
                                        $money1=$arr1[1]+$arr[1];
                                        $bet=str_replace($v,$arr[0].','.$money1.','.$arr[2],$bill['bet']);//替换投注信息
                                        $name=M('keywords')->where(['id'=>$arr[0]])->getField('name');
                                        preg_match('/(\d{1,}'.$name.'\('.$arr[2].'\))/',$bill['des'],$match);
                                        if(!empty($match)){
                                            $bill_des=str_replace($match[0],$money1.$name.'('.$arr[2].')',$bill['des']);
                                        }
                                        $res=M('bill')->where(['id'=>$bill['id']])->save(['bet'=>$bet,'des'=>$bill_des,'atime'=>time(),'total_bet'=>$bill['total_bet']+$arr1[1]]);
                                        if($res===false){
                                            M()->rollback();
                                            $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败']);
                                        }
                                    }
                                }else{
                                    $remark=1;
                                    $money1=$arr1[1]+$arr[1];
                                    $bet=str_replace($v,$arr[0].','.$money1,$bill['bet']);//替换投注信息
                                    $name=M('keywords')->where(['id'=>$arr[0]])->getField('name');
                                    preg_match('/(\d{1,}'.$name.')/',$bill['des'],$match);
                                    if(!empty($match)){
                                        $bill_des=str_replace($match[0],$money1.$name,$bill['des']);
                                    }
                                    $res=M('bill')->where(['id'=>$bill['id']])->save(['bet'=>$bet,'des'=>$bill_des,'atime'=>time(),'total_bet'=>$bill['total_bet']+$arr1[1]]);
                                    if($res===false){
                                        M()->rollback();
                                        $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败']);
                                    }
                                }
                            }else{//最后一次投注为准
                                if($arr[0]==17){//特码
                                    if($arr1[2]==$arr[2]){
                                        $remark=1;
                                        $money1=$arr1[1];
                                        $bet=str_replace($v,$arr[0].','.$money1.','.$arr[2],$bill['bet']);//替换投注信息
                                        $name=M('keywords')->where(['id'=>$arr[0]])->getField('name');
                                        preg_match('/(\d{1,}'.$name.'\('.$arr[2].'\))/',$bill['des'],$match);
                                        if(!empty($match)){
                                            $bill_des=str_replace($match[0],$money1.$name.'('.$arr[2].')',$bill['des']);
                                        }
                                        $total_money1=$bill['total_bet']+$arr1[1]-$arr[1];
                                        $res=M('bill')->where(['id'=>$bill['id']])->save(['bet'=>$bet,'des'=>$bill_des,'atime'=>time(),'total_bet'=>$total_money1]);
                                        if($res===false){
                                            M()->rollback();
                                            $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败']);
                                        }
                                    }
                                }else{
                                    $remark=1;
                                    if($type==1){
                                        $money1=$arr1[1];
                                        $total_money1=$bill['total_bet']+$arr1[1]-$arr[1];
                                    }else{
                                        $money1=$arr1[1]+$arr[1];
                                        $total_money1=$bill['total_bet']+$arr1[1];
                                    }
                                    $bet=str_replace($v,$arr[0].','.$money1,$bill['bet']);//替换投注信息
                                    $name=M('keywords')->where(['id'=>$arr[0]])->getField('name');
                                    preg_match('/(\d{1,}'.$name.')/',$bill['des'],$match);
                                    if(!empty($match)){
                                        $bill_des=str_replace($match[0],$money1.$name,$bill['des']);
                                    }
                                    $res=M('bill')->where(['id'=>$bill['id']])->save(['bet'=>$bet,'des'=>$bill_des,'atime'=>time(),'total_bet'=>$total_money1]);
                                    if($res===false){
                                        M()->rollback();
                                        $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败']);
                                    }
                                }

                            }
                            break;
                        }
                    }
                    if($remark==0){//没有相同投注就增加该投注信息
                        $money2=$bill['total_bet'];
                        $arr1=explode(';',trim($val,';'));
                        foreach ($arr1 as $v) {
                            $arr2=explode(',',$v);
                            if(in_array($arr2[0],[2,3,4,5])){
                                continue;
                            }
                            $money2+=$arr2[1];
                        }
                        $res=M('bill')->where(['id'=>$bill['id']])->save(['bet'=>$bill['bet'].';'.$val,'des'=>$bill['des'].$des_arr[$key],'atime'=>time(),'total_bet'=>$money2]);
                        if($res===false){
                            M()->rollback();
                            $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败1']);
                        }
                    }
                }else{//还没有过投注就增加投注
                    $money2=0;
                    foreach ($bet_arr as $v) {
                        $arr2=explode(',',$v);
                        $money2+=$arr2[1];
                    }
                    $bet=implode(';',$bet_arr);
                    $des=implode('',$des_arr);
                    $res=M('bill')->add(['gid'=>$gid,'uid'=>$uid,'period'=>$period,'bet'=>$bet,'des'=>$des,'ctime'=>time(),'atime'=>time(),'total_bet'=>$money2]);
                    if($res<=0){
                        M()->rollback();
                        $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败2']);
                    }
                }
            }
        }else{
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'没有匹配到任何关键词']);
        }
        $res1=M('group_user')->where(['id'=>$group_user['id']])->save(['money'=>$group_user['money']-$total_money]);
        $res2=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->save(['remain'=>$group_user['money']-$total_money]);

        if($res1===false || $res2===false){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'下注失败3']);
        }
        M()->commit();
        M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->setInc('time');
        $des=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'period'=>$period])->getField('des');
        $group_money=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->getField('money');
        if($group_user['is_admin']==2){
            $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  余分：{$group_money}",'type'=>1]);
        }else{
            $this->ajaxReturn(['code'=>'100001','msg'=>$group_user["user_nick"]."(".$group_user["user_name"].")  {$des}  余分：{$group_money}",'type'=>2]);
        }
    }
    /**
     * [getPeriod 获取最新一期期号、开奖时间]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-17
     */
    public function getPeriodNum()
    {
        $newperiod=predis::getInstance()->get('newest_period');
        if(empty($newperiod)){
            $config=C('LOTTERY.CQ');
            $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=10&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
            $data = json_decode($data,true);
            $key=array_keys($data);
            $period=$key[0];
            $res=predis::getInstance()->set('newest_period',$period,5);//存redis
        }else{
            $period=$newperiod;
        }
        return $period;
    }
    /**
     * [getPeriodInfoByNum 根据期号获取某期的开奖信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-17
     */
    public function getPeriodInfoByNum($period)
    {
        // $period=893905;
        $period_info=M('period')->where(['period'=>$data['period']])->find();
        if(empty($period_info)){
            $config=C('LOTTERY.CQ');
            $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=12&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
            $data = json_decode($data,true);
            $key=array_keys($data);
            $val=array_values($data);
            if(in_array($period,$key)){
                $info=$data[$period];
                $all_num=explode(',',$info['number']);//所有的号码
                $num1=0;$num2=0;$num3=0;
                foreach ($all_num as $k=>$v) {
                    if($k>=0 && $k<7){
                        $num1+=$v;
                    }elseif($k>=7 && $k<14){
                        $num2+=$v;
                    }elseif($k>=14 && $k<21){
                        $num3+=$v;
                    }else{
                        break;
                    }
                }
                $num1=$num1%10;
                $num2=$num2%10;
                $num3=$num3%10;
                $cal_res=$num1+$num2+$num3;
                $add=['period'=>$period,'res'=>$info['number'],'num1'=>$num1,'num2'=>$num2,'num3'=>$num3,'cal_res'=>$cal_res,'dateline'=>strtotime($info['dateline'])];
                $res=M('period')->add($add);
                $period_info=$add;
            }else{
                $period_info = false;
            }
        }
        return $period_info;
    }
    /**
     * [getTenPeriodHis 获取10开奖历史开奖结果]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-17
     * @return    [type]     [description]
     */
    public function getTenPeriodHis()
    {
        $his_num=predis::getInstance()->get('ten_period _res');
        $newest_period=$this->getPeriodNum();
        $res_arr=array();
        for ($i=0; $i < 10; $i++) {
            $period=$newest_period-$i;
            $info=$this->getPeriodInfoByNum($period);
            $res_arr[]=$info['cal_res'];
        }
        return implode($res_arr,' ');
    }
    /**
     * [matchKeyword 根据消息内容计算投注信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-08
     * @return    [type]     [description]
     */
    public function matchBetWords($msg,$keywords)
    {
        $keyword=explode('|',trim($keywords,'|'));
        $money=0;
        foreach ($keyword as $val) {
            $res=preg_match_all('/(\d{1,}'.$val.')/',$msg,$matches);
            if(!empty($matches)){
                foreach ($matches[0] as $v) {//匹配的数据
                    $money+=intval($v);
                    $msg=str_replace($v,'',$msg);
                }
            }
            $res1=preg_match_all('/('.$val.'\d{1,})/',$msg,$matches1);
            if(!empty($matches1)){
                foreach ($matches1[0] as $v) {//匹配的数据
                    preg_match_all('/\d{1,}/',$v,$matches2);
                    $money+=$matches2[0][0];
                    $msg=str_replace($v,'',$msg);
                }
            }
        }
        return ['money'=>$money,'msg'=>$msg];
    }
    /**
     * [setOdds 保存赔率]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-05-31
     */
    public function setOdds()
    {
        $id=I('post.id',0);//id
        $gid=I('post.gid',0);//id
        $odds=intval(I('post.odds',0));//赔率
        if(empty($id) || $odd<0){
            $this->ajaxReturn(['code'=>'10000','msg'=>'数据错误']);
        }
        $info=M('group_odds')->alias('go')->join('lc_odds o ON o.id=go.odds_id')->where(['go.id'=>$id])->field('go.gid,o.name')->find();
        M()->startTrans();
        $res1=M('group_odds')->where(['id'=>$id])->save(['odds'=>$odds]);
        $res2=M('group_logs')->add(['uid'=>session('uid'),'gid'=>$gid,'operate'=>'修改了'.$info['name'].'的赔率']);
        if($res===false || $res2<=0){
            M()->commit();
            $this->ajaxReturn(['code'=>'10000','msg'=>'设置失败']);
        }else{
            M()->rollback();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [setBack 设置回本]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-05-31
     */
    public function setBack()
    {
        $id=I('post.id',0);
        $status=I('post.status',0);
        $info=M('group_back')->alias('gb')->join('lc_backs b ON b.id=gb.bid')->where(['gb.id'=>$id])->field('gb.gid,b.name')->find();
        if(empty($info) || $status==0){
            $this->ajaxReturn(['code'=>'10000','msg'=>'数据错误']);
        }
        M()->startTrans();
        $res=M('group_back')->where(['id'=>$id])->save(['status'=>$status]);
        if($status==1){
            $msg='选中'.$info['name'];
        }else{
            $msg='取消'.$info['name'];
        }
        $res2=M('group_logs')->add(['gid'=>info['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>$msg]);
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'10000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [setIgnore 设置超无视]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-07
     */
    public function setIgnore()
    {
        $type=I('post.type');
        $id=I('post.id');
        $val=I('post.val');
        $info=M('exceed_ignore')->where(['id'=>$id])->find();
        if(!in_array($type,[1,2,3]) || empty($info)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        switch ($type) {
            case '1'://修改无视金额
                $res=M('exceed_ignore')->where(['id'=>$id])->save(['exceed'=>$val]);
                break;
            case '2'://修改无视规则
                $res=M('exceed_ignore')->where(['id'=>$id])->save(['type'=>$val]);
                break;
            case '3'://修改赔率
                $res=M('exceed_ignore')->where(['id'=>$id])->save(['odds'=>$val]);
                break;
        }
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'修改失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'修改成功']);
        }
    }
    /**
     * [billSet 账单设置]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-27
     * @return    [type]     [description]
     */
    public function billSet()
    {
        $id=I('post.id',0);
        $val=I('post.val',0);
        $type=I('post.type',0);//type=1改变值 type=2改变状态
        $info=M('group_bill_setting')->where(['id'=>$id])->find();
        if(empty($info)){
            $this->ajaxReturn(['code'=>'10000','msg'=>'数据错误']);
        }
        if($type==1){
            $res=M('group_bill_setting')->where(['id'=>$id])->save(['value'=>$val]);
        }else{
            $res=M('group_bill_setting')->where(['id'=>$id])->save(['status'=>$val]);
        }
        if($res===false){
            $this->ajaxReturn(['code'=>'10000','msg'=>'设置失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }

    /**
     * [getBetRes 计算中中奖结果]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-02
     * @return    [type]     [description]
     */
    public function getBetRes()
    {
        $period=I('post.period');//期数
        $gid=I('post.gid');//群Id
        $game_id=I('post.game_id');//玩法Id
        $first=I('post.first');//开奖号码1
        $second=I('post.second');//开奖号码2
        $third=I('post.third');//开奖号码3
        $res=I('post.res');//开奖结果
        $bill_count=M('bill')->where(['period'=>$period])->count();
        if(($first+$second+$third != $res) || $bill_count<=0){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $bills=M('bill')->where(['period'=>$period,'gid'=>$gid])->select();
        foreach ($bills as $val) {
            $arr=explode(';',trim($val['bet'],';'));
            $profit=0;
            foreach ($arr as $v) {
                $arr1=explode(',',trim($v,','));
                if($arr1[0]==7 && $val['comment']==$res){//判断是否是特码
                    $odds=$this->calOddsByRes($first,$second,$third,7,$game_id);
                }else{
                    $odds=$this->calOddsByRes($first,$second,$third,$arr1[0],$game_id);
                }
                $profit += ($odds-1)*$arr1[1]*$arr1[2];//(赔率-1)*金额*数量
            }
            $res=M('bill')->where(['id'=>$val['id']])->save(['profit'=>$profit]);
            if($res===false){
                $this->ajaxReturn(['code'=>'100000','msg'=>'开奖有误']);
                break;
            }
        }
        $bills=M('bill')->where(['period'=>$period,'gid'=>$gid])->select();
        $this->ajaxReturn(['code'=>0,'msg'=>'账单','data'=>$bills]);
    }
    /**
     * [sexMax 设置投注上限]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-04
     * @param     string     $value [description]
     * @return    [type]            [description]
     */
    public function setMax()
    {
        $id=I('post.id');
        $val=I('post.max');
        $odds_info=M('group_odds')->alias('go')->join('lc_odds o ON o.id=go.odds_id')->where(['go.id'=>$id])->field('go.gid,o.name')->find();
        M()->startTrans();
        $res=M('group_odds')->where(['id'=>$id])->save(['max'=>$val]);
        $res2=M('group_logs')->add(['gid'=>$odds_info['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改 '.$odds_info['name'].' 的投注上限']);
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [sexMax 设置投注上限]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-04
     * @param     string     $value [description]
     * @return    [type]            [description]
     */
    public function setMin()
    {
        $id=I('post.id');
        $val=I('post.min');
        $odds_info=M('group_odds')->alias('go')->join('lc_odds o ON o.id=go.odds_id')->where(['go.id'=>$id])->field('go.gid,o.name')->find();
        M()->startTrans();
        $res=M('group_odds')->where(['id'=>$id])->save(['min'=>$val]);
        $res2=M('group_logs')->add(['gid'=>$odds_info['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改 '.$odds_info['name'].' 的投注下限']);
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [saveKeyword 设置关键字]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-04
     * @return    [type]     [description]
     */
    public function setKeyword()
    {
        $id=I('post.id');
        $keywords=I('post.keyword');
        $keyword_info=M('group_keywords')->alias('gk')->join('lc_keywords k ON k.id=gk.kid')->where(['gk.id'=>$id])->field('k.name,gk.gid')->find();
        M()->startTrans();
        $res=M('group_keywords')->where(['id'=>$id])->save(['keywords'=>$keywords]);
        $res2=M('group_logs')->add(['gid'=>$keyword_info['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改关键词 '.$keyword_info['name'].' 的值']);
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [setNotice 设置封盘定时提醒]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-15
     */
    public function setNotice()
    {
        $id=I('post.id');
        $type=I('post.type');
        $val=I('post.val');
        M()->startTrans();
        if($type==1){//设置开启/关闭状态
            $res=M('group_bill_notice')->where(['id'=>$id])->save(['status'=>$val]);
        }elseif($type==2){
            $res=M('group_bill_notice')->where(['id'=>$id])->save(['time'=>$val]);
        }elseif($type==3){
            $res=M('group_bill_notice')->where(['id'=>$id])->save(['notice'=>$val]);
        }else{
            $res=false;
        }
        $res2=M('group_logs')->add(['gid'=>$gid,'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改封盘提醒时间']);
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [setNotice1 设置封盘后玩家投注提示消息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-23
     */
    public function setNotice1()
    {
        $id=intval(I('post.id',0));
        $gid=I('post.gid',0);
        $type=I('post.type',0);
        $val=I('post.val',0);
        if(empty($id) || empty($gid) || empty($type)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        if($type==1){//修改选中状态
            M()->startTrans();
            $res1=M('group_bill_notice')->where(['time'=>0,'gid'=>$gid])->save(['status'=>2]);
            $res2=M('group_bill_notice')->where(['id'=>$id])->save(['status'=>1]);
            $res3=M('group_logs')->add(['gid'=>$gid,'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改封盘提醒选中状态']);
            if($res1===false || $res2===false){
                M()->rollback();
                $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
            }else{
                M()->commit();
                $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
            }
        }else{//修改某一个的值
            M()->startTrans();
            $res=M('group_bill_notice')->where(['id'=>$id])->save(['notice'=>$val]);
            $res2=M('group_logs')->add(['gid'=>$gid,'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'修改封盘提醒内容']);
            if($res===false || $res2<=0){
                M()->rollback();
                $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
            }else{
                M()->commit();
                $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
            }
        }
    }
    /**
     * [calProfitByRes 通过开奖信息得到赔率]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-04
     * @param     [type]     $first  [第一个数]
     * @param     [type]     $second [第二个数]
     * @param     [type]     $third  [第三个数]
     * @param     [type]     $type   [类型]
     * @param     [type]     $type   [投注]
     * @return    [type]             [description]
     */
    private function calOddsByRes($first,$second,$third,$res,$gid)
    {
        $odds=array();
        $odds_arr=M('group_odds')
            ->alias('go')
            ->join('lc_odds o ON o.id=go.odds_id')
            ->join('lc_keywords k ON o.id=k.odds_id')
            ->where(['o.status'=>1,'gid'=>$gid])
            ->field('go.odds_id,go.odds,k.name,k.id as kid')
            ->select();
        $num=13;
        $is_big=$res>$num ? 1 : 0 ;//是否是大数 0-不是 1-是
        $is_odd=$res%2;//是否是单数 0-不是 1-是
        $arr=array();
        if(!empty($odds_arr)){
            foreach ($odds_arr as $v) {
                switch ($v['odds_id']) {
                    case '1'://大
                        if($is_big==1){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '2'://小
                        if($is_big==0){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '3'://单
                        if($is_odd==1){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '4'://双
                        if($is_odd==0){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '5'://大双
                        if($is_big==1 && $is_odd==0){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '8'://小单
                        if($is_big==0 && $is_odd==1 ){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '7'://大单
                        if($is_big==1 && $is_odd==1 ){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '6'://小双
                        if($is_big==0 && $is_odd==0){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '9'://特码
                        $arr[$v['kid']]=$v['odds'];//在外部判断
                        break;
                    case '10'://极大
                        //是否是加大或极小 0-不是 1-是
                        $num1=M('group_bill_setting')->where(['gid'=>$gid,'sid'=>1])->getField('value');
                        if($res<27 && 27-$res<=$num1){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                     case '11'://极小
                        $num1=M('group_bill_setting')->where(['gid'=>$gid,'sid'=>2])->getField('value');
                        if($res>0 && $res<=$num1){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '12'://对子
                        //是不是对子 0-不是 1-是
                        if(($first==$scecond && $second!=$third) || ($first==$third && $second!=$third) || ($second==$third && $first!=$scecond)){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '13'://顺子
                        //1.是否是顺子 0-不是 1-是
                        $straight_status=M('group_back')->where(['id'=>9])->getField('status');//089为顺子是否开启
                        if(($first+1==$second && $second+1==$third) || ($first==0 && $second==1 && $third==9 && $straight_status==1)){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    case '14'://豹子
                        //是不是豹子 0-不是 1-是
                        if($first==$second && $second==$third){
                            $arr[$v['kid']]=$v['odds'];
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $arr;
    }

    /**
     * [openPrize 开奖]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-08
     * @return    [type]     [description]
     */
    public function openPrize()
    {
        //接受参数
        $period=intval(I('post.period'))+1;//期号
        $date=intval(I('post.date'))+1;//时间
        $gid=intval(I('post.gid'));//群Id
        if(empty($period) || empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }

        //在数据库中查询是否已经有开奖信息
        $period_info=M('period')->where(['period'=>$period])->find();
        if(!empty($period_info)){//在数据库中没有开奖信息
            $this->ajaxReturn(['code'=>'100001','msg'=>'已经开过奖了']);
        }
        $res=$this->getCurrentPeriod();
        $bill_total_money_arr=M('bill')->where(['period'=>$res['period'],'gid'=>$gid])->getField('profit');
        $bill_total_money=-array_sum($bill_total_money_arr);
        $res['profit']=$bill_total_money;
        if($res['period']==$period){
            M()->startTrans();
            $res1=M('period')->add(['period'=>$period,'num1'=>$res['num1'],'num2'=>$res['num2'],'num3'=>$res['num3'],'res'=>$res['res'],'cal_res'=>$res['cal_res'],'dateline'=>$res['dateline']]);
            if($res1<=0){
                M()->rollback();
                $this->ajaxReturn(['code'=>'100000','msg'=>'开奖失败']);
            }
            $odds=$this->calOddsByRes($res['num1'],$res['num2'],$res['num3'],$res['res'],$gid);
            $bill=M('bill')->where(['period'=>$res['period'],'gid'=>$gid])->select();
            $backs=M('group_back')->alias('gb')->join('lc_backs b ON b.id=gb.bid')->where(['b.status'=>1,'gid'=>$gid,'gb.status'=>1])->field('b.id')->select();
            $backs=array_column($backs, 'id');
            $back=$this->getback($res,$odds);
            $back1314_status=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid'=>$gid,'bs.id'=>4])->getField('gbs.status');//是否开启1314超无视
            if(!empty($bill)){
                foreach ($bill as $val) {//循环所有人下注信息
                    $arr1=explode(';',trim($val['bet'],';'));
                    $profit=0;
                    if($back1314_status==1 && in_array($res['cal_res'],[13,14])){
                        $back1314_value=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid'=>$gid,'bs.id'=>4])->getField('gbs.value');//是否开启1314超无视
                        $profit_res=$this->calIgnore($gid,$val['bet'],$back1314_value);
                        if($profit_res['mark']!=0){
                            $profit=$profit_res['profit'];
                            continue;
                        }
                    }
                    if($back==2){
                        $profit=-$val['total_bet'];
                        continue;
                    }
                    foreach ($arr1 as $v) {//循环某一个人的下注信息
                        $arr2=explode(',',$v);
                        if($arr2[0]==17){//特码
                            if($res['res']==$arr2[2]){//中奖了
                                $odds1=$odds[17];
                                $profit+=($odds1-1)*$arr2[1];//（赔率-1）*投注金额
                            }else{
                                if($back==1){
                                    $profit=0;
                                }else{
                                    $profit+=-$arr2[1];
                                }
                            }
                        }else{
                            $odds_ids=array_keys($odds);
                            if(in_array($arr2[0],$odds_ids)){//中奖了
                                $odds1=$odds[$arr2[0]];
                                $profit+=($odds1-1)*$arr2[1];//（赔率-1）*投注金额
                            }else{//未中奖
                                if($back==1){
                                    $profit=0;
                                }else{
                                    $profit+=-$arr2[1];
                                }
                            }
                        }
                    }
                    $res2=M('bill')->where(['id'=>$val['id']])->save(['profit'=>$profit]);
                    if($res2===false){
                        M()->rollback();
                        $this->ajaxReturn(['code'=>'1000000','msg'=>'开奖失败1']);
                    }
                }
            }
            M()->commit();
            //账单
            $group_users=M('group_user')->alias('gu')->join('lc_user u ON u.id=gu.uid')->where(['u.is_admin'=>2,'gu.gid'=>$gid,'gu.user_status'=>1,'gu.admin_status'=>1,'gu.is_quit'=>2])->field('gu.uid,gu.user_nick,gu.money')->select();
            if(!empty($group_users)){
                foreach ($group_users as $k=>$v) {
                    if($v['money']==0){//余额为0
                        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$v['uid'],'period'=>$period])->find();
                        if(empty($bill) || $bill['total_bet']==0){//没有投注信息或者投注进额为0
                            unset($group_users[$k]);
                        }
                    }
                }
                foreach ($group_users as $key => &$value) {
                    $value['user_name']=M('user')->where(['id'=>$value['uid']])->getField('user_name');
                    $bill=M('bill')->where(['gid'=>$gid,'uid'=>$value['uid'],'period'=>$period])->find();
                    if(!empty($bill)){
                        $value['des']=$bill['des'];
                        $value['score']=$bill['total_bet'];
                        $value['time']=$bill['time'];
                        $value['profit']=$bill['profit'];
                    }else{
                        $value['des']='--';
                        $value['score']=0;
                        $value['time']=0;
                        $value['profit']=0;
                    }
                }
            }
            $this->ajaxReturn(['code'=>'0','msg'=>'开奖成功','data'=>['res'=>$res,'bill'=>$group_users]]);
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'开奖失败','data'=>$period.','.$res['period']]);
        }
    }

    /**
     * 手动开奖
     */
    public function openprizeManul()
    {
        $period=I('post.period');
        $gid=I('post.gid');
        $period_info=$this->getPeriodInfo($period);
        if(empty($period_info)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'没有找到该期开奖信息']);
        }
        $bill=M('bill')->where(['period'=>$period,'gid'=>$gid])->select();
        $odds=$this->calOddsByRes($period_info['num1'],$period_info['num2'],$period_info['num3'],$period_info['res'],$gid);
        $backs=M('group_back')->alias('gb')->join('lc_backs b ON b.id=gb.bid')->where(['b.status'=>1,'gid'=>$gid,'gb.status'=>1])->field('b.id')->select();
        $backs=array_column($backs, 'id');
        $back=$this->getback($period_info,$odds);
        $back1314_status=M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid'=>$gid,'bs.id'=>4])->getField('gbs.status');//是否开启1314超无视

        if(!empty($bill)){
            foreach ($bill as $val) {//循环所有人下注信息
                $arr1 = explode(';', trim($val['bet'], ';'));
                $profit = $val['profit'];
                if ($back1314_status == 1 && in_array($period_info['cal_res'], [13, 14])) {
                    $back1314_value = M('group_bill_setting')->alias('gbs')->join('lc_bill_setting bs ON bs.id=gbs.sid')->where(['gbs.gid' => $gid, 'bs.id' => 4])->getField('gbs.value');//是否开启1314超无视
                    $profit_res = $this->calIgnore($gid, $val['bet'], $back1314_value);
                    if ($profit_res['mark'] != 0) {
                        $profit = $profit_res['profit'];
                        continue;
                    }
                }
                if ($back == 2) {
                    $profit = -$val['total_bet'];
                    continue;
                }
                foreach ($arr1 as $v) {//循环某一个人的下注信息
                    $arr2 = explode(',', $v);
                    if ($arr2[0] == 17) {//特码
                        if ($period_info['res'] == $arr2[2]) {//中奖了
                            $odds1 = $odds[17];
                            $profit += ($odds1 - 1) * $arr2[1];//（赔率-1）*投注金额
                        } else {
                            if ($back == 1) {
                                $profit = 0;
                            } else {
                                $profit += -$arr2[1];
                            }
                        }
                    } else {
                        $odds_ids = array_keys($odds);
                        if (in_array($arr2[0], $odds_ids)) {//中奖了
                            $odds1 = $odds[$arr2[0]];
                            $profit += ($odds1 - 1) * $arr2[1];//（赔率-1）*投注金额
                        } else {//未中奖
                            if ($back == 1) {
                                $profit = 0;
                            } else {
                                $profit += -$arr2[1];
                            }
                        }
                    }
                }
                $res2 = M('bill')->where(['id' => $val['id']])->save(['profit' => $profit]);
                if ($res2 === false) {
                    M()->rollback();
                    $this->ajaxReturn(['code' => '1000000', 'msg' => '开奖失败1']);
                }
            }
            M()->commit();
            //账单
            $group_users=M('group_user')->where(['gid'=>$gid,'user_status'=>1,'admin_status'=>1,'is_quit'=>2])->select();
            if(!empty($group_users)){
                foreach ($group_users as $k=>$v) {
                    if($v['money']==0){//余额为0
                        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$v['uid'],'period'=>$period])->find();
                        if(empty($bill) || $bill['total_bet']==0){//没有投注信息或者投注进额为0
                            unset($group_users[$k]);
                        }
                    }
                }
                foreach ($group_users as $key => &$value) {
                    $value['user_name']=M('user')->where(['id'=>$value['uid']])->getField('user_name');
                    $bill=M('bill')->where(['gid'=>$gid,'uid'=>$value['uid'],'period'=>$period])->find();
                    if(!empty($bill)){
                        $value['des']=$bill['des'];
                        $value['score']=$bill['total_bet'];
                        $value['time']=$bill['time'];
                        $value['profit']=$bill['profit'];
                    }else{
                        $value['des']='--';
                        $value['score']=0;
                        $value['time']=0;
                        $value['profit']=0;
                    }
                }
            }
            $this->ajaxReturn(['code'=>'0','msg'=>'开奖成功','data'=>['res'=>$period_info,'bill'=>$group_users]]);
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'开奖失败']);
        }
    }

    /**
     * 获取指定的period开奖结果
     */
    private function getPeriodInfo($period)
    {
        $period_info=M('period')->where(['period'=>$period])->find();
        if(empty($period_info)){
            $period_num_his=predis::getInstance()->sMembers('period_num_his');
            if(!in_array($period,$period_num_his)){
                $config=C('LOTTERY.CQ');
                $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=179&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
                $data = json_decode($data,true);
                $keys=array_keys($data);
                foreach ($keys as $key){
                    if(in_array($key,$period_num_his)){
                        continue;
                    }else{
                        $res=$data[$key];
                        $num1=0;
                        $num2=0;
                        $num3=0;
                        $all_num=$res['number'];
                        foreach ($all_num as $k=>$v) {
                            if($k>=0 && $k<7){
                                $num1+=$v;
                            }elseif($k>=7 && $k<14){
                                $num2+=$v;
                            }elseif($k>=14 && $k<21){
                                $num3+=$v;
                            }else{
                                break;
                            }
                        }
                        $num1=$num1%10;
                        $num2=$num2%10;
                        $num3=$num3%10;
                        $cal_res=$num1+$num2+$num3;
                        $period_info=json_encode(['period'=>$key,'num1'=>$num1,'num2'=>$num2,'num3'=>$num3,'cal_res'=>$cal_res,'dateline'=>strtotime($res['dateline'])]);
                        predis::getInstance()->sAdd('period_num_his',$key);
                        predis::getInstance()->sAdd('period_info_his',$period_info);
                        if($period==$key){
                            $period_info1=$period_info;
                        }
                    }
                    if(!empty($period_info1)){
                        return $period_info;

                    }else{
                        return false;
                    }
                }
            }else{
                $period_info_his=predis::getInstance()->sMembers('period_info_his');
                foreach($period_info_his as $v){
                    $info=json_decode($v,true);
                    if($period==$info['period']){
                        $period_info=$info;
                        return $period_info;
                    }
                }
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 计算1314超无视
     */
    private function calIgnore($gid,$bet='',$type)
    {
        $mark=0;
        if($bet==''){
            $profit=0;
        }else{
            $bet_arr=explode(';',trim($bet,';'));
            $back1314=$back1314=M('group_exceed_ignore')->alias('gei')->join('lc_exceed_ignore ei ON ei.id=gei.eid')->where(['ei.id'=>['in',[1,2,3]],'gei.gid'=>$gid,'gei.status'=>1])->order('gei.step asc')->select();
            if(!empty($back1314)){
                foreach($back1314 as $key => $value){
                    //type 1-超无视回本 2-超无视赔率
                    if($value['type']==1){
                        $odd=1;
                    }else{
                        $odd=$value['odds'];
                    }
                    if($type=1){//算总注
                        $total=0;
                        foreach($bet_arr as $v){
                            $bet=explode(',',$v);
                            $total +=$bet[1];
                        }
                        if($total>$value['exceed']){
                            $profit=$total*($odd-1);
                            $mark=1;
                        }
                    }else{//算单注
                        foreach($bet_arr as $v){
                            $bet=explode(',',$v);
                            $profit=0;
                            if($bet[1]>$value['exceed']){
                                $profit +=$bet[1]*($odd-1);
                                $mark=2;
                            }
                        }
                    }
                }
            }
        }
        return ['profit'=>$profit,'mark'=>$mark];
    }
    /**
     * [getbacks 获取通杀/回本信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-15
     * @param     [type]     $odds [description]
     * @return    [type]           [description]
     */
    public function getback($res,$odds)
    {
        //1.判断豹子通杀
        if(in_array(8,array_flip($odds)) && in_array(2,$backs) ){
            return 2;
        }
        //2.判断对子通杀
        if(in_array(18,array_flip($odds)) && in_array(4,$backs) ){
            return 2;
        }
        //3.判断顺子通杀
        if(in_array(19,array_flip($odds)) && in_array(6,$backs) ){
            return 2;
        }
        //4.判断豹子回本
        if(in_array(8,array_flip($odds)) && in_array(1,$backs) ){
            return 1;
        }
        //5.判断对子回本
        if(in_array(18,array_flip($odds)) && in_array(3,$backs) ){
            return 1;
        }
        //6.判断顺子回本
        if(in_array(19,array_flip($odds)) && in_array(5,$backs) ){
            return 1;
        }
        //7.089回本
        $num_arr=array($res['num1']=>$res['num1'],$res['num2']=>$res['num2'],$res['num3']=>$res['num3']);
        $status089=0;
        if(in_array(0,$num_arr)){
            unset($num_arr[0]);
            if(in_array(8,$num_arr)){
                unset($num_arr[8]);
                if(in_array(9,$num_arr)){
                    $status089=1;
                }
            }
        }
        if($status089==1 && in_array(5,$backs)){
            return 1;
        }
        //8.09回本
        $status09=0;
        if(in_array(0,$num_arr)){
            unset($num_arr[0]);
            if(in_array(9,$num_arr)){
                $status09=1;
            }

        }
        if($status089==1 && in_array(5,$backs)){
            return 1;
        }
        //9.对子遇1314回本
        if(in_array(18,array_flip($odds)) && in_array(9,$backs)){
            return 1;
        }
        return 0;
    }
    /**
     * [sendBill 发送账单]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-15
     * @return    [type]     [description]
     */
    public function sendBill()
    {
        $period=I('post.period');
        $gid=I('post.gid');
        $uid=I('post.uid');
        $bill=M('bill')->where(['gid'=>$gid,'period'=>$period])->select();
        if(!empty($bill)){
            $data=array();
            foreach ($bill as $value) {
                $user['user_nick']=M('group_user')->where(['gid'=>$gid,'uid'=>$value['uid']])->getField('user_nick');
                $user['remain']=$value['remain'];
                $data[]=$user;
            }

            $msg='';
            foreach ($data as $val) {
                $msg .=$val['user_nick'].' '.$val['remain'].' ';
            }
            $content=json_encode([
                'content'=>$msg
            ]);
            $rong_config=get_rong_key_secret();
            $rongcloud= new RongCloud($rong_config['key'],$rong_config['secret']);
            $res=$rongcloud->message()->publishGroup($uid,$gid,'RC:TxtMsg',$content);
            $res=json_decode($res,true);
            if($res['code']==200){
                $this->ajaxReturn(['code'=>'0','msg'=>'账单发送成功']);
            }else{
                $this->ajaxReturn(['code'=>'100000','msg'=>'账单发送失败']);
            }
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'账单为空']);
        }
    }
    /**
     * [water 回水]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-13
     * @param     string     $value [description]
     * @return    [type]            [description]
     */
    public function water()
    {
        $begin_time=I('post.start');
        $end_time=I('post.end');
        $gid=I('post.gid');
        if(empty($begin_time) || empty($end_time) || empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $water=M('bill')->alias('b')->join('lc_user u ON u.id=b.uid')->where(['u.is_admin'=>2,'b.gid'=>$gid,'b.atime'=>[['egt',$begin_time],['elt',$end_time]]])->select();
        $user=array_unique(array_column($water,'uid'));
        $water1=array();
        if(!empty($user)){
            foreach($user as $val){
                $group_user=M('group_user')->where(['uid'=>$val,'gid'=>$gid])->field('user_nick,yid,uid,gid,id')->find();
                $y_nick=M('group_user')->where(['uid'=>$group_user['yid'],'gid'=>$gid])->getField('user_nick');
                $y_name=M('user')->where(['id'=>$group_user['yid']])->getField('user_name');
                $user_name=M('user')->where(['id'=>$val])->getField('user_name');
                $bet_money=0;
                $group_money=0;
                $profit=0;
                $mark1=1;
                $mark2=1;
                foreach($water as $v){
                    if($val==$v['uid']){
                        $bet_money +=$v['total_bet'];
                        $profit +=$v['profit'];
                        $bet_arr=explode(';',trim($v['bet'],';'));
                        foreach ($bet_arr as $v) {
                            $arr=explode(',',$v);
                            if(in_array($arr[0],[13,14,15,16])){
                                $group_money +=$arr[1];
                            }
                        }
                        if($v['is_return_profit']==2){
                            $mark1=2;
                        }
                        if($v['is_return_water']==2){
                            $mark2=2;
                        }
                    }
                }
                $data['uid']=$val;
                $data['user_nick']=$group_user['user_nick'];
                $data['y_nick']=$y_nick;
                $data['y_name']=$y_name;
                $data['profit']=$profit;
                $data['user_name']=$user_name;
                $data['bet_money']=$bet_money;
                $data['group_money']=$group_money;
                $data['proporty']=round($group_money/$bet_money*100,2);
                $data['profit_mark']=$mark1;
                $data['water_mark']=$mark2;
                $water1[$val]=$data;

            }
            if(!empty($water1)){
                $this->ajaxReturn(['code'=>0,'msg'=>'回水情况','data'=>$water1]);
            }else{
                $this->ajaxReturn(['code'=>'100000','msg'=>'没有人投注']);
            }
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'没有人投注']);
        }
    }
    /**
     * [waterInfo 某个人的下注账单详情]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-13
     * @return    [type]     [description]
     */
    public function waterInfo()
    {
        $begin_time=I('post.start_time');
        $end_time=I('post.end_time');
        $gid=I('post.gid');
        $uid=I('post.uid');
        $bills=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'atime'=>[['egt',$begin_time],['lt',$end_time]]])->field('period,atime,profit,des,remain')->select();
        if(!empty($bills)){
            foreach ($bills as &$v) {
                $v['atime']=date('Y/m/d H:i:s',$v['atime']);
                $prize=M('period')->where(['period'=>$v['period']])->find();
                $v['prize']=$prize['num1'].' + '.$prize['num2'].' + '.$prize['num3'].' = '.$prize['cal_res'];
            }
        }
        $this->ajaxReturn($bills);
    }
    /**
     * [searchUser1 回水中搜索用户]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-28
     * @return    [type]     [description]
     */
    public function searchUser1()
    {
        $gid=I('post.gid');
        $search=I('post.search');
        $begin_time=I('post.start');
        $end_time=I('post.end');
        if(empty($gid) || empty($search)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $user=M('user')->where(['user_name'=>$search,'is_admin'=>2])->find();
        $where=array();
        if(!empty($user)){
            $where[]=['uid'=>$user['id'],'gid'=>$gid];
            $where[]=['user_nick'=>$num,'gid'=>$gid];
            $where['_logic']='OR';
        }else{
            $where=['user_nick'=>$num];
        }
        $group_user=M('group_user')->where($where)->field('user_nick,uid,money,yid')->find();
        // var_dump($group_user);die;
        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$group_user['uid'],'atime'=>[['egt',$begin_time],['lt',$end_time]]])->select();//指定用户在指定时间范围内的账单
        $water=array();
        $rebate=M('group_rebate')->alias('gr')->join('lc_rebate r ON r.id=gr.rid')->where(['gr.gid'=>$gid,'r.type'=>1])->getField('gr.value');
        if(!empty($bill)){
            $water['uid']=$user['id'];
            $water['user_nick']=$group_user['user_nick'];
            $water['user_name']=$user['user_name'];
            $profit=0;
            $total_bet=0;
            $bet_money=0;//总下注(没有返利的)
            $group_money=0;//组合下注
            foreach ($bill as $k=> $val) {
                $profit += $val['profit'];
                $total_bet += $val['total_bet'];
                if($val['is_return_profit']==2){
                    $bet_money +=$v['total_bet'];
                }
                $bet_arr=explode(';',trim($val['bet'],';'));
                foreach ($bet_arr as $v) {
                    $arr=explode(',',$v);
                    if(in_array($arr[0],[13,14,15,16])){
                        $group_money +=$arr[1];
                    }
                }
            }
            $proporty=round($group_money/$total_bet*100,2);
            $water['profit']=$profit;
            $water['total_bet']=$total_bet;
            $water['group_money']=$group_money;
            $water['proporty']=$proporty;
            $water['rebate']=round($bet_money*$rebate/1000,2);
            $water['parent']=M('user')->where(['id'=>$group_user['yid']])->getField('user_nick');
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'搜索结果为空']);
        }
        $this->ajaxReturn(['code'=>'0','msg'=>'回水','data'=>$water]);
    }

    /**
     * 上下分情况
     */
    public function scoreList()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $begin_time=strtotime(I('post.start_time'));
        $end_time=strtotime(I('post.end_time'));
        $list=M('group_score')->alias('gs')->join('lc_user ON u.id=gs.uid')->where(['u.is_admin'=>2,'gid'=>$gid,'uid'=>$uid,'ctime'=>[['egt',$begin_time],['lt',$end_time]]])->select();
        if(!empty($list)){
            foreach ($list as &$v) {
                if($v['in_out']==1){
                    if(empty($v['comment'])){
                        $v['comment']='玩家上分';
                    }
                }else{
                     if(empty($v['comment'])){
                        $v['comment']='玩家下分';
                    }
                    $v['score']=-$v['score'];
                }
                $v['ctime']=date('Y-m-d H:i:s',$v['ctime']);
            }
        }
        $this->ajaxReturn($list);
    }
    /**
     * [getMoneyByBet 通过开奖结果获取中奖金额]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-04
     * @param     [type]     $bet [description]
     * @return    [type]          [description]
     */
    private function getMoneyByBet($bet)
    {
        $bet_arr=explode(';',trim($bet,';'));
        $bet_money=0;
        foreach ($bet_arr as $v) {
            $arr=explode(',',$v);//下注类型,金额,注数
            $bet_money += $arr[1]*$arr[2];
        }
        return $bet_money;
    }
    /**
     * [getCurrentPeriod 获取最新期开奖信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-08
     * @return    [type]     [description]
     */
    private function getCurrentPeriod()
    {
//        $data=predis::getInstance()->get('bill_period');
//        if(empty($data)){
//            $config=C('LOTTERY.BJ');
//            $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=1&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
//            predis::getInstance()->set('bill_period',$data,5);
//        }
        $array = json_decode($data,true);
        $key=array_keys($array);
        $val=array_values($array);
        $all_num=explode(',',$val[0]['number']);//所有的号码
        $num1=0;
        $num2=0;
        $num3=0;
        foreach ($all_num as $k=>$v) {
            if($k>=0 && $k<7){
                $num1+=$v;
            }elseif($k>=7 && $k<14){
                $num2+=$v;
            }elseif($k>=14 && $k<21){
                $num3+=$v;
            }else{
                break;
            }
        }
        $num1=$num1%10;
        $num2=$num2%10;
        $num3=$num3%10;
        $cal_res=$num1+$num2+$num3;

        return ['period'=>$key[0],'dateline'=>strtotime($val[0]['dateline']),'num1'=>$num1,'num2'=>$num2,'num3'=>$num3,'res'=>$val[0]['number'],'cal_res'=>$cal_res];
    }
    /**
     * [rongcloudCoon 连接融云]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-17
     * @return    [type]     [description]
     */
    private function rongcloudCoon()
    {
        $rong_config=get_rong_key_secret();
        $rongcloud= new RongCloud($rong_config['key'],$rong_config['secret']);
        return $rongcloud;
    }
    /**
     * [getperiod_info 获取最新一期开奖信息]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-21
     * @return    [type]     [description]
     */
    public function getNewPeriodInfo()
    {
        $config=C('LOTTERY.BJ');
        $data = file_get_contents("http://api.caipiaokong.com/lottery/?name=".$config['NAME']."&num=1&format=".$config['FORMAT']."&uid=".$config['UID']."&token=".$config['TOKEN']."");
        $array = json_decode($data,true);
        $key=array_keys($array);
        $val=array_values($array);
        $period=M('period')->where(['period'=>$key[0]])->find();
        if(empty($period) || $period['status1']==1){//没有开过奖
            $all_num=explode(',',$val[0]['number']);//所有的号码
            $num1=0;$num2=0;$num3=0;
            foreach ($all_num as $k=>$v) {
                if($k>=0 && $k<7){
                    $num1+=$v;
                }elseif($k>=7 && $k<14){
                    $num2+=$v;
                }elseif($k>=14 && $k<21){
                    $num3+=$v;
                }else{
                    break;
                }
            }
            $num1=$num1%10;
            $num2=$num2%10;
            $num3=$num3%10;
            $cal_res=$num1+$num2+$num3;
            $res= ['period'=>$key[0],'dateline'=>strtotime($val[0]['dateline']),'num1'=>$num1,'num2'=>$num2,'num3'=>$num3,'res'=>$cal_res];
            return true;
        }else{//已经开过奖
            return false;
        }
    }
    /**
     * [setRebate 设置返利]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-21
     */
    public function setRebate()
    {
        $id=I('post.id');//群id
        $type=I('post.type');//1-设置比例 2-设置条件下限
        $num=I('post.val');//返利比例（千分之几）
        $rebate=M('group_rebate')->where(['id'=>$id])->find();
        M()->startTrans();
        if($type==1){
            $res=M('group_rebate')->where(['id'=>$id])->save(['value'=>$num]);
            $res2=M('group_logs')->add(['gid'=>$rebate['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'设置返利比例']);
        }else{
            $res=M('group_rebate')->where(['id'=>$id])->save(['limit'=>$num]);
            $res2=M('group_logs')->add(['gid'=>$rebate['gid'],'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>'设置返利条件限制']);
        }
        if($res===false || $res2<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'设置失败']);
        }else{
            M()->commit();
            $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
        }
    }
    /**
     * [getScoreIncome 上下分情况]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-21
     * @return    [type]     [description]
     */
    public function getScoreIncome()
    {
        $start=I('post.start');
        $end=I('post.end');
        $uid=I('post.uid');
        $gid=I('post.gid');
        if(empty($start) || empty($end) || empty($uid) ||empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $income=M('group_score')->where(['uid'=>$uid,'gid'=>$gid,'ctime'=>[['gt',$start],['lt',$end]] ])->select();
        if(!empty($income)){
            foreach ($income as &$v) {
                $v['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
                if($v['in_out'] ==2 ){
                    $v['score']=-$v['score'];
                }
            }
        }
        $this->ajaxReturn(['code'=>0,'msg'=>$income]);
    }
    /**
     * [reward 奖励]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-21
     * @return    [type]     [description]
     */
    public function reward()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $type=I('post.type');//1-上分 2-下分
        $add_score=I('post.score');
        $group_user=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->find();
        if(empty($group_user) || empty($add_score)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        M()->startTrans();
        if($type==1){
            $msg='给群成员'.$group_user['user_nick'].'上分'.$add_score;
        }else{
            $msg='给群成员'.$group_user['user_nick'].'下分'.$add_score;
        }
        $res1=M('group_score')->add(['score'=>$add_score,'in_out'=>$type,'gid'=>$gid,'uid'=>$uid,'comment'=>$msg,'ctime'=>time()]);//增加上下分记录
        if($type==1){
            $res2=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$group_user['money']+$add_score]);//增加群积分
        }else{
            if($group_user['money']<$add_score){
                M()->rollback();
                $this->ajaxReturn(['code'=>'100000','msg'=>'最大下分'.$group_user['money']]);
            }else{
                $res2=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$group_user['money']-$add_score]);//减少群积分
            }
        }
        $res3=M('group_logs')->add(['gid'=>$gid,'uid'=>session('admin_id'),'ctime'=>time(),'operate'=>$msg]);//记录日志
        if($res1 <=0 || $res2===false || $res3<=0){
            M()->rollback();
            $this->ajaxReturn(['code'=>'100000','msg'=>'上分失败']);
        }else{
            M()->commit();
            if($type==1){
                $money=$group_user['money']+$add_score;
            }else{
                $money=$group_user['money']-$add_score;
            }
            $this->ajaxReturn(['code'=>'0','msg'=>'上分成功','data'=>$money]);
        }
    }
    /**
     * [returnProfit 给某一个用户返利]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-23
     * @return    [type]     [description]
     */
    public function returnToUser()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $start=I('post.start');
        $end=I('post.end');
        $user=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->field('user_nick')->find();
        $user['user_name']=M('user')->where(['id'=>$uid])->getField('user_name');
        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'is_return_profit'=>2,'atime'=>[['egt',$start],['lt',$end]]])->select();
        if(empty($gid) || empty($uid) || empty($start) || empty($end)){
            $this->ajaxReturn(['code'=>'100000','数据为空']);
        }
        if(!empty($bill)){
            $bill_ids=array_column($bill,'id');
            $bet_money=0;
            $group_money=0;
            $profit=0;
            foreach ($bill as &$v) {
                $v['atime']=date('Y-m-d H:i:s',$v['atime']);
                $period_info=M('period')->where(['period'=>$v['period']])->find();
                $bet_money += $v['total_bet'];//总下注
                $profit +=$v['profit'];
                if(!empty($period_info)){//只有开奖的不能返利
                    $bet_arr=explode(';',trim($v['bet'],';'));
                    if(!empty($bet_arr)){
                        foreach ($bet_arr as $val) {
                            $arr=explode(',',$val);
                            if(in_array($arr[0],[13,14,15,16])){
                                $group_money +=$arr[1];
                            }
                        }
                    }
                }
            }
            $proporty=round(($group_money/$bet_money)*100,2);
        }else{
            $bill_ids=array();
        }
        $rebate=M('group_rebate')->alias('gr')->join('lc_rebate r ON r.id=gr.rid')->where(['gr.gid'=>$gid,'r.id'=>2])->field('gr.value,gr.limit')->find();
        $return_money=$bet_money*$rebate['value']/1000;
        $user['proporty']=$proporty;
        $user['return_money']=$return_money;
        $user['bet_money']=$bet_money;
        $user['group_money']=$group_money;
        $user['profit']=$profit;
        $this->ajaxReturn(['code'=>'0','msg'=>'返利数据','data'=>['user'=>$user,'bill'=>$bill]]);
        // if($proporty>$rebate['limit']){
        //     $return_money=$bet_money*$rebate['value']/1000;
        //     $group_user=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->getField('money');
        //     $user_nick=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->getField('user_nick');
        //     M()->startTrans();
        //     $money=$group_user+$return_money;
        //     $res1=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->save(['money'=>$money]);//增加返利金额
        //     $res2=M('bill')->where(['id'=>['in',$bill_ids]])->save(['is_return_profit'=>1]);//修改返利状态
        //     $res3=M('group_logs')->add(['uid'=>session('admin_id'),'ctime'=>time(),'gid'=>$gid,'operate'=>'给群成员'.$user_nick.'返利']);//增加操作日志
        //     if($res1===false || $res2===false || $res3<=0){
        //         M()->rollback();
        //         $this->ajaxReturn(['code'=>'100000','msg'=>'返利失败']);
        //     }else{
        //         M()->commit();
        //         $this->ajaxReturn(['code'=>'0','msg'=>'返利成功']);
        //     }
        // }else{
        //     $this->ajaxReturn(['code'=>'100000','msg'=>'没有达到返利条件']);
        // }
    }
    /**
     * [returnProfit 一键返利]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-23
     * @return    [type]     [description]
     */
    public function returnProfit()
    {
        $gid=I('post.gid');
        $start=I('post.start');
        $end=I('post.end');
        if(empty($gid) || empty($start) || empty($end)  ){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $bills=M('bill')->where(['gid'=>$gid,'is_return_profit'=>2,'atime'=>[['egt',$start],['lt',$end]]])->select();
        if(empty($bills)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'全部回过水']);
        }
        //返利条件
        $rebate=M('group_rebate')->alias('gr')->join('lc_rebate r ON r.id=gr.rid')->where(['gr.gid'=>$gid,'r.id'=>2])->field('gr.value,gr.limit')->find();
        $group_users=M('group_user')->where(['gid'=>$gid,'user_status'=>1,'admin_status'=>1,'is_quit'=>2])->select();
        M()->startTrans();
        foreach ($group_users as $val) {
            $bill=M('bill')->where(['gid'=>$gid,'uid'=>$val['uid'],'is_return_profit'=>2,'atime'=>[['egt',$start],['lt',$end]]])->select();//所有该用户的账单
            if(!empty($bill)){
                $bill_ids=array_column($bill,'id');
                $bet_money=0;
                $group_money=0;
                foreach ($bill as $v) {
                    $period_info=M('period')->where(['period'=>$v['period']])->find();
                    $bet_money += $v['total_bet'];//总下注
                    if(!empty($period_info)){//没有开奖的不能返利
                        $bet_arr=explode(';',trim($v['bet'],';'));
                        if(!empty($bet_arr)){
                            foreach ($bet_arr as $val) {
                                $arr=explode(',',$val);
                                if(in_array($arr[0],[13,14,15,16])){
                                    $group_money +=$arr[1];
                                }
                            }
                        }
                    }
                }
                $proporty=round(($group_money/$bet_money)*100,2);
            }else{
                $bill_ids=array();
            }
            if($proporty>$rebate['limit']){
                $return_money=round($bet_money*$rebate['value']/1000,2);
                $group_user=M('group_user')->where(['uid'=>$val['uid'],'gid'=>$gid])->getField('money');
                M()->startTrans();
                $money=$group_user+$return_money;
                $res1=M('group_user')->where(['uid'=>$val['uid'],'gid'=>$gid])->save(['money'=>$money]);//增加返利金额
                $res2=M('bill')->where(['id'=>['in',$bill_ids]])->save(['is_return_profit'=>1]);//修改返利状态
                $res3=M('water_record')->add(['gid'=>$gid,'uid'=>$val['uid'],'oper'=>session('admin_id'),'score'=>$return_money,'start'=>$start,'end'=>$end,'ctime'=>time(),'type'=>1]);//增加返利记录
                if($res1===false || $res2===false || $res3<=0){
                    M()->rollback();
                    $this->ajaxReturn(['code'=>'100000','msg'=>'一键返利失败']);
                }
            }
        }

        M()->commit();
        $this->ajaxReturn(['code'=>'0','msg'=>'一键返利成功']);
    }
    /**
     * [totalProfit 今日平台总盈利]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-23
     * @return    [type]     [description]
     */
    private function totalProfit($gid)
    {
        $money=0;
        $today_begin=strtotime(date('Y-m-d 0:0:0'));
        $tomorrow_begin=$today_begin+86400;
        $total_profit=M('bill')->alias('b')->join('lc_user u ON u.id=b.uid')->where(['u.is_admin'=>2,'gid'=>$gid,'atime'=>[['egt',$today_begin],['lt',$tomorrow_begin]]])->getField('profit',true);
        if(!empty($total_profit)){
            $money=array_sum($total_profit);
        }
        return -$money;
    }
    /**
     * [returnPrev 返回上把账单]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-27
     * @return    [type]     [description]
     */
    public function returnPrev()
    {
        $gid=I('post.gid');
        $period=I('post.period');
        if(empty($gid) || empty($period)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $group_users=M('group_user')->where(['gid'=>$gid,'user_status'=>1,'admin_status'=>1,'is_quit'=>2])->select();
        if(!empty($group_users)){
            foreach ($group_users as $k=>$v) {
                if($v['money']==0){//余额为0
                    $bill=M('bill')->where(['gid'=>$gid,'uid'=>$v['uid'],'period'=>$period+1])->find();
                    if(empty($bill) || $bill['total_bet']==0){//没有投注信息或者投注进额为0
                        unset($group_users[$k]);
                    }
                }
            }
            foreach ($group_users as $key => &$value) {
                $value['user_name']=M('user')->where(['id'=>$value['uid']])->getField('user_name');
                $bill=M('bill')->where(['gid'=>$gid,'uid'=>$v['uid'],'period'=>$period])->find();
                if(!empty($bill)){
                    $value['des']=$bill['des'];
                    $value['score']=$bill['total_bet'];
                    $value['time']=$bill['time'];
                    $value['profit']=$bill['profit'];
                }else{
                    $value['des']='--';
                    $value['score']=0;
                    $value['time']=0;
                    $value['profit']=0;
                }
            }
        }
        $this->ajaxReturn(['code'=>0,'msg'=>'上把账单','data'=>$group_users]);
    }
    /**
     * [periodBill 开奖账单情况]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-30
     * @return    [type]     [description]
     */
    public function periodBill()
    {
        $period=I('post.period');
        $gid=I('post.gid');
        if(empty($period) || empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $bills=M('bill')->where(['period'=>$period,'gid'=>$gid])->select();
        if(!empty($bills)){
            foreach ($bills as &$v) {
                $v['user_nick']=M('group_user')->where(['gid'=>$gid,'uid'=>$v['uid']])->getField('user_nick');
                $v['user_name']=M('user')->where(['id'=>$v['uid']])->getField('user_name');
            }
            $this->ajaxReturn(['code'=>0,'msg'=>'投注情况','data'=>$bills]);
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'投注为空']);
        }
    }
    /**
     * 增加返利
     */
    public function addProfit()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $start=I('post.start');
        $end=I('post.end');
        $score=I('post.score');
        $group_user=M('group_user')->where(['gid'=>$uid,'uid'=>$uid])->getField('user_nick');
        if(empty($gid) || empty($uid) || empty($start) ||empty($end) || empty($score)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $record=M('bill')->where(['gid'=>$gid,'atime'=>[['egt'=>$start],['lt',$end]],'is_return_profit'=>2])->select();
        if(empty($record)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'没有可以返利的']);
        }
        $money=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->getField('money');
        $res1=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money+$score]);//增加群积分
        $res2=M('bill')->where(['atime'=>[['egt',$start],['elt',$end]]])->save(['is_return_profit'=>1]);//修改返利状态
        $res3=M('water_record')->add(['gid'=>$gid,'uid'=>$uid,'oper'=>session('admin_id'),'score'=>$score,'start'=>$start,'end'=>$end,'ctime'=>time(),'type'=>1]);//增加返利记录
        if($res1===false || $res2===false || $res3<=0){
            $this->ajaxReturn(['code'=>'100000','msg'=>'返利失败']);
        }else{
            $this->ajaxReturn(['code'=>0,'msg'=>'返利成功','data'=>['remain'=>$money+$score,'add'=>$score]]);
        }
    }
    /**
     * [returnWater 回水]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-06-30
     * @return    [type]     [description]
     */
    public function returnWater()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $start=I('post.start');
        $end=I('post.end');
        $user=M('group_user')->where(['uid'=>$uid,'gid'=>$gid])->field('user_nick,uid')->find();
        $user['user_name']=M('user')->where(['id'=>$uid])->getField('user_name');
        $bill=M('bill')->where(['gid'=>$gid,'uid'=>$uid,'atime'=>[['egt',$start],['lt',$end]]])->select();
        if(empty($gid) || empty($uid) || empty($start) || empty($end)){
            $this->ajaxReturn(['code'=>'100000','数据为空']);
        }
        $profit=0;
        if(!empty($bill)){
            foreach ($bill as &$v) {
                $v['atime']=date('Y-m-d H:i:s',$v['atime']);
                $profit +=$v['profit'];
            }

        }
//        $rebate=M('group_rebate')->alias('gr')->join('lc_rebate r ON r.id=gr.rid')->where(['gr.gid'=>$gid,'r.id'=>2])->field('gr.value,gr.limit')->find();

        $user['profit']=$profit;
        $this->ajaxReturn(['code'=>'0','msg'=>'返利数据','data'=>['user'=>$user,'bill'=>$bill]]);
    }
     /**
     * 增加回水
     */
    public function addWater()
    {
        $gid=I('post.gid');
        $uid=I('post.uid');
        $start=I('post.start');
        $end=I('post.end');
        $score=I('post.score');

        if(empty($gid) || empty($uid) || empty($start) ||empty($end) || empty($score)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }

        $record=M('water_record')->where(['end'=>$end,'type'=>2])->find();
        if(empty($record)){
            $money=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->getField('money');
            M()->startTrans();
            $res1=M('group_user')->where(['gid'=>$gid,'uid'=>$uid])->save(['money'=>$money+$score]);//增加群积分
            $res2=M('water_record')->add(['gid'=>$gid,'uid'=>$uid,'oper'=>session('admin_id'),'score'=>$score,'start'=>$start,'end'=>$end,'ctime'=>time(),'type'=>2]);//增加回水记录
            if($res1===false || $res2<=0){
                M()->rollback();
                $this->ajaxReturn(['code'=>'100000','msg'=>'回水失败']);
            }else{
                M()->commit();
                $this->ajaxReturn(['code'=>0,'msg'=>'回水成功','data'=>['remain'=>$money+$score,'add'=>$score]]);
            }
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'已经回过水']);
        }
    }

//    public function test()
//    {
//        $str='jia100大';
//        $k1=['jia','加','加注','jiazhu'];
//        $k2=['大','小','大双','小单'];
//        $arr=array();
//        foreach($k1 as $k){
//            foreach($k2 as $ke){
//                $preg='/('.$k.'\d{1,}'.$ke.')/';
//                preg_match($preg,$str,$matches);
//                if(!empty($matches[0])){
//                    $arr=$matches[0];
//                }
//            }
//        }
//        dump($arr);
//        preg_match('/\d{1,}/',$arr,$match);
//        dump($match);die;
//    }

    /**
     * 回水记录分页
     */
    public function waterRecord()
    {
        $gid=I('post.gid');
        $page=I('post.page',0);
        if(empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $water_records=M('water_record')->where(['gid'=>$gid])->limit($page*20,20)->select();
        if(!empty($water_records)){
            foreach($water_records as &$values){
                $values['oper']=M('group_user')->where(['gid'=>$gid,'uid'=>$values['oper']])->getField('user_nick');
                $values['user']=M('group_user')->where(['gid'=>$gid,'uid'=>$values['uid']])->getField('user_nick');
                $values['ctime']=date('Y-m-d H:i:s',$values['ctime']);
                $values['start']=date('Y-m-d H:i:s',$values['start']);
                $values['end']=date('Y-m-d H:i:s',$values['end']);
            }
        }
        $this->ajaxReturn(['code'=>'0','msg'=>'回水记录','data'=>$water_records]);
    }

    /**
     * 添加假人消息
     */
    public function addFakerCon(){
        $gid=I('post.gid');
        $con=I('post.con');
        if(empty($gid) || empty($con)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $res=M('faker_message')->add(['content'=>$con,'gid'=>$gid,'status'=>1]);
        if($res>0){
            $this->ajaxReturn(['code'=>'0','msg'=>'添加内容成功','data'=>['id'=>$res,'content'=>$con]]);
        }else{
            $this->ajaxReturn(['code'=>'100000','msg'=>'添加内容失败']);
        }
    }
    /**
     * 添加假人消息
     */
    public function delFakerCon(){
        $id=I('post.id');
        $message=M('faker_message')->where(['id'=>$id])->find();
        if(empty($message)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $res=M('faker_message')->where(['id'=>$id])->save(['status'=>2]);
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'删除内容失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'删除内容成功']);
        }
    }

    public function editFakerCon()
    {
        $id=I('post.id');
        $con=I('post.con');
        $message=M('faker_message')->where(['id'=>$id])->find();
        if(empty($message) || empty($con)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $res=M('faker_message')->where(['id'=>$id])->save(['content'=>$con]);
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'编辑内容失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'编辑内容成功']);
        }
    }

    /**
     * 修改发送消息的假人个数
     */
    public function modFakerNum()
    {
        $num=I('post.num');
        $gid=I('post.gid');
        if(empty($num) || empty($gid)){
            $this->ajaxReturn(['code'=>'100000','msg'=>'数据有误']);
        }
        $res=M('group')->where(['id'=>$gid])->save(['faker_num'=>$num]);
        if($res===false){
            $this->ajaxReturn(['code'=>'100000','msg'=>'修改失败']);
        }else{
            $this->ajaxReturn(['code'=>'0','msg'=>'修改成功']);
        }
    }

    public function test()
    {
        echo '系统时间'.date('Y-m-d H:i:s');
    }

    public function test1()
    {
        $timer = new TimerController();
        $timer->addTimer(10, 'http://www.wpa6k9.cn/Admin/Login/login');
    }
}