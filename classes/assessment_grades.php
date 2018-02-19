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
 * Grade transfer assessment grades class
 * This class gives access to grades in SAMIS for reading and writing
 *
 * @package    local_bath_grades_transfer
 * @author     Hittesh Ahuja <h.ahuja@bath.ac.uk>
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2017 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/bath_grades_transfer/classes/assessment.php');

/**
 * Class local_bath_grades_transfer_assessment_grades
 */
class local_bath_grades_transfer_assessment_grades extends local_bath_grades_transfer_assessment
{
    /**
     * @var
     */
    public $student;
    /**
     * @var
     */
    public $name;
    /**
     * @var
     */
    public $assess_pattern;
    /**
     * @var
     */
    public $assess_item;
    /**
     * @var
     */
    public $attempt;
    /**
     * @var
     */
    public $grade;
    /**
     * @var
     */
    public $mark;
    /**
     * @var
     */
    public $year;
    /**
     * @var
     */
    public $period;
    /**
     * @var
     */
    public $module;
    /**
     * @var
     */
    public $mappingid;
    /**
     * @var
     */
    public $candidate; // Anonymous assessments.

    /**
     * @var
     */
    public $occurrence;

    /**
     * Fetches the ASSESSMENT DATA from SAMIS Web Service
     * @param $lookup
     * @return array
     * @throws \Exception
     */
    public function get_grade_strucuture_samis(\local_bath_grades_transfer_assessment_lookup $lookup) {
        // Check that it is a valid lookup.
        $structure = array();
        // From the attributes and map_code, get the grade structure.
        try {
            $remotegradestructures = $this->samisdata->get_remote_grade_structure($lookup);
            // Note: there is a speartate grade structure for each MAV occurrence.
            //var_dump($remotegradestructures[0]->assessments->assessment);
            foreach ($remotegradestructures[0]->assessments->assessment as $assessment) {
                if (!empty($assessment)) {
                    //foreach ($remotegradestructure->assessment as $assessment) {
                            if ($lookup->mabpnam == 'N') {
                                $structure[(string)$assessment->candidate] = $assessment;
                            }
                            else{
                                $structure[(string)$assessment->student] = $assessment;

                            }
                            /* foreach ($assessment as $objassessmentdata) {

                                if ($lookup->mabpnam == 'N') {
                                    $structure[(string)$objassessmentdata->candidate] = array
                                    (
                                        'assessment' => self::instantiate($objassessmentdata)
                                    );
                                } else {
                                    $structure[(string)$objassessmentdata->student] = array
                                    (
                                        'assessment' => self::instantiate($objassessmentdata)
                                    );
                                }
                            }*/

                    //} //for each
                } // end if
            } // end for each
        } catch (\Exception $e) {
            throw $e;
        } //end
        error_log(json_encode($structure),0);
        //echo json_encode($structure);
        return $structure;
    } // end function
}