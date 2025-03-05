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

    public function isWithinTimeRange($storedDate, $rangeInSeconds = 120) {
        $timestamp = strtotime($storedDate); // Convert to timestamp
        return abs(time() - $timestamp) <= $rangeInSeconds;
    }

    public function saveFilesToTemp($fileFields, $parentId, $storageName) {
        $successFileMetadata = [];

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

//        $eDocsLocalFile = EDOC_PATH . \Files::getLocalStorageSubfolder($parentId, true) . $docMetadata['stored_name'];
//
//        if (file_exists($eDocsLocalFile) && is_file($eDocsLocalFile)) {
//            $localSavePath = APP_PATH_TEMP . $docMetadata['doc_name']; // Adjust directory as needed
//
//            // Open remote file for reading and local file for writing
//            $eDocsFile = fopen($eDocsLocalFile, 'rb'); // Read in binary mode
//            $tempFile = fopen($localSavePath, 'wb');  // Write in binary mode
//
//            if (!$eDocsFile) {
//                $this->getModule()->emError("Failed to open local file: $eDocsFile");
//                return false;
//            }
//
//            if (!$tempFile) {
//                $this->getModule()->emError("Failed to open temp file: $localSavePath");
//                return false;
//            }
//
//            while (!feof($eDocsFile)) {
//                fwrite($tempFile, fread($eDocsFile, 8192)); // Read and write in chunks
//            }
//            fclose($eDocsFile);
//            fclose($tempFile);
//            $this->getModule()->emDebug("File saved to: " . $localSavePath);
//            return true;
//        }
//        return false;
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

//        //Initialize Google client
//        $googleClient = Files::googleCloudStorageClient();
//        $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
//
//        //Allows interaction with Google files as if they were local
//        $googleClient->registerStreamWrapper();
//
//        $remoteFilePath = 'gs://' . $GLOBALS['google_cloud_storage_api_bucket_name'] . '/' . $docMetadata['stored_name'];
//        if (file_exists($remoteFilePath) && is_file($remoteFilePath)) {
//            $localSavePath = APP_PATH_TEMP . $docMetadata['doc_name'];
//
//            // Open remote file for reading and local file for writing
//            $remoteFile = fopen($remoteFilePath, 'rb'); // Read in binary mode
//            $localFile = fopen($localSavePath, 'wb');  // Write in binary mode
//
//            if (!$remoteFile) {
//                $this->getModule()->emError("Failed to open remote file: $remoteFilePath");
//                return false;
//            }
//
//            if (!$localFile) {
//                $this->getModule()->emError("Failed to open local file: $localSavePath");
//                return false;
//            }
//
//            while (!feof($remoteFile)) {
//                fwrite($localFile, fread($remoteFile, 8192)); // Read and write in chunks
//            }
//
//            fclose($remoteFile);
//            fclose($localFile);
//
//            $this->getModule()->emDebug("File $remoteFilePath saved to: $localSavePath Successfully");
//            return true;
//        }
//        return false;
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

    public function determineFileUploadFieldValues($projectId, $recordId){
        $settings = $this->getEMSettings();

        // If file-fields are set, use them. Else default to 4 static fields
        if(!empty($settings['file-field']) && count($settings['file-field']) > 0)
            $fields = $settings['file-field'];
        else
            $fields = ['protocol_upload', 'investigators_brochure', 'informed_consent', 'other_docs'];

        $queryParams = [
            "return_format" => "json",
            "project_id" => $projectId,
            "fields" => $fields,
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