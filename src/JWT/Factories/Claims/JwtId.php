<?php
namespace zmoauth2\JWT\Factories\Claims;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class JwtId extends Claim
{
    /**
     * Name
     */
    protected $name = 'jti';
}
