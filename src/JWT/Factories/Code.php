<?php
namespace zmoauth2\JWT\Factories;

use zmoauth2\JWT\Library\UrlSafeBase64;
use Config;
use zmoauth2\Setting\Set;
use zmoauth2\Exceptions\JWTException;
use zmoauth2\Exceptions\TokenExpiredException;
use zmoauth2\Exceptions\TokenInvalidException;
use zmoauth2\Exceptions\TokenNotBeforeException;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class Code
{
    // 配置文件信息
    protected $config;

    // 可用加密算法
    protected $algorithms = [
        'HS256' => ['hash', 'SHA256'],
        'HS512' => ['hash', 'SHA512'],
        'RS256' => ['openssl', 'SHA256'],
    ];

    // 默认加密算法
    protected $algorithm = 'HS256';

    // 默认hash_hmac加密私钥
    protected $key; 

    // 默认openssl加密私钥路径
    protected $privateKeyPath;
    // 默认openssl加密公钥路径
    protected $publicKeyPath;

    // 默认openssl加密私钥
    protected $privateKey;
    // 默认openssl加密公钥
    protected $publicKey;

    protected $deviation = 0;


    function __construct(string $moduleName = "")
    {
        $this->init($moduleName);
    }

    /**
     * 读取配置文件
     */
    protected function init(string $moduleName = "")
    {
        Set::jwt(function($config) {
            $this->config = $config;
        }, $moduleName);

        if (isset($this->config['algorithm']) && $this->config['algorithm']) {
            $this->algorithm = $this->config['algorithm'];
        }
        if (isset($this->config['key']) && $this->config['key']) {
            $this->key = $this->config['key'];
        }

        if (isset($this->config['deviation']) && $this->config['deviation']) {
            $this->deviation = $this->config['deviation'];
        }

        if (isset($this->config['privateKeyPath']) && $this->config['privateKeyPath'] && isset($this->config['publicKeyPath']) && $this->config['publicKeyPath']) {
            $this->privateKeyPath = 'file://' . env('root_path') .$this->config['privateKeyPath'];
            $this->publicKeyPath = 'file://' . env('root_path') .$this->config['publicKeyPath'];
        }

        if (empty($this->algorithms[$this->algorithm])) {
            throw new JWTException('加密算法不支持');
        }

        // 检查openssl支持和配置正确性
        if ('openssl' === $this->algorithms[$this->algorithm][0]) {
            if (!extension_loaded('openssl')) {
                throw new JWTException('php需要openssl扩展支持');
            }
            if (!file_exists($this->privateKeyPath) || !file_exists($this->publicKeyPath)) {
                throw new JWTException('密钥或者公钥的文件路径不正确');
            }
            // 读取公钥和私钥
            $this->privateKey = openssl_pkey_get_private($this->privateKeyPath);
            $this->publicKey = openssl_pkey_get_public($this->publicKeyPath);
        }
    }

    /**
     * 解密token
     * @param  [type]  $token     授权的token字符串
     * @param  [type]  $key       加密key
     * @param  [type]  $algorithm
     * @param  boolean $timeout       是否验证token已过期
     */
    public function decode($token, $key = null, $algorithm = null, $timeout = true)
    {

        if ($key) {
            $this->key = $key;
        } else {
            if ('openssl' === $this->algorithms[$this->algorithm][0]) {
                $this->key = $this->publicKey;
            }
        }
        if ($algorithm) $this->algorithm = $algorithm;

        $segments = explode('.', $token);

        if (count($segments) != 3) {
            throw new JWTException('Token文本错误');
        }

        list($header64, $payload64, $signature64) = $segments;

        // 获取3个片段
        $header = json_decode(UrlSafeBase64::decode($header64), false, 512, JSON_BIGINT_AS_STRING);
        $payload = json_decode(UrlSafeBase64::decode($payload64), false, 512, JSON_BIGINT_AS_STRING);
        $signature = UrlSafeBase64::decode($signature64);

        // 验证签名
        if (!$this->verify("$header64.$payload64", $signature)) {
            throw new TokenInvalidException('无效的 Token');
        }

        // 在什么时间之前，该jwt都是不可用的
        if (isset($payload->nbf) && $payload->nbf > (time() + $this->deviation)) {
            throw new TokenNotBeforeException('该 Token 无法在当前时间使用');
        }

        // 检查是否过期
        if ($timeout) {
            if (isset($payload->exp) && (time() - $this->deviation) >= $payload->exp) {
                throw new TokenExpiredException('该 Token 已过期');
            }
        }
        
        return $payload;
    }

    /**
     * 加密
     */
    public function encode($payload, $key = null, $algorithm = null)
    {
        if ($key) {
            $this->key = $key;
        } else {
            if ('openssl' === $this->algorithms[$this->algorithm][0]) {
                $this->key = $this->privateKey;
            }
        }
        if ($algorithm) $this->algorithm = $algorithm;

        $header = ['typ' => 'JWT', 'alg' => $this->algorithm];
        $segments = [];
        // 编码第一部分 header
        $segments[] = UrlSafeBase64::encode(json_encode($header));
        // 编码第二部分 payload
        $segments[] = UrlSafeBase64::encode(json_encode($payload));

        // 第三部分为header和payload signature
        $signature_string = implode('.', $segments);
        $signature = $this->signature($signature_string);
        // 加密第三部分
        $segments[] = UrlSafeBase64::encode($signature);

        return implode('.', $segments);
    }

    /**
     * jwt 第三部分签名
     * @param  [type] $data      [description]
     * @param  [type] $key       [description]
     * @param  [type] $algorithm [description]
     * @return [type]            [description]
     */
    public function signature($data)
    {
        list($func, $alg) = $this->algorithms[$this->algorithm];

        switch($func) {
        // hash_hmac 加密
            case 'hash':
                return hash_hmac($alg, $data, $this->key, true);
            // openssl 加密
            case 'openssl':
                $sign = '';
                $ssl = openssl_sign($data, $sign, $this->key, $alg);
                if (!$ssl) {
                    throw new JWTException("OpenSSL无法签名数据");
                }
                return $sign;
        }
    }

    public function verify($data, $signature)
    {
        list($func, $alg) = $this->algorithms[$this->algorithm];

        switch($func) {
            case 'hash':
                $hash = hash_hmac($alg, $data, $this->key, true);
                return hash_equals($signature, $hash);
            case 'openssl':
                $isVerify = openssl_verify($data, $signature, $this->key, $alg);
                if (!$isVerify) {
                    return false;
                }
                return $signature;
        }
        return false;
    }
}


