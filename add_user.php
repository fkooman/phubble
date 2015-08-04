<?php

// create_product.php
require_once 'bootstrap.php';

use fkooman\Phubble\User;

$userName = $argv[1];

$user = new User();
$user->setName($userName);

$entityManager->persist($user);
$entityManager->flush();

echo 'Created User with ID '.$user->getId()."\n";
