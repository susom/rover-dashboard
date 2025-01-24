<?php
namespace Stanford\IntakeDashboard;
use REDCap;
use Project;

class Child {
    private $module;
    private $parentSettings;
    private $childProjectId;

    public function __construct($module, $projectId, $settings)
    {
        $this->setModule($module);
        $this->setChildProjectId($projectId);
        $this->setParentSettings($settings);
    }

    /** Function that is triggered upon child request / survey completion
     * @param $recordId String record_id of completed parent survey (universal id)
     * */
    public function saveParentData($recordId)
    {
        $childId = $this->getChildProjectId();
        $pSettings = $this->getParentSettings();
        $queryParams = [
            "return_format" => "json",
            "project_id" => $pSettings['parentId'],
            "records" => $recordId
        ];

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
        $parentData['record_id'] = $recordId;
        $res = REDCap::saveData($childId, 'json', json_encode([$parentData]), 'overwrite');

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


    /** Function that is triggered upon mutable survey being updated
     *  Copies New Universal intake data to the child project records that are linked
     * @param $recordId String record_id of completed parent survey (universal id)
     * */
    public function updateParentData($recordId){
        $childId = $this->getChildProjectId();
        $pSettings = $this->getParentSettings();
        $queryParams = [
            "return_format" => "json",
            "project_id" => $pSettings['parentId'],
            "records" => $recordId
        ];

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

        $foundChildRecords = $this->childRecordExists($recordId);

        if (!is_null($foundChildRecords)) {
            // Child records exist, update all of them rather than creating a new record
            foreach ($foundChildRecords as $record) {
                $parentData['record_id'] = $record['record_id'];
                $res = REDCap::saveData($childId, 'json', json_encode([$parentData]), 'overwrite');

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
        } else {
            // No linked child record found, create a new record
            $parentData['universal_id'] = $parentData['record_id'];

            // Reserve a new record ID for the child
            $reservedRecordId = REDCap::reserveNewRecordId($childId);
            $parentData['record_id'] = $reservedRecordId;

            $res = REDCap::saveData($childId, 'json', json_encode([$parentData]), 'overwrite');

            if (!empty($res['errors'])) {
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

    // Query child records to determine if record needs to be updated or created
    public function childRecordExists($parentRecordId){
        $params = [
            "return_format" => "json",
            "project_id" => $this->getChildProjectId(),
            "filterLogic" => "[universal_id] = $parentRecordId"
        ];

        $parentData = json_decode(REDCap::getData($params), true);

        if(count($parentData))
            return $parentData;
        else
            return null;
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
}
