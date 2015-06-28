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
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use GuzzleHttp\Client;
use HTMLPurifier;
use HTMLPurifier_Config;
use fkooman\Http\Exception\ForbiddenException;

class PhubbleService extends Service
{
    /** @var PdoStorage */
    private $db;

    /** @var AclFetcher */
    private $aclFetcher;

    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var IO */
    private $io;

    /** @var TemplateManager */
    private $templateManager;

    public function __construct(PdoStorage $db, AclFetcher $aclFetcher, TemplateManager $templateManager = null, Client $client = null, IO $io = null)
    {
        parent::__construct();

        $this->db = $db;
        $this->aclFetcher = $aclFetcher;

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
            function (Request $request, UserInfoInterface $userInfo = null) {
                return $this->getIndex($request, $userInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/faq',
            function (Request $request, UserInfoInterface $userInfo = null) {
                return $this->getFaq($request, $userInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->post(
            '/',
            function (Request $request, UserInfoInterface $userInfo) {
                return $this->addSpace($request, $userInfo);
            }
        );

        $this->get(
            '/_public/',
            function (Request $request, UserInfoInterface $userInfo = null) {
                return $this->getPublicSpaces($request, $userInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/_sign_in',
            function (Request $request, UserInfoInterface $userInfo = null) {
                return $this->getSignInPage($request, $userInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/_my_spaces/',
            function (Request $request, UserInfoInterface $userInfo) {
                return $this->getMySpaces($request, $userInfo);
            }
        );

        $this->get(
            '/:space/',
            function (Request $request, UserInfoInterface $userInfo = null, $space) {
                return $this->getMessages($request, $userInfo, $space);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->get(
            '/:space/_edit',
            function (Request $request, UserInfoInterface $userInfo, $space) {
                return $this->getEditSpace($request, $userInfo, $space);
            }
        );

        $this->post(
            '/:space/_edit',
            function (Request $request, UserInfoInterface $userInfo, $space) {
                return $this->postEditSpace($request, $userInfo, $space);
            }
        );

        $this->get(
            '/:space/:id',
            function (Request $request, UserInfoInterface $userInfo = null, $space, $id) {
                return $this->getMessage($request, $userInfo, $space, $id);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'requireAuth' => false,
                ),
            )
        );

        $this->delete(
            '/:space/:id',
            function (Request $request, UserInfoInterface $userInfo, $space, $id) {
                return $this->deleteMessage($request, $userInfo, $space, $id);
            }
        );

        $this->post(
            '/:space/',
            function (Request $request, UserInfoInterface $userInfo, $space) {
                return $this->postMessage($request, $userInfo, $space);
            }
        );

        // Both IndieAuth and Bearer are allowed here... is that a good idea?
        $this->post(
            '/:space/_micropub',
            function (Request $request, UserInfoInterface $userInfo, $space) {
                return $this->micropubMessage($request, $userInfo, $space);
            }
        );

        $this->options(
            '/:space/',
            function (Request $request, $space) {
                return $this->optionsSpace($request, $space);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'enabled' => false,
                ),
            )
        );

        $this->options(
            '/:space/_micropub',
            function (Request $request, $space) {
                return $this->optionsMicropub($request, $space);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'enabled' => false,
                ),
            )
        );
    }

    public function optionsMicropub(Request $request, $space)
    {
        #        $origin = $request->getHeader('Origin');
#        if(null === $origin) {
#            $origin = '*';
#        }
        $response = new Response();
        $response->setHeader('Access-Control-Allow-Methods', 'POST');
        $response->setHeader('Access-Control-Allow-Headers', 'Authorization');
        $response->setHeader('Access-Control-Expose-Headers', 'Location');

        // allow the location header to be passed to the browser
        //$response->setHeader('XXX-Headers', 'Location');
#        $response->setHeader('Access-Control-Allow-Origin', $origin);

        return $response;
    }

    public function optionsSpace(Request $request, $space)
    {
        #        $origin = $request->getHeader('Origin');
#        if(null === $origin) {
#            $origin = '*';
#        }
        $response = new Response();
        $response->setHeader('Access-Control-Allow-Methods', 'GET');
        $response->setHeader('Access-Control-Allow-Headers', 'Authorization');
        //$response->setHeader('Access-Control-Expose-Headers', 'Location');

        // allow the location header to be passed to the browser
        //$response->setHeader('XXX-Headers', 'Location');
#        $response->setHeader('Access-Control-Allow-Origin', $origin);

        return $response;
    }

    public function addSpace(Request $request, $userInfo)
    {
        $id = $request->getPostParameter('space');
        $owner = $userInfo->getUserId();
        $acl = null;    // no ACL by default
        $space = new Space($id, $owner, $acl, false);
        $this->db->addSpace($space);

        return new RedirectResponse($request->getUrl()->getRootUrl().$id.'/', 302);
    }

    public function getEditSpace(Request $request, UserInfoInterface $userInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $userId = $userInfo->getUserId();
        $spaceAcl = $this->getSpaceAcl($space);

        if ($space->getOwner() !== $userId) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        return $this->templateManager->render(
            'editSpacePage',
            array(
                'space' => $space,
                'indieInfo' => $userInfo,
                'members' => $spaceAcl,
            )
        );
    }

    public function postEditSpace(Request $request, UserInfoInterface $userInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $userId = $userInfo->getUserId();

        if ($space->getOwner() !== $userId) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        $space->setOwner($request->getPostParameter('owner'));
        $space->setAcl($request->getPostParameter('acl'));
        $space->setSecret('on' === $request->getPostParameter('secret') ? true : false);

        $this->db->updateSpace($space);

        // retrieve/update the ACL for this particular space
        $aclUrl = $space->getAcl();
        if (null !== $aclUrl) {
            $this->aclFetcher->fetchAcl($aclUrl);
        }

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getId().'/', 302);
    }

    public function getPublicSpaces(Request $request, $userInfo)
    {
        $publicSpaces = $this->db->getPublicSpaces();

        return $this->templateManager->render(
            'publicSpacesPage',
            array(
                'publicSpaces' => $publicSpaces,
                'indieInfo' => $userInfo,
            )
        );
    }

    public function getSignInPage(Request $request, $userInfo)
    {
        return $this->templateManager->render(
            'signInPage',
            array(
                'indieInfo' => $userInfo,
            )
        );
    }

    public function getMySpaces(Request $request, $userInfo)
    {
        $publicSpaces = $this->db->getPublicSpaces();
        $secretSpaces = $this->db->getSecretSpaces();

        $mySpaces = array();
        $memberSpaces = array();

        foreach ($publicSpaces as $s) {
            if ($userInfo->getUserId() === $s->getOwner()) {
                $mySpaces[] = $s;
            } else {
                // are we a member?
                $spaceAcl = $this->getSpaceAcl($s);
                if (in_array($userInfo->getUserId(), $spaceAcl)) {
                    $memberSpaces[] = $s;
                }
            }
        }
        foreach ($secretSpaces as $s) {
            if ($userInfo->getUserId() === $s->getOwner()) {
                $mySpaces[] = $s;
            } else {
                // are we a member?
                $spaceAcl = $this->getSpaceAcl($s);
                if (in_array($userInfo->getUserId(), $spaceAcl)) {
                    $memberSpaces[] = $s;
                }
            }
        }

        return $this->templateManager->render(
            'mySpacesPage',
            array(
                'memberSpaces' => $memberSpaces,
                'mySpaces' => $mySpaces,
                'indieInfo' => $userInfo,
            )
        );
    }

    public function getIndex(Request $request, $userInfo)
    {
        return $this->templateManager->render(
            'indexPage',
            array(
                'indieInfo' => $userInfo,
            )
        );
    }

    public function getFaq(Request $request, $userInfo)
    {
        return $this->templateManager->render(
            'faqPage',
            array(
                'indieInfo' => $userInfo,
            )
        );
    }

    public function getMessages(Request $request, $userInfo, $spaceId)
    {
        // FIXME: should throw UnauthorizedException for secret space and no
        // authentication...

        $space = $this->db->getSpace($spaceId);
        $messages = $this->db->getMessages($space);
        $canPost = false;
        if (null !== $userInfo) {
            $spaceAcl = $this->getSpaceAcl($space);
            if (in_array($userInfo->getUserId(), $spaceAcl)) {
                $canPost = true;
            }
        }

        if ($space->getSecret() && !$canPost) {
            throw new ForbiddenException('no permission to access this space');
        }

        $response = new Response();
        $response->setBody(
            $this->templateManager->render(
                'messagesPage',
                array(
                    'indieInfo' => $userInfo,
                    'space' => $space,
                    'messages' => $messages,
                    'canPost' => $canPost,
                )
            )
        );
        $response->setHeader('Link', sprintf('<%s>; rel="micropub"', $request->getUrl()->getRootUrl().$space->getId().'/_micropub'));

        return $response;
    }

    public function getMessage(Request $request, $userInfo, $spaceId, $id)
    {
        // FIXME: should throw UnauthorizedException for secret space and no
        // authentication...

        $space = $this->db->getSpace($spaceId);
        $message = $this->db->getMessage($space, $id);

        $canPost = false;
        if (null !== $userInfo) {
            $spaceAcl = $this->getSpaceAcl($space);
            if (in_array($userInfo->getUserId(), $spaceAcl)) {
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
                    'indieInfo' => $userInfo,
                )
            )
        );

        return $response;
    }

    public function deleteMessage(Request $request, UserInfoInterface $userInfo, $spaceId, $id)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $userInfo->getUserId();

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

    public function postMessage(Request $request, UserInfoInterface $userInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $userInfo->getUserId();

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

    public function micropubMessage(Request $request, UserInfoInterface $userInfo, $spaceId)
    {
        $space = $this->db->getSpace($spaceId);
        $spaceAcl = $this->getSpaceAcl($space);
        $userId = $userInfo->getUserId();

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
        $spaceAcl = array(
            $space->getOwner(),
        );

        if (null === $space->getAcl()) {
            // this space has no ACL defined
            return $spaceAcl;
        }

        $aclData = $this->aclFetcher->getAcl($space->getAcl());

        return array_values(array_merge($spaceAcl, $aclData['members']));
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
