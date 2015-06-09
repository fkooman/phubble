<?php

namespace fkooman\Phubble;

use InvalidArgumentException;

class Space
{
    private $id;
    private $owner;
    private $secret;

    public function __construct($id, $owner, $secret)
    {
        $this->id = self::validateString($id);
        $this->owner = self::validateUrl($owner);
        $this->secret = self::validateBoolean($secret);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = self::validateString($owner);
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function setSecret($secret)
    {
        $this->secret = self::validateBoolean($secret);
    }

    public static function fromArray(array $a)
    {
        $requiredKeys = array('id', 'owner', 'secret');
        self::arrayHasKeys($a, $requiredKeys);

        return new self($a['id'], $a['owner'], $a['secret']);
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

    public static function validateBoolean($bool)
    {
        return (bool) $bool;
    }
}
