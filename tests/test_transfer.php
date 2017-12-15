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

/**
 * Grade transfer unit tests
 *
 * @package    local_bath_grades_transfer
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
global $CFG;
require_once($CFG->dirroot . '/local/bath_grades_transfer/lib.php');
require_once('rest_client_test.php');
/*
sync lookup - DONE
add mapping -->
lock mapping -->
get samis attributes DONE
transfer grade single user*
check connection to samis*
check spr code exists*
check xml is returned for grade strcuture DONE
*/
/**
 * Unit tests for {@link local_bath_grades_transfer}.
 * @group assessment_mapping
 */
class local_bath_grades_transfer_testcase extends advanced_testcase
{
    public function test_test() {
        $this->assertTrue(true);
    }

    /**
     * Test that we can get the current samis mapping for a course
     */
    public function test_get_samis_details_for_course() {
        global $CFG;
        $this->resetAfterTest();

        // create test course.
        $course = $this->getDataGenerator()->create_course();
        require($CFG->dirroot . '/local/bath_grades_transfer/tests/test_data_db.php');

        $gradetransfer = new \local_bath_grades_transfer();

        // create sits mapping for course.
        $sitsmapping = $this->create_sits_mapping($insertmapping, $gradetransfer->currentacademicyear);

        $samisattributeslist = $gradetransfer->get_samis_mapping_attributes($course->id);
        $samisattributes = array_pop($samisattributeslist); // only one record

        $samisattributesvalues = (array)$samisattributes;
        $samisattributesclass = get_class($samisattributes);

        $samischeck = array(
            "samisunitcode"  => $sitsmapping["sits_code"],
            "academicyear"   => $sitsmapping["acyear"],
            "periodslotcode" => $sitsmapping["period_code"]
        );

        $this->assertTrue($samisattributesvalues == $samischeck);
        $this->assertTrue($samisattributesclass == 'local_bath_grades_transfer_samis_attributes');
    }

    /**
     * Test that local assessment lookup data is correctly updated from external source
     */
    public function test_sync_grade_transfer_lookups() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // create all test data
        $course = $this->getDataGenerator()->create_course();
        require($CFG->dirroot . '/local/bath_grades_transfer/tests/test_data_db.php');
        $gradetransfer = new \local_bath_grades_transfer();

        $this->create_sits_mapping($insertmapping, $gradetransfer->currentacademicyear);
        $this->create_initial_lookups($insertlookups, $insertoccurrences, $gradetransfer->currentacademicyear);

        // This is what's being tested.
        $assessmentlookup = new local_bath_grades_transfer_assessment_lookup();
        $assessmentlookup->samisdata->restwsclient = new \test_bath_grades_transfer_rest_client();
        $assessmentlookup->sync_remote_assessments($course->id);

        // Setup data to confirm if the test worked
        $samisattributeslist = $gradetransfer->get_samis_mapping_attributes($course->id);
        $samisattributes = array_pop($samisattributeslist); // only one record
        $lookups = $assessmentlookup->get_local_assessment_details($samisattributes);
        $remotedata = $assessmentlookup->samisdata->get_remote_assessment_details_rest($samisattributes);
        $remotedata = array_pop($remotedata);

        // Check that it has been successful.
        $check = true;
        if($this->assertTrue(count($remotedata)==count($lookups))) {
            // Number of lookups match so now compare each of them
            foreach ($remotedata as $remoteitem) {
                $localitems = $DB->get_records_sql("
                SELECT o.id
                FROM {local_bath_grades_lookup} AS l, {local_bath_grades_lookup_occ} AS o
                WHERE o.lookupid = l.id
                AND l.expired   = 0
                AND l.mapcode   = '" . $remoteitem["mapcode"] . "'
                AND l.mabseq    = '" . $remoteitem["mabseq"] . "'
                AND l.astcode   = '" . $remoteitem["astcode"] . "'
                AND l.mabperc   = '" . $remoteitem["mabperc"] . "'
                AND l.mabname   = '" . $remoteitem["mabname"] . "'
                AND o.mavoccur  = '" . $remoteitem["mavoccur"] . "'
                ");
                if (count($localitems) != 1) {
                    // there should be a single local record matching each remote lookup item
                    $check = false;
                }
            }
            $this->assertTrue($check);
        }
    }

    /**
     * Test that an assessment mapping can be created
     */

    public function test_add_grade_transfer_mapping() {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Create an assessment mapping for test course.
        list($formdata, $mappings) = $this->create_assessment_mapping($course);

        $this->assertTrue(count($mappings) == 1);
        $mapping = array_pop($mappings);
        $this->assertTrue($mapping->locked == 0);
        $this->assertTrue($formdata->bath_grade_transfer_samis_lookup_id == $mapping->assessmentlookupid);
        $this->assertTrue($formdata->bath_grade_transfer_time_start == $mapping->samisassessmentenddate);
    }

    /**
     * Check that a grade structure can be retrieved
     */
    /*
    public function test_get_grade_structure() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // create all test data
        $course = $this->getDataGenerator()->create_course();
    }
*/
    /**
     * Check that a single grade can be transferred and also detects various problems with the transfer.
     */

    public function test_transfer_grade() {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Create an assessment mapping for test course.
        list($formdata, $mappings) = $this->create_assessment_mapping($course);
print_r($mappings);
        // Get the grade transfer mappings
        $transfermapping = \local_bath_grades_transfer_assessment_mapping::get_by_cm_id($coursemodule->cmid);
        // Create a user and enrol on the course
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Create the sits mapping and sits enrolment - required to be included in grade transfer.
        $coursecontext = context_course::instance($course->id);
        $sitsmapping = $DB->get_record('sits_mappings', array("courseid"=>$course->id));
        $enrol = $DB->get_record('enrol', array("courseid"=>$course->id, "enrol"=>"manual"));
        $u_enrol = $DB->get_record('user_enrolments', array("userid"=>$user->id, "enrolid"=>$enrol->id ));
        $ra = $DB->get_record('role_assignments', array("userid"=>$user->id, "contextid"=>$coursecontext->id));
        $DB->insert_record('sits_mappings_enrols', array("map_id"=>$sitsmapping->id, "u_enrol_id"=>$u_enrol->id, "ra_id"=>$ra->id));

        // Get grade item for course module and apply a grade for the user.
        $grade_item = $DB->get_record('grade_items', array("itemmodule"=>$modulename, "iteminstance"=>$coursemodule->id));
        grade_update(
            $grade_item->itemtype . '/' .$grade_item->itemmodule,
            $course->id,
            $grade_item->itemtype,
            $grade_item->itemmodule,
            $grade_item->iteminstance,
            $grade_item->itemnumber,
            array( $user->id => array( "userid"=>$user->id, "rawgrade"=>60))
        );

        $assessmentgrades = new \local_bath_grades_transfer_assessment_grades();

        // THIS IS THE FUNCTION WE ARE TESTING...
        $gradetransfer->transfer_mapping2($transfermapping->id, array($user->id), $assessmentgrades);
        print_r($DB->get_records('local_bath_grades_log'));
        print_r($DB->get_records('task_adhoc'));
    }

    /**
     * Test that an assessment mapping can be locked
     */
    public function test_lock_mapping() {

        $this->resetAfterTest();

    }

    /**
     * Test that an assessment mapping can be locked
     */
    public function test_unlock_mapping() {

    }

    /**
     * Check that a single grade will not transfer if it does not meet specific requirements
     */
    public function test_reject_transfer_grade() {
        // Grade is not a whole number.

        // Grade is not out of 100.

        // Grade not in SAS export.

        // Grade already exists in SAS export.

        // SPR code not found.

        // Transfer fails.

    }

    // END OF UNIT TESTS
    // START OF PRIVATE FUNCTIONS

    private function create_sits_mapping($insertmapping, $currentacademicyear) {
        global $DB;

        $insertmapping['acyear'] = $currentacademicyear;
        if( $DB->insert_record('sits_mappings', $insertmapping ) ) {
            return $insertmapping;
        };
    }

    private function create_initial_lookups($insertlookups, $insertoccurrences, $currentacademicyear) {
        global $DB;

        foreach ($insertlookups as $k => $v) {
            $insertlookups[$k]->academicyear = $currentacademicyear;
        }
        foreach ($insertlookups as $k => $v) {
            $id_temp = $v->id;
            $id = $DB->insert_record('local_bath_grades_lookup', $v);
            foreach ($insertoccurrences as $k => $v) {
                if ($v->lookupid == $id_temp) {
                    $insertoccurrences[$k]->lookupid = $id;
                }
            }
        }
    }

    private function create_assessment_mapping($course) {
        global $CFG, $DB;

        // create all test data
        $assessmenttime = time();
        $modulename = 'assign';

        // Set plugin config value to enable modules to do grade transfer
        $id = $DB->get_field('config_plugins', 'id', array('plugin'=>'local_bath_grades_transfer', 'name'=>'bath_grades_transfer_use'));
        $DB->update_record('config_plugins', array('value'=>'mod_assign', 'id'=>$id ));

        // Set up initial lookup data.
        require($CFG->dirroot . '/local/bath_grades_transfer/tests/test_data_db.php');
        $gradetransfer = new \local_bath_grades_transfer();
        $gradetransfer->samisdata->restwsclient = new \test_bath_grades_transfer_rest_client();
        $this->create_sits_mapping($insertmapping, $gradetransfer->currentacademicyear);
        $this->create_initial_lookups($insertlookups, $insertoccurrences, $gradetransfer->currentacademicyear);

        // Use fake rest WS client and sync lookups.
        $assessmentlookup = new local_bath_grades_transfer_assessment_lookup();
        $assessmentlookup->samisdata->restwsclient = new \test_bath_grades_transfer_rest_client();
        $assessmentlookup->sync_remote_assessments($course->id);

        // Use first lookup in the list for mapping.
        $uselookup = $DB->get_record_sql('SELECT * FROM {local_bath_grades_lookup} WHERE expired = 0 LIMIT 1');

        // Create course module.
        $formdata = new stdClass();
        $formdata->course                               = $course->id;
        $formdata->name                                 = 'Test 1';
        $formdata->grade                                = 100;
        $formdata->bath_grade_transfer_samis_lookup_id  = $uselookup->id;
        $formdata->bath_grade_transfer_time_start       = $assessmenttime;

        // THIS REPLICATES THE SAVING OF THE ASSIGN SETTINGS FORM IN MOODLE - THIS IS BEING TESTED HERE
        $coursemodule = $this->getDataGenerator()->create_module($modulename, $formdata);

        // Get the grade transfer mappings
        $conditions = array();
        $conditions["coursemodule"] =  $coursemodule->cmid;
        $mappings = $DB->get_records('local_bath_grades_mapping', $conditions);

        return array($formdata, $mappings);
    }
}