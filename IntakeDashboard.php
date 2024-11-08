<?php

namespace Stanford\IntakeDashboard;
require_once "emLoggerTrait.php";
require_once "classes/RepeatingForms.php";
require_once "classes/Sanitizer.php";


use ExternalModules\AbstractExternalModule;
use ExternalModules;
use Exception;
use REDCap;
use Project;


class IntakeDashboard extends AbstractExternalModule
{
    use emLoggerTrait;

    const BUILD_FILE_DIR = 'dashboard-ui/dist/assets';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Helper method for inserting the JSMO JS into a page along with any preload data
     * @param $data
     * @param $init_method
     * @return void
     */
    public function injectJSMO($data = null, $init_method = null): void
    {
        echo $this->initializeJavascriptModuleObject();

        // Output the script tag for loading the JSMO JavaScript file
        echo sprintf('<script src="%s"></script>', $this->getUrl("jsmo/jsmo.js", true));
    }

    /**
     * @return array
     */
    public function generateAssetFiles(): array
    {
        $cwd = $this->getModulePath();
        $assets = [];

        $full_path = $cwd . self::BUILD_FILE_DIR . '/';
        $dir_files = scandir($full_path);

        // Check if scandir failed
        if ($dir_files === false) {
            $this->emError("Failed to open directory: $full_path");
            return $assets; // Return an empty array or handle the error as needed
        }

        $dir_files = array_diff($dir_files, array('..', '.'));

        foreach ($dir_files as $file) {
            $url = $this->getUrl(self::BUILD_FILE_DIR . '/' . $file);
            $html = '';
            if (str_contains($file, '.js')) {
                $html = "<script type='module' crossorigin src='{$url}'></script>";
            } elseif (str_contains($file, '.css')) {
                $html = "<link rel='stylesheet' href='{$url}'>";
            }
            if ($html !== '') {
                $assets[] = $html;
            }
        }

        return $assets;
    }

    /**
     * This is the primary ajax handler for JSMO calls
     * @param $action
     * @param $payload
     * @param $project_id
     * @param $record
     * @param $instrument
     * @param $event_id
     * @param $repeat_instance
     * @param $survey_hash
     * @param $response_id
     * @param $survey_queue_hash
     * @param $page
     * @param $page_full
     * @param $user_id
     * @param $group_id
     * @return array|array[]|bool
     * @throws Exception
     */
    public function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance,
                                       $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
    {

        $sanitized = $this->sanitizeInput($payload);

        return match ($action) {
            'fetchIntakeParticipation' => $this->fetchIntakeParticipation(),
            default => throw new Exception ("Action $action is not defined"),
        };
    }

    public function redcap_survey_complete(
        $project_id,
        $record = null,
        $instrument,
        $event_id,
        $group_id = null,
        $survey_hash,
        $response_id = null,
        $repeat_instance = 1
    )
    {
        $a = 1;
    }

    /**
     * Sanitizes user input in the action queue nested array
     * @param $payload
     * @return array|null
     */
    public function sanitizeInput($payload): array|string
    {
        $sanitizer = new Sanitizer();
        return $sanitizer->sanitize($payload);
    }

    /**
     * @param $e
     * @return false|string
     */
    public function handleGlobalError($e): false|string
    {
        $msg = $e->getMessage();
        $this->emError("Error: $msg");
        return json_encode([
            "error" => $msg,
            "success" => false
        ]);
    }

    /**
     * @return string|false
     */
    public function fetchIntakeParticipation(): string|false
    {
        try {
            $username = $_SESSION['username'];
            if (empty($username))
                throw new \Exception('No username for current session');

            $parent_id = $this->getSystemSetting('parent-project');

            // Grab all intake IDs of a given user in join arm
            $params = array(
                "return_format" => "json",
                "project_id" => $parent_id,
                "redcap_event_name" => "user_info_arm_2",
                "fields" => array("type", "intake_id", "record_id"),
                "records" => $username
            );
            $res = json_decode(REDCap::getData($params), true);
            if (count($res)) {
                $ids = [];
                foreach ($res as $item) {
                    if (isset($item['intake_id'])) {
                        $ids[] = $item['intake_id'];
                    }
                }
                $params = array(
                    "return_format" => "json",
                    "project_id" => $parent_id,
                    "redcap_event_name" => "event_1_arm_1",
                    "records" => $ids
                );
                $sc = json_decode(REDCap::getData($params), true);
//                Blend records and add additional information to intake join response
                foreach ($sc as $intake_record) {
                    foreach ($res as $k => $join_item) {
                        if ($intake_record['record_id'] === $join_item['intake_id']) {
//                            Will change intake_complete to correct form , do it dynamically
                            $res[$k]['intake_complete'] = $intake_record['intake_complete'];
                            $res[$k]['pi_name'] = $intake_record['pi_name'];
                            $res[$k]['research_title'] = $intake_record['research_title'];
                        }
                    }
                }
            }

            return json_encode([
                "data" => $res,
                "success" => true
            ]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }

    }

    /**
     * @param $parent_id
     * @param $username
     * @return mixed
     * @throws Exception
     * Saves a user in the hash table with reference to a universal intake submission
     */
    public function saveUser($parent_id, $username)
    {
        $proj = new Project($parent_id);

        // Find the event ID for the "User" event
        $event_id = null;
        foreach ($proj->events as $key => $event) {
            if ($event['name'] === 'User') {
                $event_id = array_key_first($event['events']);
                break;
            }
        }

        // Ensure the event ID and form name are found
        if ($event_id === null || !isset($proj->eventsForms[$event_id])) {
            throw new Exception("User event or form not found.");
        }

        $form_name = reset($proj->eventsForms[$event_id]);
        $arm_num = $proj->eventInfo[$event_id]['arm_num'];
        $event_name = "{$form_name}_arm_{$arm_num}";

        // Create a RepeatingForms instance and get the next instance ID
        $rForm = new RepeatingForms('user_info', $event_id, $parent_id);
        $next_instance_id = $rForm->getNextInstanceId($username);

        // Prepare data for saving
        $saveData = [
            [
                "record_id" => $username,
                "type" => "secondary",
                "intake_id" => "5",
                "redcap_event_name" => $event_name,
                "redcap_repeat_instrument" => $form_name,
                "redcap_repeat_instance" => $next_instance_id
            ]
        ];

        // Save data using REDCap's saveData function
        return REDCap::saveData('58', 'json', json_encode($saveData), 'overwrite');
    }


    public function fetchRequiredSurveys()
    {
        try {
//            $settings = $this->getSystemSettings();
            $child_ids = $this->getSystemSetting('project-id');
            $parent_id = $this->getSystemSetting('parent-project');

            if (empty($parent_id) || empty($child_ids))
                throw new \Exception("Parent project or child projects have not been configured properly, exiting");
            $universal_survey_form_name = $this->getProjectSetting('universal-survey', $parent_id);

            $a = new \Project($parent_id);
            $surveyInfo = $a->surveys;

            $child_ids = reset($child_ids);
            foreach ($child_ids as $key => $value) {
                $a = new \Project($value);
                $surveyInfo = $a->surveys;
                // $b = saveData()
//            $url = REDCap::getSurveyLink($b, strtolower($instrument), $event_id, $ts_survey_instance);

            }
            return $child_ids;
        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }

    }
}
