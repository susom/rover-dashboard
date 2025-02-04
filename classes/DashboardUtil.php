<?php

namespace Stanford\IntakeDashboard;
use REDCap;

class DashboardUtil
{
    private $module;
    private $emSettings;

    public function __construct($module, $settings)
    {
        $this->setModule($module);
        $this->setEMSettings($settings);
    }

    /**
     * @param $projectId
     * @param $fields
     * @return array
     * Removes hidden fields before sending the contents of getData to client
     */
    public function prepareFieldsForRender($projectId, $fields, $excluded = [], $form = ""): array
    {
        $project = new \Project($projectId);
        $new = [];

        //If specific form is provided, only render that form's data
        if(!empty($form)) {
            $formFields =  json_decode(REDCap::getDataDictionary($projectId, 'json', true, null, $form), true);
            $formFieldHash = [];
            foreach($formFields as $field) {
                $formFieldHash[$field['field_name']] = 1;
            }
            foreach($fields as $variableName => $val){
                if(!array_key_exists($variableName, $formFieldHash))
                    unset($fields[$variableName]);
            }
        }

        foreach($fields as $k => $v) {
            $label = isset($project->metadata[$k]) ? trim($project->metadata[$k]['element_label']) : null;
            if(!empty($label)){
                // Hidden fields should not be shown to client
                if(str_contains($project->metadata[$k]['misc'],'HIDDEN') || in_array($project->metadata[$k], $excluded))
                    continue;

                //Change key from variable to label for UI display
                $new[$k]["value"] = $v;
                $new[$k]["label"] = $label;
                if(isset($project->metadata[$k]['element_enum'])) {
                    //Check if field has enum values, if so, replace (checkbox, etc)
                    $parsed = $this->parseEnumField($project->metadata[$k]['element_enum']);
                    if(array_key_exists($v, $parsed))
                        $new[$k]["value"] = $parsed[$v];

                }
            }
        }
        return $new;
    }

    public function parseEnumField($str): array
    {
        //REDCap uses explicit \n embedded into strings
        $lines = explode("\\n", $str);

        $parsedArray = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode(',', $line, 2)); // Split only on first comma, trim spaces
            if (count($parts) === 2) {
                $parsedArray[(int)$parts[0]] = $parts[1]; // Convert key to integer
            }
        }

        // Output the parsed array
        return $parsedArray;
    }

    private function setModule($module)
    {
     $this->module = $module;
    }

    private function setEMSettings($settings)
    {
        $this->emSettings = $settings;
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getEMSettings()
    {
        return $this->emSettings;
    }
}