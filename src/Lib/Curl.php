<?php

/**
 * curl工具
 * @author fuqiang
 *
 */
namespace Lib;
class Curl
{
    private static $_ch;
    private static $_header;
    private static $_body;

    private static $_cookie = array();
    private static $_options = array();
    private static $_url = array();
    private static $_referer = array();

    /**
     * 调用外部url
     * @param $queryUrl
     * @param $param 参数
     * @param string $method
     * @return bool|mixed
     */
    public static function callWebServer($queryUrl, $param = '', $method = 'get', $timeout = 30, $isJson = false, $isUrlcode = true)
    {
        if (empty($queryUrl)) {
            return false;
        }

        $method = strtolower($method);
        $param = empty($param) ? array() : $param;

        //初始化curl
        self::_init($queryUrl, $timeout);

        if ($method == 'get') {
            $result = self::_httpGet($queryUrl, $param);
        } elseif ($method == 'post') {
            $result = self::_httpPost($queryUrl, $param, $isJson, $isUrlcode);
        } else if ($method == 'delete') {
            $result = self::_delete($queryUrl, $param);
        }

        return $result;
    }

    /**
     * Header头请求
     * @param $queryUrl
     * @param string $param
     * @param string $method
     * @param int $timeout
     * @param $header
     * @param bool $isUrlcode
     * @return bool|mixed
     */
    public static function callWebServerWithHeader($queryUrl, $param = '', $method = 'get', $timeout = 30, $header, $isUrlcode = true)
    {
        if (empty($queryUrl)) {
            return false;
        }

        $method = strtolower($method);
        $param = empty($param) ? array() : $param;

        //初始化curl
        self::_init($queryUrl, $timeout);
        if ($header) {
            $arr = [['key' => CURLOPT_HEADER, 'value' => false], ['key' => CURLOPT_HTTPHEADER, 'value' => $header]];
            self::setOption($arr);
        }

        if ($method == 'get') {
            $result = self::_httpGet($queryUrl, $param);
        } elseif ($method == 'post') {
            $result = self::_httpPost($queryUrl, json_encode($param), false, $isUrlcode);
        } else if ($method == 'delete') {
            $result = self::_delete($queryUrl, $param);
        }

        if (!empty($result)) {
            return $result;
        }

        return true;
    }

    public static function setOption($optArray = array())
    {
        foreach ($optArray as $opt) {
            curl_setopt(self::$_ch, $opt['key'], $opt['value']);
        }
    }

    /**
     * 初始化curl
     */
    private static function _init($queryUrl, $timeout = 30)
    {
        self::$_ch = curl_init();

        curl_setopt(self::$_ch, CURLOPT_HEADER, true);
        curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$_ch, CURLOPT_TIMEOUT, $timeout);

        if (stripos($queryUrl, "https") === 0) {
            curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt(self::$_ch, CURLOPT_SSLVERSION, 1);
        }
    }

    private static function _close()
    {
        if (is_resource(self::$_ch)) {
            curl_close(self::$_ch);
        }

        return true;
    }

    private static function _httpGet($url, $query = array())
    {

        if (!empty($query)) {
            $url .= (strpos($url, '?') === false) ? '?' : '&';
            $url .= is_array($query) ? http_build_query($query) : $query;
        }

        curl_setopt(self::$_ch, CURLOPT_URL, $url);
        curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(self::$_ch, CURLOPT_HEADER, 0);

        $result = self::_execute();
        self::_close();
        return $result;
    }

    private static function _httpPost($url, $query = array(), $isJson = false, $isUrlcode = true)
    {
        if (is_array($query)) {
            if ($isUrlcode) {
                $query = self::_urlencodeArray($query);
            }

            if ($isJson) {
                $query = json_encode($query, JSON_UNESCAPED_UNICODE);
            } else {
                $query = http_build_query($query, 'pre_', '&');
            }
        }


        $headers = array();

        if ($isJson) {
            $headers[] = 'Content-type: application/json; charset=utf-8';
            $headers[] = 'Content-Length: ' . strlen($query);
            curl_setopt(self::$_ch, CURLOPT_HTTPHEADER, $headers);

        }

        curl_setopt(self::$_ch, CURLOPT_URL, $url);
        curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(self::$_ch, CURLOPT_HEADER, 0);
        curl_setopt(self::$_ch, CURLOPT_POST, true);
        curl_setopt(self::$_ch, CURLOPT_POSTFIELDS, $query);


        $result = self::_execute();
        self::_close();
        return $result;
    }

    private static function _put($url, $query = array())
    {
        curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'PUT');

        return self::_httpPost($url, $query);
    }

    private static function _delete($url, $query = array())
    {
        curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return self::_httpPost($url, $query);
    }

    private static function _head($url, $query = array())
    {
        curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'HEAD');

        return self::_httpPost($url, $query);
    }

    private static function _execute()
    {
        $response = curl_exec(self::$_ch);
        $errno = curl_errno(self::$_ch);

        if ($errno > 0) {
            if ($errno != CURLE_OPERATION_TIMEDOUT) {
                //超时的时候不抛出异常了
                throw new \Exception(curl_error(self::$_ch), $errno);
            }
        }

        return $response;
    }

    private static function _urlencodeArray($data)
    {
        $new_data = array();
        foreach ($data as $key => $val) {
            // 这里我对键也进行了urlencode
            if (is_numeric($val)) {
                $new_data[urlencode($key)] = $val;
            } else {
                $new_data[urlencode($key)] = is_array($val) ? self::_urlencodeArray($val) : urlencode($val);
            }
        }
        return $new_data;
    }


}

?>
