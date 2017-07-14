<?php
global $CFG;
require_once $CFG->dirroot . '/local/bath_grades_transfer/vendor/autoload.php';
use \GuzzleHttp\Client;
use GuzzleHttp\Promise;
use \GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Exception\RequestException;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 30/03/2017
 * Time: 15:54
 */
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
    private $is_connected;
    /**
     *
     */

    /**
     * local_bath_grades_transfer_rest_client constructor.
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
        $glue = '/';
        $body_raw = '';
        $lastElement = end($pieces);
        foreach ($pieces as $key => $value) {
            if ($value == $lastElement) {
                $body_raw .= $key . $glue . $value;
            } else {
                $body_raw .= $key . $glue . $value . $glue;
            }
        }
        return $body_raw;
    }

    /**
     * @param $method
     * @param $data
     * @param string $verb
     * @return mixed
     * @throws Exception
     */
    public function call_samis($method, $data, $verb = 'GET') {
        try {
            $data_raw = $this->construct_body($data);
            if ($verb == 'POST') {
                //post changes

                $this->promise = $this->client->postAsync( $method . '/' . $data_raw, [
                    'debug' => false,
                    'auth' => [$this->username, $this->password],
                    'connect_timeout' => 3.14,
                    'read_timeout' => 3,
                    'timeout' => 3.14,
                    'headers' => [
                        'Content-Type' => 'text/xml',
                        'Cache-Control' => 'no-cache',
                    ],
                    'body' => $data['body']
                ]);
            } else {
                $this->promise = $this->client->getAsync( $method . '/' . $data_raw, [
                    'debug' => false,
                    'connect_timeout' => 6,
                    'timeout' => 6,
                    'auth' => [$this->username, $this->password],
                    'headers' => [
                        'Content-Type' => 'text/xml',
                        'Cache-Control' => 'no-cache',
                    ]
                ]);
            }
            echo "\n\n++++++++++++REQUEST SENT !! WAITING FOR PROMISE TO COME BACK++++++++++++\n\n";
            return $this->promise->then(
            //success Callback
                function (ResponseInterface $res) {
                    //return code and response
                    $this->response['status'] = $res->getStatusCode();
                    $this->response['contents'] = $res->getBody()->getContents();
                    return $res->getBody()->getContents();
                },
                //error handling
                function (RequestException $e) {
                     if ($e->getCode() == 400) {
                        //Bad Request.
                        throw  new \Exception($e->getMessage());
                    }

                    elseif ($e->getCode() == 404) {
                        throw  new \Exception("Cant connect to SAMIS");
                    }
                    else{
                        throw  new \Exception($e->getMessage());
                    }
                }
            )->wait();

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            echo "Throwing Client Exception Exception #1";
            if ($e->getCode() == 400) {
                //Bad Request.
                throw  new \Exception($e->getMessage());
            }

            if ($e->getCode() == 404) {
                throw  new \Exception("Cant connect to SAMIS");
            }

        } catch (\GuzzleHttp\Exception\ServerException $e) {
            echo "Throwing Server Exception Exception #1";
            //Treat it as we did not get any data
            echo $e->getMessage();
        }
    }
}