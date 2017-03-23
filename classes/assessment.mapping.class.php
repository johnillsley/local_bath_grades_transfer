<?php
require_once 'local_bath_grades_transfer_samis_assessment_data.php';

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 09/02/2017
 * Time: 14:53
 */
class local_bath_grades_transfer_assessment_mapping
{
    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    /**
     * @var
     */
    public $mab_sequence;
    /**
     * @var
     */
    public $timecreated;
    /**
     * @var
     */
    public $modifierid;
    /**
     * @var
     */
    public $timeomdified;
    /**
     * @var
     */
    private $locked;
    /**
     * @var
     */
    public $samis_assessment_end_date;
    /**
     * @var
     */
    public $samis_assessment_id;
    /**
     * @var
     */
    public $assessment_lookup_id;
    /**
     * @var
     */
    public $coursemodule;
    /**
     * @var assessment_lookup
     */
    public $lookup;

    private $table = 'local_bath_grades_mapping';

    /**
     * local_bath_grades_transfer_assessment_mapping constructor.
     * @param null $data
     */
    public function __construct($data = null) {
        $this->lookup = new local_bath_grades_transfer_assessment_lookup();
    }

    /**
     * Gets a grade transfer assessment by ID
     * @param $id
     * @return mixed|null
     */
    public function get($id) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['id' => $id])) {
            $record = $DB->get_record('local_bath_grades_transfer', ['id' => $id]);
        }
        return $record;
    }


    /**
     * Fetches a grade transfer assessment by samis_assessment_lookup_id
     * @param $lookupid
     * @return mixed|null
     */
    public function get_by_lookup_id($lookupid) {
        global $DB;
        $record = null;
        if ($DB->record_exists($this->table, ['assessment_lookup_id' => $lookupid])) {
            $record = $DB->get_record($this->table, ['assessment_lookup_id' => $lookupid]);
        }
        return $record;
    }

    /**
     *
     */
    public function delete_record() {
        global $DB;
        if ($DB->record_exists($this->table)) {
            $DB->delete_records($this->table);
        }
    }

    /**
     * @param $data
     */
    public function set_data($data) {
        if (!empty($data)) {
            if (isset($data->id)) {
                $this->id = $data->id; // local_bath_grades_transfer ID
            }
            $this->coursemodule = $data->coursemodule; //settings.php
            $this->samis_assessment_end_date = $data->samis_assessment_end_date;
            $this->assessment_lookup_id = $data->assessment_lookup_id;
            if (isset($data->locked)) {
                $this->locked = $data->locked;
            }
            $this->modifierid = $data->modifierid;
        }
    }

    /**
     * Fetches a grade transfer assessment by Course Module ID
     * @param $cmid
     * @return bool|mixed
     */
    public function get_by_cm_id($cmid) {
        global $DB;
        $record = false;
        if ($this->exists_by_cm_id($cmid)) {
            $record = $DB->get_record($this->table, ['coursemodule' => $cmid]);
        }
        return $record;
    }

    /**
     * See if an transfer assessment mapping record exists by Course Module ID
     * @param $cmid
     * @return bool
     */
    private function exists_by_cm_id($cmid) {
        global $DB;
        return $DB->record_exists($this->table, ['coursemodule' => $cmid]);
    }

    /**
     * @param $map_code
     * @return bool
     */
    public function exists_by_samis_assessment_id($map_code) {
        global $DB;
        return $DB->record_exists($this->table, ['coursemodule' => $map_code]);
    }

    /**
     * This method is responsible for contacting the API Client to fetch the data
     * @param $modulecode
     * @param $academic_year
     * @param $periodslotcode
     * @param $occurrence
     * @return mixed
     */
    public function get_remote_assesment_data($modulecode, $academic_year, $periodslotcode, $occurrence) {
        //Also set any new remote assessment data
        $assessment_data = new local_bath_grades_transfer_samis_assessment_data();
        $data = json_decode($assessment_data->get_remote_assessment_details($modulecode, $academic_year, $periodslotcode, $occurrence));
        return $data;
    }

    /**
     *
     * @return bool
     */
    public function update() {
        global $DB;
        $objAssessment = new stdClass();
        $objAssessment->id = $this->id;
        $objAssessment->coursemodule = $this->coursemodule;
        $objAssessment->modifierid = $this->modifierid;
        $objAssessment->timemodified = time(); //now //TODO not every save is a time modified, only modified if the value has changed
        $objAssessment->assessment_lookup_id = $this->assessment_lookup_id;
        $objAssessment->samis_assessment_end_date = $this->samis_assessment_end_date;
        var_dump($objAssessment);
        return $DB->update_record($this->table, $objAssessment);
    }

    /**
     * This method looks after any redundant mapping and deals with it
     * @param $remoteAssessment
     * @param $samisAttributes
     */
    public function housekeeping_mapping($remoteAssessment, $samisAttributes) {
        //From remote mapping get the local mapping and compare the following:
        //1. If their assesment name has changed
        //2. That mapping does not exist in remote anymore
        //3. Which means we have to compare it first against the local mappings
        //4. If a mapping has been removed from remote but still exists in local
        //5. Remove it from the lookup table , remote its foreign link from the transfer table


        //Get all asessments locally based on SAMIS code, period, ac yr and mav_occur
        if (!empty($samisAttributes)) {
            $local_assessments = $this->lookup->get_by_samis_details($samisAttributes->samis_unit_code,
                $samisAttributes->academic_year,
                $samisAttributes->periodslotcode,
                $samisAttributes->occurrence);
        }
        var_dump($remoteAssessment);
        //Compare this to the remote assessment to see if anything has gone missing.
        foreach ($local_assessments as $assessment) {

        }
        var_dump($local_assessments);
        die();

    }

    /**
     * Save a mapping record in the moodle database
     */
    public function save() {
        global $DB;
        $objAssessment = new stdClass();
        $objAssessment->coursemodule = $this->coursemodule;
        $objAssessment->timecreated = time(); //now
        $objAssessment->modifierid = $this->modifierid;
        $objAssessment->timemodified = time(); //now
        $objAssessment->assessment_lookup_id = $this->assessment_lookup_id;
        $objAssessment->samis_assessment_end_date = $this->samis_assessment_end_date;
        $objAssessment->locked = 0;
        var_dump($objAssessment);
        $DB->insert_record($this->table, $objAssessment);
    }

    /**
     * Set an assessment mapping to be locked in the moodle database preventing users from selecting it
     * @param $locked
     */
    public function set_locked($locked) {
        $this->locked = $locked;
    }

    /**
     *  See if transfer has been locked. Usually happens after the first grade has been transferred
     */
    public function is_locked() {
        return $this->locked;
    }

    /**
     * @param $name
     * @param $value
     */
    function __set($name, $value) {
        // TODO: Implement __set() method.
    }


}