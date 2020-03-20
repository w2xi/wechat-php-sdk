<?php
require_once "config.php";
require_once "functions.php";

$scope = 'snsapi_userinfo';
getAuthCode($scope);

// 获取用户授权 code 
function getAuthCode($scopeType)
{
	$redirect_uri = urlencode('http://www.example.com/example.php');
	$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.APPID.'&redirect_uri='.$redirect_uri.'&response_type=code&scope='.$scopeType.'&state=STATE#wechat_redirect';
	header('location:'.$url); // 页面将跳转至 redirect_uri/?code=CODE&state=STATE
}

