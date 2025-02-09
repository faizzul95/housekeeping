<?php

require_once 'vendor/autoload.php';
require_once 'db.php';

use OnlyPHP\Housekeeping\DatabaseArchiver;

$db = new Database();
$archiver = new DatabaseArchiver($db->getConnection());

$archiver->logMessage("Starting backup process at :" . date('Y-m-d H:i:s'));
$result = $archiver
    ->driver('mysql')
    ->backupFrom('system_queue_job')
    // ->backupTo('system_permission')
    ->primaryKey('id')
    ->uniqueColumns('uuid, type')
    ->whereClause("DATE(created_at) = '2025-02-09'")
    ->mode('BO')  // Backup Only
    ->chunk(50000)  // Process 50000 records at a time
    ->onDebug()
    // ->allowDuplicate()
    ->run();
$archiver->logMessage("Ended backup process at :" . date('Y-m-d H:i:s'));

dd($result);

$action = isset($_POST['action']) ? $_POST['action'] : '';
$id = isset($_POST['id']) ? $_POST['id'] : '';

// if ($action == 1) {

//     $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
//     $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

//     $totalRecordsQuery = "SELECT COUNT(*) as count FROM system_queue_job";
//     $totalRecordsResult = $db->execute($totalRecordsQuery);
//     $totalRecords = $totalRecordsResult->fetch_assoc()["count"];

//     $query = "SELECT id, uuid, attempt, message, created_at FROM system_queue_job LIMIT $start, $length";
//     $result = $db->execute($query);
//     $data = array();

//     while ($row = $result->fetch_assoc()) {
//         $data[] = $row;
//     }

//     json([
//         "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
//         "recordsTotal" => $totalRecords,
//         "recordsFiltered" => $totalRecords,
//         "data" => $data
//     ]);
// }

// if ($action == 2 && empty($id)) {
//     $id = intval($_POST['id']);
//     $result = $db->execute("SELECT id, uuid, attempt, message, created_at FROM system_queue_job WHERE id = $id");
//     $row = $result->fetch_assoc();
//     json($row);
// }

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
