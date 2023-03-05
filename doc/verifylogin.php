<?php
// 在模块初始化时验证token
// 例如在 application/index/behavior/LoadModule.php->run() 中添加一下代码
use Zewail\Api\Facades\JWT;

try {
    $member = JWT::authTokenUser();
    if ($member) {
        $member = (object) $member;
    }
} catch (\Exception $e) {
}
if (!$member) {
    $member = function () {
                return false;
            };
}

bind('jwtMember', $member);