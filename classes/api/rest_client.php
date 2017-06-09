<?php
global $CFG;
require_once $CFG->dirroot.'/local/bath_grades_transfer/vendor/autoload.php';
use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
use \GuzzleHttp\Exception\RequestException;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 30/03/2017
 * Time: 15:54
 */
class local_bath_grades_transfer_rest_client
{
    private $response;
    public $promise;
    private $username;
    private $password;
    private $is_connected;

    public function __construct() {
        $api_url = get_config('local_bath_grades_transfer', 'samis_api_url');
        $this->username = get_config('local_bath_grades_transfer', 'samis_api_user');
        $this->password = get_config('local_bath_grades_transfer', 'samis_api_password');
        $this->client = new Client([
            'base_uri'=>$api_url
            ]
        );

    }
    private  function test_connection(){
        try{
            $this->client->request('GET', '/');
        }
        catch(\GuzzleHttp\Exception\ClientException $e){
            echo $e->getMessage();
            echo $e->getCode();
        }
    }
    public function is_connected(){
        return $this->is_connected;
    }
    private function construct_body(array $pieces){
        $glue = '/';
        $body_raw = '';
        $lastElement = end($pieces);
        foreach($pieces as $key => $value){
            if($value == $lastElement){
                $body_raw .= $key.$glue.$value;
            }
            else{
                $body_raw .= $key.$glue.$value.$glue;
            }
        }
        return $body_raw;
    }

    public function call_samis($method,$data){
         //Convert the data into SAMIS format
        $data_raw = $this->construct_body($data);
         try{
            $this->response = $this->client->request('GET','https://www.bath.ac.uk/samis-dev/urd/sits.urd/run/SIW_RWS/'.$method.'/'.$data_raw,[
                'debug' => false,
                'auth' => [$this->username, $this->password],
                'headers' => [
                    //'Content-Type' => 'text/xml', //dont need header for now as output is always JSON
                    'Cache-Control' => 'no-cache',
                ],
            ]);
             return $this->response->getBody()->getContents();

        }
        catch(\GuzzleHttp\Exception\ClientException $e){
            echo "Throwing Client Exception Exception #1";
            if($e->getCode() == 400){
                //Bad Request.
                 throw  new \Exception($e->getMessage());
            }

            if($e->getCode() == 404){
                throw  new \Exception("Cant connect to SAMIS");
            }

        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            echo "Throwing Server Exception Exception #1";
            //Treat it as we did not get any data
            echo $e->getMessage();
        }
    }
}