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

use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Rest\Plugin\Bearer\TokenInfo;
use fkooman\Rest\Plugin\IndieAuth\IndieInfo;
use fkooman\Rest\Service;
use GuzzleHttp\Client;
use HTMLPurifier;
use HTMLPurifier_Config;

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
            function (Request $request, IndieInfo $indieInfo = null) {
                return $this->getSpaces($request, $indieInfo);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->post(
            '/',
            function (Request $request, IndieInfo $indieInfo) {
                return $this->postSpace($request, $indieInfo);
            }
        );

        $this->get(
            '/:space/',
            function (Request $request, IndieInfo $indieInfo = null, $space) {
                return $this->getMessages($request, $indieInfo, $space);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/:space/:id',
            function (Request $request, IndieInfo $indieInfo = null, $space, $id) {
                return $this->getMessage($request, $indieInfo, $space, $id);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->delete(
            '/:space/:id',
            function (Request $request, IndieInfo $indieInfo, $space, $id) {
                return $this->deleteMessage($request, $indieInfo, $space, $id);
            }
        );

        $this->post(
            '/:space/',
            function (Request $request, IndieInfo $indieInfo, $space) {
                return $this->postMessage($request, $indieInfo, $space);
            }
        );

        $this->post(
            '/:space/micropub',
            function (Request $request, TokenInfo $tokenInfo, $space) {
                return $this->micropubMessage($request, $tokenInfo, $space);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array('enabled' => false),
                'fkooman\Rest\Plugin\Bearer\BearerAuthentication' => array('enabled' => true),
            )
        );
    }

    public function postSpace(Request $request, $indieInfo)
    {
        $spaceName = $request->getPostParameter('space');

        $this->pdoStorage->createSpace($spaceName, $indieInfo->getUserId());

        return new RedirectResponse($request->getUrl()->getRootUrl().$spaceName.'/', 302);
    }

    public function getSpaces(Request $request, $indieInfo)
    {
        $userId = null !== $indieInfo ? $indieInfo->getUserId() : null;
        $spaces = $this->pdoStorage->getSpaces();

        return $this->templateManager->render(
            'spacesPage',
            array(
                'spaces' => $spaces,
                'user_id' => $userId,
            )
        );
    }

    public function getMessages(Request $request, $indieInfo, $space)
    {
        $page = $request->getUrl()->getQueryParameter('p');

        $messages = $this->pdoStorage->getMessages($space, $page);
        $actualCount = count($messages);

        $userId = null !== $indieInfo ? $indieInfo->getUserId() : null;

        return $this->templateManager->render(
            'messagesPage',
            array(
                'space' => $space,
                'page' => $page,
                'messages' => $messages,
                'has_prev' => $page > 0,
                'has_next' => $actualCount === 50,
                'user_id' => $userId,
            )
        );
    }

    public function getMessage(Request $request, $indieInfo, $space, $id)
    {
        // FIXME: validate $id!
        $message = $this->pdoStorage->getMessage($space, $id);
        $mentions = $this->pdoStorage->getMentions($space, $id);
        $userId = null !== $indieInfo ? $indieInfo->getUserId() : null;

        return $this->templateManager->render(
            'messagePage',
            array(
                'space' => $space,
                'message' => $message,
                'mentions' => $mentions,
                'user_id' => $userId,
            )
        );
    }

    public function deleteMessage(Request $request, IndieInfo $indieInfo, $space, $id)
    {
        // FIXME: validate $id!
        $message = $this->pdoStorage->deleteMessage($space, $id);

        // FIXME: check if userid owns the post!

        return new RedirectResponse($request->getUrl()->getRootUrl().$space.'/', 302);
    }

    public function postMessage(Request $request, IndieInfo $indieInfo, $space)
    {
        // FIXME: validate space
        $authorId = $indieInfo->getUserId();
        $messageBody = $this->validateMessageBody($request->getPostParameter('message_body'));

        $postTime = $this->io->getTime();
        $messageId = $this->io->getRandomHex();

        $this->pdoStorage->storeMessage($space, $messageId, $authorId, $messageBody, $postTime);

        $messageUrls = $this->extractUrls($messageBody);
        $source = $request->getUrl()->getRootUrl().$space.'/'.$messageId;
        foreach ($messageUrls as $u) {
            $this->sendWebmention($source, $u);
        }

        return new RedirectResponse($request->getUrl()->toString(), 302);
    }

    public function micropubMessage(Request $request, TokenInfo $tokenInfo, $space)
    {
        $authorId = $tokenInfo->get('sub');
        $messageBody = $this->validateMessageBody($request->getPostParameter('content'));
        $postTime = $this->io->getTime();
        $messageId = $this->io->getRandomHex();

        $this->pdoStorage->storeMessage($space, $messageId, $authorId, $messageBody, $postTime);

        $response = new Response(201);
        $response->setHeader('Location', $request->getUrl()->getRootUrl().$space.'/'.$messageId);

        return $response;
    }

    private function extractUrls($messageBody)
    {
        // find all {a,link} href on a page and send webmentions to them if
        // the URL is absolute
        // parse DOM, do not parse plain text, too complicated
        return array();
    }

    private function sendWebmention($source, $target)
    {
        // send a webmention to the target that we mentioned them
        // check the link header, or the page content for the webmention
        // endpoint, mention it...
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
