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
namespace fkooman\Phubble;

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
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Json\Json;

class PhubbleService extends Service
{
    /** @var fkooman\Phubble\PdoStorage */
    private $db;

    /** @var string */
    private $aclFile;

    /** @var GuzzleHttp\Client */
    private $client;

    /** @var fkooman\IndieCert\IO */
    private $io;

    /** @var fkooman\IndieCert\TemplateManager */
    private $templateManager;

    public function __construct(PdoStorage $db, $aclFile, TemplateManager $templateManager = null, Client $client = null, IO $io = null)
    {
        parent::__construct();

        $this->db = $db;
        $this->aclFile = $aclFile;

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
                return $this->getIndex($request, $indieInfo);
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
                return $this->addSpace($request, $indieInfo);
            }
        );

        $this->get(
            '/_public/',
            function (Request $request, IndieInfo $indieInfo = null) {
                return $this->getPublicSpaces($request, $indieInfo);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/_sign_in',
            function (Request $request, IndieInfo $indieInfo = null) {
                return $this->getSignInPage($request, $indieInfo);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/_my_spaces/',
            function (Request $request, IndieInfo $indieInfo) {
                return $this->getMySpaces($request, $indieInfo);
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
            '/:space/_edit',
            function (Request $request, IndieInfo $indieInfo, $space) {
                return $this->getEditSpace($request, $indieInfo, $space);
            }
        );

        $this->post(
            '/:space/_edit',
            function (Request $request, IndieInfo $indieInfo, $space) {
                return $this->postEditSpace($request, $indieInfo, $space);
            }
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
            '/:space/_micropub',
            function (Request $request, TokenInfo $tokenInfo, $space) {
                return $this->micropubMessage($request, $tokenInfo, $space);
            },
            array(
                'fkooman\Rest\Plugin\IndieAuth\IndieAuthAuthentication' => array('enabled' => false),
                'fkooman\Rest\Plugin\Bearer\BearerAuthentication' => array('enabled' => true),
            )
        );
    }

    public function addSpace(Request $request, $indieInfo)
    {
        $id = $request->getPostParameter('space');
        $owner = $indieInfo->getUserId();
        $space = new Space($id, $owner, false);
        $this->db->addSpace($space);

        return new RedirectResponse($request->getUrl()->getRootUrl().$id.'/', 302);
    }

    public function getEditSpace(Request $request, IndieInfo $indieInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $userId = $indieInfo->getUserId();

        if ($space->getOwner() !== $userId) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        return $this->templateManager->render(
            'editSpacePage',
            array(
                'space' => $space,
                'indieInfo' => $indieInfo,
            )
        );
    }

    public function postEditSpace(Request $request, IndieInfo $indieInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $userId = $indieInfo->getUserId();

        if ($space->getOwner() !== $userId) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        $space->setOwner($request->getPostParameter('owner'));
        $space->setSecret('on' === $request->getPostParameter('secret') ? true : false);

        $this->db->updateSpace($space);

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getId().'/', 302);
    }

    public function getPublicSpaces(Request $request, $indieInfo)
    {
        $publicSpaces = $this->db->getPublicSpaces();

        return $this->templateManager->render(
            'publicSpacesPage',
            array(
                'publicSpaces' => $publicSpaces,
                'indieInfo' => $indieInfo,
            )
        );
    }

    public function getSignInPage(Request $request, $indieInfo)
    {
        return $this->templateManager->render(
            'signInPage',
            array(
                'indieInfo' => $indieInfo,
            )
        );
    }

    public function getMySpaces(Request $request, $indieInfo)
    {
        $publicSpaces = $this->db->getPublicSpaces();
        $secretSpaces = $this->db->getSecretSpaces();

        $mySpaces = array();
        $memberSpaces = array();

        foreach ($publicSpaces as $s) {
            if ($indieInfo->getUserId() === $s->getOwner()) {
                $mySpaces[] = $s;
            } else {
                // are we a member?
                $spaceAcl = $this->getSpaceAcl($s);
                if (in_array($indieInfo->getUserId(), $spaceAcl)) {
                    $memberSpaces[] = $s;
                }
            }
        }
        foreach ($secretSpaces as $s) {
            if ($indieInfo->getUserId() === $s->getOwner()) {
                $mySpaces[] = $s;
            } else {
                // are we a member?
                $spaceAcl = $this->getSpaceAcl($s);
                if (in_array($indieInfo->getUserId(), $spaceAcl)) {
                    $memberSpaces[] = $s;
                }
            }
        }

        return $this->templateManager->render(
            'mySpacesPage',
            array(
                'memberSpaces' => $memberSpaces,
                'mySpaces' => $mySpaces,
                'indieInfo' => $indieInfo,
            )
        );
    }

    public function getIndex(Request $request, $indieInfo)
    {
        return $this->templateManager->render(
            'indexPage',
            array(
                'indieInfo' => $indieInfo,
            )
        );
    }

    public function getMessages(Request $request, $indieInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $messages = $this->db->getMessages($space);
        $canPost = false;
        if (null !== $indieInfo) {
            $spaceAcl = $this->getSpaceAcl($space);
            if (in_array($indieInfo->getUserId(), $spaceAcl)) {
                $canPost = true;
            }
        }

        if ($space->getSecret() && !$canPost) {
            throw new ForbiddenException('no permission to access this space');
        }

        return $this->templateManager->render(
            'messagesPage',
            array(
                'indieInfo' => $indieInfo,
                'space' => $space,
                'messages' => $messages,
                'canPost' => $canPost,
            )
        );
    }

    public function getMessage(Request $request, $indieInfo, $spaceId, $id)
    {
        $space = $this->db->getSpace($spaceId);
        $message = $this->db->getMessage($space, $id);

        $canPost = false;
        if (null !== $indieInfo) {
            $spaceAcl = $this->getSpaceAcl($space);
            if (in_array($indieInfo->getUserId(), $spaceAcl)) {
                $canPost = true;
            }
        }

        if ($space->getSecret() && !$canPost) {
            throw new ForbiddenException('no permission to access this post');
        }

        $response = new Response();
        $response->setBody(
            $this->templateManager->render(
                'messagePage',
                array(
                    'space' => $space,
                    'message' => $message,
                    'indieInfo' => $indieInfo,
                )
            )
        );

        return $response;
    }

    public function deleteMessage(Request $request, IndieInfo $indieInfo, $spaceId, $id)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $indieInfo->getUserId();

        if (!in_array($userId, $spaceAcl)) {
            throw new ForbiddenException('not allowed to delete this message');
        }

        $message = $this->db->getMessage($space, $id);
        if ($message->getAuthorId() !== $userId) {
            throw new ForbiddenException('not allowed to delete this message');
        }

        $this->db->deleteMessage($message);

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getId().'/', 302);
    }

    public function postMessage(Request $request, IndieInfo $indieInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $indieInfo->getUserId();

        if (!in_array($userId, $spaceAcl)) {
            throw new ForbiddenException('not allowed to delete this message');
        }

        $id = $this->io->getRandomHex();
        $postTime = $this->io->getTime();
        $messageBody = $this->validateMessageBody($request->getPostParameter('message_body'));

        $message = new Message($space, $id, $userId, $messageBody, $postTime);
        $this->db->addMessage($message);

        return new RedirectResponse($request->getUrl()->toString(), 302);
    }

    public function micropubMessage(Request $request, TokenInfo $tokenInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $tokenInfo->get('sub');

        if (!in_array($userId, $spaceAcl)) {
            throw new ForbiddenException('user not allowed to post in this space');
        }

        $id = $this->io->getRandomHex();
        $messageBody = $this->validateMessageBody($request->getPostParameter('content'));
        $postTime = $this->io->getTime();

        $message = new Message($space, $id, $userId, $messageBody, $postTime);

        $this->db->addMessage($message);

        $response = new Response(201);
        $response->setHeader('Location', $request->getUrl()->getRootUrl().$space->getId().'/'.$message->getId());

        return $response;
    }

    private function getSpaceAcl(Space $space)
    {
        $spaceAcl = array();
        $aclData = Json::decodeFile($this->aclFile);
        if (array_key_exists($space->getId(), $aclData)) {
            $spaceAcl = $aclData[$space->getId()];
        }

        // add the owner to it as well
        $spaceAcl[] = $space->getOwner();

        return $spaceAcl;
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
