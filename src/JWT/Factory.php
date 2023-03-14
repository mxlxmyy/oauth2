<?php
namespace zmoauth2\JWT;

use Config;
use Request;
use think\Model;
use think\facade\Cookie;
use zmoauth2\Exceptions\JWTException;
use zmoauth2\Exceptions\UnauthenticateException;
use zmoauth2\Setting\Set;
use zmoauth2\JWT\Factories\Code;
use zmoauth2\JWT\Factories\Payload as PayloadFactory;
use zmoauth2\JWT\Factories\Claims\Collection;
use zmoauth2\JWT\Factories\Claims\Subject;
use zmoauth2\JWT\Factories\Claims\Custom;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class Factory
{

    protected $PayloadFactory;

    protected $defaultClaims = [
        'iss',
        'iat',
        'exp',
        'nbf',
        'jti',
    ];

    protected $claims;

    protected $config = [];

    /**
     * $mt 0:用户 1:sso
     */
    function __construct(string $moduleName = "")
    {
        Set::jwt(function($config) {
            $this->config = $config;
        }, $moduleName);

        $this->PayloadFactory = new PayloadFactory($this->config);
        $this->claims = new Collection;
    }

    /**
     * 验证账号
     * @param  array  $credentials [description]
     * @return [type]              [description]
     */
    public function attempt(array $credentials, array $customClaims = [])
    {
        $userModel = new $this->config['user'];

        // jwtSub属性不存在则使用email
        $subField = $this->getModelSub($userModel);
        $pwdField = $this->getModelPwd($userModel);
        // 查询模型
        $user = $userModel->where($subField, $credentials[$subField])->find();
        if ($user) {
            // 获取加密后的密码
            if (method_exists($userModel, 'jwtEncrypt')) {
                $inputPwd = $userModel->jwtEncrypt($credentials[$pwdField], $user);
            } else {
                $inputPwd = md5($credentials[$pwdField]);
            }
            // 验证密码
            if ($inputPwd !== $user->$pwdField) {
                throw new UnauthenticateException('账号验证失败');
            }
            return $this->fromSubject(new Subject($user->$subField), $customClaims);
        } else {
            throw new UnauthenticateException('账号不存在');
        }
    }

     /**
      * 从已认证的用户创建token
      * @param  Model  $user [description]
      * @return [type]       [description]
      */
    public function fromUser(Model $user, array $customClaims = [])
    {
        // jwtSub属性不存在则使用email
        $subField = isset($user->jwtSub) ? $user->jwtSub : 'email';
        return $this->fromSubject(new Subject($user->$subField), $customClaims);
    }

    /**
     * 将payload加密成为token
     * @param  Payload $payload [description]
     * @return [type]           [description]
     */
    public function encode(Payload $payload)
    {
        $code = new Code;
        return $code->encode($payload->toArray());
    }
    /**
     * 将token解密成为payload
     * @param  Payload $payload [description]
     * @return [type]           [description]
     */
    public function decode($token)
    {
        $code = new Code;
        return (array) $code->decode($token);
    }

    /**
     * 创建payload对象
     * @param  array  $customClaims [description]
     * @return [type]               [description]
     */
    public function makePayload(array $customClaims = [])
    {
        foreach ($customClaims as $key => $custom) {
            $paload = new Custom($key, $custom);
            $this->claims->unshift($paload->getValue(), $paload->getName());
        }
        return new Payload($this->claims);
    }

    /**
     * 解析token
     * @return [type] [description]
     */
    public function resolveToken(string $moduleName = "")
    {
        $code = new Code($moduleName);
        if ($token = $this->getToken()) {
            $payload = $code->decode($token);
            return (array) $payload;
        }
        return false;
    }

    /**
     * 验证token是否可刷新
     */
    public function verifyReAuth(bool $maxToken = true)
    {
        if ($this->config['old_token_refresh'] == false) {
            //过期token刷新禁用
            return false;
        }

        $code = new Code;
        if ($token = $this->getToken()) {
            $payload = $code->decode($token, null, null, false);
            if (time() - ($this->config['reauth'] * 3600) < $payload->exp) {
                if ($this->vMemberLoginTime($payload->id)) {
                    return $payload;
                }
            }
        }
        return false;
    }

    /**
     * 验证上次用户登录时间
     * @param  $id 用户id
     * @param  $ty 验证类型 0：已失效的token 1：有效期内的token
     */
    public function vMemberLoginTime(int $id, int $ty = 0)
    {
        $module = app("request")->module();

        //最大刷新时间
        //用户通过登录获取token授权后，可使用获取的token请求刷新接口，以获取新的token授权。
        //后台可以管理用户在一次登录后，最大可用token刷新获取新token的期限，避免token授权的无限刷新。
        $holdTime = intval($this->config['token_max_refresh']);
        $holdTime = ($holdTime < 24 ? 24 : $holdTime) * 3600; //最大刷新时间的最小值为24

        $time = redis_to_cache("member:login_token_time:" . $id . ":" . $module);
        if ($time === false) {
            $time = time();
            redis_to_cache("member:login_token_time:" . $id . ":" . $module, $time);
        }

        switch ($ty) {
            case 1:
                // 有效期内的token(即正常登录token，非已过期仅可刷新授权的token)刷新最大时间，最多可以多刷新24小时
                if (time() > ($time + $holdTime + 24 * 3600)) {
                    return false;
                }
                break;
            default:
                // 已失效的token
                if (time() > ($time + $holdTime)) {
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * 记录上次用户登录时间
     */
    public function setMemberLoginTime(int $id)
    {
        $module = app("request")->module();
        redis_to_cache("member:login_token_time:" . $id . ":" . $module, time());
        return true;
    }

    /**
     * 验证并返回用户模型
     * @return [type] [description]
     */
    public function authenticate()
    {
        $payload = $this->resolveToken();
        if ($payload && isset($payload['sub'])) {
            $userModel = new $this->config['user'];
            // jwtSub属性不存在则使用email
            $subField = $this->getModelSub($userModel);
            // 查询模型
            $user = $userModel->where($subField, $payload['sub'])->find();
            return $user;
        }
        return false;
    }

    /**
     * 验证并返回授权用户
     * 20190801-caojiangbo
     */
    public function authTokenUser(string $moduleName = "")
    {
        //获取并验证token
        $payload = $this->resolveToken($moduleName);
        //token所属账号类型 0:member 1:user 需要在生成的token中添加 'acmode' => 1 的信息，默认是0
        $acmode = [
            0 => ['index'],
            1 => ['admin'],
        ];
        $payloadAcmode = intval($payload['acmode']);
        if (!empty($moduleName) && !in_array($moduleName, $acmode[$payloadAcmode])) {
            exception("Token does not belong to this module!");
        }
        if ($this->config['is_authuser'] == false) { //严格验证模式
            return $payload;
        }
        
        //验证用户信息
        if ($payload && isset($payload['sub'])) {
            $userModel = new $this->config['user'];
            if (method_exists($userModel, 'authUser')) {
                return $userModel->authUser($payload, $moduleName);
            }

            // jwtSub属性不存在则使用email
            $subField = $this->getModelSub($userModel);
            // 查询模型
            $user = $userModel->where($subField, $payload['sub'])->find();
            if ($user) {
                $user = $user->toArray() + $payload;
            } else {
                $user = false;
            }

            return $user;
        }

        return false;
    }

    /**
     * 从请求中获取token
     * @return [type] [description]
     */
    public function getToken()
    {
        if ($Authorization = Request::header('Authorization')) {
            //未设置附加码
            if (empty($this->config['token_more'])) {
                return $Authorization;
            }

            $authArr = explode(' ', $Authorization);
            if (isset($authArr[0]) && $authArr[0] === $this->config['token_more']) {
                if (isset($authArr[1])) {
                    return $authArr[1];
                }
            }
        } else if (Request::has('member_token')) {
            $token = Request::param('member_token');
            //未设置附加码
            if (empty($this->config['token_more'])) {
                return $token;
            }

            $authArr = explode(' ', $token);
            if (isset($authArr[0]) && $authArr[0] === $this->config['token_more']) {
                if (isset($authArr[1])) {
                    return $authArr[1];
                }
            }
        } else if (Cookie::has('member_token')) {
            $token = Cookie::get('member_token');
            //未设置附加码
            if (empty($this->config['token_more'])) {
                return $token;
            }

            $authArr = explode(' ', $token);
            if (isset($authArr[0]) && $authArr[0] === $this->config['token_more']) {
                if (isset($authArr[1])) {
                    return $authArr[1];
                }
            }
        }
        return false;
    }

    /**
     * 获取sub字段
     * @return [type] [description]
     */
    protected function getModelSub(Model $userModel)
    {
        return isset($userModel->jwtSub) ? $userModel->jwtSub : 'email';
    }

    /**
     * 获取pwd字段
     * @return [type] [description]
     */
    protected function getModelPwd(Model $userModel)
    {
        return isset($userModel->jwtPassword) ? $userModel->jwtPassword : 'password';
    }


    /**
     * 增加sub并构建Claims
     * @param  Subject $sub [description]
     * @return [type]       [description]
     */
    protected function fromSubject(Subject $sub, array $customClaims = [])
    {
        $this->buildDefaultClaims($customClaims);
        $this->claims->unshift($sub->getValue(), $sub->getName());
        return $this->encode(new Payload($this->claims));
    }

    /**
     * 构建默认Claims
     * @return [type] [description]
     */
    private function buildDefaultClaims(array $customClaims = [])
    {
        // 如果过期时间未设置则删除过期时间
        if ($this->PayloadFactory->getTTL() === null && $key = array_search('exp', $this->defaultClaims)) {
            unset($this->defaultClaims[$key]);
        }
        // 遍历默认输入
        foreach ($this->defaultClaims as $claim) {
            // $this->claims[$claim] = $this->PayloadFactory->make($claim);
            $paload = $this->PayloadFactory->make($claim);
            $this->claims->unshift($paload->getValue(), $paload->getName());
        }
        // 遍历自定义输出
        foreach ($customClaims as $key => $custom) {
            $paload = new Custom($key, $custom);
            $this->claims->unshift($paload->getValue(), $paload->getName());
        }
        return $this;
    }

    /**
     * 获取加密token的payload
     * 可在生成token后执行，获取payload
     */
    public function getTokenPayload()
    {
        return $this->claims->toArray();
    }
}


