<?php

require_once 'vendor/autoload.php';
require_once 'db.php';

use OnlyPHP\Housekeeping\DatabaseArchiver;

$db = new Database();
$archiver = new DatabaseArchiver($db->getConnection());

$testType = 1;
$chunkSize = 50000;

$archiver->logMessage("Starting backup process at : " . date('Y-m-d H:i:s'));

if ($testType == 1) {
    $result = $archiver
        ->backupFrom('system_queue_job')
        ->primaryKey('id')
        ->whereClause("DATE(created_at) <= '2023-04-01'")
        ->mode('BP')
        ->chunk($chunkSize)
        ->onDebug()
        ->run();
} else if ($testType == 2) {
    $result = $archiver
        ->backupFrom('system_queue_job')
        ->primaryKey('id')
        ->uniqueColumns('uuid, type')
        ->whereClause("DATE(created_at) = '2025-02-13'")
        ->mode('BO')
        ->chunk($chunkSize)
        ->onDebug()
        ->run();
} else if ($testType == 3) {
    $result = $archiver
        ->backupFrom('system_queue_job')
        ->backupTo('system_queue_job_' . date('Ymd_His'))
        ->primaryKey('id')
        ->uniqueColumns('uuid, type')
        ->whereClause("DATE(created_at) = '2025-02-13'")
        ->mode('BO')
        ->chunk($chunkSize)
        ->onDebug()
        ->allowDuplicate()
        ->run();
}

if ($testType == 0) {
    $result = $archiver
        ->backupFrom('system_queue_job_arc')
        ->backupTo('system_queue_job')
        ->primaryKey('id')
        ->whereClause("DATE(created_at) = '2025-02-13'")
        ->mode('BP')
        ->chunk($chunkSize)
        ->onDebug()
        ->run();
}

$archiver->logMessage("Ended backup process at : " . date('Y-m-d H:i:s') . "\n");
$archiver->logMessage("Result : \n\n" . json_encode($result, JSON_PRETTY_PRINT));

dd($result);

// Helper

function json($data, $code = 200)
{
    header("Content-type:application/json");

    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
}

function dump()
{
    array_map(function ($param) {
        echo '<pre>';
        var_dump($param);
        echo '</pre>';
    }, func_get_args());
}

function dd()
{
    array_map(function ($param) {
        echo '<pre>';
        print_r($param);
        echo '</pre>';
    }, func_get_args());
    die;
}
