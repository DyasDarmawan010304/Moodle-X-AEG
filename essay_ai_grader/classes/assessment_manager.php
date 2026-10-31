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

use local_essay_ai_grader\gemini\gemini_client;
use local_essay_ai_grader\ollama\ollama_client;

/**
 * Essay Assessment Manager.
 * Manages assessment records in database and coordinates with Gemini AI.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_manager {
    
    /**
     * Process a single submission with AI provider (Gemini or Ollama).
     *
     * @param int $assessmentid The assessment record ID.
     * @return bool Success or failure.
     */
    public static function process_assessment($assessmentid) {
        global $DB;
        
        try {
            // Get assessment record.
            $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
            if (!$assessment) {
                logging_manager::warning('Assessment record not found', $assessmentid);
                return false;
            }
            
            logging_manager::info('Starting assessment processing', $assessmentid);
            
            // Check if already processed.
            if ($assessment->status === 'completed' || $assessment->status === 'error') {
                logging_manager::debug('Assessment already processed', $assessmentid, 
                    array('status' => $assessment->status));
                return false;
            }
            
            // Get submission text.
            $submission = $DB->get_record('assign_submission', array('id' => $assessment->submissionid));
            if (!$submission) {
                logging_manager::warning('Submission not found', $assessmentid,
                    array('submissionid' => $assessment->submissionid));
                self::mark_error($assessmentid, 'Submission not found');
                return false;
            }
            
            // Get assignment config.
            $config = $DB->get_record('local_essay_ai_config', array('assignid' => (int)$assessment->assignmentid));
            
            // Extract essay text from submission plugins.
            $essaytext = self::extract_essay_text($submission, (int)$assessment->assignmentid);
            
            if (empty($essaytext)) {
                logging_manager::warning('No essay text found in submission (may be still uploading)', $assessmentid);
                // This is likely transient - submission content might still be being processed
                // Retry after backoff period
                retry_manager::prepare_retry($assessmentid, 'No essay text found - submission may still be processing');
                return false;
            }
            
            // Preprocess essay text: validate, clean HTML, normalize encoding, cleanup whitespace, truncate.
            $preprocess_result = text_preprocessor::preprocess($essaytext);
            
            if (!$preprocess_result['success']) {
                logging_manager::warning('Text preprocessing failed', $assessmentid,
                    array('error' => $preprocess_result['error']));
                self::mark_error($assessmentid, 'Text preprocessing failed: ' . $preprocess_result['error']);
                return false;
            }
            
            $essaytext = $preprocess_result['text'];
            
            // Store essay text and preprocessing stats.
            $assessment->essaytext = text_preprocessor::truncate_for_db($essaytext);
            $assessment->timemodified = time();
            $assessment->modified = time();
            $DB->update_record('local_essay_ai_assess', $assessment);
            
            logging_manager::debug('Essay text preprocessed', $assessmentid, $preprocess_result['stats']);
            
            // Initialize configured AI provider client.
            $provider = get_config('local_essay_ai_grader', 'ai_provider') ?? 'gemini';
            
            // Check rate limiting before making API call.
            $ratelimit = rate_limiter::check_rate_limit($provider);
            if (!$ratelimit['allowed']) {
                logging_manager::warning('Rate limit exceeded', $assessmentid,
                    array('provider' => $provider, 'message' => $ratelimit['message']));
                // Don't mark as error - will be retried
                return false;
            }
            
            $client = self::get_ai_client();
            
            // Parse criteria configuration (prefer criteria_config which has full structure with weights)
            $criteria = array();
            if ($config) {
                // Use criteria_config if available (new 6-criteria system)
                if (!empty($config->criteria_config)) {
                    $criteria = json_decode($config->criteria_config, true) ?? array();
                } else {
                    // Fall back to legacy criteria field
                    $criteria = json_decode($config->criteria, true) ?? array();
                }
            }
            $hascriteria = !empty($criteria);
            $criteria_weights = array();
            $criteria_max_points = array();
            if ($config && !empty($config->criteria_config)) {
                $criteria_weights = \local_essay_ai_grader\default_criteria::parse_criteria_config($config->criteria_config);
                $criteria_max_points = \local_essay_ai_grader\default_criteria::parse_criteria_max_points($config->criteria_config);
            }
            
            // Get max score from config, or fallback to assignment grade, or default to 100
            if ($config && !empty($config->maxscore)) {
                $maxscore = $config->maxscore;
            } else {
                // Fallback to assignment grade from Moodle
                $assignment = $DB->get_record('assign', array('id' => $assessment->assignmentid));
                $maxscore = ($assignment && !empty($assignment->grade)) ? $assignment->grade : 100;
            }
            
            $answerkey = '';
            if ($config) {
                $answerkey = !empty($config->answerkey)
                    ? $config->answerkey
                    : ($config->expected_answer ?? '');
            }

            $guidance = array(
                'customprompt' => $config->prompttemplate ?? '',
                'answerkey' => $config->answerkey ?? '',
                'rubricguide' => $config->rubricguide ?? '',
                'assignmentid' => (int)$assessment->assignmentid,
                'use_standard' => true,
                'soal' => $config->question_text ?? '',
                'kunci_jawaban' => $config->expected_answer ?? '',
                'question_text' => $config->question_text ?? '',
            );

            logging_manager::debug('Calling AI provider for assessment', $assessmentid,
                array('provider' => $provider, 'has_criteria' => $hascriteria));
            
            // Call AI API for assessment.
            $result = $client->assess_essay($essaytext, $criteria, $maxscore, $guidance);
            
            // Record API usage for rate limiting.
            rate_limiter::record_usage($provider, $assessmentid);
            
            if (!$result['success']) {
                if (!empty($result['raw_response'])) {
                    $assessment->gemini_response = self::truncate_text_for_db($result['raw_response']);
                }
                if (!empty($result['raw_response_full'])) {
                    $assessment->gemini_response_raw = self::truncate_text_for_db($result['raw_response_full']);
                }
                $assessment->timemodified = time();
                $assessment->modified = time();
                $DB->update_record('local_essay_ai_assess', $assessment);
            
                
                logging_manager::warning('AI assessment failed', $assessmentid,
                    array('error' => $result['error'], 'provider' => $provider));
                
                // Check if this is a transient error that should be retried.
                if (self::is_transient_error($result['error'])) {
                    retry_manager::prepare_retry($assessmentid, $result['error']);
                    return false;
                }
                
                self::mark_error($assessmentid, $result['error']);
                return false;
            }
            
            // Enforce score consistency with criteria subtotal, minimum per-aspect scoring,
            // dan validasi jawaban pendek / kunci jawaban.
            $result = self::normalise_result(
                $result,
                $maxscore,
                $hascriteria,
                $criteria_weights,
                $criteria_max_points,
                $essaytext,
                $answerkey
            );

            // Update assessment record with results.
            $assessment->status = 'completed';
            $assessment->score = $result['score'];
            $assessment->feedback = self::truncate_text_for_db($result['summary_feedback']);
            $assessment->criteria_scores = json_encode($result['criteria_scores']);
            $assessment->gemini_response = self::truncate_text_for_db($result['raw_response']);
            if (!empty($result['raw_response_full'])) {
                $assessment->gemini_response_raw = self::truncate_text_for_db($result['raw_response_full']);
            }
            $assessment->error_message = null;
            $assessment->timemodified = time();
            $assessment->modified = time();
            
            $DB->update_record('local_essay_ai_assess', $assessment);
            
            // Record cost tracking for FASE 2 analytics and cost monitoring
            if (!empty($result['tokens_used']) && !empty($result['cost'])) {
                try {
                    cost_tracker::record_assessment_cost(
                        $assessmentid,
                        $provider,
                        (int)$result['tokens_used'],
                        (float)$result['cost']
                    );
                    logging_manager::debug('Cost recorded for assessment', $assessmentid,
                        array('tokens' => $result['tokens_used'], 'cost' => $result['cost']));
                } catch (\Throwable $e) {
                    logging_manager::warning('Cost tracking failed', $assessmentid,
                        array('error' => $e->getMessage()));
                    // Cost tracking failure is not critical - assessment already completed
                }
            }
            
            logging_manager::info('Assessment completed successfully', $assessmentid,
                array('score' => $assessment->score, 'provider' => $provider));

            // Auto-publish AI score to assignment gradebook if enabled.
            $autopublish = get_config('local_essay_ai_grader', 'auto_publish_grade');
            if ($autopublish === false || !empty($autopublish)) {
                try {
                    self::save_grade_to_assignment($assessment->assignmentid, $assessment->userid, $assessment->score);
                    logging_manager::debug('Grade auto-published to gradebook', $assessmentid,
                        array('score' => $assessment->score));
                    
                    // Also save feedback to assignment feedback column if enabled.
                    $autopublish_feedback = get_config('local_essay_ai_grader', 'auto_publish_feedback');
                    if ($autopublish_feedback === false || !empty($autopublish_feedback)) {
                        self::save_feedback_to_assignment($assessment->assignmentid, $assessment->userid, 
                            $assessment->feedback, $assessment->criteria_scores, $assessment->gemini_response);
                        logging_manager::debug('Feedback auto-published to assignment feedback column', $assessmentid);
                    }
                } catch (\Throwable $e) {
                    logging_manager::warning('Auto-publish grade or feedback failed', $assessmentid,
                        array('error' => $e->getMessage()));
                }
            }
            
            // Send notification to teacher.
            try {
                self::notify_teacher($assessment, $result);
                logging_manager::debug('Teacher notification sent', $assessmentid);
            } catch (\Throwable $e) {
                logging_manager::warning('Notify teacher failed', $assessmentid,
                    array('error' => $e->getMessage()));
            }
            
            return true;
            
        } catch (\Throwable $e) {
            logging_manager::log_exception($e, 'Unexpected error during assessment processing', $assessmentid);
            self::mark_error($assessmentid, 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if an error is transient (should be retried) vs permanent.
     *
     * @param string $error_message The error message
     * @return bool True if error should be retried
     */
    private static function is_transient_error($error_message) {
        $error_msg = strtolower((string)$error_message);
        
        // Network/timeout errors
        $transient_patterns = array(
            'timeout',
            'connection',
            'failed to connect',
            'temporarily unavailable',
            'service unavailable',
            '503',
            '429',  // Rate limited
            'rate limit',
            'quota exceeded',
        );
        
        foreach ($transient_patterns as $pattern) {
            if (strpos($error_msg, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract essay text from submission.
     * Tries to get text from various submission plugin types.
     *
     * @param object $submission The submission object.
     * @return string The essay text.
     */
    private static function extract_essay_text($submission, $assignmentid = 0) {
        global $DB;
        
        // Try to get text from onlinetext plugin (most common for essays).
        $element = $DB->get_record('assignsubmission_onlinetext', array(
            'submission' => $submission->id,
        ));
        
        if ($element && !empty($element->onlinetext)) {
            // Strip HTML tags and get clean text.
            return self::clean_text($element->onlinetext);
        }
        
        // Try file submission plugin (txt/docx/pdf best-effort extraction).
        return self::extract_file_submission_text($assignmentid, (int)$submission->id);
    }
    
    /**
     * Clean HTML and special characters from text.
     *
     * @param string $text The text to clean.
     * @return string Cleaned text.
     */
    private static function clean_text($text) {
        // Remove HTML tags.
        $text = strip_tags($text);
        
        // Decode HTML entities.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalise UTF-8 to avoid DB and JSON encoding failures.
        // This is critical for avoiding validation failures later
        if (function_exists('iconv')) {
            $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        } else if (function_exists('mb_convert_encoding')) {
            $text = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        } else {
            // Fallback: JSON encode/decode to sanitize
            $json = json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_string($decoded)) {
                    $text = $decoded;
                }
            }
        }
        
        // Remove extra whitespace.
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Limit essay size sent to LLM to avoid provider timeouts on very long text.
     *
     * @param string $text
     * @return string
     */
    private static function truncate_for_model($text) {
        $text = (string)$text;
        $maxchars = 3500;
        if (\core_text::strlen($text) <= $maxchars) {
            return $text;
        }

        return \core_text::substr($text, 0, $maxchars) . "\n\n[...truncated for processing...]";
    }

    /**
     * Truncate text to reduce risk of DB write failures on TEXT fields.
     *
     * @param string|null $text
     * @param int $maxbytes
     * @return string|null
     */
    private static function truncate_text_for_db($text, $maxbytes = 60000) {
        if ($text === null) {
            return null;
        }
        $text = (string)$text;
        $cleaned = false;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
                $cleaned = true;
            }
        }
        if (!$cleaned && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if ($converted !== false) {
                $text = $converted;
                $cleaned = true;
            }
        }

        // Final safety net: replace invalid UTF-8 bytes rather than failing DB writes.
        if (!$cleaned) {
            $json = json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_string($decoded)) {
                    $text = $decoded;
                }
            }
        }

        if (strlen($text) <= $maxbytes) {
            return $text;
        }
        return substr($text, 0, $maxbytes);
    }

    /**
     * Extract text from assignment file submissions.
     *
     * @param int $assignmentid
     * @param int $submissionid
     * @return string
     */
    private static function extract_file_submission_text($assignmentid, $submissionid) {
        if ($assignmentid <= 0 || $submissionid <= 0) {
            return '';
        }

        $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return '';
        }

        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submissionid,
            'filename',
            false
        );

        if (empty($files)) {
            return '';
        }

        $chunks = array();
        foreach ($files as $file) {
            if ($file->get_filesize() <= 0 || $file->get_filesize() > 5 * 1024 * 1024) {
                continue;
            }
            $text = self::extract_text_from_stored_file($file);
            if (!empty($text)) {
                $chunks[] = $text;
            }
        }

        if (empty($chunks)) {
            return '';
        }

        return trim(implode("\n\n", $chunks));
    }

    /**
     * Extract text from one stored file based on extension/mimetype.
     *
     * @param \stored_file $file
     * @return string
     */
    private static function extract_text_from_stored_file(\stored_file $file) {
        $filename = strtolower((string)$file->get_filename());
        $mimetype = strtolower((string)$file->get_mimetype());
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext === 'txt' || $ext === 'md' || $mimetype === 'text/plain') {
            return self::clean_text((string)$file->get_content());
        }

        if ($ext === 'docx' ||
            $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return self::extract_text_from_docx((string)$file->get_content());
        }

        if ($ext === 'pdf' || $mimetype === 'application/pdf') {
            return self::extract_text_from_pdf((string)$file->get_content());
        }

        if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'bmp', 'tif', 'tiff'), true) ||
            strpos($mimetype, 'image/') === 0) {
            return self::extract_text_from_image((string)$file->get_content(), $ext);
        }

        return '';
    }

    /**
     * Extract text from DOCX binary content.
     *
     * @param string $content
     * @return string
     */
    private static function extract_text_from_docx($content) {
        if (empty($content) || !class_exists('ZipArchive')) {
            return '';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eai_docx_');
        if ($tmp === false) {
            return '';
        }

        file_put_contents($tmp, $content);
        $zip = new \ZipArchive();
        $text = '';

        if ($zip->open($tmp) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml !== false) {
                $xml = preg_replace('/<\/w:p>/', "\n", (string)$xml);
                $xml = strip_tags((string)$xml);
                $text = self::clean_text((string)$xml);
            }
            $zip->close();
        }

        @unlink($tmp);
        return $text;
    }

    /**
     * Best-effort extraction from PDF binary content.
     *
     * @param string $content
     * @return string
     */
    private static function extract_text_from_pdf($content) {
        if (empty($content)) {
            return '';
        }

        $chunks = array();

        // 1) Try directly on full PDF body.
        $chunks = array_merge($chunks, self::extract_pdf_text_chunks_from_stream($content));

        // 2) Try de-compressed stream objects (common in modern PDFs).
        if (preg_match_all('/<<(.*?)>>\s*stream\s*(.*?)\s*endstream/s', $content, $streams, PREG_SET_ORDER)) {
            foreach ($streams as $s) {
                $dict = isset($s[1]) ? (string)$s[1] : '';
                $data = isset($s[2]) ? (string)$s[2] : '';
                if ($data === '') {
                    continue;
                }

                $decoded = $data;
                if (stripos($dict, 'FlateDecode') !== false) {
                    $tmp = @gzuncompress($data);
                    if ($tmp === false) {
                        $tmp = @zlib_decode($data);
                    }
                    if ($tmp === false) {
                        $tmp = @gzinflate($data);
                    }
                    if ($tmp !== false && $tmp !== '') {
                        $decoded = $tmp;
                    }
                }

                $chunks = array_merge($chunks, self::extract_pdf_text_chunks_from_stream($decoded));
            }
        }

        if (empty($chunks)) {
            return '';
        }

        return self::clean_text(implode("\n", $chunks));
    }

    /**
     * OCR extraction from image files using Tesseract (best-effort).
     *
     * @param string $content
     * @param string $ext
     * @return string
     */
    private static function extract_text_from_image($content, $ext = 'png') {
        $enabled = get_config('local_essay_ai_grader', 'ocr_enabled');
        if ($enabled !== false && empty($enabled)) {
            return '';
        }

        if (empty($content)) {
            return '';
        }

        $tesseract = self::resolve_tesseract_binary();
        if (empty($tesseract)) {
            return '';
        }

        $lang = (string)(get_config('local_essay_ai_grader', 'ocr_languages') ?: 'ind+eng');
        if (!preg_match('/^[A-Za-z0-9+_-]+$/', $lang)) {
            $lang = 'ind+eng';
        }

        $tmppath = tempnam(sys_get_temp_dir(), 'eai_img_');
        if ($tmppath === false) {
            return '';
        }

        $imagepath = $tmppath . '.' . ($ext ?: 'png');
        @rename($tmppath, $imagepath);
        if (@file_put_contents($imagepath, $content) === false) {
            @unlink($imagepath);
            return '';
        }

        $output = self::run_tesseract($tesseract, $imagepath, $lang);
        @unlink($imagepath);

        if (empty($output)) {
            return '';
        }

        return self::clean_text($output);
    }

    /**
     * Resolve tesseract binary path from config or common locations.
     *
     * @return string
     */
    private static function resolve_tesseract_binary() {
        $configured = trim((string)get_config('local_essay_ai_grader', 'tesseract_path'));
        $candidates = array();

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates = array_merge($candidates, array(
            'tesseract',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
        ));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (strpos($candidate, DIRECTORY_SEPARATOR) !== false || strpos($candidate, ':') !== false) {
                if (!is_file($candidate)) {
                    continue;
                }
            }

            if (self::check_tesseract_available($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Check if a tesseract binary is runnable.
     *
     * @param string $binary
     * @return bool
     */
    private static function check_tesseract_available($binary) {
        $cmd = self::escape_shell_arg($binary) . ' --version';
        $result = self::run_command($cmd);
        return !empty($result['stdout']) || !empty($result['stderr']);
    }

    /**
     * Run tesseract OCR and return stdout text.
     *
     * @param string $binary
     * @param string $imagepath
     * @param string $lang
     * @return string
     */
    private static function run_tesseract($binary, $imagepath, $lang) {
        $cmd = self::escape_shell_arg($binary)
            . ' ' . self::escape_shell_arg($imagepath)
            . ' stdout -l ' . self::escape_shell_arg($lang)
            . ' --psm 6';

        $result = self::run_command($cmd);
        return trim((string)$result['stdout']);
    }

    /**
     * Execute shell command and capture stdout/stderr.
     *
     * @param string $command
     * @return array{stdout:string,stderr:string,exitcode:int}
     */
    private static function run_command($command) {
        $output = array('stdout' => '', 'stderr' => '', 'exitcode' => 1);

        if (!function_exists('proc_open')) {
            $fallback = @shell_exec($command);
            $output['stdout'] = (string)$fallback;
            $output['exitcode'] = empty($fallback) ? 1 : 0;
            return $output;
        }

        $desc = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = @proc_open($command, $desc, $pipes);
        if (!is_resource($process)) {
            return $output;
        }

        fclose($pipes[0]);
        $output['stdout'] = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $output['stderr'] = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $output['exitcode'] = proc_close($process);

        return $output;
    }

    /**
     * Escape shell arguments cross-platform.
     *
     * @param string $value
     * @return string
     */
    private static function escape_shell_arg($value) {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return escapeshellarg($value);
    }

    /**
     * Extract text chunks from a PDF content stream.
     *
     * @param string $stream
     * @return array
     */
    private static function extract_pdf_text_chunks_from_stream($stream) {
        $chunks = array();

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $stream, $m1)) {
            foreach ($m1[0] as $match) {
                if (preg_match('/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s', $match, $t)) {
                    $chunks[] = self::decode_pdf_literal($t[1]);
                }
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/s', $stream, $mhex)) {
            foreach ($mhex[1] as $hex) {
                $chunks[] = self::decode_pdf_hex_string($hex);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $m2)) {
            foreach ($m2[1] as $arr) {
                $line = '';

                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $arr, $parts)) {
                    foreach ($parts[0] as $p) {
                        $line .= self::decode_pdf_literal(substr($p, 1, -1));
                    }
                }

                if (preg_match_all('/<([0-9A-Fa-f]+)>/s', $arr, $hexparts)) {
                    foreach ($hexparts[1] as $hex) {
                        $line .= self::decode_pdf_hex_string($hex);
                    }
                }

                if ($line !== '') {
                    $chunks[] = $line;
                }
            }
        }

        return $chunks;
    }

    /**
     * Decode PDF escaped literal string content.
     *
     * @param string $literal
     * @return string
     */
    private static function decode_pdf_literal($literal) {
        $text = str_replace(
            array('\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'),
            array("\n", "\r", "\t", "\x08", "\x0C", '(', ')', '\\'),
            $literal
        );

        // Decode octal escapes: \123.
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
            return chr(octdec($m[1]));
        }, $text);

        return $text;
    }

    /**
     * Decode PDF hex string content (<...>).
     *
     * @param string $hex
     * @return string
     */
    private static function decode_pdf_hex_string($hex) {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', (string)$hex);
        if ($hex === '') {
            return '';
        }
        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }

        $bin = @hex2bin($hex);
        if ($bin === false) {
            return '';
        }

        // Heuristic for UTF-16BE text in PDFs.
        if (strpos($bin, "\x00") !== false && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
            if (is_string($conv) && $conv !== '') {
                return $conv;
            }
        }

        return $bin;
    }

    /**
     * Normalise AI result so score is always consistent with criteria_scores.
     *
     * @param array $result
     * @param float|int $maxscore
     * @return array
     */
    private static function normalise_result(array $result, $maxscore, $usecriteriasum = true, array $criteria_weights = array(), array $criteria_max_points = array(), $essaytext = '', $answerkey = '') {
        $maxscore = (float)$maxscore;
        if ($maxscore <= 0) {
            $maxscore = 100.0;
        }

        $criteria = array();
        if (!empty($result['criteria_scores']) && is_array($result['criteria_scores'])) {
            foreach ($result['criteria_scores'] as $name => $value) {
                if (is_numeric($value)) {
                    $criteria[$name] = (float)$value;
                }
            }
        }

        $score = isset($result['score']) && is_numeric($result['score']) ? (float)$result['score'] : 0.0;

        // Normalize each criterion score by max points and enforce minimum credit for any non-empty answer.
        if (!empty($criteria)) {
            $normalizedcriteria = array();
            $hastext = trim((string)$essaytext) !== '';
            $minscore = $hastext ? 1.0 : 0.0;

            foreach ($criteria as $name => $value) {
                $maxpoints = isset($criteria_max_points[$name]) ? (float)$criteria_max_points[$name] : null;
                if ($maxpoints === null || $maxpoints <= 0) {
                    $maxpoints = isset($criteria_weights[$name]) ? (float)$criteria_weights[$name] : $maxscore;
                }
                if ($maxpoints <= 0) {
                    $maxpoints = $maxscore;
                }

                $value = (float)$value;
                if ($value < 0) {
                    $value = 0;
                }
                if ($value > $maxpoints) {
                    $value = $maxpoints;
                }
                if ($hastext && $value < $minscore) {
                    $value = $minscore;
                }
                $normalizedcriteria[$name] = round($value, 2);
            }
            $criteria = $normalizedcriteria;
        }

        if ($usecriteriasum && !empty($criteria)) {
            $score = array_sum($criteria);
        } else if (!$usecriteriasum) {
            // Holistic mode: support raw 0-4 score from prompt and convert to Moodle scale.
            if ($score >= 0 && $score <= 4 && $maxscore >= 100) {
                $score = $score * 25.0;
            }
        }

        if ($score < 0) {
            $score = 0.0;
        }
        if ($score > $maxscore) {
            $score = $maxscore;
        }
        $score = round($score, 2);

        $result['score'] = $score;
        $result['criteria_scores'] = $criteria;
        return $result;
    }

    /**
     * Scale criteria values proportionally so that their sum equals the target score.
     *
     * @param array $criteria
     * @param float $target
     * @return array
     */
    private static function scale_criteria_to_score(array $criteria, $target) {
        $sum = array_sum($criteria);
        if ($sum <= 0) {
            return $criteria;
        }

        $scaled = array();
        foreach ($criteria as $name => $value) {
            $scaled[$name] = round(($value / $sum) * $target, 2);
        }

        // Ensure rounding does not break the total by adjusting first criterion.
        $current = array_sum($scaled);
        $delta = round($target - $current, 2);
        if (!empty($scaled) && abs($delta) >= 0.01) {
            $firstkey = array_key_first($scaled);
            $scaled[$firstkey] = round($scaled[$firstkey] + $delta, 2);
            if ($scaled[$firstkey] < 0) {
                $scaled[$firstkey] = 0;
            }
        }

        return $scaled;
    }
    
    /**
     * Mark assessment as error and log it.
     *
     * @param int $assessmentid The assessment ID.
     * @param string $errormsg The error message.
     * @return void
     */
    private static function mark_error($assessmentid, $errormsg) {
        global $DB;
        
        try {
            $update = new \stdClass();
            $update->id = $assessmentid;
            $update->status = 'error';
            $update->score = null;
            $update->error_message = $errormsg;
            $update->timemodified = time();
            $update->modified = time();
            
            $DB->update_record('local_essay_ai_assess', $update);
            
            logging_manager::error('Assessment marked as error', $assessmentid,
                array('error_message' => $errormsg));
                
        } catch (\Throwable $e) {
            logging_manager::critical('Failed to mark assessment as error', $assessmentid,
                array('original_error' => $errormsg, 'marking_error' => $e->getMessage()));
        }
    }

    /**
     * Create AI client based on plugin provider setting.
     *
     * @return object|gemini_client|ollama_client
     */
    private static function get_ai_client() {
        $provider = get_config('local_essay_ai_grader', 'ai_provider') ?: 'gemini';
        
        if ($provider === 'ollama') {
            logging_manager::debug('Initializing Ollama AI client');
            return new ollama_client();
        }
        
        logging_manager::debug('Initializing Gemini AI client');
        return new gemini_client();
    }
    
    /**
     * Notify teacher that assessment is ready for review.
     *
     * @param object $assessment The assessment record.
     * @param array $result The assessment result.
     * @return void
     */
    private static function notify_teacher($assessment, $result) {
        global $DB;
        
        // Get user data.
        $user = $DB->get_record('user', array('id' => $assessment->userid));
        $assignment = $DB->get_record('assign', array('id' => $assessment->assignmentid));
        
        if (!$user || !$assignment) {
            return;
        }
        
        // Get course and assignment details.
        $course = $DB->get_record('course', array('id' => $assignment->course));
        $cm = get_coursemodule_from_instance('assign', $assignment->id);
        
        // Get teachers.
        $context = \context_module::instance($cm->id);
        $teachers = get_enrolled_users($context, 'mod/assign:grade');
        
        foreach ($teachers as $teacher) {
            // Send notification.
            $message = new \stdClass();
            $message->useridfrom = $user->id;
            $message->useridto = $teacher->id;
            $message->subject = get_string('assessment_ready_subject', 'local_essay_ai_grader',
                                          $assignment->name);
            $message->body = get_string('assessment_ready_body', 'local_essay_ai_grader', (object)array(
                'assignmentname' => $assignment->name,
                'studentname' => fullname($user),
            ));
            $message->contexturl = new \moodle_url('/local/essay_ai_grader/review.php', array(
                'assignmentid' => $assignment->id,
            ));
            
            // Actually sending messages would go here if configured.
            // For now, this is a placeholder.
        }
    }
    
    /**
     * Get pending assessments for an assignment.
     *
     * @param int $assignmentid The assignment ID.
     * @param int $limit Maximum number of records to fetch.
     * @return array Array of pending assessment records.
     */
    public static function get_pending_assessments($assignmentid, $limit = 100) {
        global $DB;
        
        return $DB->get_records('local_essay_ai_assess', array(
            'assignmentid' => $assignmentid,
            'status' => 'pending',
        ), 'created ASC', '*', 0, $limit);
    }
    
    /**
     * Get completed assessments awaiting teacher review.
     *
     * @param int $assignmentid The assignment ID.
     * @return array Array of completed assessment records.
     */
    public static function get_completed_assessments($assignmentid) {
        global $DB;
        
        $sql = 'SELECT * FROM {local_essay_ai_assess} 
                WHERE assignmentid = ? 
                AND status = ?
                AND teacher_accepted IS NULL
                ORDER BY timemodified DESC';
        
        return $DB->get_records_sql($sql, array($assignmentid, 'completed'));
    }
    
    /**
     * Accept teacher's decision on a grade.
     *
     * @param int $assessmentid The assessment ID.
     * @param bool $accept Whether to accept the AI suggestion.
     * @param float $finalgrade The final grade (can override AI suggestion).
     * @param string $feedback Additional teacher feedback.
     * @return bool Success.
     */
    public static function review_assessment($assessmentid, $accept, $finalgrade = null, $feedback = '') {
        global $DB;
        
        $assessment = $DB->get_record('local_essay_ai_assess', array('id' => $assessmentid));
        if (!$assessment) {
            return false;
        }
        
        $update = new \stdClass();
        $update->id = $assessmentid;
        $update->teacher_accepted = $accept ? 1 : 0;
        $update->teacher_finalgrade = $finalgrade !== null ? $finalgrade : ($accept ? $assessment->score : null);
        $update->teacher_feedback = $feedback;
        $update->timemodified = time();
        $update->modified = time();
        
        return $DB->update_record('local_essay_ai_assess', $update);
    }
    
    /**
     * Save final grade to assignment.
     *
     * @param int $assignmentid The assignment ID.
     * @param int $userid The user ID.
     * @param float $grade The grade to save.
     * @return bool Success.
     */
    public static function save_grade_to_assignment($assignmentid, $userid, $grade) {
        global $DB, $USER, $CFG;
        
        try {
            // Get assignment instance.
            $assign = $DB->get_record('assign', array('id' => $assignmentid));
            if (!$assign) {
                logging_manager::warning('Assignment not found', $assignmentid);
                return false;
            }

            $cm = get_coursemodule_from_instance('assign', $assignmentid, $assign->course);
            if (!$cm) {
                logging_manager::warning('Course module not found for assignment', $assignmentid);
                return false;
            }

            require_once($CFG->dirroot . '/mod/assign/locallib.php');

            $context = \context_module::instance($cm->id);
            $course = $DB->get_record('course', array('id' => $assign->course), '*', MUST_EXIST);

            $assignobj = new \assign($context, $cm, $course);
            $gradeitem = $assignobj->get_user_grade($userid, true);
            if (!$gradeitem) {
                logging_manager::warning('Could not get grade item for user', $userid,
                    array('assignmentid' => $assignmentid));
                return false;
            }

            $gradeitem->grade = (float)$grade;
            $gradeitem->grader = !empty($USER->id) ? $USER->id : -1;
            $result = $assignobj->update_grade($gradeitem, false);
            
            if ($result) {
                logging_manager::info('Grade saved successfully', $assignmentid,
                    array('userid' => $userid, 'grade' => $grade));
            } else {
                logging_manager::warning('Failed to update grade', $assignmentid,
                    array('userid' => $userid, 'grade' => $grade));
            }
            
            return $result;
        } catch (\Throwable $e) {
            logging_manager::error('Exception saving grade: ' . $e->getMessage(), $assignmentid,
                array('userid' => $userid, 'grade' => $grade, 'exception' => $e));
            return false;
        }
    }

    /**
     * Save LLM feedback to assignment feedback column (assignfeedback_comments).
     * This makes the AI analysis visible in the student feedback section.
     * 
     * @param int $assignmentid The assignment ID.
     * @param int $userid The user/student ID.
     * @param string $feedback The LLM feedback text.
     * @param string $criteria_scores_json JSON encoded criteria scores.
     * @return bool Success or failure.
     */
    public static function save_feedback_to_assignment($assignmentid, $userid, $feedback, $criteria_scores_json = null, $gemini_response_json = null) {
        global $DB, $CFG;

        try {
            // Get assignment instance.
            $assign = $DB->get_record('assign', array('id' => $assignmentid));
            if (!$assign) {
                return false;
            }

            $cm = get_coursemodule_from_instance('assign', $assignmentid, $assign->course);
            if (!$cm) {
                return false;
            }

            require_once($CFG->dirroot . '/mod/assign/locallib.php');

            $context = \context_module::instance($cm->id);
            $course = $DB->get_record('course', array('id' => $assign->course), '*', MUST_EXIST);

            $assignobj = new \assign($context, $cm, $course);
            
            // Get the grade record for this user.
            $gradeitem = $assignobj->get_user_grade($userid, false);
            if (!$gradeitem) {
                return false;
            }

            // Build feedback text with criteria scores if available.
            $feedbacktext = '<div class="llm-assignment-feedback">';
            $feedbacktext .= '<div class="llm-feedback-summary">' . htmlspecialchars($feedback) . '</div>';

            if (!empty($criteria_scores_json)) {
                $criteria_scores = json_decode($criteria_scores_json, true);
                if (is_array($criteria_scores) && !empty($criteria_scores)) {
                    $feedbacktext .= '<div class="llm-feedback-section">';
                    $feedbacktext .= '<h3>' . htmlspecialchars(get_string('criteria_breakdown', 'local_essay_ai_grader', false) ?? 'Criteria Breakdown:') . '</h3>';
                    $feedbacktext .= '<ul>';
                    foreach ($criteria_scores as $criterion => $score) {
                        $feedbacktext .= '<li><strong>' . htmlspecialchars($criterion) . '</strong>: ' . round($score, 2) . '/100</li>';
                    }
                    $feedbacktext .= '</ul>';
                    $feedbacktext .= '</div>';
                }
            }

            if (!empty($gemini_response_json)) {
                $raw = json_decode($gemini_response_json, true);
                if (is_array($raw) && !empty($raw['improvement_suggestions'])) {
                    $suggestions = $raw['improvement_suggestions'];
                    if (is_array($suggestions) && !empty($suggestions)) {
                        $feedbacktext .= '<div class="llm-feedback-section">';
                        $feedbacktext .= '<h3>' . htmlspecialchars(get_string('improvement_suggestions', 'local_essay_ai_grader')) . '</h3>';
                        $feedbacktext .= '<ol>';
                        foreach ($suggestions as $s) {
                            $feedbacktext .= '<li>' . nl2br(htmlspecialchars($s)) . '</li>';
                        }
                        $feedbacktext .= '</ol>';
                        $feedbacktext .= '</div>';
                    }
                }
            }

            $feedbacktext .= '</div>';

            // Check if feedback already exists.
            $existing = $DB->get_record('assignfeedback_comments', array('grade' => $gradeitem->id));
            
            if ($existing) {
                // Append to existing feedback.
                // If AI feedback block doesn't exist, add full block.
                if (strpos($existing->commenttext, 'LLM Analysis') === false) {
                    $existing->commenttext .= '<div class="llm-ai-analysis"><h2>LLM Analysis</h2>' . $feedbacktext . '</div>';
                    $existing->commentformat = FORMAT_HTML;
                    return $DB->update_record('assignfeedback_comments', $existing);
                }

                // If AI feedback exists but improvement suggestions are missing,
                // append only the suggestions so teachers/students get them.
                $improvlabel = get_string('improvement_suggestions', 'local_essay_ai_grader');
                if (strpos($existing->commenttext, $improvlabel) === false && !empty($gemini_response_json)) {
                    $raw = json_decode($gemini_response_json, true);
                    if (is_array($raw) && !empty($raw['improvement_suggestions'])) {
                        $suggestions = $raw['improvement_suggestions'];
                        if (is_array($suggestions) && !empty($suggestions)) {
                            $existing->commenttext .= '<div class="llm-feedback-section">';
                            $existing->commenttext .= '<h3>' . htmlspecialchars($improvlabel) . '</h3>';
                            $existing->commenttext .= '<ol>';
                            foreach ($suggestions as $s) {
                                $existing->commenttext .= '<li>' . nl2br(htmlspecialchars($s)) . '</li>';
                            }
                            $existing->commenttext .= '</ol>';
                            $existing->commenttext .= '</div>';
                            $existing->commentformat = FORMAT_HTML;
                            return $DB->update_record('assignfeedback_comments', $existing);
                        }
                    }
                }

                return true;  // Already has AI feedback (and suggestions), skip.
            } else {
                // Create new feedback record.
                $feedbackrecord = new \stdClass();
                $feedbackrecord->grade = $gradeitem->id;
                $feedbackrecord->assignment = $assignmentid;
                $feedbackrecord->commenttext = '<div class="llm-ai-analysis"><h2>LLM Analysis</h2>' . $feedbacktext . '</div>';
                $feedbackrecord->commentformat = FORMAT_HTML;
                
                return $DB->insert_record('assignfeedback_comments', $feedbackrecord) > 0;
            }
        } catch (\Throwable $e) {
            logging_manager::warning('Failed to save feedback to assignment', 0,
                array('error' => $e->getMessage(), 'userid' => $userid, 'assignmentid' => $assignmentid));
            return false;
        }
    }
}

