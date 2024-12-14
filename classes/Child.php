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

    // Function that copies Universal intake data to the child project
    public function saveParentData($recordId){
        $pSettings = $this->getParentSettings();

        $module = $this->getModule();
        $parentParams = [
            "return_format" => "json",
            "project_id" => $pSettings['parentId'],
            "records" => $recordId
        ];

        $currentChildFields = REDCap::getFieldNames($pSettings['universal-survey-form-immutable']);
        $currentChildFields = array_merge($currentChildFields, REDCap::getFieldNames($pSettings['universal-survey-form-mutable']));
        $childKeys = array_fill_keys($currentChildFields, 1);

        $parentData = json_decode(REDCap::getData($parentParams), true);
        $parentData = reset($parentData);
        foreach($parentData as $field => $val){
            if(!isset($childKeys[$field])){
                unset($parentData[$field]);
            }
        }

//        unset($parentData[0]['redcap_repeat_instrument']);
//        unset($parentData[0]['redcap_repeat_instance']);
//        unset($test[0]['type']);
//        unset($test[0]['intake_id']);
//        unset($test[0]['user_info_complete']);
        $res = REDCap::saveData($this->getChildProjectId(), 'json', json_encode([$parentData]), 'overwrite');

        $a = 1;
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
