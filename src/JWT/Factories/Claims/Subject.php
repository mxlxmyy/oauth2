<?php
namespace zmoauth2\JWT\Factories\Claims;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class Subject extends Claim
{
    /**
     * Name
     */
    protected $name = 'sub';
}
