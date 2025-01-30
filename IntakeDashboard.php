<?php

namespace Stanford\IntakeDashboard;
require_once "emLoggerTrait.php";
require_once "Utilities/RepeatingForms.php";
require_once "Utilities/Sanitizer.php";
//require_once "classes/ModuleCore/ModuleCore.php";
require_once("classes/Child.php");

use ExternalModules;
use Exception;
use REDCap;
use Project;
use Survey;


class IntakeDashboard extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    const BUILD_FILE_DIR = 'dashboard-ui/dist/assets';
//    private $moduleCore;

    public function __construct()
    {
        parent::__construct();

    }

//    public function setModuleCore(){
//        $moduleCore = new ModuleCore($this);
//    }
//
//    public function getModuleCore(){
//        if(!$this->moduleCore)
//            $this->setModuleCore(new ModuleCore($this));
//        return $this->moduleCore;
//    }

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
        try{
            $sanitized = $this->sanitizeInput($payload);
            return match ($action) {
                'fetchIntakeParticipation' => $this->fetchIntakeParticipation(),
                'getUserDetail' => $this->getUserDetail($payload),
                'fetchRequiredSurveys' => $this->fetchRequiredSurveys($payload),
                'toggleProjectActivation' => $this->toggleProjectActivation($payload),
                'newChildRequest' => $this->newChildRequest($payload),
                default => throw new Exception ("Action $action is not defined"),
            };
        } catch (\Exception $e ) {
            $this->handleGlobalError($e);
        }

    }

    /**
     * @param $project_id
     * @param $record
     * @param $instrument
     * @param $event_id
     * @param $group_id
     * @param $survey_hash
     * @param $response_id
     * @param $repeat_instance
     * @return void
     * @throws Exception
     * Function that runs on each survey completion
     */
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
        try {
            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);

            $detailsParams = [
                "return_format" => "json",
                "project_id" => $project_id,
                "records" => $record
            ];

            $completedIntake = json_decode(REDCap::getData($detailsParams), true);
            if(!empty($completedIntake)){
                // Child survey has been saved, we have to copy data from the parent project
                if($parent_id !== $project_id){
                    $child = new Child($this, $project_id, $parent_id, $pSettings);
                    $child->saveParentData($record);
                } else {
                    $fields = reset($completedIntake);

                    // Universal immutable survey form has been saved for the first time (new intake)
                    if($instrument === $pSettings['universal-survey-form-immutable']) {
                        // Determine username
                        $requesterUsername = $this->determineREDCapUsername($fields['requester_sunet_sid'], $fields['requester_email']);
                        if (!empty($requesterUsername)) {
                            $this->saveUser($requesterUsername, $fields['record_id']);
                        } else {
                            $this->emError("Usernames not found for Universal Survey: record $record project $parent_id");
                        }

                    } else if($instrument === $pSettings['universal-survey-form-mutable']) { // Editable survey has been altered
                        $proj = new Project($parent_id);
                        $event_name = $this->generateREDCapEventName($proj, $pSettings['user-info-event']);

                        // Function will add new users / delete old users
                        $this->validateUserPermissions($project_id, $record, $event_name);
                        foreach($pSettings['project-id'] as $childProjectId) {
                            $child = new Child($this, $childProjectId, $parent_id, $pSettings);
                            $child->updateParentData($record);
                        }



                        //TODO Iterate through all linked children and overwrite with new parent data
                    }
                }
            }
        } catch (\Exception $e ) {
            $this->handleGlobalError($e);
        }

    }

    /**
     * @param $payload
     * @return void
     */
    public function toggleProjectActivation($payload): string
    {
        try {
            if (empty($payload['uid']))
                throw new \Exception("UID is empty");

            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);

            $completedIntake = $this->fetchParentRecordData($parent_id, $payload['uid'], $pSettings['universal-survey-event']);
            $completedIntake = reset($completedIntake);

            if($completedIntake['intake_active'] === "0")
                $completedIntake['intake_active'] = "1";
            else //if active is null or any other value besides 0, set explicitly as inactive.
                $completedIntake['intake_active'] = "0";

            //Set activation date change
            $completedIntake['active_change_date'] = date('Y-m-d');
            $completedIntake['deactivation_reason'] = $payload['reason'] ?? null;
            $completedIntake['deactivation_user'] = $_SESSION['username'] ?? null;
            $saveData = [
                $completedIntake
            ];

            // Save data using REDCap's saveData function
            $response = REDCap::saveData($parent_id, 'json', json_encode($saveData), 'overwrite');
            if(!empty($response['errors'])){
                throw new \Exception("Error on reactivation/deactivation save" . implode(', ', $response['errors']));
            }
            return json_encode(["data" => $completedIntake, "success" => true]);
        } catch (\Exception $e) {
            $this->handleGlobalError($e);
        }

    }


    /**
     * @return
     *
     */
    public function validateUserPermissions($projectId, $recordId, $childEventName) {
        $parentParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "records" => $recordId,
        ];

        // This parent data has already been saved, let it be source of truth
        $parentData = json_decode(REDCap::getData($parentParams), true);
        $parentData = reset($parentData);

        $savedUsers = array_filter([
                $this->determineREDCapUsername($parentData['requester_sunet_sid'], $parentData['requester_email']) ?? null => 'requester_sunet_sid',
                $this->determineREDCapUsername($parentData['sunet_sid'], $parentData['email']) ?? null => 'sunet_sid',
                $this->determineREDCapUsername($parentData['pi_sunet_sid'], $parentData['pi_email']) ?? null => 'pi_sunet_sid',
                $this->determineREDCapUsername($parentData["op_sunet_sid_1"], $parentData["op_email_1"]) ?? null => 'op_sunet_sid_1',
                $this->determineREDCapUsername($parentData["op_sunet_sid_2"], $parentData["op_email_2"]) ?? null => 'op_sunet_sid_2',
                $this->determineREDCapUsername($parentData["op_sunet_sid_3"], $parentData["op_email_3"]) ?? null => 'op_sunet_sid_3',
        ], function ($key) {
            return !empty($key);
        }, ARRAY_FILTER_USE_KEY);

        $userParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "events" => $childEventName
        ];

        //Get all users that exist within the permission schema for the dashboard
        $fullUserData = json_decode(REDCap::getData($userParams), true);
        $deletions = [];
        $instance_count = [];
        // Goal : check if user already has access, if removed, remove permissions, if not add permissions.
        // Iterate through each instance of user_info
        foreach($fullUserData as $entry) {
            if(array_key_exists($entry['record_id'],$instance_count))
                $instance_count[$entry['record_id']] += 1;
            else
                $instance_count[$entry['record_id']] = 1;

            if($entry['intake_id'] === $recordId) { // We have encountered a user that has access to this intake on dashboard
                $username = $entry['record_id'];
                if(!array_key_exists($username, $savedUsers)) { // saved users are only usernames that should have access.
                    //username does not exist as an entry in fullUserData (source of truth), remove
                    $deletions[] = $entry;
                } else { // User exists in fullUserData & saved users
                    //remove username from the list of users we have to check for later removal
                    unset($savedUsers[$username]);
                }
            }
        }


        //Delete users, this will allow users to delete themselves
        foreach($deletions as $userEntry) {
            //If users only have one repeating instance, delete the entire record
            if(array_key_exists($userEntry['record_id'], $instance_count) && $instance_count[$userEntry['record_id']] === 1) {
                $res = REDCap::deleteRecord($projectId, $userEntry['record_id'], null, null, null, null);
            } else { // Otherwise delete the specific instance
                $res = REDCap::deleteRecord($projectId, $userEntry['record_id'], null, $childEventName, $userEntry['redcap_repeat_instrument'], $userEntry['redcap_repeat_instance']);
            }
        }

        // SavedUsers now only has individuals who should be added
        foreach($savedUsers as $username => $role) {
            $res = $this->saveUser($username, $recordId);
        }

    }


    public function determineREDCapUsername($su_sid_field, $email_field) {
        if (!isset($email_field)) {
            $this->emError("Email field has not been submitted for $su_sid_field, no dashboard access will be given");
            return null; // Return null if email field is not set
        }

        $domains = [
            'stanford.edu' => $su_sid_field,
            'stanfordchildrens.org' => $email_field,
            'stanfordhealthcare.org' => $su_sid_field . '@stanfordhealthcare.org',
        ];

        foreach ($domains as $domain => $returnValue) {
            if (strpos($email_field, $domain) !== false) {
                return $returnValue;
            }
        }

        return null; // Return null if no domain matches
    }

    /**
     * @param $username
     * @return mixed
     * @throws Exception
     * Saves a user in the hash table with reference to a universal intake submission
     */
    public function saveUser($username, $recordId)
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

        // No need to check if the linkage already exists in user table, intake ID will always be unique
        // Prepare data for saving
        $saveData = [
            [
                "record_id" => $username,
                "type" => "Secondary",
                "intake_id" => $recordId,
                "redcap_event_name" => $event_name,
                "redcap_repeat_instrument" => $form_name,
                "redcap_repeat_instance" => $next_instance_id,
                "{$form_name}_complete" => 2
            ]
        ];

        // Save data using REDCap's saveData function
        return REDCap::saveData($parent_id, 'json', json_encode($saveData), 'overwrite');
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
     * Rendered on the intake dashboard view
     *
     * @return string|false JSON-encoded string of intake participation data, or false on error.
     */
    public function fetchIntakeParticipation(): string|false
    {
        try {
            $username = $_SESSION['username'] ?? null;

            if (is_null($username)) {
                throw new \Exception('No username for current session found, please refresh');
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
                        $survey_id = $proj->forms[$pSettings['universal-survey-form-immutable']]['survey_id'];
                        $timestamp = Survey::isResponseCompleted($survey_id, $detail['record_id'], $pSettings['universal-survey-event'], 1, true);
                        $intake['completion_timestamp'] = date('Y-m-d', strtotime($timestamp));
                        $intake['intake_complete'] = $detail['intake_complete'] ?? null;
                        $intake['pi_name'] = trim($detail['pi_f_name'] . " " . $detail['pi_l_name']);;
                        $intake['research_title'] = $detail['research_title'] ?? null;
                        $intake['intake_active'] = $detail['intake_active'] ?? null;
                        $intake['active_change_date'] = $detail['active_change_date'] ?? null;
                        break;
                    }
                }
            }

            // Generate survey link for main page
            $link = $this->getPublicSurveyUrl($parent_id);

//            $reserved = REDCap::reserveNewRecordId($parent_id);
//            $link = REDCap::getSurveyLink($reserved, $pSettings['universal-survey-form-immutable'], $pSettings['universal-survey-event'], 1, $parent_id);

            return json_encode([
                "data" => $userIntakes,
                "success" => true,
                "link" => $link
            ]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
    }

    public function checkIntakeActivity($parentId, $uid, $surveyEvent){
        $completedIntake = $this->fetchParentRecordData($parentId, $uid, $surveyEvent);
        $completedIntake = reset($completedIntake);
        if($completedIntake['intake_active'] === "0") //If explicitly set to zero return false, otherwise default to true
            return false;
        return true;
    }

    /**
     * @param $payload
     * @return string
     */
    public function getUserDetail($payload): string
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

            //Prevent detail page rendering (navigating) for inactive intake projects
//            if(!$this->checkIntakeActivity($parentId, $uid, $projectSettings['universal-survey-event']))
//                throw new \Exception("Intake is inactive, no detail access can be granted");

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
                    return $this->fetchRequiredSurveys($payload);
                }
            }
//            $this->emLog()
            return json_encode([
                "success" => false
            ]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
    }

    public function checkChildDataExists($universalId, $pid){
        $params = [
            "return_format" => "json",
            "project_id" => $pid,
            "filterLogic" => "[universal_id] = $universalId"
        ];

        $response = json_decode(REDCap::getData($params), true);
        if(count($response))
            return reset($response);
        return [];

    }

    /**
     * @param $project
     * @param $fields
     * @return array
     * Removes hidden fields before sending the contents of getData to client
     */
    public function filterHiddenFields($project, &$fields) {
        $new = [];
        $excluded = ["requester_lookup", "pi_lookup", "one_lookup"];

        foreach($fields as $k => $v) {
            if($project->metadata[$k] && (str_contains($project->metadata[$k]['misc'],'HIDDEN') || in_array($project->metadata[$k], $excluded))) { // Hidden fields should not be shown to client
                unset($fields[$k]);
            } else {
                $label = trim($project->metadata[$k]['element_label']);
                if(!empty($label)){
                    $new[$label] = $v;
                }
            }
        }
        return $new;
    }
    /**
     * @param $universalId
     * @return false|string
     */
    public function fetchRequiredSurveys($payload)
    {

        try {
            if (empty($payload['uid']))
                throw new \Exception("No Universal ID passed to fetchRequiredSurveys");

            $parentId = $this->getSystemSetting('parent-project');
            $project = new \Project($parentId);

            $projectSettings = $this->getProjectSettings($parentId);

            $completedIntake = $this->fetchParentRecordData($parentId, $payload['uid'], $projectSettings['universal-survey-event'], $projectSettings['universal-survey-form-immutable']);
            $mutableIntake = $this->fetchParentRecordData($parentId, $payload['uid'], $projectSettings['universal-survey-event'], $projectSettings['universal-survey-form-mutable']);

            $requiredChildPIDs = $this->getRequiredChildPIDs($completedIntake, $projectSettings);

            $pretty = $completedIntake[0];
            $pretty = $this->filterHiddenFields($project, $pretty);

            $childSurveys = $project->surveys;
            $mutableUrl = [];
            foreach($childSurveys as $id => $survey) {
                $childEventId = $this->getChildEventId($project, $survey['form_name']);
                if($survey['form_name'] === $projectSettings['universal-survey-form-mutable'])
                    $mutableUrl = REDCap::getSurveyLink(reset($completedIntake)['record_id'], $survey['form_name'], $childEventId, 1, $parentId);
            }

            //Grab survey completion timestamp
            $survey_id = $project->forms[$projectSettings['universal-survey-form-immutable']]['survey_id'];

            // Reduce array
            $completedIntake = reset($completedIntake);
            $mutableIntake = reset($mutableIntake);

            $completedIntake['completion_ts'] = Survey::isResponseCompleted($survey_id, $payload['uid'], $projectSettings['universal-survey-event'], 1, true);

            return json_encode([
//                "surveys" => $this->generateSurveyLinks($payload['uid'], $requiredChildPIDs),
                "surveys" => $this->generateSurveyTitles($payload['uid'], $requiredChildPIDs),
                "completed_form_immutable" => $completedIntake,
                "completed_form_mutable" => $mutableIntake,
                "completed_form_pretty" => $pretty,
                "mutable_url" => $mutableUrl,
                "success" => true
            ]);

        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
    }

    /**
     * @param $parentId
     * @param $universalId
     * @return mixed
     */
    private function fetchParentRecordData($parentId, $universalId, $event, $form = 'intake')
    {
        $formFields =  json_decode(REDCap::getDataDictionary($parentId, 'json', true, null, $form), true);
        $fields = [];

        foreach($formFields as $field)
            $fields[] = $field['field_name'];

        $detailsParams = [
            "return_format" => "json",
            "project_id" => $parentId,
            "records" => $universalId,
            "fields" => $fields,
            "events" => $event
        ];
        return json_decode(REDCap::getData($detailsParams), true);
//        $data = reset($data);
//        foreach($data as $field => $val) {
//            foreach($formFields as $full){
//                if($field === $full['field_name']){
//                    $data[$full['field_name']] = $val;
//                    unset($data[$field]);
//                }
//            }
//
//        }
//        return $data;
//        return json_decode(REDCap::getData($detailsParams2), true);
    }

    /**
     * @param $completedIntake
     * @param $projectSettings
     * @return array
     */
    private function getRequiredChildPIDs($completedIntake, $projectSettings)
    {
        $requiredChildPIDs = [];
        $firstRecord = reset($completedIntake);

        foreach($projectSettings['project-id'] as $pid)
            $requiredChildPIDs[] = $pid;

//        foreach ($firstRecord as $key => $value) {
//            if ($this->isValidServiceKey($key, $value)) {
//                $mappingKey = array_search($key, $projectSettings['mapping-field']);
//                if ($mappingKey !== false) {
//                    $requiredChildPIDs[] = $projectSettings['project-id'][$mappingKey];
//                }
//            }
//        }

        return $requiredChildPIDs;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    private function isValidServiceKey($key, $value)
    {
        return preg_match('/^serv_map_\d+$/', $key) && $value === "1";
    }

    //Creates the project-specific survey titles for a given array of Child PIDs
    private function generateSurveyTitles($universalId, $requiredChildPIDs): array
    {
        $parent_id = $this->getSystemSetting('parent-project');
        $pSettings = $this->getProjectSettings($parent_id);
        $titles = [];
        foreach($requiredChildPIDs as $childProjectId) {
            $child = new Child($this, $childProjectId, $parent_id, $pSettings);
            $titles = array_merge($titles, $child->getSurveyTitle());
        }

        return $titles;
    }

    private function newChildRequest($payload){
        try {
            if(empty($payload['child_id']))
                throw new \Exception("Missing child ID");

            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);

            $child = new Child($this, $payload['child_id'], $parent_id, $pSettings);
            $child->getNewSurveyUrl();
            return json_encode(["data" => [], "success" => true]);
        } catch (\Exception $e) {
            $this->handleGlobalError($e);
        }
    }

    /**
     * @param $universalId
     * @param $requiredChildPIDs
     * @return array
     * @throws Exception
     */
    private function generateSurveyLinks($universalId, $requiredChildPIDs)
    {
        $surveyLinks = [];

        foreach ($requiredChildPIDs as $childProjectId) {
            $item = [];
            $project = new \Project($childProjectId);
            $childInstrument = $this->getChildInstrument($project);
            $childEventId = $this->getChildEventId($project, $childInstrument);

            $check = $this->checkChildDataExists($universalId, $childProjectId);
//            if (empty($check)) {
            $recordId = $this->preCreateChildRecord($childProjectId, $universalId);
            $item['url'] = REDCap::getSurveyLink($recordId, $childInstrument, $childEventId, 1, $childProjectId);
//            } else {
//                $item['url'] = REDCap::getSurveyLink($check['record_id'], $childInstrument, $childEventId, 1, $childProjectId);
//            }

//            $item['complete'] = $check[$childInstrument . '_complete'];
//            $item['child_pid'] = $childProjectId;
            $item['form_name'] = reset($project->surveys)['form_name'];
            $item['title'] = reset($project->surveys)['title'];
            $surveyLinks[] = $item;
        }

        return $surveyLinks;
    }

    /**
     * @param $project
     * @return mixed|string
     */
    private function getChildInstrument($project)
    {
        $surveyInfo = $project->surveys;
        return reset($surveyInfo)['form_name']; // Assumes a single survey per child project
    }

    /**
     * @param $project
     * @param $childInstrument
     * @return int|string|null
     */
    private function getChildEventId($project, $childInstrument)
    {
        foreach ($project->eventsForms as $eventId => $forms) {
            if (in_array($childInstrument, $forms, true)) {
                return $eventId;
            }
        }

        return null;
    }

    private function preCreateChildRecord($childProjectId, $universalId)
    {
        $saveData = [
            ["record_id" => $universalId],
        ];

        $response = REDCap::saveData($childProjectId, 'json', json_encode($saveData), 'overwrite');
        if (!empty($response['errors'])) {
            $errorDetails = json_encode($response['errors']);
            throw new \Exception("Error in pre-creation save data call: $errorDetails");
        }

        return array_key_first($response['ids']);
    }
}
