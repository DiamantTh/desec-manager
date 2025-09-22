<?php
declare(strict_types=1);

use App\Database\DatabaseConnection;
use App\Service\StatusReporter;

require_once __DIR__ . '/vendor/autoload.php';

$configPath = __DIR__ . '/config/config.php';
$config = null;
if (file_exists($configPath)) {
    $config = require $configPath;
    DatabaseConnection::bootstrap($config);
}

$reporter = new StatusReporter($config);
$report = $reporter->generate();

$format = $_GET['format'] ?? 'json';

if ($format === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'overall=' . $report['overall_status'] . "\n";
    foreach ($report['checks'] as $name => $check) {
        $status = $check['status'] ?? 'ok';
        $message = $check['message'] ?? '';
        echo $name . '=' . $status;
        if ($message !== '') {
            echo ' #' . $message;
        }
        echo "\n";
    }
    if (!empty($report['metrics'])) {
        foreach ($report['metrics'] as $metric => $value) {
            echo 'metric_' . $metric . '=' . $value . "\n";
        }
    }
    exit;
}

if ($format === 'prometheus') {
    header('Content-Type: text/plain; version=0.0.4');
    echo $reporter->renderPrometheus($report);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
