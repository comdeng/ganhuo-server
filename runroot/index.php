<?php
/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/16 19:20
 * @copyright : 2016 huimang.com
 * @filesource: index.php
 */

$dir = dirname(__DIR__);

include $dir.'/vendor/autoload.php';
// Register application's root dir and namespace
\wisphp\web\Application::register($dir.'/src/', '\\com\\huimang\\ganhuo\\');
(new \wisphp\web\Application)->run();