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
use fkooman\MessageBoard\MessageBoardService;
use fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Bearer\IntrospectionBearerValidator;
use fkooman\MessageBoard\PdoStorage;
use fkooman\MessageBoard\TemplateManager;
use fkooman\Rest\ExceptionHandler;
use fkooman\Rest\PluginRegistry;

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

$webmentionEndpoint = $iniReader->v('webmentionEndpoint', false, false);

$templateManager = new TemplateManager($iniReader->v('templateCache', false, null));
$templateManager->setGlobalVariables(
    array(
        'webmentionEndpoint' => $webmentionEndpoint
    )
);

$service = new MessageBoardService($pdoStorage, $templateManager);

$bearerAuth = new BearerAuthentication(
    new IntrospectionBearerValidator(
        $iniReader->v('Introspection', 'endpoint'),
        $iniReader->v('Introspection', 'secret')
    ),
    'Phubble'
);

$pluginRegistry = new PluginRegistry();
$pluginRegistry->registerDefaultPlugin(
   new IndieAuthAuthentication()
);
$pluginRegistry->registerOptionalPlugin($bearerAuth);
$service->setPluginRegistry($pluginRegistry);

$response = $service->run();

// add Webmention response header if webmentionEndpoint is set in config
$webmentionEndpoint = $iniReader->v('webmentionEndpoint', false, false);
if (false !== $webmentionEndpoint) {
    $response->setHeader(
        'Link',
        sprintf(
            '<%s>; rel="webmention"',
            $webmentionEndpoint
        )
    );
}
$response->send();
