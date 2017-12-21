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

class local_bath_grades_transfer_log
{
    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $coursemoduleid;
    /**
     * @var
     */
    public $userid;
    /**
     * @var
     */
    public $gradetransfermappingid;
    /**
     * @var
     */
    public $gradetransferred;
    public $assessmentlookupid;
    /**
     * @var
     */
    public $timetransferred;
    /**
     * @var
     */
    public $outcomeid;
    /**
     * @var
     */
    public $gradetransfererrorid;
    /**
     * @var string
     */
    private static $table = 'local_bath_grades_log';

    public function __construct() {

    }

    /**
     *
     */
    public static function get_logs($userid, $mappingid, $limit = null, $literaloutcomes = false) {
        global $DB;
        $logs = array();
        $fields = array();
        $fields[] = 'FROM_UNIXTIME(l.timetransferred) as \'timetransferred\'';
        $fields[] = 'l.gradetransferred';
        $sql = 'SELECT ';
        $sql .= implode(',', $fields);
        $table = '{local_bath_grades_log} l ';

        if ($literaloutcomes) {
            $otherfields[] = ',o.outcome';
            $otherfields[] = 'o.id';
            $sql .= implode(',', $otherfields);
            $sql .= ' FROM ' . $table;
            $join = " JOIN {local_bath_grades_outcome} o ON o.id = l.outcomeid";

            $sql .= $join;
        } else {
            $sql .= ' FROM ' . $table;
        }

        $sql .= " WHERE l.userid = :userid AND l.gradetransfermappingid = :mappingid ORDER BY l.timetransferred DESC";
        $rs = $DB->get_recordset_sql($sql, array('userid' => $userid, 'mappingid' => $mappingid), 0, $limit);
        if ($rs->valid()) {
            foreach ($rs as $record) {
                $logs[] = $record;
            }
        }
        return $logs;
    }

    /**
     * @param $id
     */
    public static function get_log_by_id($id) {

    }

    /**
     *
     */
    public function save() {
        global $DB;
        $data = new stdClass();
        $data->coursemoduleid = $this->coursemoduleid;
        $data->userid = $this->userid;
        $data->gradetransfermappingid = $this->gradetransfermappingid;
        $data->assessmentlookupid = $this->assessmentlookupid;
        $data->timetransferred = $this->timetransferred;
        $data->outcomeid = $this->outcomeid;
        $data->gradetransferred = $this->gradetransferred;
        if (!empty($data->errormessage)) {
            $error = new stClass();
            $error->errormessage = $data->errormessage;
            $lastinsertid = $DB->insert_record('local_bath_grades_error', $error);
            $data->gradetransfererrorid = $lastinsertid;
        }
        $DB->insert_record(self::$table, $data);
    }


}