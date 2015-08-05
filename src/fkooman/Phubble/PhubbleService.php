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

use DateTime;
use Doctrine\ORM\EntityManager;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\IO\IO;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Tpl\TemplateManagerInterface;
use GuzzleHttp\Client;
use HTMLPurifier;
use HTMLPurifier_Config;

class PhubbleService extends Service
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var \fkooman\IO\IO */
    private $io;

    /** @var \fkooman\Tpl\TemplateManagerInterface */
    private $templateManager;

    public function __construct(EntityManager $entityManager, TemplateManagerInterface $templateManager, Client $client = null, IO $io = null)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
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
                    'require' => false,
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
                    'require' => false,
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
                    'require' => false,
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
                    'require' => false,
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
                    'require' => false,
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
                    'require' => false,
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

        $this->post(
            '/:space/members/',
            function (Request $request, UserInfoInterface $userInfo, $space) {
                return $this->addMember($request, $userInfo, $space);
            }
        );

        $this->delete(
            '/:space/members/:id',
            function (Request $request, UserInfoInterface $userInfo, $space, $id) {
                return $this->deleteMember($request, $userInfo, $space, $id);
            }
        );

        // XXX Both IndieAuth and Bearer are allowed here... is that a good idea?
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

    public function addSpace(Request $request, $userInfo)
    {
        // XXX validate space_name
        $spaceName = $request->getPostParameter('space_name');

        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if ($space) {
            throw new BadRequestException('space with this name already exists');
        }

        $userId = $userInfo->getUserId();

        $user = $this->getUser($userId);

        $space = new Space();
        $space->setOwner($user);
        $space->setName($spaceName);
        $space->addMember($user);
        $space->setSecret(false);

        $this->entityManager->persist($space);
        $this->entityManager->flush();

        return new RedirectResponse(
            $request->getUrl()->getRootUrl().$space->getName().'/',
            302
        );
    }

    public function getEditSpace(Request $request, UserInfoInterface $userInfo, $spaceName)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }
        $userId = $userInfo->getUserId();

        if ($space->getOwner()->getName() !== $userId) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        return $this->templateManager->render(
            'editSpacePage',
            array(
                'space' => $space,
                'indieInfo' => $userInfo,
            )
        );
    }

    public function postEditSpace(Request $request, UserInfoInterface $userInfo, $spaceName)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }
        $userId = $userInfo->getUserId();

        if (!$space->isOwner($userId)) {
            throw new ForbiddenException('not allowed to edit this space');
        }

        $user = $this->getUser($request->getPostParameter('owner'));

        $space->setOwner($user);
        $space->setSecret('on' === $request->getPostParameter('secret') ? true : false);

        $this->entityManager->persist($space);
        $this->entityManager->flush();

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getName().'/', 302);
    }

    public function getPublicSpaces(Request $request, $userInfo)
    {
        $publicSpaces = array();

        $allSpaces = $this->entityManager->getRepository('fkooman\Phubble\Space')->findAll();
        foreach ($allSpaces as $space) {
            if (!$space->getSecret()) {
                $publicSpaces[] = $space;
            }
        }

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
        $mySpaces = array();
        $memberSpaces = array();

        $userId = $userInfo->getUserId();

        $allSpaces = $this->entityManager->getRepository('fkooman\Phubble\Space')->findAll();

        foreach ($allSpaces as $space) {
            if ($userId === $space->getOwner()->getName()) {
                $mySpaces[] = $space;
            } else {
                // are we a member, but not owner
                $members = $space->getMembers();
                foreach ($members as $member) {
                    if ($userId === $member->getName()) {
                        $memberSpaces[] = $space;
                    }
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

    public function getMessages(Request $request, $userInfo, $spaceName)
    {
        // FIXME: should throw UnauthorizedException for secret space and no
        // authentication...

        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }

        $messages = $space->getMessages();

        $canPost = false;
        if (null !== $userInfo) {
            $userId = $userInfo->getUserId();
            $canPost = $space->isOwner($userId) || $space->isMember($userId);
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
        $response->addHeader('Link', sprintf('<%s>; rel="micropub"', $request->getUrl()->getRootUrl().$space->getId().'/_micropub'));

        return $response;
    }

    public function getMessage(Request $request, $userInfo, $spaceName, $id)
    {
        // FIXME: should throw UnauthorizedException for secret space and no
        // authentication...
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }

        $message = $this->entityManager->getRepository('fkooman\Phubble\Message')
            ->findOneBy(array('id' => $id, 'space' => $space));
        if (!$message) {
            throw new NotFoundException('message not found');
        }

#        $space = $this->db->getSpace($spaceId);
#        $message = $this->db->getMessage($space, $id);

        $canPost = false;
        if (null !== $userInfo) {
            $userId = $userInfo->getUserId();
            $canPost = $space->isOwner($userId) || $space->isMember($userId);
        }

#        $canPost = false;
#        if (null !== $userInfo) {//
#            //$spaceAcl = $this->getSpaceAcl($space);
#            //if (in_array($userInfo->getUserId(), $spaceAcl)) {
#            //    $canPost = true;
#            //}
#        }

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

    public function deleteMessage(Request $request, UserInfoInterface $userInfo, $spaceName, $id)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }

        $message = $this->entityManager->getRepository('fkooman\Phubble\Message')
            ->findOneBy(array('id' => $id, 'space' => $space));
        if (!$message) {
            throw new NotFoundException('message not found');
        }
        $userId = $userInfo->getUserId();

        if (!$space->isMember($userId)) {
            throw new ForbiddenException('not a member of this space');
        }

        if ($message->getAuthor()->getName() !== $userId) {
            throw new ForbiddenException('not the owner of this message');
        }

        $this->entityManager->remove($message);
        $this->entityManager->flush();

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getName().'/', 302);
    }

    public function addMember(Request $request, UserInfoInterface $userInfo, $spaceName)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }
        $userId = $userInfo->getUserId();

        if (!$space->isMember($userId)) {
            throw new ForbiddenException('not a member of this space');
        }

        $userToAdd = $request->getPostParameter('user_id');
        $user = $this->getUser($userToAdd);
        $space->addMember($user);

        $this->entityManager->persist($space);
        $this->entityManager->flush();

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getName().'/_edit', 302);
    }

    public function deleteMember(Request $request, UserInfoInterface $userInfo, $spaceName, $id)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }
        $userId = $userInfo->getUserId();

        $user = $this->entityManager->getRepository('fkooman\Phubble\User')
            ->find($id);
        if (!$user) {
            throw new NotFoundException('user not found');
        }

        if (!$space->isOwner($userId)) {
            throw new ForbiddenException('not the owner of this space');
        }

        $space->getMembers()->removeElement($user);
        $this->entityManager->persist($space);
        $this->entityManager->flush();

        return new RedirectResponse($request->getUrl()->getRootUrl().$space->getName().'/_edit', 302);
    }

    public function postMessage(Request $request, UserInfoInterface $userInfo, $spaceName)
    {
        $space = $this->entityManager->getRepository('fkooman\Phubble\Space')
            ->findOneBy(array('name' => $spaceName));
        if (!$space) {
            throw new NotFoundException('space not found');
        }
        $userId = $userInfo->getUserId();

        $user = $this->entityManager->getRepository('fkooman\Phubble\User')
            ->findOneBy(array('name' => $userId));
        if (!$user) {
            throw new NotFoundException('user not found');
        }

        if (!$space->isOwner($userId) && !$space->isMember($userId)) {
            throw new ForbiddenException('not allowed to post this message');
        }

        $message = new Message();
        $message->setAuthor($user);
        $message->setSpace($space);
        $dt = new DateTime();
        $dt->setTimestamp($this->io->getTime());

        $message->setPosted($dt);
        $message->setContent($this->validateMessageBody($request->getPostParameter('message_body')));
        $this->entityManager->persist($message);
        $this->entityManager->flush();

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

    private function getUser($userId)
    {
        $user = $this->entityManager->getRepository('fkooman\Phubble\User')
            ->findOneBy(array('name' => $userId));
        if (!$user) {
            $user = new User();
            $user->setName($userId);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return $user;
    }
}
