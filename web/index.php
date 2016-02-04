<?php
session_start();
//declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

$site_path = realpath(dirname(__FILE__));

define ('__SITE_PATH', $site_path);

if(!file_exists(__SITE_PATH . DIRECTORY_SEPARATOR.'conf'.DIRECTORY_SEPARATOR.'config.php'))
{
    die('Config file doesn\'t exist');
}

include 'includes'.DIRECTORY_SEPARATOR.'init.php';

$registry->router = new router($registry);

$registry->router->setPath(__SITE_PATH.DIRECTORY_SEPARATOR.'controller');

$registry->template = new template($registry);

$registry->router->loader();
?>