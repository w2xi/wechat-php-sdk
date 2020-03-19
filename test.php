<?php

$str = '你好';
$str = urlencode($str);
// 转成json格式出现乱码
// 解决办法：在json_encode()前进行urlencode()转换，在json_encode()后进行urldecode()转换
echo urldecode(json_encode($str)); 