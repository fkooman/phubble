<?php

namespace fkooman\Phubble;

use InvalidArgumentException;

class Space
{
    private $id;
    private $owner;
    private $acl;
    private $secret;

    public function __construct($id, $owner, $acl, $secret)
    {
        $this->id = InputValidation::validateString($id);
        $this->owner = InputValidation::validateUrl($owner);
        $this->acl = InputValidation::validateUrl($acl, true);
        $this->secret = InputValidation::validateBoolean($secret);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function getAcl()
    {
        return $this->acl;
    }

    public function setOwner($owner)
    {
        $this->owner = InputValidation::validateString($owner);
    }

    public function setAcl($acl)
    {
        $this->acl = InputValidation::validateUrl($acl, true);
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function setSecret($secret)
    {
        $this->secret = InputValidation::validateBoolean($secret);
    }

    public static function fromArray(array $a)
    {
        $requiredKeys = array('id', 'owner', 'acl', 'secret');
        self::arrayHasKeys($a, $requiredKeys);

        return new self($a['id'], $a['owner'], $a['acl'], (bool) $a['secret']);
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
}
