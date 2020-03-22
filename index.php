<?php
require_once "config.php";
require_once "functions.php";

$wechatObj = new wechat();

if ( isset($_GET['echostr']) ) $wechatObj->valid();
$wechatObj->responseMsg();
// $wechatObj->createWxMenu();
// $wechatObj->sendTplMsg();
// $wechatObj->getUserDetailInfo();


class wechat {
	public function valid()
	{
		$echostr = $_GET['echostr'];
		if ( $this->checkSignature() ){
			ob_clean();
			echo $echostr;
			exit;
		}
	}
	/**
	 * 校验签名
	 * 1）将token、timestamp、nonce三个参数进行字典序排序 
	 * 2）将三个参数字符串拼接成一个字符串进行sha1加密
     * 3）开发者获得加密后的字符串可与signature对比，标识该请求来源于微信
	 */
	public function checkSignature()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		
		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
		    return true;
		}else{
		    return false;
		}
	}
	
	/**
	 * 响应用户消息
	 */
	public function responseMsg()
	{
		recordLog('***** 日志开始 *****');
		recordLog(date('Y-m-d H:i:s',time()));
		/****** 接收数据 ******/
		$postStr = $GLOBALS['HTTP_RAW_POST_DATA']; //POST数据
		// 写入日志
		recordLog($postStr);
		//用SimpleXML解析POST过来的XML数据
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $resultStr = $this->responseDataHandle($postObj);
		
		recordLog('***** 日志结束 *****');

		echo $resultStr; // 输出消息
	}
	/**
	 * 处理响应数据
	 * @access private
	 * @param  object $postObj 
	 * @return string 
	 */
	private function responseDataHandle($postObj)
	{
		$toUserName = $postObj->ToUserName; // 接收方账号
		$fromUserName = $postObj->FromUserName; // //发送方帐号（OpenID）
		$time = time();	// 当前时间戳
		
		$textTpl = $this->getTplData();
		$msgType = 'text'; // 消息类型
		$content = '';
		
		switch ( $postObj->MsgType )
		{
			$noticeStr = 'Hi.\n\n'.'回复 1： 每天一遍，防止变老\n'.'回复 城市名称，如： 北京，查询城市天气情况';
			case 'event':
				if ( $postObj->Event == 'subscribe' ){
					$content = $noticeStr;
				}else if ( $postObj->Event == 'unsubscribe' ){
					$content = '取消关注';
				}else if ( $postObj->Event == 'CLICK' || $postObj->Event == 'VIEW' ){
					$content = $postObj->EventKey;
				}
				$resultStr = sprintf($textTpl, $fromUserName, $toUserName, $time, $msgType, $content);
				break;
			case 'text':
				if ( $postObj->Content == '1' ){ // 图文消息		每天一遍，防止变老
					$msgType = 'news';
					$textTpl = $this->getTplData($msgType);
					$resultStr = sprintf($textTpl, $fromUserName, $toUserName, $time, $msgType);
				}else{	// 文本消息		天气查询
					// 获取城市天气信息
					$cityWeatherInfo = getCityWeatherInfo($postObj->Content); // ['status', '', 'info'=>'']
					if ( $cityWeatherInfo['status'] ){
						$content = $cityWeatherInfo['info']; // string
					}else{
						$content = $noticeStr;
					}
					// 格式化消息模板
					$resultStr = sprintf($textTpl, $fromUserName, $toUserName, $time, $msgType, $content);
				}
				break;
		}
		// 记录日志
		recordLog($resultStr);
		
		return $resultStr;
	}
	
	// 1 第一步：用户同意授权，获取code
	// public function getBaseInfoCode()
	// {
	// 	$redirect_uri = urlencode('http://www.example.com/example.php');
	// 	$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.APPID.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect';
	// 	header('Location: '.$url); // 页面将跳转至 redirect_uri/?code=CODE&state=STATE
	// }
	
	// 2 第二部：通过code换取网页授权access_token（与基础支持中的access_token不同）
	public function getAccessTokenByCode()
	{
		$code = $_GET['code'];
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.APPID.'&secret='.APPSECRET.'&code='.$code.'&grant_type=authorization_code';
		$res = curlHandle($url);
		
		return $res;
		/*
		{
		  "access_token":"ACCESS_TOKEN",
		  "expires_in":7200,
		  "refresh_token":"REFRESH_TOKEN",
		  "openid":"OPENID",
		  "scope":"SCOPE" 
		}
		*/
	}
	
	// 4 第四步：拉取用户信息(需scope为 snsapi_userinfo)
	public function getUserDetailInfo()
	{
		$res = $this->getAccessTokenByCode(); // json 
		$res = json_decode($res, true);		  // array
		
		$access_token = $res['access_token'];
		$openid = $res['openid'];
		$url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
		
		$result = curlHandle($url);
		
		echo $result;
	}
	// 创建公众号菜单
	public function createWxMenu()
	{
		$access_token = getWxAccessToken(); // get access_token
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token; // 接口url
		$menu = [
			'button'	=>	[
				[
					"type"	=>	"click",
		            "name"	=>	urlencode("菜单一"),
		            "key"	=>	"menu1"	
				], //	一级菜单
				[
		            "name"	=>	urlencode("菜单二"),
		            "sub_button"	=>	[
		            	[
		            		'type'	=>	'view',
		            		'name'	=>	urlencode('搜索'),
		            		'url'	=>	'https://baidu.com/',
		            	], // 二级菜单
		            	[
		            		'type'	=>	'click',
		            		'name'	=>	urlencode('点赞'),
		            		'key'	=>	'thumbup',
		            	], // 二级菜单
		            	
		            ],
				], //	一级菜单
				[
					"type"	=>	"view",
		            "name"	=>	urlencode("示例"),
		            "url"	=>	"http://www.example.com",		
				],//	一级菜单
			]
		];
		
		$menuJson = urldecode(json_encode($menu, true));
		// 正确时的返回JSON数据包如下：
		// {"errcode":0,"errmsg":"ok"}
		$res = httpCurl($url, 'post', $menuJson); 
		$resArr = json_decode($res, true); // array
		
		echo $menuJson;
		echo '<br/><hr/>';
		print_r($res);
	}
	
	// 发送模板消息
	public function sendTplMsg()
	{
		$access_token = getWxAccessToken();
		// 模板消息接口
		$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
		
		$msgTpl = [
			'touser'	=>	'ocQBa1c5rBfS7HT6n6bxA8t9m6HY',
			'template_id'	=>	'rkHDI7XE-efV7Ed08T1AURsFT0ijPMTmg1CSGQsPgGg',
			'url'	=>	'https://www.imooc.com/',
			'data'	=>	[
				'name'	=>	[
					'value'	=>	urlencode('巧克力'),
					"color"	=> "#173177",
				],	
				'money'	=>	[
					'value'	=>	29.9,
					"color"	=>	"#173177",
				],
				'time'	=>	[
					'value'	=>	date('Y-m-d H:m:i', time()),
					"color"	=>	"#173177",
				],
			]
		];
		
		$msgTplJson = urldecode(json_encode($msgTpl));
		// 在调用模板消息接口后，会返回JSON数据包。正常时的返回JSON数据包示例：
		/*
		 {
		    "errcode":0,
		     "errmsg":"ok",
		     "msgid":200228332
		  }
		  */
		$res = httpCurl($url, 'post', $msgTplJson); // json 
		
		$resArr = json_decode($res, true);
		
		echo '<pre>';
		echo 'access_token: ' . $access_token.'<br /><hr />';
		print_r($msgTpl);
		echo '<br /><hr />';
		print_r($msgTplJson);
		echo '<br /><hr />';
		var_dump($res);
		/*
			{
	           "touser":"OPENID",
	           "template_id":"ngqIpbwh8bUfcSsECmogfXcV14J0tQlEpBO27izEYtY",
	           "url":"http://weixin.qq.com/download",  
	           "miniprogram":{
	             "appid":"xiaochengxuappid12345",
	             "pagepath":"index?foo=bar"
	           },          
	           "data":{
	                   "first": {
	                       "value":"恭喜你购买成功！",
	                       "color":"#173177"
	                   },
	                   "keyword1":{
	                       "value":"巧克力",
	                       "color":"#173177"
	                   },
	           }
    	   }
		*/
	}
	
	/**
	 * 获取模板数据
	 * @access	private
	 * @param	$type	text|news|click 文本或图文
	 * @return	string	模板数据 
	 */ 
	private function getTplData($type='text')
	{
		switch ( $type )
		{
			case 'text':
				$tpl = '<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[%s]]></MsgType>
							<Content><![CDATA[%s]]></Content>
						</xml>';
				break;
			case 'news':
				$newsArr = [
					[
						'title'	=>	'每天一遍，防止变老',
						'description'	=>	'每天一遍，防止变老',
						'picUrl'	=>	'http://i2.hdslb.com/bfs/archive/9e06f76551541500ce390f7f3d23279d26969489.jpg@380w_240h_100Q_1c.webp',
						'url'	=>	'https://www.bilibili.com/video/av95966001',
					],
				];
				$tpl = '<xml>
						  <ToUserName><![CDATA[%s]]></ToUserName>
						  <FromUserName><![CDATA[%s]]></FromUserName>
						  <CreateTime>%s</CreateTime>
						  <MsgType><![CDATA[%s]]></MsgType>
						  <ArticleCount>'.count($newsArr).'</ArticleCount>
						  <Articles>';
				foreach ( $newsArr as $val ){
					$tpl .= '<item>
						      <Title><![CDATA['.$val['title'].']]></Title>
						      <Description><![CDATA['.$val['description'].']]></Description>
						      <PicUrl><![CDATA['.$val['picUrl'].']]></PicUrl>
						      <Url><![CDATA['.$val['url'].']]></Url>
						  </item>';	
				}
				$tpl .= '</Articles>
						</xml>';
				break;
		}
		
		return $tpl;
	}
	
}















