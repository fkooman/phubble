<?php

namespace fkooman\Phubble;

use InvalidArgumentException;

class Message
{
    private $space;
    private $id;
    private $authorId;
    private $messageBody;
    private $postTime;

    public function __construct(Space $space, $id, $authorId, $messageBody, $postTime)
    {
        $this->space = $space;
        $this->id = self::validateString($id);
        $this->authorId = self::validateUrl($authorId);
        $this->messageBody = self::validateString($messageBody);
        $this->postTime = self::validateInt($postTime);
    }

    public function getSpace()
    {
        return $this->space;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthorId()
    {
        return $this->authorId;
    }

    public function getMessageBody()
    {
        return $this->messageBody;
    }

    public function getPostTime()
    {
        return $this->postTime;
    }

    public static function fromArray(Space $space, array $a)
    {
        $requiredKeys = array('id', 'author_id', 'message_body', 'post_time');
        self::arrayHasKeys($a, $requiredKeys);

        return new self($space, $a['id'], $a['author_id'], $a['message_body'], $a['post_time']);
    }

    public static function arrayHasKeys(array $a, array $keys)
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $a)) {
                throw new InvalidArgumentException(
                    sprintf('missing key "%s"', $k)
                );
            }
        }
    }

    public static function validateString($str)
    {
        return $str;
    }

    public static function validateUrl($url)
    {
        return $url;
    }

    public static function validateInt($int)
    {
        return $int;
    }

    public static function validateBoolean($bool)
    {
        return (bool) $bool;
    }
}
