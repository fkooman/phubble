<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace fkooman\Phubble;

use PDO;
use RuntimeException;

class PdoStorage
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function addSpace(Space $space)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (id, owner, secret) VALUES(:id, :owner, :secret)',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $space->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':owner', $space->getOwner(), PDO::PARAM_STR);
        $stmt->bindValue(':secret', $space->getSecret(), PDO::PARAM_BOOL);
        $stmt->execute();
        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to add item');
        }

        return true;
    }

    public function getSpace($spaceId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, owner, secret FROM %s WHERE id = :id',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $spaceId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            throw new RuntimeException('requested item not found');
        }

        return Space::fromArray($result);
    }

    public function updateSpace(Space $space)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET owner = :owner, secret = :secret WHERE id = :id',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $space->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':owner', $space->getOwner(), PDO::PARAM_STR);
        $stmt->bindValue(':secret', $space->getSecret(), PDO::PARAM_BOOL);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to update item');
        }
    }

    public function getPublicSpaces()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, owner, secret FROM %s WHERE NOT secret',
                $this->prefix.'spaces'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spaces = array();
        foreach ($result as $r) {
            $spaces[] = Space::fromArray($r);
        }

        return $spaces;
    }

    public function getSecretSpaces()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, owner, secret FROM %s WHERE secret',
                $this->prefix.'spaces'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spaces = array();
        foreach ($result as $r) {
            $spaces[] = Space::fromArray($r);
        }

        return $spaces;
    }

    public function addMessage(Message $message)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (id, space_id, author_id, message_body, post_time) VALUES(:id, :space_id, :author_id, :message_body, :post_time)',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':id', $message->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':space_id', $message->getSpace()->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':author_id', $message->getAuthorId(), PDO::PARAM_STR);
        $stmt->bindValue(':message_body', $message->getMessageBody(), PDO::PARAM_STR);
        $stmt->bindValue(':post_time', $message->getPostTime(), PDO::PARAM_INT);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to add item');
        }
    }

    public function getMessage(Space $space, $messageId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, space_id, author_id, message_body, post_time FROM %s WHERE space_id = :space_id AND id = :id',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $space->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':id', $messageId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            throw new RuntimeException('requested item not found');
        }

        return Message::fromArray($space, $result);
    }

    public function deleteMessage(Message $message)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s WHERE space_id = :space_id AND id = :id',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $message->getSpace()->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':id', $message->getId(), PDO::PARAM_STR);

        $stmt->execute();
        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to delete item');
        }
    }

    public function getMessages(Space $space)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * FROM %s WHERE space_id = :space_id ORDER BY post_time DESC',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $space->getId(), PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $messages = array();
        foreach ($result as $r) {
            $messages[] = Message::fromArray($space, $r);
        }

        return $messages;
    }

    public static function createTableQueries($prefix)
    {
        $query = array();

        $query[] = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id VARCHAR(64) NOT NULL,
                owner VARCHAR(255) NOT NULL,
                secret BOOLEAN NOT NULL,
                PRIMARY KEY (id)
            )',
            $prefix.'spaces'
        );

        $query[] = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id VARCHAR(64) NOT NULL,
                space_id VARCHAR(64) NOT NULL,
                author_id VARCHAR(255) NOT NULL,
                message_body VARCHAR(255) NOT NULL,
                post_time INT NOT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (space_id) REFERENCES %s(id)
            )',
            $prefix.'messages',
            $prefix.'spaces'
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('spaces', 'messages');
        foreach ($tables as $t) {
            // make sure the tables are empty
            $this->db->query(
                sprintf(
                    'DELETE FROM %s',
                    $this->prefix.$t
                )
            );
        }
    }
}
