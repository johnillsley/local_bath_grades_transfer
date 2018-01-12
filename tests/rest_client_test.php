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
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
global $CFG;
require_once($CFG->dirroot . '/local/bath_grades_transfer/vendor/autoload.php');

use \GuzzleHttp\Client;
use GuzzleHttp\Promise;
use \GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Exception\RequestException;

class test_bath_grades_transfer_rest_client
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
                'https' => 'tcp://' . $CFG->proxyhost . ':' . $CFG->proxyport, // Use this proxy with "https".
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
     * FOR UNIT TESTING ONLY: Main function that is used to make a WEB SERVICE call
     * @param string $method
     * @param $data
     * @param string $verb
     * @return boolean
     */
    public function call_samis($method, $data, $verb = 'GET') {
        global $CFG;
        $this->response = array();
        /**
         **** FOR UNIT TESTING ONLY ****
         */
        // Mimics the web service responses from SAMIS.

        if ($method == "MABS" && $verb == "GET") {
            $this->response['status'] = 200;
            $this->response['contents'] = file_get_contents($CFG->dirroot .
                '/local/bath_grades_transfer/tests/test_data_ws.json');
        }

        if ($method == "ASSESSMENTS" && $verb == "GET") {
            $this->response['status'] = 200;
            $this->response['contents'] = file_get_contents($CFG->dirroot .
                '/local/bath_grades_transfer/tests/test_data_ws_assessments.xml');
        }

        if ($method == "USERS" && $verb == "GET") {
            $this->response['status'] = 200;
            $this->response['contents'] = file_get_contents($CFG->dirroot .
                '/local/bath_grades_transfer/tests/test_data_ws_users.xml');
        }

        return true;
    }
}