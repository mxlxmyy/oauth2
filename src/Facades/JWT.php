<?php
namespace zmoauth2\Facades;

use think\Facade;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class JWT extends Facade
{
    protected static function getFacadeClass()
    {
        return 'zmoauth2\JWT\Factory';
    }
}