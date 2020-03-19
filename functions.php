<?php 
session_start();
require_once "config.php";

// 日志记录
function recordLog($msg)
{
	$filename = 'wx_log.txt';
	file_put_contents($filename, $msg . PHP_EOL, FILE_APPEND);
}

function httpCurl($url, $method='get', $arr=null)
{
	// 创建新的 cURL 资源
	$ch = curl_init();
	// 设置 URL 和相应的选项
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if ( $method == 'post' ){ 
		// 如果发送post请求
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
	}
	// 抓取 URL
	$res = curl_exec($ch); // json 
	
	// 关闭 cURL 资源，并且释放系统资源
	curl_close($ch);
	
	return $res;
}

function curlHandle($url)
{
	// 创建新的 cURL 资源
	$ch = curl_init();
	
	// 设置 URL 和相应的选项
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// 抓取 URL
	$res = curl_exec($ch);
	
	// 关闭 cURL 资源，并且释放系统资源
	curl_close($ch);
	
	return $res;
}

// 获取access_token
function getWxAccessToken()
{
	$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.APPID.'&secret='.APPSECRET;
	if ( $_SESSION['access_token'] && $_SESSION['expire_time'] > time() ){
		$access_token = $_SESSION['access_token'];
	}else{
		$res = curlHandle($url);
		$resArr = json_decode($res, true); // ['access_token'=>'ACCESS_TOKEN', 'expires_in'=>7200]
		// 将access_token存入session中
		$_SESSION['access_token'] = $resArr['access_token'];
		// 为access_token设置过期时间
		$_SESSION['expire_time'] = time() + $resArr['expires_in'];
		
		$access_token = $resArr['access_token'];
		
		if ( !$access_token ){
			$access_token = 'access_token is empty.';
		}
	}
	return $res;
}

function getWxIpAddr()
{
	$access_token = getWxAccessToken();
	$url = 'https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token='.$access_token;
	$res = curlHandle($url);
	$resArr = json_decode($res, true);
	
	echo '<pre>';
	var_dump($resArr);
}
// 从线上获取
function getCityCodeData()
{
	$url = 'https://raw.githubusercontent.com/baichengzhou/weather.api/master/src/main/resources/citycode-2019-08-23.json';
	$res = curlHandle($url);
	$resArr = json_decode($res, true); // 二位数组
	
	return $resArr;
}
// 从本地文件中获取
function getCityCodeDataFromLocal()
{
	$res = file_get_contents('citycode.json');
	$resArr = json_decode($res, true);
	
	return $resArr;
}
// 将索引数组转换成关联数组
function numIndex2CityNameIndex()
{
	$resArr = getCityCodeDataFromLocal();
	
	foreach ( $resArr as $key => $val ){
		$result[$val['city_name']] = $resArr[$key];
	}
	
	return $result;
}

// 获取 city_code
function getCityCode($city)
{
	$resArr = numIndex2CityNameIndex();
	if ( array_key_exists($city, $resArr) && !empty($resArr[$city]['city_code']) ){
		$city_code = $resArr[$city]['city_code'];
	}else{
		$city_code = -1;
	}
	
	return $city_code;
}

// 获取城市天气
function getCityWeatherInfo($city_name)
{
	$city_code = getCityCode(trim($city_name));
	if ( $city_code == -1 ){
		$msg = ['status'=>0, 'info'=>''];
	}else{
		$url = 'http://t.weather.sojson.com/api/weather/city/'.$city_code;
		$res = curlHandle($url);
		$resArr = json_decode($res, true);
		
		$info = '';
		$weatherData = $resArr['data']['forecast'];
		$today = $weatherData[0];// 只获取今日的天气信息
		$info = "{$today['ymd']} {$today['week']}\n{$today['type']}\n最{$today['high']} 最{$today['low']}\n{$today['fx']} {$today['fl']}\n{$today['notice']}";
		
		$msg = ['status'=>1, 'info'=>$info];
	}
	
	return $msg;
}

// getWxIpAddr();
// $resArr = numIndex2CityNameIndex();
// $resArr = getCityCodeDataFromLocal();
// $res = getWxAccessToken();

// echo $res;
// echo '<pre>';
// print_r($resArr);
















