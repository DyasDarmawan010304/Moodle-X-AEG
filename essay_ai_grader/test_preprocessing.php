<?php
// Test script for preprocessing layers - run this in browser or CLI

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/input_validator.php');
require_once($CFG->dirroot . '/local/essay_ai_grader/classes/text_preprocessor.php');

use local_essay_ai_grader\input_validator;
use local_essay_ai_grader\text_preprocessor;

// Test cases
$test_cases = array(
    // Test 1: Valid essay
    array(
        'name' => 'Valid essay',
        'input' => 'This is a valid essay about artificial intelligence and machine learning. It discusses various aspects of AI systems.',
        'should_pass' => true,
    ),
    
    // Test 2: Essay with HTML tags
    array(
        'name' => 'Essay with HTML tags',
        'input' => '<p>This essay discusses <strong>important</strong> topics in education. It covers <em>multiple</em> aspects.</p>',
        'should_pass' => true,
    ),
    
    // Test 3: Essay with extra whitespace
    array(
        'name' => 'Essay with extra whitespace',
        'input' => "This   essay   has    extra    spaces\n\n\nand multiple newlines\t\ttabs.",
        'should_pass' => true,
    ),
    
    // Test 4: Empty essay
    array(
        'name' => 'Empty essay',
        'input' => '',
        'should_pass' => false,
    ),
    
    // Test 5: Only whitespace
    array(
        'name' => 'Only whitespace',
        'input' => '   \n\n\t\t   ',
        'should_pass' => false,
    ),
    
    // Test 6: Too short
    array(
        'name' => 'Too short (less than 10 chars)',
        'input' => 'short',
        'should_pass' => false,
    ),
    
    // Test 7: Very long essay (should truncate)
    array(
        'name' => 'Very long essay (5000+ chars)',
        'input' => str_repeat('This is a very long essay. ', 200), // ~5400 chars
        'should_pass' => true,
    ),
    
    // Test 8: Essay with HTML entities
    array(
        'name' => 'Essay with HTML entities',
        'input' => 'This essay discusses &quot;important&quot; concepts &amp; theories. It&rsquo;s quite interesting.',
        'should_pass' => true,
    ),
);

echo "<h2>Preprocessing Layer Tests</h2>";
echo "<table border='1' cellpadding='10' cellspacing='0'>";
echo "<tr><th>Test Name</th><th>Input Length</th><th>Validation</th><th>Preprocessing</th><th>Output Length</th><th>Status</th></tr>";

$passed = 0;
$failed = 0;

foreach ($test_cases as $test) {
    // Step 1: Validation
    $validation = input_validator::validate_essay_input($test['input']);
    $validation_passed = $validation['valid'];
    
    // Step 2: Preprocessing
    $preprocess_result = null;
    $output_length = 0;
    if ($validation_passed) {
        $preprocess_result = text_preprocessor::preprocess($test['input']);
        if ($preprocess_result['success']) {
            $output_length = strlen($preprocess_result['text']);
        }
    }
    
    // Determine if test passed
    $test_passed = ($validation_passed === $test['should_pass']);
    if ($test_passed) {
        $passed++;
        $status = '<span style="color:green"><b>✓ PASS</b></span>';
    } else {
        $failed++;
        $status = '<span style="color:red"><b>✗ FAIL</b></span>';
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($test['name']) . "</td>";
    echo "<td>" . strlen($test['input']) . " chars</td>";
    echo "<td>" . ($validation_passed ? '✓ Valid' : '✗ ' . $validation['error']) . "</td>";
    echo "<td>";
    if ($preprocess_result) {
        echo ($preprocess_result['success'] ? '✓ Success' : '✗ ' . $preprocess_result['error']);
        if (isset($preprocess_result['stats']['was_truncated']) && $preprocess_result['stats']['was_truncated']) {
            echo " (truncated)";
        }
    } else {
        echo "Skipped";
    }
    echo "</td>";
    echo "<td>" . $output_length . " chars</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<br><h3>Test Summary</h3>";
echo "Passed: <b style='color:green'>" . $passed . "</b><br>";
echo "Failed: <b style='color:red'>" . $failed . "</b><br>";
echo "Total: " . count($test_cases);

echo "<br><br><h3>Detailed Test Output Example</h3>";
if (!empty($test_cases[1])) {
    echo "<h4>Test: " . htmlspecialchars($test_cases[1]['name']) . "</h4>";
    echo "<pre>";
    echo "Input:\n";
    echo htmlspecialchars($test_cases[1]['input']) . "\n\n";
    $result = text_preprocessor::preprocess($test_cases[1]['input']);
    echo "Output:\n";
    echo htmlspecialchars($result['text']) . "\n\n";
    echo "Stats:\n";
    echo json_encode($result['stats'], JSON_PRETTY_PRINT);
    echo "</pre>";
}
