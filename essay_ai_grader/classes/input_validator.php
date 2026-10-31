<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_essay_ai_grader;

defined('MOODLE_INTERNAL') || die();

/**
 * Input validator for essay text.
 * Validates essay submissions before preprocessing and model inference.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class input_validator {
    
    // Validation rules (in bytes/characters)
    const MIN_TEXT_LENGTH = 10;          // Minimum 10 characters
    const MAX_TEXT_LENGTH = 50000;       // Maximum 50,000 characters (before preprocessing)
    
    /**
     * Validate essay input before processing.
     *
     * @param string $text The essay text to validate
     * @return array Result array with keys: 'valid' (bool), 'error' (string|null)
     */
    public static function validate_essay_input($text) {
        $text = (string)$text;
        
        // Check 1: Not empty
        if (empty($text)) {
            return self::error_result('Essay text cannot be empty');
        }
        
        // Check 2: Trim and check again
        $trimmed = trim($text);
        if (empty($trimmed)) {
            return self::error_result('Essay contains only whitespace');
        }
        
        // Check 3: Minimum length (at least 10 characters of actual content)
        if (strlen($trimmed) < self::MIN_TEXT_LENGTH) {
            return self::error_result('Essay is too short (minimum ' . self::MIN_TEXT_LENGTH . ' characters)');
        }
        
        // Check 4: Maximum length (before any preprocessing)
        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            return self::error_result('Essay is too long (maximum ' . self::MAX_TEXT_LENGTH . ' characters)');
        }
        
        // Check 5: Validate UTF-8 encoding FIRST (and clean if needed)
        // Try to clean UTF-8 if invalid - don't fail immediately
        $cleaned_text = self::clean_invalid_utf8($text);
        if ($cleaned_text !== $text) {
            // Text had invalid UTF-8 but we cleaned it
            $text = $cleaned_text;
        }
        
        // Check 6: Not only whitespace or special characters (must have alphanumeric)
        // Use regex without /u flag for better compatibility with mixed UTF-8 content
        if (!preg_match('/[a-zA-Z0-9]/', $text)) {
            return self::error_result('Essay must contain at least some alphabetic or numeric characters');
        }
        
        // All validations passed
        return array(
            'valid' => true,
            'error' => null,
            'message' => 'Input validation passed'
        );
    }
    
    /**
     * Check if text is valid UTF-8 and clean it if necessary.
     *
     * @param string $text
     * @return bool
     */
    private static function is_valid_utf8($text) {
        // Try multiple methods to validate/clean UTF-8
        
        // Method 1: mb_check_encoding (most reliable)
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($text, 'UTF-8')) {
                // Try to clean invalid UTF-8 using iconv
                if (function_exists('iconv')) {
                    $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
                    if ($cleaned !== false && !empty($cleaned)) {
                        // Text can be cleaned, consider it valid
                        return true;
                    }
                }
                return false;
            }
            return true;
        }
        
        // Method 2: JSON encode/decode (fallback)
        $json = json_encode($text);
        if ($json === false) {
            return false;
        }
        
        $decoded = json_decode($json, true);
        return is_string($decoded);
    }
    
    /**
     * Clean invalid UTF-8 bytes from text.
     * Removes or replaces invalid UTF-8 sequences.
     *
     * @param string $text
     * @return string Cleaned text (or original if already valid)
     */
    private static function clean_invalid_utf8($text) {
        // Method 1: Use iconv to strip invalid UTF-8
        if (function_exists('iconv')) {
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($cleaned !== false && strlen($cleaned) > 0) {
                return $cleaned;
            }
        }
        
        // Method 2: Use mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            $cleaned = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if ($cleaned !== false) {
                return $cleaned;
            }
        }
        
        // Method 3: JSON encode/decode with invalid UTF-8 substitution
        $json = json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_string($decoded)) {
                return $decoded;
            }
        }
        
        // Return original if cleaning fails
        return $text;
    }
    
    /**
     * Create error result array.
     *
     * @param string $error_message
     * @return array
     */
    private static function error_result($error_message) {
        return array(
            'valid' => false,
            'error' => $error_message,
            'message' => null
        );
    }
    
    /**
     * Quick validation (boolean return).
     *
     * @param string $text
     * @return bool
     */
    public static function is_valid($text) {
        $result = self::validate_essay_input($text);
        return (bool)$result['valid'];
    }
    
    /**
     * Get validation error message if invalid.
     *
     * @param string $text
     * @return string|null
     */
    public static function get_error_message($text) {
        $result = self::validate_essay_input($text);
        return $result['error'] ?? null;
    }
}
