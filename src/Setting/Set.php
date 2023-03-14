<?php 
namespace zmoauth2\Setting;

use Config;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class Set
{
    protected static $files = [
        'resources' => 'resources.php',
        'api' => 'api.php',
        'jwt' => 'jwt.php',
    ];

    public static function __callStatic($func, $args)
    {
        //获取网站“config”目录下的配置
        $config = config($func . '.');
        //是否设置了使用其他模块配置替换当前模块配置
        //例如，要在admin模块验证index模块的token，则通过 new Factory('index'); 实现在admin模块加载index模块配置。
        if (!empty($args[1])) {
            $config['replace_module'] = $args[1];
        }
        if (!empty($config['replace_module'])) {
            // config 文件夹下配置
            $configFile = env("root_path") . 'config' . DIRECTORY_SEPARATOR . $config['replace_module'] . DIRECTORY_SEPARATOR . $func . '.php';
            is_file($configFile) && Config::load($configFile, $func);

            // data/config 文件夹下配置
            $configFile = env("root_path") . 'data' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $config['replace_module'] . DIRECTORY_SEPARATOR . $func . '.php';
            is_file($configFile) && Config::load($configFile, $func);
            $config = config($func . '.');
        }

        $path = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        if ($file = self::$files[$func]) {
            $config = $config ?: require($path . $file);
            $_config = Config::pull($func);
            if ($_config && is_array($_config)) {
                $config = array_merge($config, $_config);
            }
            call_user_func_array($args[0], [$config]);
        }
    }
}