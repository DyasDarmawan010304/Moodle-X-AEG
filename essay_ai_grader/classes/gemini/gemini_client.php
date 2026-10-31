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

namespace local_essay_ai_grader\gemini;

defined('MOODLE_INTERNAL') || die();

/**
 * Gemini API Client for essay assessment.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini_client {
    
    const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    private $apikey;
    private $model;
    private $lasterror = '';
    private static $availablemodelscache = array();
    
    /**
     * Initialize Gemini client with API key.
     *
     * @param string $apikey The Gemini API key.
     * @param string $model The model to use.
     */
    public function __construct($apikey = null, $model = null) {
        if (!$apikey) {
            $apikey = get_config('local_essay_ai_grader', 'gemini_api_key');
        }
        
        if (!$model) {
            $model = get_config('local_essay_ai_grader', 'gemini_model') ?? 'gemini-flash-latest';
        }

        $this->apikey = trim((string)$apikey);
        $requestedmodel = $this->sanitize_model_name((string)$model);
        $this->model = $this->resolve_model_name($requestedmodel);
    }

    /**
     * Sanitize model name from admin setting/user input.
     *
     * @param string $model
     * @return string
     */
    private function sanitize_model_name($model) {
        $clean = strtolower(trim($model));
        while (strpos($clean, 'models/') === 0) {
            $clean = substr($clean, 7);
        }
        $clean = str_replace(array(' ', '_'), '-', $clean);
        $clean = preg_replace('/-+/', '-', $clean);
        return trim((string)$clean, '-');
    }

    /**
     * Resolve model name against available generateContent models.
     *
     * @param string $requestedmodel
     * @return string
     */
    private function resolve_model_name($requestedmodel) {
        $defaultmodel = 'gemini-flash-latest';
        $requestedmodel = $requestedmodel !== '' ? $requestedmodel : $defaultmodel;

        $available = $this->get_available_models();
        if (empty($available)) {
            return $requestedmodel;
        }

        if (in_array($requestedmodel, $available, true)) {
            return $requestedmodel;
        }

        if (in_array($defaultmodel, $available, true)) {
            debugging('Gemini model "' . $requestedmodel . '" unavailable; fallback to "' . $defaultmodel . '".',
                DEBUG_DEVELOPER);
            return $defaultmodel;
        }

        $fallback = reset($available);
        debugging('Gemini model "' . $requestedmodel . '" unavailable; fallback to "' . $fallback . '".',
            DEBUG_DEVELOPER);
        return (string)$fallback;
    }

    /**
     * Fetch available Gemini models supporting generateContent.
     *
     * @return array
     */
    private function get_available_models() {
        if ($this->apikey === '') {
            return array();
        }

        $cachekey = md5($this->apikey);
        if (isset(self::$availablemodelscache[$cachekey])) {
            return self::$availablemodelscache[$cachekey];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($this->apikey);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $response = curl_exec($ch);
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        curl_close($ch);

        if ($curlerror !== '' || $httpcode !== 200 || !is_string($response) || trim($response) === '') {
            self::$availablemodelscache[$cachekey] = array();
            return array();
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['models']) || !is_array($decoded['models'])) {
            self::$availablemodelscache[$cachekey] = array();
            return array();
        }

        $models = array();
        foreach ($decoded['models'] as $modelinfo) {
            if (!is_array($modelinfo) || empty($modelinfo['name'])) {
                continue;
            }
            $methods = $modelinfo['supportedGenerationMethods'] ?? array();
            if (!is_array($methods) || !in_array('generateContent', $methods, true)) {
                continue;
            }
            $name = (string)$modelinfo['name'];
            if (strpos($name, 'models/') === 0) {
                $name = substr($name, 7);
            }
            $name = $this->sanitize_model_name($name);
            if ($name !== '') {
                $models[$name] = $name;
            }
        }

        $result = array_values($models);
        self::$availablemodelscache[$cachekey] = $result;
        return $result;
    }
    
    /**
     * Assess an essay using Gemini AI.
     *
     * @param string $essaytext The essay text to assess.
     * @param array $criteria Evaluation criteria array.
     * @param float $maxscore Maximum score for the essay.
     * @param array $guidance Optional teacher guidance (custom prompt, answer key, rubric).
     * @return array Assessment results with score and feedback.
     * @throws \moodle_exception
     */
    public function assess_essay($essaytext, $criteria = array(), $maxscore = 100, $guidance = array()) {
        if (empty($this->apikey)) {
            throw new \moodle_exception('apikey_not_configured', 'local_essay_ai_grader');
        }
        
        if (empty($essaytext)) {
            throw new \moodle_exception('essaytext_empty', 'local_essay_ai_grader');
        }
        
        // Build evaluation prompt.
        $prompt = $this->build_assessment_prompt($essaytext, $criteria, $maxscore, $guidance);
        
        // Call Gemini API.
        $response = $this->call_gemini_api($prompt);
        
        if (!$response) {
            throw new \moodle_exception('api_call_failed', 'local_essay_ai_grader');
        }
        
        // Parse response and extract scores/feedback.
        $result = $this->parse_assessment_response($response, $maxscore);
        
        return $result;
    }
    
    /**
     * Build the assessment prompt for Gemini.
     * Automatically detects and uses:
     * 0. New 5-aspect rubric (if rubricguide contains specific structure)
     * 1. Custom 6-criteria configuration (if enabled)
     * 2. Official standards (Kemendikbud, regional, or custom) if available
     * 3. Legacy 3-criteria mode as fallback
     *
     * @param string $essaytext The essay to assess.
     * @param array $criteria Evaluation criteria configuration.
     * @param float $maxscore Maximum score.
     * @param array $guidance Optional guidance: customPrompt, answerKey, rubricGuide, assignmentid, use_standard, soal, kunci_jawaban.
     * @return string The prompt to send to Gemini.
     */
    private function build_assessment_prompt($essaytext, $criteria = array(), $maxscore = 100, $guidance = array()) {
        // PRIORITY 0: Use custom dynamic criteria if provided.
        if (!empty($guidance['assignmentid']) && is_array($criteria) && !empty($criteria)) {
            $iscustom_config = false;
            foreach ($criteria as $key => $value) {
                if (is_array($value) && isset($value['enabled'])) {
                    $iscustom_config = true;
                    break;
                }
            }

            if ($iscustom_config) {
                try {
                    require_once(dirname(__FILE__) . '/../prompt_sourcer.php');
                    $customprompt = \local_essay_ai_grader\prompt_sourcer::build_prompt_with_custom_criteria(
                        (int)$guidance['assignmentid'], 
                        $criteria,
                        $maxscore,
                        $guidance,
                        $essaytext
                    );
                    return $customprompt;
                } catch (\Exception $e) {
                    debugging('Custom criteria prompting failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    // Fall through to standards or legacy mode
                }
            }
        }

        // PRIORITY 1: Check if 5-aspect rubric is being used (new rubric format)
        // Detect by looking for "Relevansi dan Ketepatan Isi" in rubricguide or use explicit flag
        if (!empty($guidance['rubricguide']) && 
            (strpos($guidance['rubricguide'], 'Relevansi dan Ketepatan Isi') !== false || 
             strpos($guidance['rubricguide'], 'Kelengkapan Konsep') !== false ||
             strpos($guidance['rubricguide'], 'relevansi_ketepatan') !== false)) {
            try {
                require_once(dirname(__FILE__) . '/../prompt_sourcer.php');
                
                // Get soal and kunci_jawaban from guidance
                $soal = !empty($guidance['soal']) ? $guidance['soal'] : '';
                $kunci_jawaban = !empty($guidance['kunci_jawaban']) ? $guidance['kunci_jawaban'] : '';
                
                // Fallback to question_text and expected_answer if soal/kunci_jawaban not set
                if (empty($soal) && !empty($guidance['question_text'])) {
                    $soal = $guidance['question_text'];
                }
                if (empty($kunci_jawaban) && !empty($guidance['answerkey'])) {
                    $kunci_jawaban = $guidance['answerkey'];
                }
                
                // Only use 5-aspect if we have the rubric guide
                $prompt = \local_essay_ai_grader\prompt_sourcer::build_prompt_with_five_aspects_rubric(
                    $soal,
                    $kunci_jawaban,
                    $essaytext
                );
                return $prompt;
            } catch (\Exception $e) {
                debugging('5-aspect rubric prompting failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                // Fall through to standards or legacy mode
            }
        }
        
        // PRIORITY 2: Try to use official standards-based prompt if available
        if (!empty($guidance['use_standard']) && !empty($guidance['assignmentid'])) {
            try {
                require_once(dirname(__FILE__) . '/../prompt_sourcer.php');
                $standardprompt = \local_essay_ai_grader\prompt_sourcer::build_prompt_with_standards(
                    (int)$guidance['assignmentid'],
                    $criteria,
                    $maxscore,
                    $guidance,
                    $essaytext
                );
                return $standardprompt;
            } catch (\Exception $e) {
                // Fall back to legacy prompting if standards system fails
                debugging('Standards-based prompting failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        
        // PRIORITY 3: Legacy prompt building (used when custom/standards not available)
        $criteriatext = '';
        $teacherguidance = '';
        $activecriteria = array();
        
        if (!empty($criteria)) {
            $criteriatext = "\n" . get_string('evaluation_criteria', 'local_essay_ai_grader') . ":\n";
            if (isset($criteria['grammar']) && $criteria['grammar']) {
                $criteriatext .= "- " . get_string('eval_grammar', 'local_essay_ai_grader') . "\n";
                $activecriteria[] = 'grammar';
            }
            if (isset($criteria['content']) && $criteria['content']) {
                $criteriatext .= "- " . get_string('eval_content', 'local_essay_ai_grader') . "\n";
                $activecriteria[] = 'content';
            }
            if (isset($criteria['organization']) && $criteria['organization']) {
                $criteriatext .= "- " . get_string('eval_organization', 'local_essay_ai_grader') . "\n";
                $activecriteria[] = 'organization';
            }
        }
        $isholistic = empty($activecriteria);
        $criterioncount = count($activecriteria);
        $percriterionmax = $criterioncount > 0 ? round(((float)$maxscore / $criterioncount), 2) : (float)$maxscore;
        $criteriajson = json_encode($activecriteria);

        // Build dynamic criteria format based on active criteria (not hardcoded)
        if ($isholistic) {
            $criteriaformat = "\"criteria_scores\": {},\n    \"detailed_feedback\": {}";
            $scoringrules = "- Ini adalah mode holistik: biarkan \"criteria_scores\" dan \"detailed_feedback\" sebagai objek JSON kosong {}\n- Atur \"score\" langsung dalam skala 0..{$maxscore}.";
        } else {
            // Build dynamic score fields based on active criteria
            $score_fields = '';
            $feedback_fields = '';
            $first = true;
            foreach ($activecriteria as $criterion) {
                if (!$first) {
                    $score_fields .= ",\n        ";
                    $feedback_fields .= ",\n        ";
                }
                $first = false;
                $score_fields .= "\"$criterion\": <score_0_hingga_" . round($percriterionmax, 2) . ">";
                $feedback_fields .= "\"$criterion\": \"<umpan_balik_spesifik_untuk_kriteria_ini>\"";
            }
            
            $criteriaformat = "\"criteria_scores\": {\n        " . $score_fields . "\n    },\n    \"detailed_feedback\": {\n        " . $feedback_fields . "\n    }";
            
            // Build dynamic scoring rules
            $band25 = round($percriterionmax * 0.25, 2);
            $band50 = round($percriterionmax * 0.5, 2);
            $band75 = round($percriterionmax * 0.75, 2);
            
            $scoringrules = "- Kriteria yang diaktifkan: {$criteriajson}\n- Gunakan HANYA kunci kriteria yang diaktifkan dalam \"criteria_scores\" dan \"detailed_feedback\"\n- Setiap skor kriteria harus dalam rentang 0..{$percriterionmax}\n- Gunakan pembanding tetap (25%, 50%, 75%, 100%) untuk mengurangi keacakan:\n  0, {$band25}, {$band50}, {$band75}, atau {$percriterionmax}\n- Total \"score\" HARUS sama dengan jumlah semua nilai dalam \"criteria_scores\"\n- Jangan keluarkan skor kriteria di luar rentang 0..{$percriterionmax}";
        }

        if (!empty($guidance['answerkey'])) {
            $teacherguidance .= "\nKunci Jawaban Referensi (disediakan guru):\n" . trim($guidance['answerkey']) . "\n";
        }

        if (!empty($guidance['rubricguide'])) {
            $teacherguidance .= "\n" . get_string('rubric_guide_label', 'local_essay_ai_grader') . " (disediakan guru):\n" . trim($guidance['rubricguide']) . "\n\n";
            $teacherguidance .= "PENTING: Nilai KETAT sesuai dengan panduan rubrik ini. Rubrik mendefinisikan standar yang diharapkan dan bobot penilaian.\n";
        }

        if (!empty($guidance['customprompt'])) {
            $teacherguidance .= "\nInstruksi Guru Tambahan:\n" . trim($guidance['customprompt']) . "\n";
        }
        
        $prompt = <<<PROMPT
Anda adalah penilai esai ahli dengan keahlian mendalam dalam evaluasi tulisan siswa.

Tugas Anda:
1. Nilai esai siswa dari 0 hingga {$maxscore}
2. Berikan umpan balik terperinci untuk setiap kriteria evaluasi
3. Berikan saran perbaikan konkret untuk siswa

{$criteriatext}
{$teacherguidance}

BERIKAN PENILAIAN DALAM FORMAT JSON BERIKUT (HANYA JSON TANPA TEKS LAIN):
{
    "score": <nilai_numerik_0_ke_{$maxscore}>,
    "summary_feedback": "<umpan_balik_keseluruhan_singkat>",
    {$criteriaformat},
    "improvement_suggestions": [
        "<saran_konkret_1_untuk_perbaikan>",
        "<saran_konkret_2_untuk_perbaikan>",
        "<saran_konkret_3_untuk_perbaikan>"
    ]
}

Aturan penilaian (WAJIB PATUHI):
{$scoringrules}
- JANGAN keluarkan teks apa pun di luar JSON yang valid
- Respons HARUS hanya JSON yang valid, tanpa penjelasan atau markdown
- PASTIKAN setiap field terisi dengan nilai konkret, jangan kosong {}

ESAI SISWA YANG AKAN DIEVALUASI:
---
{$essaytext}
---

BERIKAN HANYA JSON YANG VALID. TIDAK BOLEH ADA TEKS LAIN.
PROMPT;
        
        return $prompt;
    }
    
    /**
     * Call the Gemini API.
     *
     * @param string $prompt The prompt to send.
     * @return string|false The API response or false on failure.
     */
    private function call_gemini_api($prompt) {
        $url = self::GEMINI_API_URL . urlencode($this->model) . ':generateContent?key=' . urlencode($this->apikey);
        
        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.0,
                'topP' => 0.95,
                'topK' => 40,
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
                // Reduce reasoning token usage on newer Gemini variants so JSON output is not truncated.
                'thinkingConfig' => array(
                    'thinkingBudget' => 0,
                ),
            )
        );
        
        $jsonpayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonpayload === false) {
            debugging('Gemini payload encoding failed: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            return false;
        }

        $maxattempts = 3;
        $this->lasterror = '';
        for ($attempt = 1; $attempt <= $maxattempts; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonpayload,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                ),
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true,
            ));

            $response = curl_exec($ch);
            $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlerror = curl_error($ch);
            curl_close($ch);

            if (empty($curlerror) && $httpcode === 200 && is_string($response) && trim($response) !== '') {
                return $response;
            }

            $bodypreview = '';
            if (is_string($response) && trim($response) !== '') {
                $bodypreview = ' body=' . substr(trim($response), 0, 300);
            }
            $this->lasterror = $curlerror !== ''
                ? ('Curl error: ' . $curlerror)
                : ('Gemini API error (HTTP ' . $httpcode . ')' . $bodypreview);

            if ($httpcode === 429 || $httpcode >= 500 || $curlerror !== '') {
                $waitseconds = 1 * $attempt;
                if ($httpcode === 429) {
                    $waitseconds = 3 * $attempt;
                }
                usleep($waitseconds * 1000000);
                continue;
            }

            break;
        }

        debugging($this->lasterror, DEBUG_DEVELOPER);
        return false;
    }
    
    /**
     * Parse Gemini API response and extract assessment data.
     *
     * @param string $response The raw API response.
     * @return array Parsed assessment results.
     */
    private function parse_assessment_response($response, $maxscore = 100) {
        try {
            $decoded = json_decode($response, true);

            if (isset($decoded['error']['message'])) {
                return array(
                    'success' => false,
                    'error' => 'Gemini API error: ' . (string)$decoded['error']['message'],
                    'raw_response' => $response,
                    'raw_response_full' => $response,
                );
            }
            
            // Extract text from Gemini response structure.
            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $text = trim((string)$decoded['candidates'][0]['content']['parts'][0]['text']);
                $assessment = $this->decode_assessment_json($text);

                if (is_array($assessment)) {
                    $assessment = $this->normalize_assessment_payload($assessment);
                    // Store extracted assessment JSON to avoid truncation of full API response
                    $assessment_json = json_encode($assessment);
                    return array(
                        'success' => true,
                        'score' => $assessment['score'] ?? 0,
                        'summary_feedback' => $assessment['summary_feedback'] ?? '',
                        'criteria_scores' => $assessment['criteria_scores'] ?? array(),
                        'detailed_feedback' => $assessment['detailed_feedback'] ?? array(),
                        'improvement_suggestions' => $assessment['improvement_suggestions'] ?? array(),
                        'raw_response' => $assessment_json,
                        'raw_response_full' => $response,
                    );
                }

                $fallback = $this->decode_assessment_text_fallback($text, $maxscore);
                if (is_array($fallback)) {
                    // Store extracted assessment JSON from fallback
                    $fallback_json = json_encode($fallback);
                    $fallback['raw_response'] = $fallback_json;
                    $fallback['raw_response_full'] = $response;
                    $fallback['success'] = true;
                    return $fallback;
                }
            }

            if (isset($decoded['candidates'][0]['finishReason'])) {
                return array(
                    'success' => false,
                    'error' => 'Gemini returned non-JSON content (finishReason=' .
                        (string)$decoded['candidates'][0]['finishReason'] . ')',
                    'raw_response' => $response,
                    'raw_response_full' => $response,
                );
            }
            
            return array(
                'success' => false,
                'error' => 'Unable to parse API response',
                'raw_response' => $response,
                'raw_response_full' => $response,
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => $response,
                'raw_response_full' => $response,
            );
        }
    }

    /**
     * Decode assessment JSON with robust fallbacks.
     *
     * @param string $text
     * @return array|null
     */
    private function decode_assessment_json($text) {
        if ($text === '') {
            return null;
        }

        // Remove markdown fences if present.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: extract largest JSON object from mixed text.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Normalize assessment payload from Gemini to a standard shape.
     *
     * @param array $assessment
     * @return array
     */
    private function normalize_assessment_payload(array $assessment) {
        if (empty($assessment)) {
            return $assessment;
        }

        if (!isset($assessment['score']) && isset($assessment['total_skor'])) {
            $assessment['score'] = (float)$assessment['total_skor'];
        }

        if (!isset($assessment['summary_feedback']) && isset($assessment['feedback_umum'])) {
            $assessment['summary_feedback'] = (string)$assessment['feedback_umum'];
        }

        if (!empty($assessment['criteria_scores']) && is_array($assessment['criteria_scores'])) {
            $normalized = array();
            foreach ($assessment['criteria_scores'] as $criterion => $value) {
                if (is_numeric($value)) {
                    $normalized[$criterion] = (float)$value;
                    continue;
                }

                if (is_array($value)) {
                    if (isset($value['skor'])) {
                        $normalized[$criterion] = (float)$value['skor'];
                    } else if (isset($value['score'])) {
                        $normalized[$criterion] = (float)$value['score'];
                    }

                    if (!empty($value['feedback']) && empty($assessment['detailed_feedback'][$criterion])) {
                        if (!is_array($assessment['detailed_feedback'])) {
                            $assessment['detailed_feedback'] = array();
                        }
                        $assessment['detailed_feedback'][$criterion] = (string)$value['feedback'];
                    }
                }
            }
            $assessment['criteria_scores'] = $normalized;
        }

        return $assessment;
    }

    /**
     * Parse non-JSON model output into minimal assessment structure.
     *
     * @param string $text
     * @param float|int $maxscore
     * @return array|null
     */
    private function decode_assessment_text_fallback($text, $maxscore) {
        $text = trim((string)$text);
        if ($text === '') {
            return null;
        }

        $score = null;
        $patterns = array(
            '/"score"\s*[:=]\s*(-?\d+(?:\.\d+)?)/i',
            '/(?:final\s*)?score\s*(?:is|=|:)?\s*(-?\d+(?:\.\d+)?)/i',
            '/^\s*(-?\d+(?:\.\d+)?)\s*$/',
        );
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $score = (float)$m[1];
                break;
            }
        }

        if ($score === null) {
            return null;
        }

        $maxscore = (float)$maxscore;
        if ($maxscore <= 0) {
            $maxscore = 100.0;
        }
        if ($score < 0) {
            $score = 0.0;
        }
        if ($score > $maxscore) {
            $score = $maxscore;
        }

        $summary = preg_replace('/\s+/', ' ', strip_tags($text));
        $summary = trim((string)$summary);
        if (strlen($summary) > 500) {
            $summary = substr($summary, 0, 500) . '...';
        }

        return array(
            'score' => round($score, 2),
            'summary_feedback' => $summary,
            'criteria_scores' => array(),
            'detailed_feedback' => array(),
            'improvement_suggestions' => array(),
        );
    }
}
