<?php

$url = "";
$payload = "";
$threads = 500;
$requestCounter = 0;
$printedMsgs = array();
$lock = new \Mutex();

function printMsg($msg) {
    global $printedMsgs, $requestCounter, $lock;

    $lock->synchronized(function() use ($msg, &$printedMsgs, &$requestCounter) {
        if (!in_array($msg, $printedMsgs)) {
            echo "\n{$msg} after {$requestCounter} requests\n";
            $printedMsgs[] = $msg;
        }
    });
}

function handleStatusCodes($statusCode) {
    global $requestCounter;

    $requestCounter++;
    echo "\r{$requestCounter} requests have been sent";

    if ($statusCode == 429) {
        printMsg("You have been throttled");
    }
    if ($statusCode == 500) {
        printMsg("Status code 500 received");
    }
}

function sendGET() {
    global $url;

    $response = file_get_contents($url);
    $httpStatus = $http_response_header[0];

    preg_match('/\d{3}/', $httpStatus, $matches);
    $statusCode = $matches[0];

    handleStatusCodes($statusCode);
}

function sendPOST() {
    global $url, $payload;

    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => $payload
        )
    );
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $httpStatus = $http_response_header[0];

    preg_match('/\d{3}/', $httpStatus, $matches);
    $statusCode = $matches[0];

    handleStatusCodes($statusCode);
}

if ($argc < 2) {
    echo "Usage: php script.php -g '<url>' -p '<payload>' -t <threads>\n";
    exit(1);
}

for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case "-g":
            $url = $argv[++$i];
            break;
        case "-p":
            $payload = $argv[++$i];
            break;
        case "-t":
            $threads = (int)$argv[++$i];
            break;
        default:
            break;
    }
}

if (empty($url) || ($payload === "" && isset($argv[2]))) {
    echo "You must specify either a GET (-g) or POST (-p) request.\n";
    exit(1);
}

$threadsArray = array();

for ($i = 0; $i < $threads; $i++) {
    if (!empty($url)) {
        if (!empty($payload)) {
            $threadsArray[] = new Thread('sendPOST');
        } else {
            $threadsArray[] = new Thread('sendGET');
        }
    }
}

foreach ($threadsArray as $thread) {
    $thread->start();
}

foreach ($threadsArray as $thread) {
    $thread->join();
}
