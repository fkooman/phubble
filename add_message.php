<?php

// add_message.php
require_once 'bootstrap.php';

use fkooman\Phubble\Message;
use fkooman\Phubble\User;

$userId = $argv[1];
$spaceId = $argv[2];
$dateTime = new DateTime('now');
$content = $argv[3];

$user = $entityManager->find("fkooman\Phubble\User", $userId);
if (!$user) {
    echo 'user not found'.PHP_EOL;
    exit(1);
}

$space = $entityManager->find("fkooman\Phubble\Space", $spaceId);
if (!$space) {
    echo 'space not found'.PHP_EOL;
    exit(1);
}

$message = new Message();
$message->setContent($content);
$message->setPosted($dateTime);
$message->setAuthor($user);
$message->setSpace($space);

##foreach ($productIds as $productId) {
##    $product = $entityManager->find("Product", $productId);
##    $bug->assignToProduct($product);
##}

#$bug->setReporter($reporter);
#$bug->setEngineer($engineer);

$entityManager->persist($message);
$entityManager->flush();

echo 'Your new Message Id: '.$message->getId()."\n";
