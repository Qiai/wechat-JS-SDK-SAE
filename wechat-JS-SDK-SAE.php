<?php
/*
  微信JS SDK SAE特别优化版
  作者：Qiai
  博客：cnbattle.com
  邮箱：liqiai@qq.com
  
  使用说明：使用前请先初始化Memcache，免费版可初始化20MB。
*/
class JSSDK {
  private $appId;
  private $appSecret;

  public function __construct($appId, $appSecret) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
  }

  public function getSignPackage() {
    $jsapiTicket = $this->getJsApiTicket();
    // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function getJsApiTicket() {
    // jsapi_ticket 应该全局存储与更新
    $mmc = memcache_init();
    $jsapi_ticket = $mmc->get("jsapi_ticket");
    $jsapi_ticket_time = $mmc->get("jsapi_ticket_time");
       
    if ($jsapi_ticket_time < time()) {
      $accessToken = $this->getAccessToken();
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $jsapi_ticket = $res->ticket;

      if ($jsapi_ticket) {
        $jsapi_ticket_time = time() + 7000;
        $mmc->set("jsapi_ticket", $jsapi_ticket);
        $mmc->set("jsapi_ticket_time", $jsapi_ticket_time);
      }
    }
    return $jsapi_ticket;
  }

  private function getAccessToken() {
    // access_token 应该全局存储与更新
    $mmc = memcache_init();
    $access_token = $mmc->get("access_token");
    $access_token_time = $mmc->get("access_token_time");

    if ($access_token_time < time()) {
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
      $res = json_decode($this->httpGet($url));
      $access_token = $res->access_token;

      if ($access_token) {
        $access_token_time = time() + 7000;
        $mmc->set("access_token", $access_token);
        $mmc->set("access_token_time", $access_token_time);
      }
    }
    return $access_token;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);
    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
  }
}

?>
