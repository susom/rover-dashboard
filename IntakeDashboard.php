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
            'checkUserDetailAccess' => $this->checkUserDetailAccess($payload),
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
        $detailsParams = [
            "return_format" => "json",
            "project_id" => $project_id,
            "records" => $record
        ];

        $completedIntake = json_decode(REDCap::getData($detailsParams), true);
        if(!empty($completedIntake))
            $this->createUserLinkingRecord(reset($completedIntake));
    }

    public function createUserLinkingRecord($intake) {
        // Create each link table entry per user
        $name = $intake['pi_name'];
        $first_name = $intake['first_name'];
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
     * @param $proj
     * @param $event_id
     * @return string
     * @throws Exception
     */
    public function generateREDCapEventName($proj, $event_id): string
    {
        if(empty($event_id))
            throw new \Exception("Event ID not passed, check config.json");
        $event_name = $proj->eventInfo[$event_id]['name'];
        $arm_num = $proj->eventInfo[$event_id]['arm_num'];
        $convertedName = strtolower(str_replace(' ', '_', $event_name));
        return $convertedName . "_arm_" . $arm_num;
    }

    /**
     * Fetches intake participation data for the current user.
     *
     * @return string|false JSON-encoded string of intake participation data, or false on error.
     */
    public function fetchIntakeParticipation(): string|false
    {
        try {
            $username = $_SESSION['username'] ?? null;
            if (!$username) {
                throw new \Exception('No username for current session');
            }

            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);
            $proj = new Project($parent_id);
            $full_user_event_name = $this->generateREDCapEventName($proj, $pSettings['user-info-event']);
            $full_intake_event_name = $this->generateREDCapEventName($proj, $pSettings['universal-survey-event']);
            // Fetch all intake IDs for the given user
            $initialParams = [
                "return_format" => "json",
                "project_id" => $parent_id,
                "redcap_event_name" => $full_user_event_name,
                "fields" => ["type", "intake_id", "record_id"],
                "records" => $username
            ];
            $userIntakes = json_decode(REDCap::getData($initialParams), true);

            if (empty($userIntakes)) {
                return json_encode(["data" => [], "success" => true]);
            }

            // Extract intake IDs from the response
            $intakeIds = array_column($userIntakes, 'intake_id');

            // Fetch additional details for each intake ID
            $detailsParams = [
                "return_format" => "json",
                "project_id" => $parent_id,
                "redcap_event_name" => $full_intake_event_name,
                "records" => $intakeIds
            ];

            $intakeDetails = json_decode(REDCap::getData($detailsParams), true);

            // Merge additional information into the user's intake data
            foreach ($userIntakes as &$intake) {
                foreach ($intakeDetails as $detail) {
                    if ($detail['record_id'] === $intake['intake_id']) {
                        $intake['intake_complete'] = $detail['intake_complete'] ?? null;
                        $intake['pi_name'] = $detail['pi_name'] ?? null;
                        $intake['research_title'] = $detail['research_title'] ?? null;
                        break;
                    }
                }
            }

            return json_encode(["data" => $userIntakes, "success" => true]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
    }

    /**
     * @param $username
     * @return mixed
     * @throws Exception
     * Saves a user in the hash table with reference to a universal intake submission
     */
    public function saveUser($username)
    {
        $parent_id = $this->getSystemSetting('parent-project');
        $pSettings = $this->getProjectSettings($parent_id);
        $proj = new Project($parent_id);

        // Find the event ID for the "User" event
        $event_id = $pSettings['user-info-event'];

        // Ensure the event ID and form name are found
        if ($event_id === null || !isset($proj->eventsForms[$event_id])) {
            throw new Exception("User event or form not found.");
        }

        $form_name = reset($proj->eventsForms[$event_id]);
        $event_name = $this->generateREDCapEventName($proj, $event_id);
        // Create a RepeatingForms instance and get the next instance ID
        $rForm = new RepeatingForms('user_info', $event_id, $parent_id);
        $next_instance_id = $rForm->getNextInstanceId($username);

        // Prepare data for saving
        //TODO Implement type naming , intake parameter
        $saveData = [
            [
                "record_id" => $username,
                "type" => "Secondary",
                "intake_id" => "5",
                "redcap_event_name" => $event_name,
                "redcap_repeat_instrument" => $form_name,
                "redcap_repeat_instance" => $next_instance_id,
                "{$form_name}_complete" => 2
            ]
        ];

        // Save data using REDCap's saveData function
        return REDCap::saveData('58', 'json', json_encode($saveData), 'overwrite');
    }

    /**
     * @param $payload
     * @return string
     */
    public function checkUserDetailAccess($payload): string
    {
        try {
            if (empty($payload['username']) || empty($payload['uid'])) {
                throw new \Exception("Either username or UID is empty");
            }

            $username = $payload['username'];
            $uid = $payload['uid'];
            $parentId = $this->getSystemSetting('parent-project');
            $projectSettings = $this->getProjectSettings($parentId);
            $project = new Project($parentId);

            $userEventName = $this->generateREDCapEventName($project, $projectSettings['user-info-event']);

            // Fetch all intake IDs for the given user
            $params = [
                "return_format" => "json",
                "project_id" => $parentId,
                "redcap_event_name" => $userEventName,
                "fields" => ["type", "intake_id", "record_id"],
                "records" => $username,
            ];

            $userIntakes = json_decode(REDCap::getData($params), true);

            // Iterate over to determine if current user has linked access to detail form
            foreach ($userIntakes as $submission) {
                if ($submission['intake_id'] === $uid) {
                    return json_encode([
                        "success" => true
                    ]);
                }
            }

            return json_encode([
                "success" => false
            ]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
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
