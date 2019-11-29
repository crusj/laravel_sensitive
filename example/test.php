<?php
/**
 * author crusj
 * date   2019/11/29 11:48 下午
 */

use Crusj\Sensitive\Sensitive;
use Predis\Client;

require "../vendor/autoload.php";
$predisClient = new Client([
    'scheme' => "tcp",
    "host"   => "127.0.0.1",
    "port"   => "6379"
]);
//多敏感词分割符号
$delimiter = [',','，','+'];
$sensitiveWords=[];
$content="";

$sensitive = new Sensitive($predisClient,$sensitiveWords,$content,$delimiter);
$sensitiveWords = ["最高领导人+捌酒+装聋作哑", "烛光夜悼", "最高领导人+捌酒+真相", "最高领导人+捌酒+昭雪"];

$content = "最高领导人我不知道,装聋作哑烛光夜悼念,真相是什么，捌酒重逢";
$sensitive->setSensitiveWords($sensitiveWords);//设置敏感词
$sensitive->setContent($content);//设置过滤内容
$rsl = $sensitive->analyzeSensitiveWords();
var_dump($rsl);
/**
array (size=2)
    'single' =>
        array (size=1)
            0 => string '烛光夜悼' (length=12)
    'combine' =>
        array (size=2)
            0 => string '捌酒_最高领导人_装聋作哑' (length=35)
            1 => string '捌酒_最高领导人_真相' (length=29)
 */

