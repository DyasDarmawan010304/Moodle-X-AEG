<?php
/**
 * FASE 2 Testing Script - Runs within Moodle environment
 * 
 * Usage: php admin/cli/exec.php --file=local/essay_ai_grader/test_fase2.php
 *        OR from command line: php -d display_errors=1 test_fase2.php
 * 
 * @package    local_essay_ai_grader
 * @category   testing
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Suppress Moodle's normal output buffering for CLI tests
define('CLI_SCRIPT', true);

// Get Moodle config
$dir = dirname(__FILE__);
while (!is_file($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || $dir === substr($dir, 0, 3)) {
        echo "ERROR: Cannot find Moodle config.php\n";
        exit(1);
    }
}

require_once($dir . '/config.php');

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "FASE 2 COMPONENT TESTING\n";
echo str_repeat("=", 80) . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Moodle: " . $CFG->release . "\n\n";

$passes = 0;
$fails = 0;
$warnings = 0;

// Helper functions
function test_pass($msg) {
    global $passes;
    $passes++;
    echo "  [✅ PASS] $msg\n";
}

function test_fail($msg) {
    global $fails;
    $fails++;
    echo "  [❌ FAIL] $msg\n";
}

function test_warn($msg) {
    global $warnings;
    $warnings++;
    echo "  [⚠️  WARN] $msg\n";
}

function section($name) {
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "§ $name\n";
    echo str_repeat("-", 80) . "\n";
}

// ========== PHASE 1: Verify Classes Loaded ==========
section("PHASE 1: CLASS LOADING");

try {
    require_once($CFG->dirroot . '/local/essay_ai_grader/classes/cache_manager.php');
    test_pass("cache_manager class loaded");
} catch (Exception $e) {
    test_fail("cache_manager: " . $e->getMessage());
}

try {
    require_once($CFG->dirroot . '/local/essay_ai_grader/classes/cost_tracker.php');
    test_pass("cost_tracker class loaded");
} catch (Exception $e) {
    test_fail("cost_tracker: " . $e->getMessage());
}

try {
    require_once($CFG->dirroot . '/local/essay_ai_grader/classes/bulk_operations.php');
    test_pass("bulk_operations class loaded");
} catch (Exception $e) {
    test_fail("bulk_operations: " . $e->getMessage());
}

try {
    require_once($CFG->dirroot . '/local/essay_ai_grader/classes/analytics_manager.php');
    test_pass("analytics_manager class loaded");
} catch (Exception $e) {
    test_fail("analytics_manager: " . $e->getMessage());
}

try {
    require_once($CFG->dirroot . '/local/essay_ai_grader/classes/assessment_manager.php');
    test_pass("assessment_manager class loaded");
} catch (Exception $e) {
    test_fail("assessment_manager: " . $e->getMessage());
}

// ========== PHASE 2: Verify Settings Configuration ==========
section("PHASE 2: SETTINGS CONFIGURATION");

// Check if caching setting exists
$caching_enabled = get_config('local_essay_ai_grader', 'enable_caching');
if ($caching_enabled !== false) {
    test_pass("Caching setting exists (value: " . var_export($caching_enabled, true) . ")");
} else {
    test_warn("Caching setting not yet configured (will use default)");
}

// Check if cost tracking setting exists
$cost_tracking = get_config('local_essay_ai_grader', 'enable_cost_tracking');
if ($cost_tracking !== false) {
    test_pass("Cost tracking setting exists (value: " . var_export($cost_tracking, true) . ")");
} else {
    test_warn("Cost tracking setting not yet configured (will use default)");
}

// Check if analytics setting exists
$analytics = get_config('local_essay_ai_grader', 'enable_analytics');
if ($analytics !== false) {
    test_pass("Analytics setting exists (value: " . var_export($analytics, true) . ")");
} else {
    test_warn("Analytics setting not yet configured (will use default)");
}

// ========== PHASE 3: Verify Database Schema ==========
section("PHASE 3: DATABASE SCHEMA");

global $DB;

try {
    // Check if tables exist
    $tables = $DB->get_tables();
    
    if (isset($tables['local_essay_ai_usage'])) {
        test_pass("Table mdl_local_essay_ai_usage exists");
        
        // Check for new columns
        $columns = $DB->get_columns('local_essay_ai_usage');
        
        if (isset($columns['cost'])) {
            test_pass("Column 'cost' exists");
        } else {
            test_fail("Column 'cost' missing - database migration needed");
        }
        
        if (isset($columns['costcurrency'])) {
            test_pass("Column 'costcurrency' exists");
        } else {
            test_fail("Column 'costcurrency' missing - database migration needed");
        }
    } else {
        test_fail("Table mdl_local_essay_ai_usage does not exist");
    }
} catch (Exception $e) {
    test_fail("Database check: " . $e->getMessage());
}

// ========== PHASE 4: Verify Plugin Version ==========
section("PHASE 4: PLUGIN VERSION");

$plugin_version = get_config('local_essay_ai_grader');
if ($plugin_version) {
    if (is_object($plugin_version)) {
        $version = $plugin_version->version ?? null;
    } else {
        $version = null;
    }
    
    if ($version && $version >= 2026022701) {
        test_pass("Plugin version meets FASE 2 requirement: " . $version);
    } elseif ($version) {
        test_warn("Plugin version is " . $version . " (FASE 2 requires 2026022701+)");
    } else {
        test_fail("Could not determine plugin version");
    }
} else {
    test_fail("Plugin not properly installed - no config found");
}

// ========== PHASE 5: Verify Language Strings ==========
section("PHASE 5: LANGUAGE STRINGS");

$test_strings = [
    'caching_settings' => 'Cache settings heading',
    'enable_caching' => 'Enable cache option',
    'cost_tracking_settings' => 'Cost tracking heading',
    'analytics_dashboard' => 'Analytics heading',
    'bulk_operations_title' => 'Bulk operations heading',
];

$missing_strings = [];
foreach ($test_strings as $key => $desc) {
    $string = get_string($key, 'local_essay_ai_grader', null, true);
    if ($string && $string !== "[[" . $key . "]]") {
        test_pass("Language string '$key' exists");
    } else {
        $missing_strings[] = $key;
        test_fail("Language string '$key' missing");
    }
}

// ========== PHASE 6: File Integrity ==========
section("PHASE 6: FILE INTEGRITY");

$required_files = [
    '/local/essay_ai_grader/classes/cache_manager.php',
    '/local/essay_ai_grader/classes/cost_tracker.php',
    '/local/essay_ai_grader/classes/bulk_operations.php',
    '/local/essay_ai_grader/classes/analytics_manager.php',
    '/local/essay_ai_grader/classes/assessment_manager.php',
    '/local/essay_ai_grader/ajax/bulk_accept.php',
    '/local/essay_ai_grader/ajax/bulk_reject.php',
    '/local/essay_ai_grader/ajax/export_csv.php',
    '/local/essay_ai_grader/amd/src/bulk_operations.js',
    '/local/essay_ai_grader/analytics.php',
    '/local/essay_ai_grader/settings.php',
    '/local/essay_ai_grader/version.php',
];

foreach ($required_files as $file) {
    $full_path = $CFG->dirroot . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        test_pass("File exists: " . basename($file) . " (" . number_format($size) . " bytes)");
    } else {
        test_fail("File missing: " . basename($file));
    }
}

// ========== SUMMARY ==========
section("TEST SUMMARY");

$total = $passes + $fails + $warnings;
echo "\nResults:\n";
echo "  ✅ Passed:  $passes\n";
echo "  ❌ Failed:  $fails\n";
echo "  ⚠️  Warnings: $warnings\n";
echo "  ━━━━━━━━━━━━━━━━━━━━\n";
echo "  Total:      $total\n";

if ($total > 0) {
    $pass_rate = ($passes / $total) * 100;
    echo "\n  Pass Rate: " . number_format($pass_rate, 1) . "%\n";
}

// Overall verdict
echo "\n" . str_repeat("-", 80) . "\n";
if ($fails === 0) {
    echo "✅ VALIDATION PASSED - Ready for Phase testing\n";
    $exit_code = 0;
} elseif ($fails <= 2) {
    echo "⚠️  VALIDATION PASSED WITH WARNINGS - Minor issues detected\n";
    $exit_code = 0;
} else {
    echo "❌ VALIDATION FAILED - Critical issues must be fixed\n";
    $exit_code = 1;
}
echo str_repeat("-", 80) . "\n";

echo "\nNext Steps:\n";
if ($fails === 0) {
    echo "  1. Run database migration: php admin/cli/upgrade.php\n";
    echo "  2. Verify schema: php local/essay_ai_grader/test_schema.php\n";
    echo "  3. Test cache manager performance\n";
    echo "  4. Test cost tracking\n";
    echo "  5. Test AJAX endpoints\n";
    echo "  6. Test analytics dashboard\n";
} else {
    echo "  1. Fix all failed checks above\n";
    echo "  2. Re-run this validation\n";
}

echo "\nValidation completed at " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

exit($exit_code);
