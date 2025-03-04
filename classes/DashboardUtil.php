<?php

namespace Stanford\IntakeDashboard;
use REDCap;
use Files;

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
            $label = strip_tags($label);

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

    /**
     * Returns the Google cloud storage bucket name used to store files on production
     * @return ?string
     */
    public function getStorageBucketName(){
        $sql = "select * from redcap_config where field_name ='" . "google_cloud_storage_api_bucket_name". "'";
        $q = db_query($sql);
        return db_fetch_assoc($q)['value'] ?? null;
    }

    /**
     * Returns file metadata from edocs
     * @param $docId
     * @param $projectId
     * @return mixed|null
     */
    public function getFileMetadata($docId, $projectId){
        if(empty($docId) || empty($projectId)){
            return null;
        }

        $sql = "select * from redcap_edocs_metadata where doc_id = '" . db_escape($docId). "' and (delete_date is null or (delete_date is not null and delete_date > '".NOW."'))"; // Allow future delete dates
        $sql .= " and project_id = " . $projectId;
        $q = db_query($sql);
        return db_fetch_assoc($q);
    }

    /**
     * Downloads a file to temp directory from localhost
     * @param $docMetadata
     * @param $parentId
     * @return boolean
     */
    public function downloadLocalhostFileToTemp($docMetadata, $parentId){
        $local_file = EDOC_PATH . \Files::getLocalStorageSubfolder($parentId, true) . $docMetadata['stored_name'];
        $timestamp = strtotime($docMetadata['stored_date']); // Convert to timestamp
        $currentTime = time(); // Get current timestamp

        // Check if the current time is within 2 minutes (120 seconds) of the storage date
        // This check is used to prevent copying files to children when the user has not uploaded a new file
        if (!(abs($currentTime - $timestamp) <= 120))
            return false;

        if (file_exists($local_file) && is_file($local_file)) {
            $localSavePath = APP_PATH_TEMP . $docMetadata['doc_name']; // Adjust directory as needed

            // Open remote file for reading and local file for writing
            $remoteFile = fopen($local_file, 'rb'); // Read in binary mode
            $localFile = fopen($localSavePath, 'wb');  // Write in binary mode

            if ($remoteFile && $localFile) {
                while (!feof($remoteFile)) {
                    fwrite($localFile, fread($remoteFile, 8192)); // Read and write in chunks
                }
                fclose($remoteFile);
                fclose($localFile);
                $this->getModule()->emDebug("File saved to: " . $localSavePath);
                return true;
            } else {
                $this->getModule()->emDebug("Failed to open file for reading/writing.");
                return false;
            }
        }
        return false;
    }

    public function determineFileUploadFieldValues($projectId, $recordId){
        $queryParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "fields" => ['protocol_upload', 'investigators_brochure', 'informed_consent', 'other_docs'],
            "records" => $recordId
        ];
        $parentFiles = json_decode(REDCap::getData($queryParams), true);
        return reset($parentFiles);
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