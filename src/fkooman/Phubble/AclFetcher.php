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

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Url;
use RuntimeException;

/**
 * Retrieve the ACL for a space.
 */
class AclFetcher
{
    /** @var string */
    private $aclDir;

    /** @var \GuzzleHttp\Client */
    private $client;

    public function __construct($aclDir, Client $client = null)
    {
        $this->aclDir = $aclDir;
        if (null === $client) {
            $client = new Client();
        }
        $this->client = $client;
    }

    /**
     * Get the ACL from the URL and store it in the file system.
     */
    public function fetchAcl($aclUrl)
    {
        $aclUrl = InputValidation::validateUrl($aclUrl);
        $aclData = $this->fetchUrl($aclUrl);
        $aclData = self::validateAcl($aclUrl, $aclData);

        if (!file_exists($this->aclDir)) {
            @mkdir($this->aclDir);
        }

        $fileName = $this->aclDir.'/'.str_replace('/', '_', $aclUrl);
        if (false === @file_put_contents($fileName.'.tmp', json_encode($aclData))) {
            throw new RuntimeException('unable to write ACL file');
        }
        if (false === @rename($fileName.'.tmp', $fileName)) {
            throw new RuntimeException('unable to rename ACL file');
        }
    }

    /**
     * Get the ACL from the file system.
     */
    public function getAcl($aclUrl)
    {
        $aclUrl = InputValidation::validateUrl($aclUrl);
        $fileName = $this->aclDir.'/'.str_replace('/', '_', $aclUrl);
        $aclJsonData = @file_get_contents($fileName);
        if (false === $aclJsonData) {
            return array();
        }
        $aclData = json_decode($aclJsonData, true);
        $aclData = self::validateAcl($aclUrl, $aclData);

        return $aclData;
    }

    private function fetchUrl($pageUrl)
    {
        // we track all URLs on the redirect path (if any) and make sure none
        // of them redirect to a HTTP URL. Unfortunately Guzzle 3/4 can not do
        // this by default but we need this "hack". This is fixed in Guzzle 5+
        // see https://github.com/guzzle/guzzle/issues/841
        $history = new History();
        $this->client->getEmitter()->attach($history);

        $request = $this->client->createRequest(
            'GET',
            $pageUrl,
            array(
                'headers' => array('Accept' => 'application/json'),
            )
        );
        $response = $this->client->send($request);

        foreach ($history as $transaction) {
            $u = Url::fromString($transaction['request']->getUrl());
            if ('https' !== $u->getScheme()) {
                throw new RuntimeException('redirect path contains non-HTTPS URLs');
            }
        }

        return $response->json();
    }

    public static function validateAcl($aclUrl, $aclData)
    {
        $requiredFields = array('id', 'name', 'members');

        if (!is_array($aclData)) {
            throw new RuntimeException('ACL file MUST be a JSON encoded array');
        }
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $aclData)) {
                throw new RuntimeException(sprintf('mssing field "%s"', $field));
            }
        }

        if ($aclData['id'] !== $aclUrl) {
            throw new RuntimeException('ACL file id is not the same as URL');
        }

        if (!is_array($aclData['members'])) {
            throw new RuntimeException('ACL members field MUST be array');
        }

        foreach ($aclData['members'] as $memberUrl) {
            InputValidation::validateUrl($memberUrl);
        }

        return $aclData;
    }
}
