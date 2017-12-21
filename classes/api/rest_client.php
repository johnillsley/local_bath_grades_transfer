<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
global $CFG;
require_once($CFG->dirroot . '/local/bath_grades_transfer/vendor/autoload.php');

use \GuzzleHttp\Client;
use GuzzleHttp\Promise;
use \GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Exception\RequestException;

class local_bath_grades_transfer_rest_client
{
    /**
     * @var
     */
    public $response;
    /**
     * @var
     */
    public $promise;
    /**
     * @var mixed
     */
    private $username;
    /**
     * @var mixed
     */
    private $password;
    /**
     * @var
     */
    public $isconnected;
    public $dataraw;

    /**
     * local_bath_grades_transfer_rest_client constructor.
     */
    public function __construct() {
        global $CFG;
        $apiurl = get_config('local_bath_grades_transfer', 'samis_api_url');
        $this->username = get_config('local_bath_grades_transfer', 'samis_api_user');
        $this->password = get_config('local_bath_grades_transfer', 'samis_api_password');
        $proxy = array();
        if (!empty($CFG->proxyhost) && !empty($CFG->proxyport)) {
            $proxy = array(
                'http' => 'tcp://' . $CFG->proxyhost . ':' . $CFG->proxyport, // Use this proxy with "http"
                'https' => 'tcp://' . $CFG->proxyhost . ':' . $CFG->proxyport, // Use this proxy with "https",
            );
        }
        $this->client = new Client([
                'base_uri' => $apiurl,
                'proxy' => $proxy
            ]
        );
    }

    /**
     * Function to test connection to SAMIS
     */
    public function test_connection() {
        try {
            $response = $this->client->request('GET', '/', ['verify' => false]);
            if ($response->getStatusCode() == 200) {
                $this->isconnected = true;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            echo $e->getMessage();
            echo $e->getCode();
        }
    }

    /**
     * @return mixed
     */
    public function is_connected() {
        return $this->isconnected;
    }

    /**
     * @param array $pieces
     * @return string
     */
    private function construct_body(array $pieces) {
        $glue = '/';
        $bodyraw = '';
        $lastElement = end($pieces);
        foreach ($pieces as $key => $value) {
            if ($value == $lastElement) {
                $bodyraw .= $key . $glue . $value;
            } else {
                $bodyraw .= $key . $glue . $value . $glue;
            }
        }
        return $bodyraw;
    }

    /**
     * Main function that is used to make a WEB SERVICE call to the SAMIS system
     * @param string $method
     * @param array $data
     * @param string $verb
     * @return mixed
     * @throws Exception
     */
    public function call_samis($method, $data, $verb = 'GET') {
        global $CFG;
        try {
            $dataraw = $this->construct_body($data);
            $this->dataraw = (string)$dataraw;
            if ($verb == 'POST') {
                // Post changes.
                $this->promise = $this->client->postAsync($method . '/' . $dataraw, [
                    'debug' => false,
                    'auth' => [$this->username, $this->password],
                    'timeout' => 40,
                    'verify' => false, // for dev
                    'headers' => [
                        'Content-Type' => 'text/xml',
                        'Cache-Control' => 'no-cache',
                    ],
                    'body' => $data['body']
                ]);
            } else {
                $this->promise = $this->client->getAsync($method . '/' . $dataraw, [
                    'debug' => false,
                    'timeout' => 6,
                    'verify' => false, // for dev
                    'auth' => [$this->username, $this->password],
                    'headers' => [
                        'Content-Type' => 'text/xml',
                        'Cache-Control' => 'no-cache',
                    ],
                ]);
            }
            return $this->promise->then(
            // Success Callback.
                function (ResponseInterface $res) {
                    // Return code and response.
                    $this->response['status'] = $res->getStatusCode();
                    $this->response['contents'] = $res->getBody()->getContents();
                },
                // Error handling.
                function (RequestException $e) {
                    if ($e->getCode() == 400) {
                        // Bad Request.
                        throw  new \Exception("Could not find remote assessments for " . $e->getMessage());
                    } else if ($e->getCode() == 404) {
                        throw  new \Exception("Cant connect to SAMIS");
                    } else {
                        throw  new \Exception($e->getMessage());
                    }
                }
            )->wait();

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getCode() >= 400) {
                // Bad Request.
                throw  new \Exception($e->getMessage());
            }

        } catch (\GuzzleHttp\Exception\ServerException $e) {
            echo "Throwing Server Exception Exception #1";
            // Treat it as we did not get any data.
            echo $e->getMessage();
        }
    }
}