<?php
global $CFG;
require_once $CFG->dirroot.'/local/bath_grades_transfer/vendor/autoload.php';

/**
 * Class local_bath_grades_transfer_samis_api_client
 */
class local_bath_grades_transfer_samis_api_client extends \GuzzleHttp\Client

{
    /**
     * @var
     */
    public $authenticated;
    /**
     * @var
     */
    public $client;
    /**
     * @var
     */
    public $request;
    public $response;
    private $connected;

    /**
     * local_bath_grades_transfer_samis_api_client constructor.
     */
    public function __construct() {
        global $CFG;
        //$uri = $CFG->wwwroot.'/blocks/bath_samis_grades_transfer/web-service.php/';
        $api_url = get_config('local_bath_grades_transfer', 'samis_api_url');
        $hash = get_config('local_bath_grades_transfer', 'samis_api_key');
        if(isset($api_url)){
            parent::__construct(['base_uri' => $api_url]);
        }

    }

    /**
     * Authenticate credentials against SAMIS
     */
    public function autheticate(){
        $api_url = get_config('local_bath_grades_transfer', 'samis_api_url');
        //$api_key = get_config('block_bath_samis_grades_transfer', 'samis_api_key');
         $this->authenticated = true;

    }

    /**
     * See if the client can connect to SAMIS
     * @return mixed
     */
    public function is_connected(){
        return $this->connected;
    }
    /**
     * Call a SAMIS API function
     * @param $method
     * @param $data
     * @return string
     */
    public function call($method, $data){
        try{
            $this->response = $this->request('GET', 'bath_grades_transfer/web-service.php2', [
                'query' => ['method' => $method,
                    'data'=>$data],

                'debug' => false,
                'timeout'=> 5,
                'auth'=>['username','password'],
                'body'=> $data
            ]);
            $this->connected = true;
        }
        catch (\GuzzleHttp\Exception\RequestException $exception){
             if($exception->hasResponse()){
                 $this->connected = false;
                 $this->response = \GuzzleHttp\Psr7\str($exception->getResponse());

            }

        }
    }


}