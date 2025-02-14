<?php
namespace Stanford\IntakeDashboard;
use REDCap;
use Survey;

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

        if(!empty($parentData['universal_id'])){
            $parentData['universal_id'] = $parentData['record_id'];
            $parentData['record_id'] = $universalId;
        }

        $parentData[$this->getPrimaryField()] = $currentChildRecordId;
        $res = REDCap::saveData($childId, 'json', json_encode([$parentData]), 'normal');

        if (!empty($res['errors'])) {
            // Child form names have to match parent form names - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name
            $errorString = $res['errors'];
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
        $queryParams = [
            "return_format" => "json",
            "project_id" => $this->getParentProjectId(),
            "records" => $universalId
        ];

        if (!defined('PROJECT_ID')){ //If we are navigating from deactivation (still have to update child records) - getData will be outside of project context
            define("PROJECT_ID", $this->getParentProjectId()); //Force project context to enable getFieldNames
            global $Proj;
            $Proj = new \Project($this->getParentProjectId());
        }

        // Grab all field names for first two required surveys
        $currentChildFields = REDCap::getFieldNames($pSettings['universal-survey-form-immutable']);
        $currentChildFields = array_merge($currentChildFields, REDCap::getFieldNames($pSettings['universal-survey-form-mutable']));
        $childKeys = array_fill_keys($currentChildFields, 1);

        $parentData = json_decode(REDCap::getData($queryParams), true);
        $parentData = reset($parentData);

        foreach($parentData as $field => $val){
            if(!isset($childKeys[$field])){
                unset($parentData[$field]);
            }
        }
        return $parentData;
    }

    /** Function that is triggered upon mutable survey being updated
     *  Copies New Universal intake data to the child project records that are linked
     * @param $recordId String record_id of completed parent survey (universal id)
     * */
    public function updateParentData($recordId){
        $childId = $this->getChildProjectId();
        $foundChildRecords = $this->childRecordExists($recordId);

        if (!is_null($foundChildRecords)) {
            // Child records exist, update all of them
            $parentData = $this->prepareChildRecord($recordId);
            foreach ($foundChildRecords as $record) {
                $parentData['record_id'] = $record['record_id']; //Replace record id of parent data with found child for copying
                $res = REDCap::saveData($childId, 'json', json_encode([$parentData]));

                if (!empty($res['errors'])) {
                    // Child form names have to match parent form names - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name
                    $errorString = $res['errors'];
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

    // Query child project for any matching records given universal id
    public function childRecordExists($universalId, $additionalParams = []){
        $params = [
            "return_format" => "json",
            "project_id" => $this->getChildProjectId(),
            "filterLogic" => "[universal_id] = $universalId"
        ];

        if(!empty($additionalParams))
            $params = array_merge($params, $additionalParams);

        $parentData = json_decode(REDCap::getData($params), true);

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

            return [
                'form_name' => $survey['form_name'],
                'title' => $survey['title'],
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
        $url = REDCap::getSurveyLink($record, $first_survey['form_name'], $eventID, 1, $this->getChildProjectId(), false);
        return $url;
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
        $eventId = $this->getChildEventId($formName);
        return REDCap::getSurveyLink($childRecordId, $formName, $eventId, 1, $this->getChildProjectId());
    }

    //Returns the main survey form for a given child project - (First survey)
    public function getMainSurveyFormName(){
        $pro = new \Project($this->getChildProjectId());
        $survey = reset($pro->surveys);
        return !empty($survey['form_name']) ? $survey['form_name'] : null;
    }

    public function getChildEventId($formName){
        $pro = new \Project($this->getChildProjectId());
        foreach($pro->eventsForms as $eventId => $event) {
            if(in_array($formName, $event))
                return $eventId;
        }
        return null;
    }

    public function getSurveyTimestamp($childRecordId){
        $pro = new \Project($this->getChildProjectId());
        $formName = $this->getMainSurveyFormName();
        $eventId = $this->getChildEventId($formName);

        //Grab survey completion timestamp
        $survey_id = $pro->forms[$formName]['survey_id'];
        return Survey::isResponseCompleted($survey_id, $childRecordId, $eventId, 1, true);


    }
}
