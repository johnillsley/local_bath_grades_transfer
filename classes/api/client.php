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
require_once $CFG->dirroot . '/local/bath_grades_transfer/vendor/autoload.php';
use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
use \GuzzleHttp\Exception\RequestException;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 30/03/2017
 * Time: 15:54
 */

/**
 * Class samis_http_client
 */
class samis_http_client
{
    /**
     * @var
     */
    private $response;
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
    private $is_connected;

    /**
     * samis_http_client constructor.
     */
    public function __construct() {
        $api_url = get_config('local_bath_grades_transfer', 'samis_api_url');
        $this->username = get_config('local_bath_grades_transfer', 'samis_api_user');
        $this->password = get_config('local_bath_grades_transfer', 'samis_api_password');
        $this->client = new Client([
                'base_uri' => $api_url
            ]
        );

    }

    /**
     *
     */
    private function test_connection() {
        try {
            $this->client->request('GET', '/');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            echo $e->getMessage();
            echo $e->getCode();
        }
    }

    /**
     * @return mixed
     */
    public function is_connected() {
        return $this->is_connected;
    }

    /**
     * @param array $pieces
     * @return string
     */
    private function construct_body(array $pieces) {
        $glue = '~';
        $body_raw = urldecode(http_build_query($pieces, '', $glue));
        return $body_raw;
    }

    /**
     * @param $method
     * @param $message
     * @return string
     */
    private function get_calculated_hash($method, $message) {
        $query = 'function=' . $method;
        $auth = "Basic " . base64_encode($this->username . ':' . $this->password);
        $slr_string = $auth . "|" . SECRET_WORD_1 . "|" . $query . "|" . SECRET_WORD_2 . "|" . $message;
        echo "\n BODY: " . $slr_string . "\n\n ";
        $hash = strtoupper(hash(ALGO, $slr_string));
        echo "\n HASH: " . $hash . "\n\n";
        return $hash;
    }

    /**
     * @param $method
     * @param $data
     * @return string
     * @throws Exception
     */
    public function call_samis($method, $data) {
        //Convert the data into SAMIS format
        $data_raw = $this->construct_body($data);
        $hash = $this->get_calculated_hash($method, $data_raw);
        $hash = '12B03226A6D8BE9C6E8CD5E55DC6C7920CAAA39DF14AAB92D5E3EA9340D1C8A4D3D0B8E4314F1F6EF131BA4BF1CEB9186AB87C801AF0D5C95B1BEFB8CEDAE2B9';
        try {
            $this->response = $this->client->request('POST', '/samis-dev/urd/sits.urd/run/siw_wsf.http_action', [
                'debug' => false,
                'body' => $data_raw,
                'auth' => [$this->username, $this->password],
                'query' => ['FUNCTION' => $method, 'hash' => $hash],
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'Cache-Control' => 'no-cache',
                ],
            ]);
            return $this->response->getBody()->getContents();

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            echo "Throwing Client Exception Exception #1";
            if ($e->getCode() == 404) {
                throw  new \Exception("Cant connect to SAMIS");
            }

        } catch (\GuzzleHttp\Exception\ServerException $e) {
            echo "Throwing Server Exception Exception #1";
            echo $e->getMessage();
        }
    }

    /**
     * @param $method
     * @param $data
     * @param string $auth
     * @param string $hash
     * @return string
     * @throws Exception
     */
    public function call($method, $data, $auth = '', $hash = '') {

        //Before every call, test connnection to SAMIS
        $this->test_connection();

        //TODO change method to function, add hash query param
        try {
            $this->response = $this->client->request('POST', 'local/bath_grades_transfer/web-service.php', [
                'debug' => false,
                'body' => $data,
                //'body'=> 'P04=<<@SRS_QAEO_042>>~P05=S1~P06=MN10001~P07=A~P08=MN10001A~P09=01',
                'auth' => ['username', 'password'],
                'query' => ['method' => $method],
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'Cache-Control' => 'no-cache',
                ],
            ]);
            //$xml = simplexml_load_string($data);
            return $this->response->getBody()->getContents();

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            echo "Throwing Client Exception Exception #1";
            if ($e->getCode() == 404) {
                throw  new \Exception("Cant connect to SAMIS");
            }

        } catch (\GuzzleHttp\Exception\ServerException $e) {
            echo "Throwing Server Exception Exception #1";
            echo $e->getMessage();
        }


    }
}