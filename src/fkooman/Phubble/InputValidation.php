<?php

namespace fkooman\Phubble;

use InvalidArgumentException;

class InputValidation
{
    public static function validateUrl($urlStr, $allowEmptyAndNull = false)
    {
        if (null === $urlStr && !$allowEmptyAndNull) {
            throw new InvalidArgumentException('URL cannot be null');
        }

        if (0 === strlen($urlStr) && $allowEmptyAndNull) {
            return;
        }

        if (null !== $urlStr) {
            if (false === filter_var($urlStr, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('invalid URL');
            }
            if (0 !== stripos($urlStr, 'https://')) {
                throw new InvalidArgumentException('URL must be a valid https URL');
            }
        }

        return $urlStr;
    }

    public static function validateString($str, $allowEmpty = true)
    {
        if (!is_string($str)) {
            throw new InvalidArgumentException('not a string');
        }

        if (0 === strlen($str) && !$allowEmpty) {
            throw new InvalidArgumentException('string MUST NOT be empty');
        }

        return $str;
    }

    public static function validateInt($int, $mustBePositive = false)
    {
        if (!is_int($int)) {
            throw new InvalidArgumentException('not an integer');
        }

        if (0 > $int) {
            throw new InvalidArgumentException('integer MUST NOT be negative');
        }

        return $int;
    }

    public static function validateBoolean($bool)
    {
        if (!is_bool($bool)) {
            throw new InvalidArgumentException('not a boolean');
        }

        return $bool;
    }
}
