<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 13/07/2017
 * Time: 12:10
 */
class local_bath_grades_transfer_error
{
    public $id;
    public $error_message;
    private $table = 'local_bath_grades_error';

    public function save(){
        global $DB;
        $data = new stdClass();
        $data->error_message = $this->error_message;
        $this->id = $DB->insert_record($this->table,$data,true);
    }

}