<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Http\Request;
use fkooman\Ini\IniReader;
use fkooman\Phubble\PhubbleService;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\IntrospectionBearerValidator;
use fkooman\Rest\Plugin\Authentication\IndieAuth\IndieAuthAuthentication;
use fkooman\Tpl\TwigTemplateManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$iniReader = IniReader::fromFile(
    dirname(__DIR__).'/config/phubble.ini'
);

// STORAGE
$databaseUrl = $iniReader->v('Database', 'url');

$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(
    array(
        dirname(__DIR__).'/src',
    ),
    $isDevMode
);
$entityManager = EntityManager::create(
    array(
        'url' => $databaseUrl,
    ),
    $config
);

$request = new Request($_SERVER);

$templateManager = new TwigTemplateManager(
    array(
        dirname(__DIR__).'/views',
        dirname(__DIR__).'/config/views',
    ),
    $iniReader->v('templateCache', false, null)
);

$templateManager->setDefault(
    array(
        'root' => $request->getUrl()->getRoot(),
        'rootUrl' => $request->getUrl()->getRootUrl(),
        'authorizationEndpoint' => $iniReader->v('Discovery', 'authorization_endpoint'),
        'tokenEndpoint' => $iniReader->v('Discovery', 'token_endpoint'),
    )
);

$service = new PhubbleService($entityManager, $templateManager);

$bearerAuth = new BearerAuthentication(
    new IntrospectionBearerValidator(
        $iniReader->v('Introspection', 'endpoint'),
        $iniReader->v('Introspection', 'secret')
    ),
    array(
        'realm' => 'Phubble',
        'scope' => 'https://micropub.net/scope#create',
        'authorization_endpoint' => $iniReader->v('Discovery', 'authorization_endpoint'),
        'token_endpoint' => $iniReader->v('Discovery', 'token_endpoint'),
    )
);
$indieAuth = new IndieAuthAuthentication(null, array('realm' => 'Phubble'));

$authenticationPlugin = new AuthenticationPlugin();
$authenticationPlugin->register($indieAuth, 'user');
$authenticationPlugin->register($bearerAuth, 'micropub');
$service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
$service->run($request)->send();
