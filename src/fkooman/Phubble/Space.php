<?php

namespace fkooman\Phubble;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity @Table(name="spaces")
 **/
class Space
{
    /**
     * @Id @Column(type="integer") @GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @Column(type="string", unique=true)
     *
     * @var string
     */
    protected $name;

    /**
     * @Column(type="boolean")
     *
     * @var bool
     */
    protected $secret;

    /**
     * @ManyToOne(targetEntity="User", inversedBy="ownedSpaces")
     **/
    protected $owner;

    /**
     * @ManyToMany(targetEntity="User", inversedBy="memberSpaces")
     **/
    protected $members;

    /**
     * @OneToMany(targetEntity="Message", mappedBy="space")
     *
     * @var Message[]
     **/
    protected $messages = null;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setOwner($owner)
    {
        $owner->addOwnedSpace($this);
        $this->owner = $owner;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function isOwner($userName)
    {
        return $userName === $this->owner->getName();
    }

    public function isMember($userName)
    {
        foreach ($this->members as $member) {
            if ($userName === $member->getName()) {
                return true;
            }
        }

        return false;
    }

    public function addMember($member)
    {
        $this->members[] = $member;
    }

    public function getMembers()
    {
        return $this->members;
    }

    public function addMessage($message)
    {
        $this->messages[] = $message;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
