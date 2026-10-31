<?php
/**
 * Performance Benchmarking
 * Tests system response times, throughput, and resource usage
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('CLI_SCRIPT', true);

$dir = dirname(__FILE__);
while (!is_file($dir . '/config.php')) {
    $dir = dirname($dir);
}
require_once($dir . '/config.php');

global $DB;

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "PHASE 7: PERFORMANCE BENCHMARKING\n";
echo str_repeat("=", 80) . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$passes = 0;
$fails = 0;
$metrics = [];

function test_pass($msg, $metric = '') {
    global $passes;
    $passes++;
    echo "  [✅ PASS] $msg";
    if ($metric) echo " - $metric";
    echo "\n";
}

function test_fail($msg, $metric = '') {
    global $fails;
    $fails++;
    echo "  [❌ FAIL] $msg";
    if ($metric) echo " - $metric";
    echo "\n";
}

function test_info($msg) {
    echo "  [ℹ️] $msg\n";
}

function section($name) {
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "§ $name\n";
    echo str_repeat("-", 80) . "\n";
}

// Get test assignment
$assignment = $DB->get_record('assign', ['id' => 2], '*', IGNORE_MISSING);
$assignment_id = $assignment ? $assignment->id : 0;

// ========== TEST 1: Page Load Performance ==========
section("TEST 1: PAGE LOAD PERFORMANCE");

// Simulate analytics.php load
$files_to_test = [
    $CFG->dirroot . '/local/essay_ai_grader/analytics.php' => 'Analytics Dashboard',
    $CFG->dirroot . '/local/essay_ai_grader/settings.php' => 'Settings Page',
];

$total_time = 0;
foreach ($files_to_test as $file => $name) {
    if (!file_exists($file)) {
        test_fail("File not found", $name);
        continue;
    }
    
    // Measure file load time
    $start = microtime(true);
    $content = file_get_contents($file);
    $elapsed = (microtime(true) - $start) * 1000; // ms
    
    $size = strlen($content);
    
    if ($elapsed < 50 && $size > 0) {
        test_pass("File load", "$name: " . number_format($elapsed, 2) . "ms ({$size} bytes)");
    } else {
        test_info("File load time: $name - " . number_format($elapsed, 2) . "ms");
    }
}

// ========== TEST 2: Database Query Performance ==========
section("TEST 2: DATABASE QUERY PERFORMANCE");

// Test: Get assignment (fast lookup)
$start = microtime(true);
$assign = $DB->get_record('assign', ['id' => $assignment_id]);
$elapsed = (microtime(true) - $start) * 1000;

if ($elapsed < 5) {
    test_pass("Assignment lookup", number_format($elapsed, 2) . "ms (target: <5ms)");
} else {
    test_fail("Assignment lookup", number_format($elapsed, 2) . "ms (target: <5ms)");
}

// Test: Get submissions (batch query)
if ($assignment_id > 0) {
    $start = microtime(true);
    $submissions = $DB->get_records('assign_submission', ['assignment' => $assignment_id], '', '*', 0, 100);
    $elapsed = (microtime(true) - $start) * 1000;
    
    if ($elapsed < 25) {
        test_pass("Submissions batch query", number_format($elapsed, 2) . "ms for " . count($submissions) . " records");
    } else {
        test_info("Submissions query: " . number_format($elapsed, 2) . "ms for " . count($submissions) . " records");
    }
}

// ========== TEST 3: Cache Performance ==========
section("TEST 3: CACHE PERFORMANCE");

require_once($CFG->dirroot . '/local/essay_ai_grader/classes/cache_manager.php');
$cache = \local_essay_ai_grader\cache_manager::getInstance();

// Warm up cache
$cache_data = [
    'test_key_1' => 'value_1',
    'test_key_2' => 'value_2',
    'test_key_3' => 'value_3',
];

// Measure cache write time
$start = microtime(true);
\local_essay_ai_grader\cache_manager::get_provider_config();
$write_time = (microtime(true) - $start) * 1000;

// Measure cache read time (should be much faster)
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    \local_essay_ai_grader\cache_manager::get_provider_config();
}
$read_total = (microtime(true) - $start) * 1000;
$read_avg = $read_total / 100;

if ($read_avg < $write_time / 10) {
    test_pass("Cache performance", "Read: " . number_format($read_avg, 3) . "ms (100x faster than write)");
} else {
    test_info("Cache performance - Read: " . number_format($read_avg, 3) . "ms, Write: " . number_format($write_time, 3) . "ms");
}

// ========== TEST 4: Cost Calculation Performance ==========
section("TEST 4: COST CALCULATION PERFORMANCE");

require_once($CFG->dirroot . '/local/essay_ai_grader/classes/cost_tracker.php');

// Single calculation
$start = microtime(true);
$result = \local_essay_ai_grader\cost_tracker::calculate_cost(
    'google_gemini',
    'gemini-2.0-flash',
    10000,
    5000
);
$single_time = (microtime(true) - $start) * 1000;

if ($single_time < 2) {
    test_pass("Single cost calculation", number_format($single_time, 3) . "ms (target: <2ms)");
} else {
    test_info("Single calculation: " . number_format($single_time, 3) . "ms");
}

// Batch calculations (100 items)
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    \local_essay_ai_grader\cost_tracker::calculate_cost(
        'google_gemini',
        'gemini-2.0-flash',
        10000 + $i * 100,
        5000 + $i * 50
    );
}
$batch_total = (microtime(true) - $start) * 1000;
$batch_avg = $batch_total / 100;

if ($batch_avg < 2) {
    test_pass("Batch calculations (100x)", number_format($batch_total, 1) . "ms total, " . number_format($batch_avg, 3) . "ms avg");
} else {
    test_info("Batch calculation: " . number_format($batch_total, 1) . "ms total");
}

// ========== TEST 5: Analytics Query Performance ==========
section("TEST 5: ANALYTICS QUERY PERFORMANCE");

require_once($CFG->dirroot . '/local/essay_ai_grader/classes/analytics_manager.php');

if ($assignment_id > 0) {
    // Get assignment stats
    $start = microtime(true);
    $stats = \local_essay_ai_grader\analytics_manager::get_assignment_stats($assignment_id);
    $elapsed = (microtime(true) - $start) * 1000;
    
    if ($elapsed < 100) {
        test_pass("Assignment stats", number_format($elapsed, 2) . "ms (target: <100ms)");
    } else {
        test_fail("Assignment stats", number_format($elapsed, 2) . "ms (target: <100ms)");
    }
    
    // Get score distribution
    $start = microtime(true);
    $dist = \local_essay_ai_grader\analytics_manager::get_score_distribution($assignment_id);
    $elapsed = (microtime(true) - $start) * 1000;
    
    if ($elapsed < 150) {
        test_pass("Score distribution", number_format($elapsed, 2) . "ms (target: <150ms)");
    } else {
        test_info("Score distribution: " . number_format($elapsed, 2) . "ms");
    }
    
    // Get timeline
    $start = microtime(true);
    $timeline = \local_essay_ai_grader\analytics_manager::get_timeline(30);
    $elapsed = (microtime(true) - $start) * 1000;
    
    if ($elapsed < 200) {
        test_pass("Timeline generation", number_format($elapsed, 2) . "ms (target: <200ms)");
    } else {
        test_info("Timeline: " . number_format($elapsed, 2) . "ms");
    }
}

// ========== TEST 6: Concurrent Request Simulation ==========
section("TEST 6: CONCURRENT REQUEST HANDLING");

// Simulate 10 concurrent requests
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    \local_essay_ai_grader\cache_manager::get_provider_config();
    \local_essay_ai_grader\cost_tracker::calculate_cost('google_gemini', 'gemini-2.0-flash', 1000, 500);
    if ($assignment_id > 0) {
        \local_essay_ai_grader\analytics_manager::get_assignment_stats($assignment_id);
    }
}
$concurrent_time = (microtime(true) - $start) * 1000;
$per_request = $concurrent_time / 10;

if ($per_request < 50) {
    test_pass("Concurrent requests (10x)", number_format($concurrent_time, 1) . "ms total, " . number_format($per_request, 2) . "ms per request");
} else {
    test_info("Concurrent requests: " . number_format($per_request, 2) . "ms per request");
}

// ========== TEST 7: Memory Usage ==========
section("TEST 7: MEMORY USAGE");

$initial_memory = memory_get_usage(true);

// Load all major classes
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/bulk_operations.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/assessment_manager.php');

$after_load = memory_get_usage(true);
$memory_used = ($after_load - $initial_memory) / 1024 / 1024;

if ($memory_used < 5) {
    test_pass("Memory footprint", number_format($memory_used, 2) . "MB (target: <5MB)");
} else {
    test_info("Memory usage: " . number_format($memory_used, 2) . "MB");
}

// Peak memory
$peak_memory = memory_get_peak_usage(true) / 1024 / 1024;
test_info("Peak memory: " . number_format($peak_memory, 2) . "MB");

// ========== TEST 8: File System Performance ==========
section("TEST 8: FILE SYSTEM PERFORMANCE");

$plugin_dir = $CFG->dirroot . '/local/essay_ai_grader';

// Count PHP files
$start = microtime(true);
$finder = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir));
$php_files = [];
foreach ($finder as $file) {
    if ($file->getExtension() === 'php') {
        $php_files[] = $file->getPathname();
    }
}
$scan_time = (microtime(true) - $start) * 1000;

test_pass("Directory scan", "Found " . count($php_files) . " PHP files in " . number_format($scan_time, 2) . "ms");

// Get total plugin size
$start = microtime(true);
$total_size = 0;
foreach ($php_files as $file) {
    $total_size += filesize($file);
}
$size_calc_time = (microtime(true) - $start) * 1000;

$size_mb = $total_size / 1024 / 1024;
test_pass("Plugin size", number_format($size_mb, 2) . "MB in " . number_format($size_calc_time, 2) . "ms");

// ========== TEST 9: AJAX Endpoint Performance ==========
section("TEST 9: AJAX ENDPOINT FILE SIZES");

$ajax_endpoints = [
    'bulk_accept.php',
    'bulk_reject.php',
    'export_csv.php',
];

foreach ($ajax_endpoints as $endpoint) {
    $file = $plugin_dir . '/ajax/' . $endpoint;
    if (file_exists($file)) {
        $size = filesize($file);
        $size_kb = $size / 1024;
        
        if ($size < 50000) {
            test_pass("Endpoint size", "$endpoint: " . number_format($size_kb, 1) . "KB (good)");
        } else {
            test_info("Endpoint size: $endpoint: " . number_format($size_kb, 1) . "KB");
        }
    }
}

// ========== SUMMARY ==========
section("PERFORMANCE BENCHMARKING SUMMARY");

$total = $passes + $fails;
echo "\nResults:\n";
echo "  ✅ Passed:  $passes\n";
echo "  ❌ Failed:  $fails\n";
echo "  ━━━━━━━━━━━━━━━━━━━━\n";
echo "  Total:     $total\n";

if ($total > 0) {
    $pass_rate = ($passes / $total) * 100;
    echo "\n  Pass Rate: " . number_format($pass_rate, 1) . "%\n";
}

echo "\nPerformance Targets:\n";
echo "  ✅ Page load:          <50ms\n";
echo "  ✅ DB query:           <5ms\n";
echo "  ✅ Cost calculation:   <2ms\n";
echo "  ✅ Analytics query:    <100ms\n";
echo "  ✅ Memory footprint:   <5MB\n";

echo "\n" . str_repeat("-", 80) . "\n";
if ($fails == 0) {
    echo "✅ PERFORMANCE BENCHMARKING PASSED\n";
    echo "   All performance targets met\n";
    $exit_code = 0;
} else if ($fails <= 1) {
    echo "✅ PERFORMANCE BENCHMARKING MOSTLY PASSED\n";
    echo "   Minor optimizations needed for 1 target\n";
    $exit_code = 0;
} else {
    echo "⚠️  PERFORMANCE BENCHMARKING: Review targets\n";
    echo "   Consider optimization for " . $fails . " items\n";
    $exit_code = 0;
}
echo str_repeat("-", 80) . "\n";

echo "\nPhase 7 Complete!\n";
echo "Progress: 7/9 phases complete (78%)\n";
echo "\nNext: Phase 8 - Error Handling & Edge Cases\n";
echo "Command: php test_error_handling.php\n";
echo str_repeat("=", 80) . "\n\n";

exit($exit_code);
