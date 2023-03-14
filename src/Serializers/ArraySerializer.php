<?php 
namespace zmoauth2\Serializers;

use zmoauth2\Serializers\Serializer;

/**
 * @author   Chan Zewail <chanzewail@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/czewail/think-api
 */
class ArraySerializer extends Serializer
{

    protected $content;

    function __construct($content, $meta = [], $adds = [])
    {
        $this->content = $content;
        $this->setContent();
        parent::__construct($meta, $adds);
    }

    protected function setContent()
    {
        $this->data = $this->content;
    }
}