<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" href="/Public/admin/css/reset.css" />
		<link rel="stylesheet" href="/Public/admin/css/login.css" />
		<meta http-equiv="X-UA-Compatible" content="IE=9; IE=8; IE=7; IE=Edge"/>
    	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title>轻聊</title>
	</head>
	<body id='height'>	
		<div class="login" >
			<h1>轻聊</h1>
			<form id="form1"  name="formSignup"  onsubmit="return login()" method="post" action="login" >
				<div class="form-group">
					<label>轻聊号/手机号</label>
					<input id="user_name" name="user_name"  type="text" placeholder="请输入轻聊号或手机号" />
					<p class="propmt"></p>
				</div>
				<div class="form-group">
					<label>密码</label>
					<input id="password" name="password"  type='password'   placeholder="请输入密码" />
					<p class="propmt"></p>
				</div>
				<div class="form-group">
					<input class="checkbox" type="checkbox" name="login_status"   />
					<em>下次自动登录</em>
					<a class="right" href="forget">忘记密码？</a>
				</div>
				<button  class="btn" type="submit">登 录</button>
			</form>
			<p class="goto-register">还没账号，去<a href="register">注册</a></p>
		</div>
		<script type="text/javascript" src="/Public/admin/js/jquery-2.2.3.js" ></script>
		<script>
			var height=window.innerHeight
			document.getElementById('height').style.height=height+"px";
			$('#user_name').blur(function(){
				var user_name=$(this).val();
				console.log(user_name)
				$.ajax({
			        type: "POST",//方法类型
	                dataType: "json",//预期服务器返回的数据类型
	                url: "/Web/Login/checkName" ,//url
	                data:  'user_name='+user_name,
	                success: function (result) {
	                    console.log(result);//打印服务端返回的数据(调试用)
	                    if (result.code != '0') {
	                        $('.propmt').eq(0).show().text(result.msg);	
	                    }else{
	                    	 $('.propmt').eq(0).hide();
	                    }
	                },
	                error : function() {
	                    $('.propmt').eq(0).show().text(result.msg);	
	                }
			    });
			});
			function login(){
				var username = document.getElementById('user_name').value	
				var pwd = document.getElementById('password').value	
				if((username=='') ){
					$('.propmt').eq(0).show().text('轻聊号不能为空');					
					return false
				}else if((pwd == '')){
					$('.propmt').eq(1).show().text('密码不能为空');
				}else{
					$.ajax({
				        type: "POST",//方法类型
		                dataType: "json",//预期服务器返回的数据类型
		                url: "/Web/Login/login" ,//url
		                data:  $('#form1').serialize(),
		                success: function (result) {
		                    console.log(result);//打印服务端返回的数据(调试用)
		                    if (result.code === '0') {
		                        window.location.href="<?php echo U('/Web/Index/index');?>"; 
		                    }else{
		                    	alert(result.msg);
		                        window.location.href="<?php echo U('/Web/Login/login');?>"; 
		                    };
		                },
		                error : function() {
		                    alert(result.msg);
		                    window.location.href="<?php echo U('/Web/Login/login');?>"; 
		                }
				    });
				}
				return false;
			}
			
			
		</script>
		
	</body>
</html>