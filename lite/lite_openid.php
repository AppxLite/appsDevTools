<?php

// 获取用户的微信 openid
add_action('rest_api_init', function() {
    register_rest_route('apps/v1', 'user/openid', array(
      'methods' => 'POST', 
      'callback' => 'getUserOpenid'
   ));
});
function getUserOpenid($request) {
    $js_code = $request['js_code'];
    $encryptedData = $request['encryptedData'];
    $iv = $request['iv'];
    $avatarUrl = $request['avatarUrl'];
    $nickname = empty($request['nickname']) ? '' : $request['nickname'];
    if(empty($js_code)) {
        return new WP_Error('error', 'js_code is empty', array('status' => 500));
    } else if(!function_exists('curl_init')) {
        return new WP_Error('error', 'php curl is not enabled', array('status' => 500));
    } else {
        $data = post_user_openid_data($js_code, $encryptedData, $iv, $avatarUrl, $nickname); 
        if (empty($data)) {
            return new WP_Error('error', 'get openid error', array('status' => 404));
        }
        $response = new WP_REST_Response($data);
        $response->set_status(200);
        return $response;
    } 
}

function post_user_openid_data($js_code, $encryptedData, $iv, $avatarUrl, $nickname) {
    $appid = get_option('lite_appid');
    $appsecret = get_option('lite_appsecretkey');
    $role = 'subscriber';//subscriber：订阅者, contributor：投稿者, author：作者, editor：编辑
    if(empty($appid) || empty($appsecret)) {
        $result["code"] = "success";
        $result["message"] = "appid or appsecret is empty";
        $result["status"] = "500";
        return $result;
    } else {
        $access_url = "https://api.weixin.qq.com/sns/jscode2session?appid=".$appid."&secret=".$appsecret."&js_code=".$js_code."&grant_type=authorization_code";
        $access_result = https_request($access_url);
        if($access_result != "ERROR") {
            $access_array = json_decode($access_result, true);
            if(empty($access_array['errcode'])) {
                $openid = $access_array['openid']; 
                $passwd = base64_encode($openid);
                $sessionKey = $access_array['session_key'];
                $pc = new liteWXBizDataCrypt($appid, $sessionKey);
                $errCode = $pc->decryptData($encryptedData, $iv, $data);
                if ($errCode == 0) {
                    if(!username_exists($openid)) {
                        $data = json_decode($data, true);
                        $userdata = array(
                            'user_login' => $openid, 
                            'nickname' => $nickname, 
                            'user_nicename' => $nickname, 
                            'display_name' => $nickname, 
                            'user_email' => $openid.'@weixin.com', 
                            'wxavatar' => $avatarUrl, 
                            'openid' => $openid, 
                            'role' => $role, 
                            'user_pass' => $passwd // MD5加密为密码
                        );
                        $user_id = wp_insert_user($userdata);
                        if (is_wp_error($user_id)) {
                            $result["code"] = "success";
                            $result["message"] = "insert openid error";
                            $result["status"] = "500";
                            return $result;
                        } else {
                            $result["code"] = "success";
                            $result["message"] = "get openid success";
                            $result["status"] = "200";
                            $result["openid"] = $openid;
                            return $result;
                        }
                    } else {
                        $result["code"] = "success";
                        $result["message"] = "get openid success";
                        $result["status"] = "200";
                        $result["openid"] = $openid;
                        return $result;
                    }
                } else {
                    $result["code"] = "success";
                    $result["message"] = $errCode;
                    $result["status"] = "500";
                    return $result;
                }
            } else {
                $result["code"] = $access_array['errcode'];
                $result["message"] = $access_array['errmsg'];
                $result["status"] = "500";
                return $result; 
            }
        } else {
            $result["code"] = "success";
            $result["message"] = "https request error";
            $result["status"] = "500";
            return $result;
        }
    }
}
/**
* error code 说明.
* <ul>
*    <li>-41001: encodingAesKey 非法 </li>
*    <li>-41003: aes 解密失败 </li>
*    <li>-41004: 解密后得到的 buffer 非法 </li>
*    <li>-41005: base64 加密失败 </li>
*    <li>-41016: base64 解密失败 </li>
* </ul>
*/
class liteErrorCode {
    public static $OK = 0;
    public static $IllegalAesKey = -41001;
    public static $IllegalIv = -41002;
    public static $IllegalBuffer = -41003;
    public static $DecodeBase64Error = -41004;
}
/**
* 对微信小程序用户加密数据的解密示例代码.
*
* @copyright Copyright (c) 1998-2014 Tencent Inc.
*/
class liteWXBizDataCrypt {
    private $appid;
    private $sessionKey;
    /**
    * 构造函数
    * @param $sessionKey string 用户在小程序登录后获取的会话密钥
    * @param $appid string 小程序的 appid
    */
    public function __construct($appid, $sessionKey) {
        $this->sessionKey = $sessionKey;
        $this->appid = $appid;
    }
    /**
    * 检验数据的真实性，并且获取解密后的明文.
    * @param $encryptedData string 加密的用户数据
    * @param $iv string 与用户数据一同返回的初始向量
    * @param $data string 解密后的原文
    *
    * @return int 成功 0，失败返回对应的错误码
    */
    public function decryptData($encryptedData, $iv, &$data) {
        if (strlen($this->sessionKey) != 24) {
            return liteErrorCode::$IllegalAesKey;
        }
        $aesKey = base64_decode($this->sessionKey);
        if (strlen($iv) != 24) {
            return liteErrorCode::$IllegalIv;
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj = json_decode($result);
        if($dataObj == NULL) {
            return liteErrorCode::$IllegalBuffer;
        }
        if($dataObj->watermark->appid != $this->appid) {
            return liteErrorCode::$IllegalBuffer;
        }
        $data = $result;
        return liteErrorCode::$OK;
    }
}