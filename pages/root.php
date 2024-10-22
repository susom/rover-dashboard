<?php

/** @var \Stanford\IntakeDashboard\IntakeDashboard $module */

$module->injectJSMO();
$files = $module->generateAssetFiles();
$urls = $module->fetchRequiredSurveys();
//$surveys = $module->fetchRequiredSurveys();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>TDS Universal intake dashboard</title>

    <?php foreach ($files as $file): ?>
        <?= $file ?>
    <?php endforeach; ?>
</head>
<body>
    <div id="root"></div>
</body>
</html>