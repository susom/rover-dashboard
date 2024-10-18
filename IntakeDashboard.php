<?php

namespace Stanford\IntakeDashboard;
require_once "emLoggerTrait.php";


use ExternalModules\AbstractExternalModule;
use ExternalModules;
use Exception;


class IntakeDashboard extends AbstractExternalModule
{
    use emLoggerTrait;
    const BUILD_FILE_DIR = 'dashboard-ui/dist/assets';

    public function __construct()
    {
        parent::__construct();
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
    public function generateAssetFiles(): array {
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

//        $sanitized = $this->sanitizeInput($payload);

//        return match ($action) {
//            'runReport' => $this->runReport($payload, 'data_entry'),
//            'fetchFieldNames' => $this->fetchFieldNames($payload),
//            'downloadCSV' => $this->downloadCSV($payload),
//            default => throw new Exception ("Action $action is not defined"),
//        };
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

    public function handleGlobalError($e){
        $this->emError($e->getMessage());

        $err = array(
            "success" => false,
            "error" => $e->getMessage() ?? "Invalid request."
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($err);
//        $this->exitAfterHook(); //necessary
        die;
    }

    public function fetchRequiredSurveys() {
        $settings = $this->getSystemSettings();
        $child_ids = $this->getSystemSetting('project-id');
        $child_ids = reset($child_ids);
        foreach ( $child_ids as $key => $value) {
            $a = new \Project($value);
            $surveyInfo = $a->surveys;
            // $b = saveData()
//            $url = REDCap::getSurveyLink($b, strtolower($instrument), $event_id, $ts_survey_instance);

        }
        return $child_ids;
    }
}
