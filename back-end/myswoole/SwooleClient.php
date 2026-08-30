<?php
namespace myswoole;

use think\facade\Db;

require_once __DIR__ . '/../config/env.php';

class SwooleClient
{
    private $client;

    public function __construct()
    {
        $this->client = new \Swoole\Client(SWOOLE_SOCK_TCP);
    }

    public function connect()
    {
        //9501要和swoole服务监听的端口号一致
        if (!$this->client->connect(\config_swoole_host(), \config_swoole_port(), -1)) {
            throw new \Exception(sprintf('Swoole Error: %s', $this->client->errCode));
        }
    }

    public function isconnect(){
        return $this->client->isConnected();
    }

    /**
     * 执行任务
     */
    public function startTask($data)
    {
        if ($this->client->isConnected()) {
            if (!is_string($data)) {
                $data = json_encode($data);
            }
            //拼接"\r\n"，是解决在循环场景下，投递任务可能会出现的tcp粘包问题。
            return $this->client->send($data . "\r\n");
        } else {
            throw new \Exception('Swoole Server does not connected.');
        }
    }

    public function close()
    {
        $this->client->close();
    }

    public function httpGet($url, $params = [], $timeout = 10, $header=[])
    {
        $ret = [
            'errno' => 0,
            'error' => '',
            'data' => '',
            'http_code' => 0,
        ];

        if ($params) {
            $url = $url . '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 QianZhuangApi/2.0");
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        $data = curl_exec($ch);

        $httpStatus = curl_getinfo($ch);
        $ret['http_code'] = $httpStatus['http_code'];

        if (empty($data)) {
            $ret['errno'] = 1;
            $ret['error'] = curl_error($ch);
        } else {
            $ret['data'] = $data;
        }
        curl_close($ch);

        return $ret;
    }

    public static function httpGetProxy($url, $proxyIp, $proxyPort, $userAgent, $stoptime)
    {
        // 要访问的目标页面
        $targetUrl = $url;

        // 代理服务器
        $proxyServer = "http://".$proxyIp.":".$proxyPort;;

        // 隧道身份信息
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetUrl);

        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // 设置代理服务器
        curl_setopt($ch, CURLOPT_PROXYTYPE, 0); //http

        // curl_setopt($ch, CURLOPT_PROXYTYPE, 5); //sock5

        curl_setopt($ch, CURLOPT_PROXY, $proxyServer);

        // 设置隧道验证信息
        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);

        if(empty($userAgent)){
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727;)");
        }else{
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_setopt($ch, CURLOPT_HEADER, true);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        sleep($stoptime);

        curl_close($ch);

        // var_dump($result);
    }
}
