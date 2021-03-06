#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

$description = <<<EOT
Runs a JMESPath expression on the provided input or a test case.

Provide the JSON input and expression:
    echo '{}' | jp.php expression [--twice 1]

Or provide the path to a compliance script, a suite, and test case number:
    jp.php --script path_to_script --suite test_suite_number --case test_case_number [expression]


EOT;

// Parse the provided arguments
$args = array();
$currentKey = null;

for ($i = 1, $total = count($argv); $i < $total; $i++) {
    if ($i % 2) {
        if (substr($argv[$i], 0, 2) == '--') {
            $currentKey = str_replace('--', '', $argv[$i]);
        } else {
            $currentKey = trim($argv[$i]);
        }
    } else {
        $args[$currentKey] = $argv[$i];
        $currentKey = null;
    }
}

$runtime = null;
$expression = $currentKey;

if (isset($args['compile'])) {
    if ($args['compile'] == '1' || $args['compile'] == 'true' || $args['compile'] == 'false') {
        $runtime = \JmesPath\createRuntime(array(
            'compile' => __DIR__ . '/../compiled'
        ));
    }
}

if (!$runtime) {
    $runtime = \JmesPath\createRuntime();
}

if (isset($args['compile']) && $args['compile'] == 'false') {
    $runtime->clearCache();
    exit(0);
}

if (isset($args['file']) || isset($args['suite']) || isset($args['case'])) {

    if (!isset($args['file']) || !isset($args['suite']) || !isset($args['case'])) {
        die($description);
    }

    // Manually run a compliance test
    $path = realpath($args['file']);
    file_exists($path) or die('File not found at ' . $path);
    $json = json_decode(file_get_contents($path), true);
    $set = $json[$args['suite']];
    $data = $set['given'];

    if (!isset($expression)) {
        $expression = $set['cases'][$args['case']]['expression'];
        echo "Expects\n=======\n";
        if (isset($set['cases'][$args['case']]['result'])) {
            echo json_encode($set['cases'][$args['case']]['result'], JSON_PRETTY_PRINT) . "\n\n";
        } elseif (isset($set['cases'][$args['case']]['error'])) {
            echo "{$set['cases'][$argv['case']]['error']} error\n\n";
        } else {
            echo "NULL\n\n";
        }
    }

} elseif (isset($expression)) {
    // Pass in an expression and STDIN as a standalone argument
    $data = json_decode(stream_get_contents(STDIN), true);
} else {
    die($description);
}

if (isset($args['twice']) && $args['twice'] == '1') {
    $runtime->search($expression, $data);
}

$runtime->debug($expression, $data);
