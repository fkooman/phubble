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

    public function createSpace($id, $owner)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (id, owner, private) VALUES(:id, :owner, :private)',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':owner', $owner, PDO::PARAM_STR);
        $stmt->bindValue(':private', false, PDO::PARAM_BOOL);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to add');
        }
    }

    public function updateSpace($spaceId, $owner, $private)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET owner = :owner, private = :private WHERE id = :id',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $spaceId, PDO::PARAM_STR);
        $stmt->bindValue(':owner', $owner, PDO::PARAM_STR);
        $stmt->bindValue(':private', $private, PDO::PARAM_BOOL);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to update');
        }
    }

    public function getSpaceInfo($spaceId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * FROM %s WHERE id = :id',
                $this->prefix.'spaces'
            )
        );
        $stmt->bindValue(':id', $spaceId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return false;
    }

    public function getSpaces()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * FROM %s',
                $this->prefix.'spaces'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public function storeMessage($spaceId, $messageId, $authorId, $messageBody, $postTime)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (space_id, id, author_id, message_body, post_time) VALUES(:space_id, :id, :author_id, :message_body, :post_time)',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $spaceId, PDO::PARAM_STR);
        $stmt->bindValue(':id', $messageId, PDO::PARAM_STR);
        $stmt->bindValue(':author_id', $authorId, PDO::PARAM_STR);
        $stmt->bindValue(':message_body', $messageBody, PDO::PARAM_STR);
        $stmt->bindValue(':post_time', $postTime, PDO::PARAM_INT);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new PdoStorageException('unable to add');
        }
    }

    public function getMessage($spaceId, $messageId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, author_id, message_body, post_time FROM %s WHERE space_id = :space_id AND id = :id',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $spaceId, PDO::PARAM_STR);
        $stmt->bindValue(':id', $messageId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return false;
    }

    public function deleteMessage($spaceId, $messageId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s WHERE space_id = :space_id AND id = :id',
                $this->prefix.'messages'
            )
        );
        $stmt->bindValue(':space_id', $spaceId, PDO::PARAM_STR);
        $stmt->bindValue(':id', $messageId, PDO::PARAM_STR);
        // FIXME: figure out return values...
        return $stmt->execute();
    }

    public function getMessages($spaceId, $page)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * FROM %s WHERE space_id = :space_id ORDER BY post_time DESC LIMIT %d,50',
                $this->prefix.'messages',
                intval($page) * 50
            )
        );
        $stmt->bindValue(':space_id', $spaceId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public static function createTableQueries($prefix)
    {
        $query = array();

        $query[] = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id VARCHAR(64) NOT NULL,
                owner VARCHAR(255) NOT NULL,
                private BOOLEAN NOT NULL,
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
                FOREIGN KEY (space_id) REFERENCES spaces(id)
            )',
            $prefix.'messages'
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
