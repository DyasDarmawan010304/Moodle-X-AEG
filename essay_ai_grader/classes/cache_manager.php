<?php
namespace local_essay_ai_grader;

defined("MOODLE_INTERNAL") || die();

class cache_manager {
    public static function get_config($assignmentid) {
        global $DB;
        return $DB->get_record("local_essay_ai_config", array("assignid" => $assignmentid));
    }
    
    public static function get_api_key($keytype) {
        global $DB;
        return $DB->get_record("local_essay_ai_apikeys", array("keytype" => $keytype, "active" => 1));
    }
}
?>
