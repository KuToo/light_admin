<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="keywords" content="维修,电脑,装软件">
    <title>轻聊-CMS</title>
		<link href="/Public/admin/css/reset.css" rel="stylesheet">
    <link href="//at.alicdn.com/t/font_689059_7s52zbcnhom.css" rel="stylesheet">
    <link href="/Public/admin/css/style.css" rel="stylesheet">
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://cdn.bootcss.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://cdn.bootcss.com/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
<body>
	<div class="header clear">
		<h1 class="left"><a href="index.html"><img src="/Public/admin/img/logo.png"/></a></h1>
		<div class="right">
			<img src="/Public/admin/img/tou.png" alt="" />
			<span>斯柯达</span>
			<i class="iconfont icon-shouye"></i>
			<i class="iconfont icon-tuichu1"></i>
		</div>		
	</div>
	<div class="clear" id="hei">
		<div class="left side">
			<ul>
				<li><a class="active" href="/Admin/User/index"><i class="use_icon"></i>用户管理</a></li>
				<li><a href=""><i class="gro_icon"></i>群组管理</a></li>
				<li><a href=""><i class="man_icon"></i>管理员管理</a></li>
				<li><a href=""><i class="muban_icon"></i>模板管理</a></li>
				<li><a href=""><i class="note_icon"></i>公告管理</a></li>
			</ul>
		</div>
		<div class="left content">
			<div class="breadcrumb">
				<a href="javascript:void(0);">首页</a>
				<em>></em>
				<span>用户管理</span>
			</div>
			<div class="user_manger">
				<div class="clear search">
					<input class="left" type="text" id="" value="" />
					<button class="left">搜索用户</button>
					<button class="right"><i class="iconfont icon-tianjia"></i>添加用户</button>				
				</div>
				<div class="user_list">
					<h4 class="clear">
						<span class="left">用户列表</span>
						<span class="right">共<em>5</em>条数据</span>
					</h4>
					<table>
						<thead>
							<tr>
								<th width="5%">ID</th>
								<th width="5%">头像</th>
								<th width="7%">轻聊号</th>
								<th width="11%">昵称</th>
								<th width="10%">手机</th>
								<th width="10%">余额</th>
								<th width="5%">性别</th>
								<th width="10%">地区</th>
								<th width="15%">注册时间</th>
								<th width="6%">状态</th>
								<th width="21%">操作</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>5</td>
								<td><img src="/Public/admin/img/tou.png"/></td>
								<td>10005</td>
								<td>手机壳</td>
								<td>15062662547</td>
								<td>32153215</td>
								<td>男</td>
								<td>北京/北京</td>
								<td>2018-06-10 14:20:15</td>
								<td><i class="iconfont icon-kaiguan" title="允许登录"></i></td>
								<td class="operation clear"><i onclick="plus(this)" class="iconfont icon-shangfen1" title="上分"></i><i onclick="reduce(this)" class="iconfont icon-xiafen1" title="下分"></i><i class="iconfont icon-bianji1" title="编辑用户"></i><i class="iconfont icon-zhongzhimima " title="重置密码"></i><i class="iconfont icon-shanchu1" title="删除"></i></td>
							</tr>							
						</tbody>
					</table>
				</div>
				<div class="page">当前共<em>1</em>页，没有更多数据了！</div>
			</div>
		</div>
	</div>
	<div class="add_point plus">
		<h3>上分</h3>
		<form onsubmit="return false">					
			<div class="clear">
				<label class="left">用户名</label>
				<input class="left" type="text" name="user_nick" id="" value="" readonly/>
			</div>
			<div class="clear">
				<label class="left">金额</label>
				<input class="left" type="number" onpropertychange="jiance(this)" oninput="jiance(this)" name="point" id="" value=""/>
			</div>
			<div class="clear">
				<label class="left">备注</label>
				<input class="left" type="text" name="extra" id="" value="" />
			</div>
			<div class="btn">			
				<button class="confirm disabled">确定</button>
				<button class="cancel" onclick="cancel()">取消</button>
			</div>
		</form>
	</div>
	<div class="add_point reduce">
		<h3>下分</h3>
		<form onsubmit="return false">					
			<div class="clear">
				<label class="left">用户名</label>
				<input class="left" type="text" name="user_nick" id="" value="" readonly/>
			</div>
			<div class="clear">
				<label class="left">现有金额</label>
				<input class="left" type="number" name="gobl" id="" value="" readonly/>
			</div>
			<div class="clear">
				<label class="left">下分金额</label>
				<input class="left" type="number" onpropertychange="jiance(this)" oninput="jiance(this)" name="reduce" id="" value=""/>
			</div>
			<div class="clear">
				<label class="left">备注</label>
				<input class="left" type="text" name="extra" id="" value="" />
			</div>
			<div class="btn">			
				<button class="confirm disabled">确定</button>
				<button class="cancel" onclick="cancel()">取消</button>
			</div>
		</form>
	</div>
	<div class="result true">
		<img src="/Public/admin/img/confirm.png"/>
		<p>操作成功！</p>
	</div>
	<div class="result false">
		<img src="/Public/admin/img/false.png"/>
		<p>操作失败，请重新操作！</p>
	</div>
	<div class="remove_psd">
		<img src="/Public/admin/img/remove.png"/>
		<p>是否确认重置密码？</p>
		<button class="confirm">确定</button>
		<button class="cancel">取消</button>
	</div>
	<div class="shade" onclick="cancel()"></div>
	<script src="/Public/admin/js/jquery-2.2.3.js"></script>
	<script src="/Public/admin/js/main.js"></script>
	<script type="text/javascript">
		 var w = document.documentElement.clientWidth;
			if(w>1000){
				window.onresize = getContentSize;
			setInterval('getContentSize()',1);//自动刷新
			function getContentSize() {
	            var wh = document.documentElement.clientHeight;
	            var ch=document.getElementById('hei');
	            ch = (wh-58) + "px";
	            document.getElementById( "hei" ).style.height = ch;
			      }
			     window.onresize = getContentSize;
			}	
			function plus(e){
				$('.plus').show();
				$('.shade').show();
			}
			function reduce(e){
				$('.reduce').show();
				$('.shade').show();
			}
			function cancel(){
				$('.add_point').hide();
				$('.add_point input').val('');
				$('.shade').hide();
				$('.confirm').addClass('disabled');
			}
			function jiance(e){
				var s=$(e).val();
				if (s=='') {
						$('.confirm').addClass('disabled');
				} else{
					$('.confirm').removeClass('disabled');
				}
			}
	</script>
</body>
</html>