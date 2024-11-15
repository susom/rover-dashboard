<?php

/** @var \Stanford\IntakeDashboard\IntakeDashboard $module */

$module->injectJSMO();
$files = $module->generateAssetFiles();
//$test = $module->checkUserDetailAccess("redcap", "1");
// Inject username info to dom
$globalUsername = $_SESSION['username'];
//$urls = $module->fetchRequiredSurveys();
//$surveys = $module->fetchIntakeParticipation();
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
    <script>
        const globalUsername = <?php echo json_encode($globalUsername); ?>;
    </script>
</head>
<body>
    <div id="root"></div>
</body>
</html>