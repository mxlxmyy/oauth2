<?php
// 生成token
use Zewail\Api\Facades\JWT;

// 用户模型
$member = model("Member")->where("id", 1)->find();
// token包含的数据
$payload = $member->toArray();
// 设置所属模块
$payload['md'] = 'index';
// 设置账号类型
$payload['acmode'] = 0;

// 生成token
$token = JWT::fromUser($member, $payload);