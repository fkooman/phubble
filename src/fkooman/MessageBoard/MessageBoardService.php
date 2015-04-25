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

namespace fkooman\MessageBoard;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use GuzzleHttp\Client;
use fkooman\Http\RedirectResponse;
use fkooman\Rest\Plugin\UserInfo;
use InvalidArgumentException;
use HTMLPurifier;
use HTMLPurifier_Config;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\UnauthorizedException;
use fkooman\Http\Response;

class MessageBoardService extends Service
{
    /** @var fkooman\RelMeAuth\PdoStorage */
    private $pdoStorage;

    /** @var GuzzleHttp\Client */
    private $client;

    /** @var fkooman\IndieCert\IO */
    private $io;

    /** @var fkooman\IndieCert\TemplateManager */
    private $templateManager;

    public function __construct(PdoStorage $pdoStorage, TemplateManager $templateManager = null, Client $client = null, IO $io = null)
    {
        parent::__construct();

        $this->pdoStorage = $pdoStorage;

        if (null === $templateManager) {
            $templateManager = new TemplateManager();
        }
        $this->templateManager = $templateManager;

        if (null === $client) {
            $client = new Client();
        }
        $this->client = $client;
   
        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;
    
        $this->get(
            '/',
            function (Request $request, UserInfo $userInfo = null) {
                return $this->getMessages($request, $userInfo);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false
                )
            )
        );

        $this->get(
            '/:id',
            function (Request $request, UserInfo $userInfo = null, $id) {
                return $this->getMessage($request, $userInfo, $id);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false
                )
            )
        );

        $this->delete(
            '/:id',
            function (Request $request, UserInfo $userInfo, $id) {
                return $this->deleteMessage($request, $userInfo, $id);
            }
        );

        $this->post(
            '/',
            function (Request $request, UserInfo $userInfo) {
                return $this->postMessage($request, $userInfo);
            }
        );

        $this->post(
            '/micropub/',
            function (Request $request) {
                return $this->micropubMessage($request);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false
                )
            )
        );
    }

    public function getMessages(Request $request, $userInfo)
    {
        $page = $request->getQueryParameter('p');

        $messages = $this->pdoStorage->getMessages($page);
        $actualCount = count($messages);

        $userId = null !== $userInfo ? $userInfo->getUserId() : null;

        return $this->templateManager->render(
            'messagesPage',
            array(
                'page' => $page,
                'messages' => $messages,
                'has_prev' => $page > 0,
                'has_next' => $actualCount === 5,
                'user_id' => $userId
            )
        );
    }

    public function getMessage(Request $request, $userInfo, $id)
    {
        // FIXME: validate $id!
        $message = $this->pdoStorage->getMessage($id);
        $userId = null !== $userInfo ? $userInfo->getUserId() : null;

        return $this->templateManager->render(
            'messagePage',
            array(
                'message' => $message,
                'user_id' => $userId
            )
        );
    }

    public function deleteMessage(Request $request, UserInfo $userInfo, $id)
    {
        // FIXME: validate $id!
        $message = $this->pdoStorage->deleteMessage($id);
        
        // FIXME: check if userid owns the post!

        return new RedirectResponse($request->getAbsRoot(), 302);
    }

    public function postMessage(Request $request, UserInfo $userInfo)
    {
        $authorId = $userInfo->getUserId();
        $messageBody = $this->validateMessageBody($request->getPostParameter('message_body'));
        $postTime = $this->io->getTime();
        $messageId = $this->io->getRandomHex();

        $this->pdoStorage->storeMessage($messageId, $authorId, $messageBody, $postTime);

        return new RedirectResponse($request->getRequestUri()->getUri(), 302);
    }

    public function micropubMessage(Request $request)
    {
        $authHeader = $request->getHeader('Authorization');

        // validate the token at the endpoint
        $endpointUri = 'https://indiecert.net/token';
        
        $response = $this->client->get(
            $endpointUri,
            array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Authorization' => $authHeader
                )
            )
        );

        $jsonData = $response->json();

        $authorId = $jsonData['me'];
        $messageBody = $this->validateMessageBody($request->getPostParameter('content'));
        $postTime = $this->io->getTime();
        $messageId = $this->io->getRandomHex();

        $this->pdoStorage->storeMessage($messageId, $authorId, $messageBody, $postTime);
        
        $response = new Response(201);
        $response->setHeader('Location', $request->getAbsRoot() . $messageId);
        return $response;
    }

    private function validateMessageBody($messageBody)
    {
        if (!is_string($messageBody)) {
            throw new BadRequestException('message must be string');
        }
        if (0 >= strlen($messageBody)) {
            throw new BadRequestException('message body cannot be empty');
        }
        if (255 < strlen($messageBody)) {
            throw new BadRequestException('message body can only contain 255 characters');
        }
    
        // purify the input
        $c = HTMLPurifier_Config::createDefault();
        $c->set('Cache.DefinitionImpl', null);
        $p = new HTMLPurifier($c);

        $purifiedBody = $p->purify($messageBody);
        if (0 >= strlen($purifiedBody)) {
            throw new BadRequestException('message body cannot be empty');
        }

        return $purifiedBody;
    }
}