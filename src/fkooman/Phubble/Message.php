<?php

namespace fkooman\Phubble;

use DateTime;

/**
 * @Entity @Table(name="messages")
 **/
class Message
{
    /**
     * @Id @Column(type="integer") @GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @Column(type="string")
     *
     * @var string
     */
    protected $content;

    /**
     * @Column(type="datetime")
     *
     * @var DateTime
     */
    protected $posted;

    /**
     * @ManyToOne(targetEntity="User", inversedBy="postedMessages")
     **/
    protected $author;

    /**
     * @ManyToOne(targetEntity="Space", inversedBy="messages")
     **/
    protected $space;

    public function getId()
    {
        return $this->id;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setPosted(DateTime $posted)
    {
        $this->posted = $posted;
    }

    public function getPosted()
    {
        return $this->posted;
    }

    public function setAuthor($author)
    {
        $author->addPostedMessage($this);
        $this->author = $author;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function setSpace($space)
    {
        $space->addMessage($this);
        $this->space = $space;
    }

    public function getSpace()
    {
        return $this->space;
    }
}
