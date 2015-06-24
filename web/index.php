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

use fkooman\Ini\IniReader;
use fkooman\Phubble\PhubbleService;
use fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Bearer\IntrospectionBearerValidator;
use fkooman\Phubble\PdoStorage;
use fkooman\Phubble\TemplateManager;
use fkooman\Rest\ExceptionHandler;
use fkooman\Rest\PluginRegistry;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Phubble\AclFetcher;

ExceptionHandler::register();

$iniReader = IniReader::fromFile(
    dirname(__DIR__).'/config/config.ini'
);

// STORAGE
$pdo = new PDO(
    $iniReader->v('PdoStorage', 'dsn'),
    $iniReader->v('PdoStorage', 'username', false),
    $iniReader->v('PdoStorage', 'password', false)
);
$pdoStorage = new PdoStorage($pdo);

$request = new Request($_SERVER);

$templateManager = new TemplateManager($iniReader->v('templateCache', false, null));
$templateManager->setGlobalVariables(
    array(
        'root' => $request->getUrl()->getRoot(),
        'rootUrl' => $request->getUrl()->getRootUrl(),
        'authorizationEndpoint' => $iniReader->v('Discovery', 'authorization_endpoint'),
        'tokenEndpoint' => $iniReader->v('Discovery', 'token_endpoint'),
    )
);

//$aclFile = $iniReader->v('Acl', 'aclFile');
$aclFetcher = new AclFetcher($iniReader->v('Acl', 'aclPath'));

$service = new PhubbleService($pdoStorage, $aclFetcher, $templateManager);

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
$authenticationPlugin->registerAuthenticationPlugin($indieAuth);
$authenticationPlugin->registerAuthenticationPlugin($bearerAuth);

$pluginRegistry = new PluginRegistry();
$pluginRegistry->registerDefaultPlugin($authenticationPlugin);
$service->setPluginRegistry($pluginRegistry);
$service->run($request)->send();
