<?php

$vendorDir = '/usr/share/php';
$pearDir = '/usr/share/pear';
$baseDir = dirname(__DIR__);

require_once $vendorDir.'/htmlpurifier/HTMLPurifier.composer.php';
require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\Rest' => $vendorDir,
        'fkooman\\Phubble' => $baseDir.'/src',
        'fkooman\\Json' => $vendorDir,
    'fkooman\\Tpl' => $vendorDir,
        'fkooman\\Ini' => $vendorDir,
        'fkooman\\Http' => $vendorDir,
        'GuzzleHttp\\Stream' => $vendorDir,
        'GuzzleHttp' => $vendorDir,
        'React\\Promise' => $vendorDir,
    )
);

$loader->registerPrefixes(array(
    'Twig_' => array($pearDir, $vendorDir),
    'HTMLPurifier' => $vendorDir.'/htmlpurifier',
));

$loader->register();

require_once $vendorDir.'/React/Promise/functions_include.php';
