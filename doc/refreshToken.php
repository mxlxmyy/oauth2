<?php
// 刷新token授权
use Zewail\Api\Facades\JWT;

// 验证token是否有效
$payload = JWT::verifyReAuth();

// 验证是否可刷新
if (JWT::vMemberLoginTime($payload->id, 1) == false) {
    exception(return_msg(2031));
}

// 用户模型
$member = model("Member")->where("id", $payload->id)->find();
// token包含的数据
$payload = $member->toArray();
// 设置所属模块
$payload['md'] = 'index';
// 设置账号类型
$payload['acmode'] = 0;

// 生成token
$token = JWT::fromUser($member, $payload);