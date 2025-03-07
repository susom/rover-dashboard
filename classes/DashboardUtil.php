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

    public function  saveFilesToTemp($fileFields, $parentId) {
        $successFileMetadata = [];
        $storageName = $this->getStorageBucketName(); //Name of Edocs bucket if Edocs is on cloud

        foreach ($fileFields as $variable => $docId) {
            if (empty($docId)) continue;

            $thisFile = $this->getFileMetadata($docId, $parentId);
            if (empty($thisFile)) continue;

            $downloadSuccess = !empty($storageName)
                ? $this->downloadGoogleCloudFileToTemp($thisFile)
                : $this->downloadLocalhostFileToTemp($thisFile, $parentId);

            if ($downloadSuccess) {
                $successFileMetadata[$variable] = $thisFile;
                $name = $thisFile['doc_name'];
                $this->getModule()->emDebug("File metadata saved successfully to Temp for file: $name for variable $variable in projectId $parentId");
            } else {
                $name = $thisFile['doc_name'];
                $this->getModule()->emError("File metadata failed to save to Temp for file: $name for variable $variable in projectId $parentId");
            }
        }

        return $successFileMetadata;
    }

    /**
     * Downloads a file to temp directory from localhost
     * @param $docMetadata
     * @param $parentId
     * @return boolean
     */
    public function downloadLocalhostFileToTemp($docMetadata, $parentId): bool
    {
//        if (!$this->isWithinTimeRange($docMetadata['stored_date']))
//            return false;

        $sourceFile = EDOC_PATH . \Files::getLocalStorageSubfolder($parentId, true) . $docMetadata['stored_name'];
        $destination = APP_PATH_TEMP . $docMetadata['doc_name'];

        return $this->downloadFile($sourceFile, $destination);

    }

    public function downloadGoogleCloudFileToTemp($docMetadata): bool
    {
//        if (!$this->isWithinTimeRange($docMetadata['stored_date']))
//            return false;

        $googleClient = Files::googleCloudStorageClient();
        $googleClient->registerStreamWrapper();

        $sourceFile = 'gs://' . $GLOBALS['google_cloud_storage_api_bucket_name'] . '/' . $docMetadata['stored_name'];
        $destination = APP_PATH_TEMP . $docMetadata['doc_name'];

        return $this->downloadFile($sourceFile, $destination);
    }

    /**
     * Downloads a file from a given source to the temp directory
     * @param string $sourceFilePath
     * @param string $destinationPath
     * @return bool
     */
    private function downloadFile($sourceFilePath, $destinationPath): bool
    {
        if (!file_exists($sourceFilePath) || !is_file($sourceFilePath)) {
            $this->getModule()->emError("File not found: $sourceFilePath");
            return false;
        }

        $source = fopen($sourceFilePath, 'rb'); // Read in binary mode
        $destination = fopen($destinationPath, 'wb'); // Write in binary mode

        if (!$source || !$destination) {
            $this->getModule()->emError("Failed to open file: " . ($source ? $destinationPath : $sourceFilePath));
            return false;
        }

        while (!feof($source)) {
            fwrite($destination, fread($source, 8192)); // Read and write in chunks
        }

        fclose($source);
        fclose($destination);

        $this->getModule()->emDebug("File saved to: $destinationPath");
        return true;
    }

    public function determineFileUploadFieldValues($projectId, $recordId)
    {
        $settings = $this->getEMSettings();

        // If file-fields are set, use them. Else default to 4 static fields
        if (!empty($settings['file-field']) && count($settings['file-field']) > 0){
            $fields = $settings['file-field'];
        } else {
            $this->getModule()->emError("File fields not set");
            return [];
        }

        $queryParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "fields" => $fields,
            "records" => $recordId
        ];
        $parentFiles = json_decode(REDCap::getData($queryParams), true);
        return reset($parentFiles);
    }

    /**
     * @param $parent_id
     * @param $record
     * @return void
     * @throws \Exception
     * Updates the file-field-cache with a new json object detailing the current state of uploaded files (docIDs)
     */
    public function updateFileCache($parent_id, $record): void
    {
        try {
            //Update file fields here
            $pSettings = $this->getEMSettings();
            $cacheField = $pSettings['file-field-cache-json'];

            if(!empty($cacheField)) {
                $pro = new \Project($parent_id);
                $primary_field = $pro->table_pk;

                //Fetch current state uploaded file fields
                $fileFields = $this->determineFileUploadFieldValues($parent_id, $record);
                $saveData = [
                    [
                        $primary_field => $record,
                        "$cacheField" => json_encode($fileFields),
                    ]
                ];

                $res = REDCap::saveData($parent_id, 'json', json_encode($saveData));
                if(!empty($res['errors'])) {
                    if(!is_string($res['errors']))
                        $errors = json_encode($res['errors']);
                    else
                        $errors = $res['errors'];
                    $this->getModule()->emError("Failed to update file cache for $parent_id. Errors: $errors");
                }
            }
        } catch (\Exception $e) {
            $this->getModule()->emError($e->getMessage());
        }

    }


    /**
     * @param $projectId string - parentId
     * @param $recordId string - universal recordId
     * files param should be an array with K = variable and V = DocID
     * @return array
     */
    public function checkFileChanges($projectId, $recordId): array
    {
        $settings = $this->getEMSettings();
        $cacheField = $settings['file-field-cache-json'];
        $currentFileState = $this->determineFileUploadFieldValues($projectId, $recordId);

        if(empty($cacheField)) // No field value, update all file fields everytime
            return $currentFileState;

        $queryParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "fields" => $cacheField,
            "records" => $recordId
        ];

        $fileCacheField = json_decode(REDCap::getData($queryParams), true);
        $fileCacheField = reset($fileCacheField);

        //This value will be the last saved file state in the record
        $previousFileState = json_decode($fileCacheField[$cacheField], true);

        //If there is no current value, we have never saved the JSON before, copy every file
        if(!empty($previousFileState)){
            return array_diff_assoc($currentFileState, $previousFileState);
        }

        return $currentFileState;
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