<?php

namespace Stanford\IntakeDashboard;
require_once "emLoggerTrait.php";
require_once "Utilities/RepeatingForms.php";
require_once "Utilities/Sanitizer.php";
//require_once "classes/ModuleCore/ModuleCore.php";
require_once("classes/Child.php");
require_once ("classes/DashboardUtil.php");

use ExternalModules;
use Exception;
use Files;
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

    /**
     * //Required for link-based access from control panel (non-admin)
     * @param $project_id
     * @param $link
     * @return mixed|null
     */
    function redcap_module_link_check_display($project_id, $link)
    {
        if(empty($_SESSION['username']))
            return null;
        return $link;
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
        try{
            $sanitized = $this->sanitizeInput($payload);
            return match ($action) {
                'fetchIntakeParticipation' => $this->fetchIntakeParticipation(),
                'getUserDetail' => $this->getUserDetail($sanitized),
                'fetchRequiredSurveys' => $this->fetchRequiredSurveys($sanitized),
                'toggleProjectActivation' => $this->toggleProjectActivation($sanitized),
                'newChildRequest' => $this->newChildRequest($sanitized),
                'getChildSubmissions' => $this->getChildSubmissions($sanitized),
                default => throw new Exception ("Action $action is not defined"),
            };
        } catch (\Exception $e ) {
            return $this->handleGlobalError($e);
        }

    }

    /**
     * @param int $project_id
     * @param string|NULL $record
     * @param string $instrument
     * @param int $event_id
     * @param int|NULL $group_id
     * @param string|NULL $survey_hash
     * @param int|NULL $response_id
     * @param int $repeat_instance
     * @return void
     * @throws Exception
     */
    public function redcap_save_record(
        int    $project_id,
        string $record = NULL,
        string $instrument,
        int $event_id,
        int $group_id = NULL,
        string $survey_hash = NULL,
        int $response_id = NULL,
        int $repeat_instance = 1)
    {
        //Functionality here serves to update child records in case edits are made on data-entry page (doesn't trigger survey complete hook)
        if(!isset($survey_hash)){ //Save record hook triggered from data-entry page only
            $parent_id = $this->getSystemSetting('parent-project');
            $successFileMetadata = [];

            if($project_id === intval($parent_id)){ // Only trigger if parent intakes are updated
                $pSettings = $this->getProjectSettings($parent_id);
                $util = new DashboardUtil($this, $pSettings);

                if($instrument === $pSettings['universal-survey-form-mutable']){ // Only have to handle file uploads on the mutable form
                    if($pSettings['enable-file-copying']){ //If setting is enabled
                        $file_fields = $util->checkFileChanges($project_id, $record);
                        $successFileMetadata = $util->saveFilesToTemp($file_fields, $parent_id);

                        //Update file field cache in parent here for subsequent saves
                        $util->updateFileCache($parent_id, $record);
                    }
                }

                //Iterate through all linked children and overwrite with new parent data
                foreach($pSettings['project-id'] as $childProjectId) {
                    $child = new Child($this, $childProjectId, $parent_id, $pSettings);

                    // Update record data for each child, copying from parent
                    $child->updateParentData($record, $instrument);

                    // If there were any downloaded files, copy them to each record / child combo
                    if(count($successFileMetadata) > 0){
                        foreach($successFileMetadata as $variable => $fileMetadata){
                            $child->copyFileFromParent($fileMetadata, $variable, $record, null);
                        }
                    }
                }
            }
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
            $completedIntake = reset($completedIntake);
            $successFileMetadata = [];

            if(!empty($completedIntake)){
                if($parent_id !== $project_id){
                    // Child survey has been saved from dashboard for the first time, we have to copy data from the parent project
                    $child = new Child($this, $project_id, $parent_id, $pSettings);
                    $child->saveParentData($completedIntake['universal_id'], $record);

                    if($pSettings['enable-file-copying']) {
                        // Copy all mutable files over, no restriction as we know they don't exist yet
                        $util = new DashboardUtil($this, $pSettings);
                        $file_fields = $util->determineFileUploadFieldValues($parent_id, $completedIntake['universal_id']);
                        $successFileMetadata = $util->saveFilesToTemp($file_fields, $parent_id);
                    }

                    // If there were any downloaded files, copy them to each record / child combo
                    if(count($successFileMetadata) > 0){
                        foreach($successFileMetadata as $variable => $fileMetadata){
                            // Pass $record as the new Child ID -> this will restrict the scope to only updating the current record
                            $child->copyFileFromParent($fileMetadata, $variable, $completedIntake['universal_id'], $record);
                        }
                    }

                } else {
                    // Universal immutable survey form has been saved for the first time (new intake)
                    if($instrument === $pSettings['universal-survey-form-immutable']) {
                        // Determine username and set permissions for dashboard
                        $requesterUsername = $this->determineREDCapUsername($completedIntake['requester_sunet_sid'], $completedIntake['requester_email']);
                        if (!empty($requesterUsername)) {
                            $this->saveUser($requesterUsername, $completedIntake['record_id']);
                        } else {
                            $submitted_sunet_sid = $completedIntake['requester_sunet_sid'];
                            $submitted_email = $completedIntake['requester_email'];
                            $this->emError("Username cannot be determined for Universal Survey: record $record project $parent_id");
                            REDCap::logEvent(
                                "Username cannot be determined on Intake submission",
                                "Username : $submitted_sunet_sid \n Email: $submitted_email \n Dashboard access has not been granted, likely due to an incorrect email \n access will require manual entry",
                                "",
                                "$record"
                            );
                        }

                    } else if($instrument === $pSettings['universal-survey-form-mutable']) {
                        // Mutable survey has been altered via dashboard - Can occur infinite times
                        $proj = new Project($parent_id);
                        $event_name = $this->generateREDCapEventName($proj, $pSettings['user-info-event']);

                        // Function will add new users / delete old users
                        $this->validateUserPermissions($project_id, $record, $event_name);

                        if($pSettings['enable-file-copying']) {
                            // File copy functionality
                            $util = new DashboardUtil($this, $pSettings);
                            $file_fields = $util->checkFileChanges($project_id, $record);
                            $successFileMetadata = $util->saveFilesToTemp($file_fields, $parent_id);

                            //Update file field cache in parent here for subsequent saves
                            $util->updateFileCache($parent_id, $record);
                        }

                        //Iterate through all linked children and overwrite with new parent data
                        foreach($pSettings['project-id'] as $childProjectId) {
                            $child = new Child($this, $childProjectId, $parent_id, $pSettings);
                            $child->updateParentData($record, $instrument);

                            // For each file field -> update each child record file field
                            if(count($successFileMetadata) > 0){
                                foreach($successFileMetadata as $variable => $fileMetadata){
                                    $child->copyFileFromParent($fileMetadata, $variable, $record, null);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e ) {
            // No need to return anything from global error handler. This is a hook
            $this->emError("Error in redcap_survey_complete for PID $project_id, record $record, Reason:", $e->getMessage());
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
            // Update all children with new data
            $requiredChildPIDs = $this->getRequiredChildPIDs($pSettings);
            foreach($requiredChildPIDs as $childProjectId) {
                $child = new Child($this, $childProjectId, $parent_id, $pSettings);
                $child->updateParentData($completedIntake['record_id'], $pSettings['universal-survey-form-immutable']);
            }

            return json_encode(["data" => $completedIntake, "success" => true]);
        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
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
    public function saveUser($username, $universalId)
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
                "intake_id" => $universalId,
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

            // Generate survey link for main page
            $link = $this->framework->getPublicSurveyUrl($parent_id);

            if (empty($userIntakes)) {
                return json_encode(["data" => [], "link" => $link, "success" => true]);
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

            $this->emDebug("User $username attempting to access intakes for UID $uid, they have no access");
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
            $requiredChildPIDs = $this->getRequiredChildPIDs($projectSettings);

            //Parse fields & convert labels for UI render of submitted form
            $pretty_immutable = $completedIntake[0];
            $pretty_mutable = $mutableIntake[0];
            $excluded = ["requester_lookup", "pi_lookup", "one_lookup", "webauth_user"];

            $du = new DashboardUtil($this, $projectSettings);
            $pretty_immutable = $du->prepareFieldsForRender($parentId, $pretty_immutable, $excluded);
            $pretty_mutable = $du->prepareFieldsForRender($parentId, $pretty_mutable, $excluded);

            $childSurveys = $project->surveys;
            $mutableUrl = [];
            foreach($childSurveys as $id => $survey) {
                $childEventId = $this->getChildEventId($project, $survey['form_name']);
                if($survey['form_name'] === $projectSettings['universal-survey-form-mutable'])
                    $mutableUrl = REDCap::getSurveyLink(reset($completedIntake)['record_id'], $survey['form_name'], $childEventId, 1, $parentId);
            }

            //Grab survey completion timestamps
            $survey_id = $project->forms[$projectSettings['universal-survey-form-immutable']]['survey_id'];
            $survey_id_mutable = $project->forms[$projectSettings['universal-survey-form-mutable']]['survey_id'];

            // Reduce array
            $completedIntake = reset($completedIntake);
            $mutableIntake = reset($mutableIntake);

            //Manually add timestamp if completed
            $completedIntake['completion_ts'] = Survey::isResponseCompleted($survey_id, $payload['uid'], $projectSettings['universal-survey-event'], 1, true);
            $mutableIntake['completion_ts'] = Survey::isResponseCompleted($survey_id_mutable, $payload['uid'], $projectSettings['universal-survey-event'], 1, true);

            //Manually add agnostic completed variable if form changes for frontend logic
            $completedIntake['complete'] = $completedIntake[$projectSettings['universal-survey-form-immutable'] . '_complete'];
            unset($completedIntake[$projectSettings['universal-survey-form-immutable'] . '_completed']);
            $mutableIntake['complete'] = $mutableIntake[$projectSettings['universal-survey-form-mutable'] . '_complete'];
            unset($mutableIntake[$projectSettings['universal-survey-form-mutable'] . '_completed']);

            // Adding username to query link
            $current_user = $_SESSION['username'];
            return json_encode([
                "surveys" => $this->generateSurveyTitles($payload['uid'], $requiredChildPIDs),
                "completed_form_immutable" => $completedIntake,
                "completed_form_mutable" => $mutableIntake,
                "completed_form_pretty" => [$pretty_immutable, $pretty_mutable],
                "mutable_url" => "$mutableUrl&last_editing_user=$current_user",
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
        $fields[] = $form . '_complete';

        $detailsParams = [
            "return_format" => "json",
            "project_id" => $parentId,
            "records" => $universalId,
            "fields" => $fields,
            "events" => $event
        ];

        return json_decode(REDCap::getData($detailsParams), true);
    }

    /**
     * @param $projectSettings
     * @return array
     */
    private function getRequiredChildPIDs($projectSettings)
    {
        $requiredChildPIDs = [];
        foreach($projectSettings['project-id'] as $pid)
            $requiredChildPIDs[] = $pid;

        return $requiredChildPIDs;
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
    private function newChildRequest($payload): false|string
    {
        try {
            if(empty($payload['child_id']) || empty($payload['universal_id']))
                throw new \Exception("Missing child ID or Universal ID");

            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);

            $child = new Child($this, $payload['child_id'], $parent_id, $pSettings);
            $url = $child->getNewSurveyUrl($payload['universal_id']);
            return json_encode(["url" => $url, "success" => true]);
        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
    }

    /**
     * Given a child PID + Universal record ID: determine
     * @param $payload
     * @return false|string|void
     */
    private function getChildSubmissions($payload){
        try {
            if(empty($payload['child_pid']) || empty($payload['universal_id']))
                throw new \Exception("Missing child ID or Universal ID");

            $parent_id = $this->getSystemSetting('parent-project');
            $pSettings = $this->getProjectSettings($parent_id);
            $child = new Child($this, $payload['child_pid'], $parent_id, $pSettings);
            $submissions = $child->allChildRecordsExist($payload['universal_id']);

            // Grab first form name for this child
            $mainSurvey = $child->getMainSurveyFormName();

            //Iterate over all submissions to determine if we need to render a return link
            foreach($submissions as $in => $submission){
                $completionVariable = $mainSurvey . "_complete";
                if(isset($submission[$completionVariable]) && $submission[$completionVariable] !== "2"){
                    $surveyLink = $child->getSurveyLink($submission['record_id']);
                    $username = $_SESSION['username'];
                    $submissions[$in]['survey_url'] = "$surveyLink&dashboard_submission_user=$username";
                }

                // Grab timestamp for dashboard display
                $submissions[$in]['survey_completion_ts'] = $child->getSurveyTimestamp($submission['record_id']);

                // Set default field to make it easier to render survey completion status on dashboard (form name agnostic)
                $submissions[$in]['child_survey_status'] = $child->determineChildSurveyStatus($submission, $completionVariable);

                //Get pretty form to render submitted information
                $du = new DashboardUtil($this, $pSettings);
                $pretty = $du->prepareFieldsForRender($payload['child_pid'], $submissions[$in], [], $mainSurvey);
                $submissions[$in]['completed_form_pretty'] = $pretty;
            }

            return json_encode(["data" => $submissions, "success" => true]);
        } catch (\Exception $e) {
            return $this->handleGlobalError($e);
        }
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
}
