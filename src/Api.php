<?php 
namespace zmoauth2;

use zmoauth2\Response\Factory as ResponseFactory;
use zmoauth2\JWT\Factory as JWTFactory;
use think\Config;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
trait Api
{

    protected $response;
    protected $jwt;

    function __construct()
    {
        $this->init();
    }

    protected function init() {
        $this->response = new ResponseFactory;
        $this->jwt = new JWTFactory;
    }
}