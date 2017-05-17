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
const SECRET_WORD_1 = 'SITS';
const SECRET_WORD_2 = 'VISION';
const ALGO ='sha512';
class samis_http_client
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
        $glue = '~';
        $body_raw =  urldecode (http_build_query($pieces,'',$glue));
        return $body_raw;
    }
    private function get_calculated_hash($method,$message){
        $query = 'function='.$method;
        $auth = "Basic ".base64_encode($this->username.':'.$this->password);
        $slr_string = $auth."|".SECRET_WORD_1."|".$query."|".SECRET_WORD_2."|".$message;
        echo "\n BODY: ".$slr_string."\n\n <br>";
        $hash = strtoupper(hash(ALGO,$slr_string));
        echo "\n HASH: ".$hash."\n\n";
        return $hash;
    }
    public function call_samis($method,$data){
         //Convert the data into SAMIS format
        $data_raw = $this->construct_body($data);
        //$hash = $this->get_calculated_hash($method,$data_raw);
        try{
            $this->response = $this->client->request('POST','/samis-dev/urd/sits.urd/run/',[
                'debug' => false,
                'body'=> $data_raw,
                'auth' => [$this->username, $this->password],
                //'query'=> ['FUNCTION' => $method,'hash'=>$hash],
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'Cache-Control' => 'no-cache',
                ],
            ]);
             return $this->response->getBody()->getContents();

        }
        catch(\GuzzleHttp\Exception\ClientException $e){
            echo "Throwing Client Exception Exception #1";
            if($e->getCode() == 404){
                throw  new \Exception("Cant connect to SAMIS");
            }

        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            echo "Throwing Server Exception Exception #1";
            echo $e->getMessage();
        }
    }
    public function call($method,$data,$auth = '',$hash = ''){

        //Before every call, test connnection to SAMIS
        $this->test_connection();

        //TODO change method to function, add hash query param
        try{
            $this->response = $this->client->request('POST','local/bath_grades_transfer/web-service.php',[
                'debug' => false,
                'body'=> $data,//TODO make this work later
                //'body'=> 'P04=<<@SRS_QAEO_042>>~P05=S1~P06=MN10001~P07=A~P08=MN10001A~P09=01',
                'auth' => ['username', 'password'], //TODO change this to dynamic
                'query'=> ['method' => $method],
                'headers' => [
                    'Content-Type' => 'text/xml',
                    'Cache-Control' => 'no-cache',
                 ],
            ]);
             //$xml = simplexml_load_string($data);
               return $this->response->getBody()->getContents();

        }
        catch(\GuzzleHttp\Exception\ClientException $e){
            echo "Throwing Client Exception Exception #1";
            if($e->getCode() == 404){
                throw  new \Exception("Cant connect to SAMIS");
            }

        }
        catch(\GuzzleHttp\Exception\ServerException $e){
            echo "Throwing Server Exception Exception #1";
            echo $e->getMessage();
        }


    }
}