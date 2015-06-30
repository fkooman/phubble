<?php

$vendorDir = '/usr/share/php';
$pearDir = '/usr/share/pear';
$baseDir = dirname(__DIR__);

require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\Rest\\Plugin\\IndieAuth\\' => $vendorDir,
        'fkooman\\Rest\\Plugin\\Bearer\\' => $vendorDir,
        'fkooman\\Rest\\' => $vendorDir,
        'fkooman\\Phubble\\' => $baseDir.'/src',
        'fkooman\\Json' => $vendorDir,
        'fkooman\\Ini' => $vendorDir,
        'fkooman\\Http\\' => $vendorDir,
        'HTMLPurifier' => $vendorDir,
        'Guzzle\\Tests' => $vendorDir,
        'Guzzle' => $vendorDir,
        'Symfony\\Component\\EventDispatcher\\' => $vendorDir,
        'GuzzleHttp\\Stream\\' => $vendorDir,
        'GuzzleHttp\\' => $vendorDir,
    )
);

$loader->registerPrefixes(array(
    'Twig_' => array($pearDir, $vendorDir),
));

$loader->register();

require_once $vendorDir.'/htmlpurifier/HTMLPurifier.composer.php';
require_once $vendorDir.'/GuzzleHttp/functions.php';
require_once $vendorDir.'/GuzzleHttp/Stream/functions.php';
