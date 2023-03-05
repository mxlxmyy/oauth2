<?php 
return [
    // 加密算法
    'algorithm' => 'HS256',
    // HMAC算法使用的加密字符串
    'key' => 'jwtencryptstr',
    // RSA算法使用的私钥文件路径
    'privateKeyPath' => 'cert/encryption_private_key.pem',
    // RSA算法使用的公钥文件路径
    'publicKeyPath' => 'cert/encryption_public_key.pem',
    // 误差时间，单位秒
    'deviation' => 60,
    // 过期时间, 单位分钟
    'ttl' => 120,
    // 授权有效期在仅剩多少时可刷新, 单位分钟
    'auth_refresh' => 60,
    // 已过期token可在多长时间内，刷新获取新token，单位小时 （此配置会受到“token最大刷新时间”设置的限制）
    'reauth' => 168,
    // 用户模型路径
    'user' => app\api\model\Member::class,
    // 设置Authorization头 的附加码
    'token_more' => 'Bearer',
    // 是否允许使用过期的token刷新获取新的token  true允许  false不允许
    'old_token_refresh' => false,
    // 允许授权token最大刷新时间，单位小时
    // 用户通过登录获取token授权后，可使用获取的token请求刷新接口，以获取新的token授权。
    // 后台可以管理用户在一次登录后，最大可用token刷新获取新token的期限，避免token授权的无限刷新。
    'token_max_refresh' => 720,
    // 使用其他模块配置替换当前配置
    // 'replace_module'    => 'index',
];