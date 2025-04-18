<?php
namespace Stanford\IntakeDashboard;
use REDCap;
use Survey;
use Files;

class Child {
    private $module;
    private $parentSettings;
    private $childProjectId;
    private $parentProjectId;

    public function __construct($module, $childPid, $parentPid, $settings)
    {
        $this->setModule($module);
        $this->setChildProjectId($childPid);
        $this->setParentProjectId($parentPid);
        $this->setParentSettings($settings);
    }

    /** Function that is triggered upon child request / survey completion
     * @param $universalId String record_id of completed parent survey (universal id)
     * */
    public function saveParentData($universalId, $currentChildRecordId)
    {
        $childId = $this->getChildProjectId();
        $parentData = $this->prepareChildRecord($universalId);
        $ts = $this->getParentSurveyTimestamp($universalId);

        if(!empty($parentData['universal_id'])){
            $parentData['universal_id'] = $parentData['record_id'];
            $parentData['record_id'] = $universalId;
        }

        $parentData['univ_last_update'] = $ts;
        $parentData[$this->getPrimaryField()] = $currentChildRecordId;
        $res = REDCap::saveData($childId, 'json', json_encode([$parentData]), 'normal');

        if (!empty($res['errors'])) {
            // Child form names have to match parent form names - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name
            $errorString = is_array($res['errors']) ? json_encode($res['errors']) : $res['errors'];
            $this->getModule()->emError(
                "Failed to save data from parent to child. Parent Record ID: $universalId, Child PID: $childId. Reason: $errorString"
            );
            REDCap::logEvent(
                "Failed to save data from parent to child. Parent Record ID: $universalId, Child PID: $childId. Likely field mismatch."
            );
        }

    }

    /**
     * Creates payload of variables for saving to children records based on parent data
     * @param $universalId
     * @return false|mixed
     */
    public function prepareChildRecord($universalId){
        $pSettings = $this->getParentSettings();
        $cSettings = $this->getModule()->getProjectSettings($this->getChildProjectId());

        $queryParams = [
            "return_format" => "json",
            "project_id" => $this->getParentProjectId(),
            "records" => $universalId
        ];

        if (!defined('PROJECT_ID') || PROJECT_ID !== $this->getParentProjectId()){ //If we are navigating from deactivation (still have to update child records) - getData will be outside of project context
            define("PROJECT_ID", $this->getParentProjectId()); //Force project context to enable getFieldNames
            global $Proj;
            $Proj = new \Project($this->getParentProjectId());
        }

        // Grab all field names for first two required surveys from the parent
        $currentParentFields = REDCap::getFieldNames($pSettings['universal-survey-form-immutable']);
        $currentParentFields = array_merge($currentParentFields, REDCap::getFieldNames($pSettings['universal-survey-form-mutable']));
        $childKeys = array_fill_keys($currentParentFields, 1);

        // Grab parent (source of truth data)
        $parentData = json_decode(REDCap::getData($queryParams), true);
        $parentData = reset($parentData);

        // Remove all extra fields not present on the first two surveys
        foreach($parentData as $field => $val){
            if(!isset($childKeys[$field])){
                unset($parentData[$field]);
            }
        }

        // If the naming for the forms between parent / child no longer matches
        if(!empty($cSettings['child-universal-survey-form-immutable']) && !empty($cSettings['child-universal-survey-form-mutable'])){
            //Complete fields will not copy correctly, edit them
            if($pSettings['universal-survey-form-immutable'] !== $cSettings['child-universal-survey-form-immutable']) {
                $completeName = $pSettings['universal-survey-form-immutable'] . "_complete";
                $newCompleteName = $cSettings['child-universal-survey-form-immutable'] . "_complete";
                $parentData[$newCompleteName] = $parentData[$completeName];
                unset($parentData[$completeName]);
            }

            if($pSettings['universal-survey-form-mutable'] !== $cSettings['child-universal-survey-form-mutable']){
                $completeName = $pSettings['universal-survey-form-mutable'] . "_complete";
                $newCompleteName = $cSettings['child-universal-survey-form-mutable'] . "_complete";
                $parentData[$newCompleteName] = $parentData[$completeName];
                unset($parentData[$completeName]);
            }
        }

        // Explicitly unset file-field-cache-json : not needed in children projects
        if(isset($pSettings['file-field-cache-json']))
            unset($parentData[$pSettings['file-field-cache-json']]);

        return $parentData;
    }

    /** Function that is triggered upon mutable survey being updated
     *  Copies New Universal intake data to the child project records that are linked
     * @param $recordId String record_id of completed parent survey (universal id)
     * @param $instrument
     * */
    public function updateParentData($recordId, $instrument){
        $childId = $this->getChildProjectId();
        $pSettings = $this->getParentSettings();

        if($instrument == $pSettings['universal-survey-form-immutable']){ //If updating immutable data (active/inactive options) we have to query every child regardless of activity
            $foundChildRecords = $this->allChildRecordsExist($recordId);
        } else { //Otherwise filter out the inactive projects
            $foundChildRecords = $this->childRecordExists($recordId);
        }


        if (!is_null($foundChildRecords)) {
            // Child records exist, update all of them
            $parentData = $this->prepareChildRecord($recordId);
            $ts = $this->getParentSurveyTimestamp($recordId);
            foreach ($foundChildRecords as $record) {
                $parentData['record_id'] = $record['record_id']; //Replace record id of parent data with found child for copying
                $parentData['univ_last_update'] = $ts;
                $res = REDCap::saveData($childId, 'json', json_encode([$parentData]));

                if (!empty($res['errors'])) {
                    // Child form names have to match parent form names - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name
                    $errorString = is_array($res['errors']) ? json_encode($res['errors']) : $res['errors'];
                    $this->getModule()->emError(
                        "Failed to save data from parent to child. Parent Record ID: $recordId, Child PID: $childId. Reason: $errorString"
                    );
                    REDCap::logEvent(
                        "Failed to save data from parent to child. Parent Record ID: $recordId, Child PID: $childId. Likely field mismatch."
                    );
                }
            }
        }
    }

    /**
     * @param $universalId
     * @return void
     * Returns record data for any matching records given universal id (deactivated intakes included)
     */
    public function allChildRecordsExist($universalId){
        $params = [
            "return_format" => "json",
            "project_id" => $this->getChildProjectId(),
            "filterLogic" => "[universal_id] = $universalId"
        ];
        $parentData = json_decode(REDCap::getData($params), true);

        if(count($parentData))
            return $parentData;
        else
            return null;
    }

    /**
     * @param $universalId
     * @param $childRecordId
     * @return mixed|null
     * Fetch child record data for any matching records given universal id (omits inactive intakes)
     */
    public function childRecordExists($universalId, $childRecordId = null): mixed
    {
        // After a child survey is saved for the first time, we only need to return the specific record rather than all for updates
        if(isset($childRecordId)){
            $params = [
                "return_format" => "json",
                "project_id" => $this->getChildProjectId(),
                "filterLogic" => "[universal_id] = $universalId and [record_id] = $childRecordId and [intake_active] = 1"
            ];
        } else { // Regular request, return all linked children to this universal id
            $params = [
                "return_format" => "json",
                "project_id" => $this->getChildProjectId(),
                "filterLogic" => "[universal_id] = $universalId and [intake_active] = 1"
            ];
        }

        $parentData = json_decode(REDCap::getData($params), true);

        //Grab child project settings
        $pSettings = $this->getModule()->getProjectSettings($this->getChildProjectId());
        $trackingStatus = $pSettings['status-field'] ?? "submission_status";

        /**
         * Filter all child records that have a status besides [1 / 5 / 77] so we do not update them (files and data)
         * 1 - processing , 5 -> processing - awaiting additional updates , 77 -> default "Received" are only statuses that allow updating
         */
        foreach ($parentData as $key => $rec) {
            if (in_array($rec[$trackingStatus], ["0", "2", "3", "4", "99"], true)) {
                unset($parentData[$key]);
            }
        }

        if(count($parentData))
            return $parentData;
        else
            return null;
    }

    public function getSurveyTitle(): array
    {
        return array_map(function ($id) {
            $project = new \Project($id);
            $survey = reset($project->surveys);


            // Edit title of dashboard tab to ensure blank surveys have a naming value
            $title = !empty($survey['title']) ? $survey['title'] : ucwords(str_replace('_', ' ', $survey['form_name']));

            return [
                'form_name' => $survey['form_name'],
                'title' => $title,
                'child_id' => $id
            ];
        }, [$this->getChildProjectId()]);
    }

    // Generate a new survey url for a child record
    // Called by New Request -> will auto-create records
    public function getNewSurveyUrl($universalId): string
    {
        $settings = $this->getParentSettings();
        $project = new \Project($this->getChildProjectId());
        $first_survey = reset($project->surveys);
        $eventID = null;
        foreach($project->eventsForms as $eventId => $event) {
            foreach($event as $form) {
                if($form === $first_survey['form_name']) {
                    $eventID = $eventId;
                    break 2;
                }
            }
        }

        $record = $this->preCreateChildRecord($universalId);
        $username = $_SESSION['username'];
        $url = REDCap::getSurveyLink($record, $first_survey['form_name'], $eventID, 1, $this->getChildProjectId(), false);
        return "$url&dashboard_submission_user=$username";
    }

    // Create new record in child project
    private function preCreateChildRecord($universalId)
    {
        $pro = new \Project($this->getChildProjectId());
        $primary_field = $pro->table_pk;
        $reserved = REDCap::reserveNewRecordId($this->getChildProjectId());

        $this->getModule()->emDebug("Attempting to create new child record for $universalId");
        $saveData = [
            [
                "universal_id" => $universalId,
                $primary_field => $reserved
            ],
        ];


        $response = REDCap::saveData($this->getChildProjectId(), 'json', json_encode($saveData), 'overwrite');
        $this->getModule()->emDebug("PreCreating Child Record: $reserved for UID: $universalId using primary field $primary_field");

        if (!empty($response['errors'])) {
            $errorDetails = json_encode($response['errors']);
            $this->getModule()->emError("Error in pre-creation save data call: $errorDetails");
            return null;
        }

        return array_key_first($response['ids']);
    }

    /**
     * @param $childRecordData
     * @return string
     * Merges Survey Completion Statuses (0,1,2) for the completed survey & predefined variable statuses into a single variable
     */
    public function determineChildSurveyStatus($childRecordData, $completionVariable){
        if(!empty($childRecordData)){
            $cSettings = $this->getModule()->getProjectSettings($this->getChildProjectId());
            $trackingStatus = $cSettings['status-field'] ?? "submission_status";

            //Field that corresponds to survey completion status (0,1,2)
            $surveyCompletionStatus = $childRecordData[$completionVariable];

            //Field present on each child survey that admins of the project use to give transparency of their request
            $backendProcessingStatus = $childRecordData[$trackingStatus];

            // Coded as "Incomplete" - User still has to finish and click submit, as the survey is not completed
            if (in_array($surveyCompletionStatus, ["0", "1"])) return "0";

            // Coded as "Received" - User has completed the survey , default status after this point
            if (empty($backendProcessingStatus)) return "77";

            $statusMap = [
                "1", // Admins explicitly allow mutable data to overwrite this child
                "2", //"Processing - updates locked", // Updates locked, same as above but prevents any further updates to child
                "3", //"Complete"
                "4", //"Unable to process"
                "5", //"Processing - awaiting additional updates" - functionally the same as 1
                "99" // Canceled
            ];

            return in_array($backendProcessingStatus, $statusMap, true) ? $backendProcessingStatus : "77";
        }
        return "77";
    }

    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param mixed $module
     */
    public function setModule($module): void
    {
        $this->module = $module;
    }/**

    * @return mixed
    */public function getParentSettings()
    {
        return $this->parentSettings;
    }

    /**
    * @param mixed $parentSettings
    */public function setParentSettings($parentSettings): void
    {
        $this->parentSettings = $parentSettings;
    }

    /**
     * @return mixed
     */
    public function getChildProjectId()
    {
        return $this->childProjectId;
    }

    /**
     * @param mixed $projectId
     */
    public function setChildProjectId($projectId): void
    {
        $this->childProjectId = $projectId;
    }

    public function setParentProjectId($parentPid)
    {
        $this->parentProjectId = $parentPid;
    }

    /**
     * @return mixed
     */
    public function getParentProjectId()
    {
        return $this->parentProjectId;
    }

    public function getPrimaryField()
    {
        $pro = new \Project($this->getChildProjectId());
        return $pro->table_pk;
    }

    //Given the specific record ID in a child project, generate a link to said record
    public function getSurveyLink($childRecordId): ?string
    {
        $pro = new \Project($this->getChildProjectId());
        $formName = $this->getMainSurveyFormName();
        $eventId = $this->getEventId($this->getChildProjectId(), $formName);
        return REDCap::getSurveyLink($childRecordId, $formName, $eventId, 1, $this->getChildProjectId());
    }

    /**
     * @param $childRecordId
     * @return string|null
     * Render the survey url for document form given a child request
     */
    public function getDocumentsLink($childRecordId): ?string
    {
        $childProjectId = $this->getChildProjectId();
        $cSettings = $this->getModule()->getProjectSettings($childProjectId);
        $docForm = $cSettings['documents-form'] ?? "";

        if(empty($docForm)){
            $this->getModule()->emError("Documents form is not set in project $childProjectId");
            return null;
        }

        if (!defined('PROJECT_ID')){ // Have to explicitly set PROJECT_ID, Proj for getFieldNames
            define("PROJECT_ID", $this->getChildProjectId()); //Force project context to enable getFieldNames
            global $Proj;
            $Proj = new \Project($this->getChildProjectId());
        }

        // Grab all field names in documents form
        $fields = REDCap::getFieldNames($docForm);

        $queryParams = [
            "return_format" => "json",
            "project_id" => $this->getChildProjectId(),
            "records" => $childRecordId,
            "fields" => $fields
        ];

        $parentData = json_decode(REDCap::getData($queryParams), true);
        $parentData = reset($parentData);

        // Check if file upload fields have data
        if(!empty($parentData)){
            $filteredFiles = array_filter(
                $parentData,
                function ($value, $key) {
                    return preg_match('/^file\d+$/', $key) && !empty($value);
                },
                ARRAY_FILTER_USE_BOTH
            );

            if(!empty($filteredFiles)){ // If there are any file fields with a document uploaded, render link
                $eventId = $this->getEventId($this->getChildProjectId(), $docForm);
                return REDCap::getSurveyLink($childRecordId, $docForm, $eventId, 1, $this->getChildProjectId());
            } else { // Otherwise return null
                return null;
            }
        }

        // File form not completed, return null
        return null;
    }

    //Returns the main survey form for a given child project - (First survey)
    public function getMainSurveyFormName(){
        $pro = new \Project($this->getChildProjectId());
        $survey = reset($pro->surveys);
        return !empty($survey['form_name']) ? $survey['form_name'] : null;
    }

    public function getEventId($projectId, $formName){
        $pro = new \Project($projectId);
        foreach($pro->eventsForms as $eventId => $event) {
            if(in_array($formName, $event))
                return $eventId;
        }
        return null;
    }

    public function getSurveyTimestamp($childRecordId){
        $pro = new \Project($this->getChildProjectId());
        $formName = $this->getMainSurveyFormName();
        $eventId = $this->getEventId($this->getChildProjectId(), $formName);

        //Grab survey completion timestamp
        $survey_id = $pro->forms[$formName]['survey_id'];
        return Survey::isResponseCompleted($survey_id, $childRecordId, $eventId, 1, true);
    }

    public function getParentSurveyTimestamp($recordId){
        $pro = new \Project($this->getParentProjectId());
        $p = $this->parentSettings;
        $sv = null;
        foreach($pro->surveys as $surveyId => $survey) {
            if($survey['form_name'] == $p['universal-survey-form-mutable']) {
                $sv = $survey;
            }
        }

        $formName = !empty($sv['form_name']) ? $sv['form_name'] : null;
        $e = $this->getEventId($this->getParentProjectId(), $formName);
        $survey_id = $pro->forms[$formName]['survey_id'];
        return Survey::isResponseCompleted($survey_id, $recordId, $e, 1, true);
    }

    /**
     * @param $docMetadata
     * @param $fieldName
     * @param $recordId //Universal ID
     * @param null $newChildRecordId //Called from redcap_survey_complete to restrict copying all files from parent to a single new child record
     * @return int
     */
    public function copyFileFromParent($docMetadata, $fieldName, $recordId, $newChildRecordId = null){
        try {
            $localSavePath = APP_PATH_TEMP . $docMetadata['doc_name'];
            $doc_size = filesize($localSavePath);
            $mime_type = $docMetadata['mime_type'];
            $doc_name = $docMetadata['doc_name'];
            $childId = $this->getChildProjectId();
            $file_extension = getFileExt($docMetadata['doc_name']);

            if(!file_exists($localSavePath)){ // Temp file doesn't exist, we cant do anything
                return 0;
            }

            $childRecords = $this->childRecordExists($recordId, $newChildRecordId); //Fetch all matching records that require file update
            if(is_null($childRecords) || sizeof($childRecords) == 0){ // No matching records with the same universal ID exist in this child project
                $this->getModule()->emDebug("No matching records exist in child projectID: $childId for universalID : $recordId. Skipping copy");
                return 0;
            }

            // If not an allowed file extension, then prevent uploading the file
            if (!Files::fileTypeAllowed($docMetadata['doc_name'])) {
                unlink($localSavePath);
                $this->getModule()->emError("File type not allowed for upload.");
                return 0;
            }

            // If filesize is too large, exit
            if(($doc_size/1024/1024) > maxUploadSizeEdoc()){
                unlink($localSavePath);
                $this->getModule()->emError("File size exceeds maxUploadSizeEdoc.");
                return 0;
            }

            // Iterate over each matching record in current child, creating file copy from parent and updating link tables
            foreach($childRecords as $childRecord){
                $stored_name = date('YmdHis') . "_pid" . ($childId ?: "0") . "_" . generateRandomHash(6) . getFileExt($docMetadata['doc_name'], true);
                $result = 0;

                // Upload file to either bucket storage or local edocs
                if(!empty($GLOBALS['google_cloud_storage_api_bucket_name'])){
                    $googleClient = Files::googleCloudStorageClient();
                    $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);

                    // if pid sub-folder is enabled then upload the file under pid folder
                    if($GLOBALS['google_cloud_storage_api_use_project_subfolder']){
                        $stored_name = $this->getChildProjectId() . '/' . $stored_name;
                    }

                    $googleResp = $bucket->upload(file_get_contents($localSavePath), array('name' => $stored_name));
                    $result = 1;
                }else{
                    //Save the data in the correct child location in edocs
                    if (file_put_contents(EDOC_PATH . \Files::getLocalStorageSubfolder($childId, true) . $stored_name, file_get_contents($localSavePath))) {
                        $result = 1;
                    }
                }

                if($result){
                    // Update 3 required tables to allow files to be seen on REDCap UI
                    $docId = $this->updateEdocsMetadata($stored_name, $mime_type, $doc_name, $doc_size, $file_extension);
                    if($docId){ //Updated successfully
                        $res1 = $this->updateEdocsDataMapping($docId, $childRecord['record_id'], $fieldName);
                        $res2 = $this->updateRedcapData($docId, $childRecord['record_id'], $fieldName);
                    }
                } else {
                    $this->getModule()->emError("Failed to save file from local temp folder to $localSavePath");
                    return 0;
                }
            }
            return 1;
        } catch (\Exception $e) {
            $this->getModule()->emError('EXCEPTION IN COPY FILE FROM PARENT : ' . $e->getMessage());
        }
    }

    public function updateEdocsMetadata($storedName, $mimeType, $docName, $docSize, $fileExtension){
        $childId = $this->getChildProjectId();
        $this->getModule()->emLog("Updating redcap_edocs_metadata with values: $storedName, $mimeType, $docName, $docSize, $fileExtension, $childId");

        // Add file info the redcap_edocs_metadata table for retrieval later
        $q = db_query("INSERT INTO redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, stored_date)
						  VALUES ('" . db_escape($storedName) . "', '" . db_escape($mimeType) . "', '" . db_escape($docName) . "',
						  '" . db_escape($docSize) . "', '" . db_escape($fileExtension) . "',
						  " . ($childId ?: "null") . ", '".NOW."')");
        if(!$q)
            $this->getModule()->emError("Failed updating redcap_edocs_metadata with values: $storedName, $mimeType, $docName, $docSize, $fileExtension, $childId");

        return (!$q ? 0 : db_insert_id());
    }

    public function updateEdocsDataMapping($docId, $childRecordId, $fieldName){
        $childId = $this->getChildProjectId();
        $cSettings = $this->getModule()->getProjectSettings($this->getChildProjectId());
        $pSettings = $this->getParentSettings();

        $mutableFormName = $cSettings['child-universal-survey-form-mutable'] ?? $pSettings['universal-survey-form-mutable'];
        $eventId = $this->getEventId($this->getChildProjectId(), $mutableFormName);
        $this->getModule()->emLog("Updating redcap_edocs_data_mapping with values: $docId, $childId, $eventId, $childRecordId, $fieldName, 1");
        $query = "INSERT INTO redcap_edocs_data_mapping (doc_id, project_id, event_id, record, field_name, instance)
          VALUES ('" . db_escape($docId) . "', '" . db_escape($childId) . "', '" . db_escape($eventId) . "',
                  '" . db_escape((int) $childRecordId) . "', '" . db_escape($fieldName) . "', 1)";
        $q = db_query($query);

        if(!$q)
            $this->getModule()->emError("Failed updating redcap_edocs_data_mapping with values: $docId, $childId, $eventId, $childId, $fieldName, 1");

        return (!$q ? 0 : db_insert_id());
    }

    public function updateRedcapData($docId, $childRecordId, $fieldName){
        $childId = $this->getChildProjectId();
        $cSettings = $this->getModule()->getProjectSettings($this->getChildProjectId());
        $pSettings = $this->getParentSettings();
        $mutableFormName = $cSettings['child-universal-survey-form-mutable'] ?? $pSettings['universal-survey-form-mutable'];

        $eventId = $this->getEventId($this->getChildProjectId(), $mutableFormName);
        $dataTable = method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($childId) : "redcap_data";
        $this->getModule()->emLog("Updating $dataTable with values: $childId, $eventId, $childRecordId, $fieldName, $docId, NULL");
        $q = db_query("INSERT INTO `" . $dataTable . "` (project_id, event_id, record, field_name, value, instance)
               VALUES ('" . db_escape($childId) . "', '" . db_escape($eventId) . "', '" . db_escape((int)$childRecordId) . "', 
                       '" . db_escape($fieldName) . "', '" . db_escape($docId) . "', NULL)");

        if(!$q)
            $this->getModule()->emError("Failed updating $dataTable with values: $docId, $childId, $eventId, $childId, $fieldName, 1");

        return (!$q ? 0 : db_insert_id());
    }
}
