<?php

namespace fkooman\Phubble;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity @Table(name="users")
 **/
class User
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
     * @OneToMany(targetEntity="Message", mappedBy="author")
     *
     * @var Message[]
     **/
    protected $postedMessages = null;

    /**
     * @OneToMany(targetEntity="Space", mappedBy="owner")
     *
     * @var Space[]
     **/
    protected $ownedSpaces = null;

    /**
     * @ManyToMany(targetEntity="Space", mappedBy="members")
     *
     * @var Space[]
     **/
    protected $memberSpaces = null;

    public function __construct()
    {
        $this->postedMessages = new ArrayCollection();
        $this->ownedSpaces = new ArrayCollection();
        $this->memberSpaces = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function addPostedMessage($message)
    {
        $this->postedMessages[] = $message;
    }

    public function addMemberSpace($space)
    {
        $this->memberSpaces[] = $space;
    }

    public function addOwnedSpace($space)
    {
        $this->ownedSpaces[] = $space;
    }
}
