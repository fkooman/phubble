<?php

// create_product.php
require_once 'bootstrap.php';

use fkooman\Phubble\Space;
use fkooman\Phubble\User;

$spaceName = $argv[1];
$userId = $argv[2];

$user = $entityManager->find("fkooman\Phubble\User", $userId);
if (!$user) {
    echo 'user not found'.PHP_EOL;
    exit(1);
}

$space = new Space();
$space->setName($spaceName);
$space->setOwner($user);

$entityManager->persist($space);
$entityManager->flush();

echo 'Created Space with ID '.$space->getId()."\n";
