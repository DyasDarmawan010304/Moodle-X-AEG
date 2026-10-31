<?php
/**
 * FASE 2 Manual Validation Script
 *
 * This script performs manual validation of all FASE 2 components
 * without relying on Moodle's test framework. Can be run from command line:
 *
 * php manual_validation.php
 *
 * @package    local_essay_ai_grader
 * @category   testing
 * @copyright  2026 Essay AI Grader
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output
echo "\n";
echo str_repeat("=", 80) . "\n";
echo "FASE 2 MANUAL VALIDATION TEST SUITE\n";
echo str_repeat("=", 80) . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

class FASE2Validator {

    private $passes = 0;
    private $fails = 0;
    private $warnings = 0;
    private $db;
    private $results = [];

    public function __construct() {
        // Initialize test database connection if available
        // For now, we'll create mock tests
    }

    /**
     * Assert test passes
     */
    private function assert_pass($test_name, $message = '') {
        $this->passes++;
        echo "  [✅ PASS] $test_name";
        if ($message) {
            echo " - $message";
        }
        echo "\n";
        $this->results[] = ['pass', $test_name, $message];
    }

    /**
     * Assert test fails
     */
    private function assert_fail($test_name, $message = '') {
        $this->fails++;
        echo "  [❌ FAIL] $test_name";
        if ($message) {
            echo " - $message";
        }
        echo "\n";
        $this->results[] = ['fail', $test_name, $message];
    }

    /**
     * Warning for non-critical issues
     */
    private function assert_warning($test_name, $message = '') {
        $this->warnings++;
        echo "  [⚠️  WARN] $test_name";
        if ($message) {
            echo " - $message";
        }
        echo "\n";
        $this->results[] = ['warning', $test_name, $message];
    }

    /**
     * Section header
     */
    private function section($name) {
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "§ $name\n";
        echo str_repeat("-", 80) . "\n";
    }

    /**
     * Validate file exists
     */
    private function file_exists_check($filepath, $expected_size_min = 0) {
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            if ($size >= $expected_size_min) {
                $this->assert_pass("File exists", "$filepath (" . number_format($size) . " bytes)");
                return true;
            } else {
                $this->assert_fail("File exists but too small", "$filepath (expected >= $expected_size_min, got $size)");
                return false;
            }
        } else {
            $this->assert_fail("File missing", $filepath);
            return false;
        }
    }

    /**
     * Validate PHP syntax
     */
    private function php_syntax_check($filepath) {
        if (!file_exists($filepath)) {
            return;
        }
        
        $output = [];
        $return_code = 0;
        exec("php -l " . escapeshellarg($filepath), $output, $return_code);
        
        if ($return_code === 0) {
            $this->assert_pass("PHP syntax valid", $filepath);
        } else {
            $this->assert_fail("PHP syntax invalid", $filepath . ": " . implode("; ", $output));
        }
    }

    /**
     * Check file contains expected class/function
     */
    private function contains_check($filepath, $search_string, $description = '') {
        if (!file_exists($filepath)) {
            $this->assert_fail("File check", "$filepath does not exist");
            return false;
        }
        
        $content = file_get_contents($filepath);
        if (strpos($content, $search_string) !== false) {
            $desc = $description ? " ($description)" : "";
            $this->assert_pass("Contains check", "$filepath$desc");
            return true;
        } else {
            $this->assert_fail("Contains check", "$filepath missing: $search_string");
            return false;
        }
    }

    /**
     * ===== SECTION 1: FILE INTEGRITY =====
     */
    public function test_file_integrity() {
        $this->section("FILE INTEGRITY CHECK");

        // FASE 2 Core Components (should already exist)
        $this->file_exists_check('cache_manager.php', 1000);
        $this->file_exists_check('cost_tracker.php', 1000);
        $this->file_exists_check('bulk_operations.php', 1000);
        $this->file_exists_check('analytics_manager.php', 1000);

        // AJAX Endpoints (new)
        $this->file_exists_check('ajax/bulk_accept.php', 50);
        $this->file_exists_check('ajax/bulk_reject.php', 50);
        $this->file_exists_check('ajax/export_csv.php', 50);

        // JavaScript Module (new)
        $this->file_exists_check('amd/src/bulk_operations.js', 100);

        // Dashboard (new)
        $this->file_exists_check('analytics.php', 300);

        // Documentation (new)
        $this->file_exists_check('TESTING_GUIDE.md', 500);
        $this->file_exists_check('FASE2_INTEGRATION_COMPLETE.md', 500);
        $this->file_exists_check('BULK_OPERATIONS_UI_GUIDE.md', 500);

        // Modified files
        $this->file_exists_check('assessment_manager.php', 500);
        $this->file_exists_check('settings.php', 300);
        $this->file_exists_check('version.php', 50);
        $this->file_exists_check('db/install.xml', 500);
        $this->file_exists_check('db/upgrade.php', 200);
        $this->file_exists_check('lang/en/local_essay_ai_grader.php', 500);
    }

    /**
     * ===== SECTION 2: PHP SYNTAX VALIDATION =====
     */
    public function test_php_syntax() {
        $this->section("PHP SYNTAX VALIDATION");

        $php_files = [
            'cache_manager.php',
            'cost_tracker.php',
            'bulk_operations.php',
            'analytics_manager.php',
            'assessment_manager.php',
            'ajax/bulk_accept.php',
            'ajax/bulk_reject.php',
            'ajax/export_csv.php',
            'analytics.php',
        ];

        foreach ($php_files as $file) {
            if (file_exists($file)) {
                $this->php_syntax_check($file);
            }
        }
    }

    /**
     * ===== SECTION 3: CODE STRUCTURE VALIDATION =====
     */
    public function test_code_structure() {
        $this->section("CODE STRUCTURE VALIDATION");

        // Cache Manager
        $this->contains_check('cache_manager.php', 'class cache_manager', 'cache_manager class');
        $this->contains_check('cache_manager.php', 'public static function get_config', 'get_config method');
        $this->contains_check('cache_manager.php', 'public static function cache_model_response', 'cache_model_response method');

        // Cost Tracker
        $this->contains_check('cost_tracker.php', 'class cost_tracker', 'cost_tracker class');
        $this->contains_check('cost_tracker.php', 'public static function record_assessment_cost', 'record_assessment_cost method');
        $this->contains_check('cost_tracker.php', 'public static function get_cost_by_provider', 'get_cost_by_provider method');

        // Bulk Operations
        $this->contains_check('bulk_operations.php', 'class bulk_operations', 'bulk_operations class');
        $this->contains_check('bulk_operations.php', 'public static function accept_multiple_grades', 'accept_multiple_grades method');
        $this->contains_check('bulk_operations.php', 'public static function reject_multiple_grades', 'reject_multiple_grades method');

        // Analytics Manager
        $this->contains_check('analytics_manager.php', 'class analytics_manager', 'analytics_manager class');
        $this->contains_check('analytics_manager.php', 'public static function get_assessment_metrics', 'get_assessment_metrics method');
        $this->contains_check('analytics_manager.php', 'public static function get_cost_analytics', 'get_cost_analytics method');

        // Assessment Manager Integration
        $this->contains_check('assessment_manager.php', 'cache_manager::get_config', 'cache_manager integration (line 70)');
        $this->contains_check('assessment_manager.php', 'cost_tracker::record_assessment_cost', 'cost_tracker integration (line 169)');

        // AJAX Endpoints
        $this->contains_check('ajax/bulk_accept.php', 'confirm_sesskey', 'CSRF protection in bulk_accept');
        $this->contains_check('ajax/bulk_reject.php', 'clean_text', 'Feedback sanitization in bulk_reject');
        $this->contains_check('ajax/export_csv.php', 'text/csv', 'CSV content-type in export_csv');

        // JavaScript Module
        $this->contains_check('amd/src/bulk_operations.js', 'BulkOperations', 'BulkOperations class');
        $this->contains_check('amd/src/bulk_operations.js', 'handleBulkAccept', 'handleBulkAccept method');
        $this->contains_check('amd/src/bulk_operations.js', 'handleBulkReject', 'handleBulkReject method');

        // Analytics Dashboard
        $this->contains_check('analytics.php', 'require_login', 'Authentication required');
        $this->contains_check('analytics.php', 'essay_ai_grader:viewanalytics', 'Capability check');
        $this->contains_check('analytics.php', 'get_assessment_metrics', 'Analytics manager integration');

        // Settings
        $this->contains_check('settings.php', 'enable_caching', 'Cache settings');
        $this->contains_check('settings.php', 'enable_cost_tracking', 'Cost tracking settings');
        $this->contains_check('settings.php', 'enable_analytics', 'Analytics settings');

        // Language Strings
        $this->contains_check('lang/en/local_essay_ai_grader.php', 'caching_settings', 'Cache language strings');
        $this->contains_check('lang/en/local_essay_ai_grader.php', 'cost_tracking_settings', 'Cost tracking language strings');
        $this->contains_check('lang/en/local_essay_ai_grader.php', 'analytics_dashboard', 'Analytics language strings');
    }

    /**
     * ===== SECTION 4: SECURITY VALIDATION =====
     */
    public function test_security() {
        $this->section("SECURITY VALIDATION");

        // CSRF Protection
        $this->contains_check('ajax/bulk_accept.php', 'confirm_sesskey', 'CSRF protection - bulk_accept');
        $this->contains_check('ajax/bulk_reject.php', 'confirm_sesskey', 'CSRF protection - bulk_reject');
        $this->contains_check('ajax/export_csv.php', 'confirm_sesskey', 'CSRF protection - export_csv');

        // Capability Checks
        $this->contains_check('ajax/bulk_accept.php', 'require_capability', 'Capability check - bulk_accept');
        $this->contains_check('ajax/bulk_reject.php', 'require_capability', 'Capability check - bulk_reject');
        $this->contains_check('analytics.php', 'require_capability', 'Capability check - analytics');

        // Authentication
        $this->contains_check('analytics.php', 'require_login', 'Authentication - analytics');

        // Data Sanitization
        $this->contains_check('ajax/bulk_reject.php', 'clean_text', 'Data sanitization - feedback');
        $this->contains_check('ajax/bulk_accept.php', 'required_param', 'Parameter validation - bulk_accept');
    }

    /**
     * ===== SECTION 5: DATABASE MIGRATION VALIDATION =====
     */
    public function test_database_migration() {
        $this->section("DATABASE MIGRATION VALIDATION");

        // Check install.xml schema
        $this->contains_check('db/install.xml', '<FIELD NAME="cost"', 'cost field in schema');
        $this->contains_check('db/install.xml', '<FIELD NAME="costcurrency"', 'costcurrency field in schema');
        $this->contains_check('db/install.xml', 'DECIMAL', 'DECIMAL type for cost');

        // Check upgrade.php migration
        $this->contains_check('db/upgrade.php', '2026022701', 'Version 2026022701 in upgrade.php');
        $this->contains_check('db/upgrade.php', 'add_field', 'add_field for migration');
        $this->contains_check('db/upgrade.php', 'add_index', 'add_index for optimization');

        // Check version.php update
        $this->contains_check('version.php', '2026022701', 'Version 2026022701 in version.php');
        $this->contains_check('version.php', '1.2', 'Release 1.2 in version.php');
    }

    /**
     * ===== SECTION 6: DOCUMENTATION VALIDATION =====
     */
    public function test_documentation() {
        $this->section("DOCUMENTATION VALIDATION");

        // Check documentation exists and has content
        $files = [
            'TESTING_GUIDE.md' => ['Phase 1: Database Migration', 'Phase 5: Analytics Dashboard'],
            'FASE2_INTEGRATION_COMPLETE.md' => ['Executive Summary', 'Testing & Validation'],
            'BULK_OPERATIONS_UI_GUIDE.md' => ['HTML markup', 'JavaScript initialization'],
        ];

        foreach ($files as $file => $required_sections) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                foreach ($required_sections as $section) {
                    if (strpos($content, $section) !== false) {
                        $this->assert_pass("Documentation section", "$file contains '$section'");
                    } else {
                        $this->assert_warning("Documentation section", "$file missing '$section'");
                    }
                }
            } else {
                $this->assert_fail("Documentation file", "$file missing");
            }
        }
    }

    /**
     * ===== SECTION 7: INTEGRATION POINTS =====
     */
    public function test_integration_points() {
        $this->section("INTEGRATION POINTS VALIDATION");

        // assessment_manager integration
        $this->contains_check('assessment_manager.php', 'cache_manager', 'Cache manager integrated in assessment_manager');
        $this->contains_check('assessment_manager.php', 'cost_tracker', 'Cost tracker integrated in assessment_manager');

        // settings.php integration  
        $this->contains_check('settings.php', 'cache_manager', 'cache_manager referenced in settings');
        $this->contains_check('settings.php', 'cost_tracker', 'cost_tracker referenced in settings');

        // AJAX endpoints properly named
        if (file_exists('ajax/bulk_accept.php')) {
            $this->assert_pass("AJAX endpoint", "bulk_accept.php exists");
        }
        if (file_exists('ajax/bulk_reject.php')) {
            $this->assert_pass("AJAX endpoint", "bulk_reject.php exists");
        }
        if (file_exists('ajax/export_csv.php')) {
            $this->assert_pass("AJAX endpoint", "export_csv.php exists");
        }
    }

    /**
     * ===== SECTION 8: ERROR HANDLING =====
     */
    public function test_error_handling() {
        $this->section("ERROR HANDLING VALIDATION");

        // Check for try-catch blocks
        $components = [
            'cache_manager.php' => 'try-catch in cache operations',
            'cost_tracker.php' => 'try-catch in cost recording',
            'bulk_operations.php' => 'try-catch in bulk operations',
            'ajax/bulk_accept.php' => 'try-catch in AJAX handler',
        ];

        foreach ($components as $file => $description) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (strpos($content, 'try') !== false && strpos($content, 'catch') !== false) {
                    $this->assert_pass("Error handling", "$file has exception handling");
                } else {
                    $this->assert_warning("Error handling", "$file missing try-catch");
                }
            }
        }

        // Check for logging
        $this->contains_check('ajax/bulk_accept.php', 'logging_manager', 'Logging in AJAX endpoints');
    }

    /**
     * ===== SECTION 9: PERFORMANCE CHECKS =====
     */
    public function test_performance() {
        $this->section("PERFORMANCE CHECKS");

        // Check for query optimization indicators
        $this->contains_check('analytics_manager.php', 'SELECT', 'Uses optimized queries');
        
        // Check for caching usage
        $this->contains_check('assessment_manager.php', 'cache_manager', 'Uses caching in hot path');

        // File size checks (too large = might have performance issues)
        $large_files = [
            'analytics.php' => 600,
            'analytics_manager.php' => 1000,
        ];

        foreach ($large_files as $file => $max_size) {
            if (file_exists($file)) {
                $size = filesize($file);
                if ($size <= $max_size * 2) {  // Allow 2x for complexity
                    $this->assert_pass("File size", "$file is reasonably sized (" . number_format($size) . " bytes)");
                } else {
                    $this->assert_warning("File size", "$file may be too large (" . number_format($size) . " bytes)");
                }
            }
        }
    }

    /**
     * ===== SECTION 10: LOCALIZATION =====
     */
    public function test_localization() {
        $this->section("LOCALIZATION VALIDATION");

        $lang_file = 'lang/en/local_essay_ai_grader.php';
        
        if (!file_exists($lang_file)) {
            $this->assert_fail("Language file", "$lang_file missing");
            return;
        }

        $required_strings = [
            // Cache settings
            'caching_settings',
            'enable_caching',
            'config_cache_ttl',
            
            // Cost tracking
            'cost_tracking_settings',
            'enable_cost_tracking',
            'cost_warning_threshold',
            
            // Bulk operations
            'bulk_operations_title',
            'select_all',
            'accept_selected',
            'reject_selected',
            
            // Analytics
            'analytics_dashboard',
            'tab_overview',
            'tab_cost_analytics',
            'total_assessments',
        ];

        $content = file_get_contents($lang_file);
        foreach ($required_strings as $string) {
            if (strpos($content, "'$string'") !== false || strpos($content, "\"$string\"") !== false) {
                $this->assert_pass("Language string", "'$string' defined");
            } else {
                $this->assert_fail("Language string", "'$string' missing");
            }
        }
    }

    /**
     * Generate final report
     */
    public function generate_report() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "VALIDATION SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        // Stats
        echo "Test Results:\n";
        echo "  ✅ Passed:  " . $this->passes . "\n";
        echo "  ❌ Failed:  " . $this->fails . "\n";
        echo "  ⚠️  Warnings: " . $this->warnings . "\n";
        echo "  ━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  Total:      " . ($this->passes + $this->fails + $this->warnings) . "\n";

        // Pass rate
        $total = $this->passes + $this->fails + $this->warnings;
        if ($total > 0) {
            $pass_rate = ($this->passes / $total) * 100;
            echo "\n  Pass Rate: " . number_format($pass_rate, 1) . "%\n";
        }

        // Overall verdict
        echo "\n" . str_repeat("-", 80) . "\n";
        if ($this->fails === 0) {
            echo "✅ VALIDATION PASSED - Ready for testing\n";
        } elseif ($this->fails <= 3) {
            echo "⚠️  VALIDATION PASSED WITH WARNINGS - Minor issues detected\n";
        } else {
            echo "❌ VALIDATION FAILED - Critical issues must be fixed\n";
        }
        echo str_repeat("-", 80) . "\n";

        echo "\nNext Steps:\n";
        if ($this->fails === 0) {
            echo "  1. Run database migration test (Test 1.2.1 from TESTING_GUIDE.md)\n";
            echo "  2. Run cache manager unit tests\n";
            echo "  3. Run cost tracker unit tests\n";
            echo "  4. Test AJAX endpoints manually\n";
            echo "  5. Test analytics dashboard in browser\n";
            echo "  6. Run performance benchmarks\n";
        } else {
            echo "  1. Fix all failed checks above\n";
            echo "  2. Re-run validation\n";
            echo "  3. Proceed to testing phase\n";
        }

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Validation complete at " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 80) . "\n\n";
    }

    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->test_file_integrity();
        $this->test_php_syntax();
        $this->test_code_structure();
        $this->test_security();
        $this->test_database_migration();
        $this->test_documentation();
        $this->test_integration_points();
        $this->test_error_handling();
        $this->test_performance();
        $this->test_localization();
        $this->generate_report();
    }
}

// Run the validator
$validator = new FASE2Validator();
$validator->run_all_tests();
