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

require_once 'vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\MessageBoard\MessageBoardService;
use fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Bearer\IntrospectionBearerValidator;
use fkooman\MessageBoard\PdoStorage;
use fkooman\MessageBoard\TemplateManager;

try {
    $iniReader = IniReader::fromFile(
        'config/config.ini'
    );

    // STORAGE
    $pdo = new PDO(
        $iniReader->v('PdoStorage', 'dsn'),
        $iniReader->v('PdoStorage', 'username', false),
        $iniReader->v('PdoStorage', 'password', false)
    );
    $pdoStorage = new PdoStorage($pdo);

    $templateManager = new TemplateManager($iniReader->v('templateCache', false, null));

    $service = new MessageBoardService($pdoStorage, $templateManager);
    $service->registerOnMatchPlugin(
        new IndieAuthAuthentication()
    );
    $service->registerOnMatchPlugin(
        new BearerAuthentication(
            new IntrospectionBearerValidator(
                $iniReader->v('Introspection', 'endpoint'),
                $iniReader->v('Introspection', 'secret')
            ),
            'Phubble'
        ),
        array('defaultDisable' => true)
    );
    $service->run()->sendResponse();
} catch (Exception $e) {
    error_log(
        $e->getMessage()
    );
    MessageBoardService::handleException($e)->sendResponse();
}
