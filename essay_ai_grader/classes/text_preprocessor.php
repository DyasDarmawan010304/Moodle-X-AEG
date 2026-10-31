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
 * Text preprocessor for essay content.
 * Implements complete preprocessing pipeline:
 * 1. Input validation
 * 2. Remove HTML tags
 * 3. Normalize UTF-8 encoding
 * 4. Clean whitespace
 * 5. Clean special characters (emoji, control chars)
 * 6. Truncate for model
 *
 * Also provides formatting utilities for AI prompts:
 * - format_for_prompt(): Adds markup delimiters and metadata
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_preprocessor {
    
    // Configuration constants
    const MAX_CHARS_FOR_MODEL = 3500;    // Max chars to send to Gemini (avoid timeouts)
    const MAX_BYTES_FOR_DB = 60000;      // Max bytes for TEXT field storage
    
    /**
     * Complete preprocessing pipeline.
     * Orchestrates all preprocessing steps in correct order.
     *
     * Step 1: Validate input
     * Step 2: Remove HTML tags
     * Step 3: Normalize UTF-8 encoding
     * Step 4: Clean whitespace
     * Step 5: Clean special characters (emoji, control chars)
     * Step 6: Truncate for model
     *
     * @param string $text The raw essay text
     * @return array Result with keys: 'success' (bool), 'text' (string|null), 'error' (string|null), 'stats' (array)
     * @throws \Exception If validation fails
     */
    public static function preprocess($text) {
        $original_length = strlen($text);
        
        try {
            // Step 1: Validate input
            $validation = input_validator::validate_essay_input($text);
            if (!$validation['valid']) {
                throw new \Exception('Validation failed: ' . $validation['error']);
            }
            
            // Step 2: Remove HTML tags
            $text = self::remove_html_tags($text);
            
            // Step 3: Normalize UTF-8 encoding
            $text = self::normalize_encoding($text);
            
            // Step 4: Clean whitespace and extra spaces
            $text = self::clean_whitespace($text);
            
            // Step 5: Clean special characters (emoji, control chars, etc.)
            $text = self::clean_special_characters($text);
            
            // Step 6: Truncate for model if needed
            $was_truncated = false;
            if (strlen($text) > self::MAX_CHARS_FOR_MODEL) {
                $text = self::truncate_for_model($text);
                $was_truncated = true;
            }
            
            return array(
                'success' => true,
                'text' => $text,
                'error' => null,
                'stats' => array(
                    'original_length' => $original_length,
                    'final_length' => strlen($text),
                    'was_truncated' => $was_truncated,
                    'reduction_percent' => round(((strlen($text) - $original_length) / $original_length) * 100, 2)
                )
            );
            
        } catch (\Exception $e) {
            logging_manager::warning('Text preprocessing failed', null,
                array('error' => $e->getMessage(), 'original_length' => $original_length));
            
            return array(
                'success' => false,
                'text' => null,
                'error' => $e->getMessage(),
                'stats' => array('original_length' => $original_length)
            );
        }
    }
    
    /**
     * Step 2: Remove HTML tags from text.
     * Strip all HTML/XML tags but preserve text content.
     *
     * @param string $text
     * @return string Text without HTML tags
     */
    private static function remove_html_tags($text) {
        // Strip all HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities (e.g., &nbsp; → space, &quot; → ")
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $text;
    }
    
    /**
     * Step 3: Normalize UTF-8 encoding.
     * Ensures text is valid UTF-8 and removes invalid bytes.
     *
     * @param string $text
     * @return string Text with valid UTF-8 encoding
     */
    private static function normalize_encoding($text) {
        // Try iconv first (most efficient)
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        
        // Fallback to mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if ($converted !== false) {
                return $converted;
            }
        }
        
        // Last resort: JSON encode/decode to sanitize invalid bytes
        $json = json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_string($decoded)) {
                return $decoded;
            }
        }
        
        // Return original if all else fails
        return $text;
    }
    
    /**
     * Step 4: Clean whitespace.
     * - Remove extra spaces/tabs/newlines
     * - Trim leading/trailing whitespace
     * - Collapse multiple spaces to single space
     *
     * @param string $text
     * @return string Text with cleaned whitespace
     */
    private static function clean_whitespace($text) {
        // Collapse multiple whitespace (spaces, tabs, newlines) into single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim leading and trailing whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Step 4a: Clean special characters.
     * Remove emojis, control characters, and other non-essential special chars.
     * Preserves Unicode letters, numbers, punctuation, and common symbols.
     *
     * @param string $text
     * @return string Text with cleaned special characters
     */
    private static function clean_special_characters($text) {
        // Remove emoji and other non-text Unicode symbols
        // This regex matches: emoji, symbols, pictographs, transport/map symbols, flags
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Symbols & pictographs
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Transport & map symbols
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $text); // Flags
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Misc symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Dingbats
        
        // Remove control characters (except newlines, tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Remove zero-width characters
        $text = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}]/u', '', $text);
        
        // Normalize quotes (smart quotes to straight quotes)
        $text = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D"], '"', $text);
        $text = str_replace("\xE2\x80\xA6", '...', $text);
        
        return $text;
    }
    
    /**
     * Format text for inclusion in AI prompt.
     * Adds markup delimiters and metadata to help the AI understand the input.
     *
     * @param string $text The cleaned essay text
     * @param array $options Options: 'include_metadata' (bool), 'delimiter_style' (string)
     * @return string Formatted text ready for prompt
     */
    public static function format_for_prompt($text, $options = array()) {
        $defaults = array(
            'include_metadata' => true,
            'delimiter_style' => 'standard' // 'standard', 'minimal', 'none'
        );
        $options = array_merge($defaults, $options);
        
        $formatted = '';
        
        // Add opening delimiter
        if ($options['delimiter_style'] === 'standard') {
            $formatted .= "=== ESSAY TEXT ===\n\n";
        } else if ($options['delimiter_style'] === 'markdown') {
            $formatted .= "```essay\n";
        }
        
        // Add the text
        $formatted .= $text;
        
        // Add closing delimiter
        if ($options['delimiter_style'] === 'standard') {
            $formatted .= "\n\n=== END ESSAY ===";
        } else if ($options['delimiter_style'] === 'markdown') {
            $formatted .= "\n```";
        }
        
        // Add metadata if requested
        if ($options['include_metadata']) {
            $word_count = str_word_count($text);
            $char_count = strlen($text);
            $line_count = substr_count($text, "\n") + 1;
            
            $formatted .= "\n\n---\n";
            $formatted .= "Essay Statistics: {$word_count} words, {$char_count} characters, {$line_count} lines.";
        }
        
        return $formatted;
    }
    
    /**
     * Step 6: Truncate text to maximum length for model.
     * Prevents timeouts when sending very long essays to Gemini.
     *
     * @param string $text
     * @return string Truncated text with indicator
     */
    private static function truncate_for_model($text) {
        if (strlen($text) <= self::MAX_CHARS_FOR_MODEL) {
            return $text;
        }
        
        // Truncate and add indicator
        $truncated = \core_text::substr($text, 0, self::MAX_CHARS_FOR_MODEL);
        return $truncated . "\n\n[...truncated for processing...]";
    }
    
    /**
     * Truncate text for database storage.
     * Ensures TEXT field won't overflow (60KB limit).
     *
     * @param string|null $text
     * @param int $maxbytes
     * @return string|null
     */
    public static function truncate_for_db($text, $maxbytes = self::MAX_BYTES_FOR_DB) {
        if ($text === null) {
            return null;
        }
        
        $text = (string)$text;
        
        // Ensure UTF-8 validity
        $text = self::normalize_encoding($text);
        
        // Truncate if needed
        if (strlen($text) <= $maxbytes) {
            return $text;
        }
        
        return substr($text, 0, $maxbytes);
    }
    
    /**
     * Get preprocessing stats without actually preprocessing.
     * Useful for logging and monitoring.
     *
     * @param string $text
     * @return array Stats about the text
     */
    public static function get_text_stats($text) {
        $text = (string)$text;
        
        return array(
            'raw_length' => strlen($text),
            'word_count' => str_word_count($text),
            'line_count' => substr_count($text, "\n") + 1,
            'is_valid' => input_validator::is_valid($text),
            'has_html' => self::contains_html($text),
            'needs_truncate' => strlen($text) > self::MAX_CHARS_FOR_MODEL,
        );
    }
    
    /**
     * Check if text contains HTML tags.
     *
     * @param string $text
     * @return bool
     */
    private static function contains_html($text) {
        return $text !== strip_tags($text);
    }
}
