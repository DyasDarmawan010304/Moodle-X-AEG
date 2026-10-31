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
 * Default Evaluation Criteria Manager.
 * 
 * Provides 6 standard evaluation criteria that teachers can use:
 * 1. Content/Isi - Quality and relevance of content
 * 2. Organization/Organisasi - Structure and flow
 * 3. Grammar/Tata Bahasa - Grammar and syntax
 * 4. Vocabulary/Kosakata - Word choice and usage
 * 5. Spelling/Ejaan - Spelling and punctuation
 * 6. Relevance/Relevansi - Relevance to question/topic
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class default_criteria {
    
    // Criterion IDs
    const CRITERION_CONTENT = 'content';
    const CRITERION_ORGANIZATION = 'organization';
    const CRITERION_GRAMMAR = 'grammar';
    const CRITERION_VOCABULARY = 'vocabulary';
    const CRITERION_SPELLING = 'spelling';
    const CRITERION_RELEVANCE = 'relevance';
    
    // Default weights (sum = 100)
    const DEFAULT_WEIGHTS = array(
        self::CRITERION_RELEVANCE => 20,
        self::CRITERION_CONTENT => 30,
        self::CRITERION_ORGANIZATION => 15,
        self::CRITERION_GRAMMAR => 15,
        self::CRITERION_VOCABULARY => 10,
        self::CRITERION_SPELLING => 10,
    );
    
    /**
     * Get all default criteria with descriptions.
     *
     * @return array Array with criterion ID as key, and array with name/description as value
     */
    public static function get_all_criteria() {
        return array(
            self::CRITERION_CONTENT => array(
                'id' => self::CRITERION_CONTENT,
                'name_en' => 'Content Quality',
                'name_id' => 'Kualitas Isi',
                'description_en' => 'Quality, accuracy, and relevance of content; depth of ideas; coverage of key points from expected answer',
                'description_id' => 'Kualitas, keakuratan, dan relevansi konten; kedalaman ide; cakupan poin-poin penting dari kunci jawaban',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_CONTENT],
            ),
            self::CRITERION_ORGANIZATION => array(
                'id' => self::CRITERION_ORGANIZATION,
                'name_en' => 'Organization & Structure',
                'name_id' => 'Organisasi & Struktur',
                'description_en' => 'Logical flow, clear structure, transitions between ideas',
                'description_id' => 'Alur logis, struktur jelas, transisi antar ide',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_ORGANIZATION],
            ),
            self::CRITERION_GRAMMAR => array(
                'id' => self::CRITERION_GRAMMAR,
                'name_en' => 'Grammar & Sentence Structure',
                'name_id' => 'Tata Bahasa & Struktur Kalimat',
                'description_en' => 'Correct grammar, sentence variety, proper syntax',
                'description_id' => 'Tata bahasa benar, variasi kalimat, sintaksis tepat',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_GRAMMAR],
            ),
            self::CRITERION_VOCABULARY => array(
                'id' => self::CRITERION_VOCABULARY,
                'name_en' => 'Vocabulary & Word Choice',
                'name_id' => 'Kosakata & Pilihan Kata',
                'description_en' => 'Appropriate, varied, and precise vocabulary usage',
                'description_id' => 'Penggunaan kosakata yang tepat, beragam, dan presisi',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_VOCABULARY],
            ),
            self::CRITERION_SPELLING => array(
                'id' => self::CRITERION_SPELLING,
                'name_en' => 'Spelling & Punctuation',
                'name_id' => 'Ejaan & Tanda Baca',
                'description_en' => 'Correct spelling, punctuation, and capitalization',
                'description_id' => 'Ejaan, tanda baca, dan kapitalisasi yang benar',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_SPELLING],
            ),
            self::CRITERION_RELEVANCE => array(
                'id' => self::CRITERION_RELEVANCE,
                'name_en' => 'Relevance to Topic/Question',
                'name_id' => 'Relevansi dengan Topik/Soal',
                'description_en' => 'How well the response addresses the question/topic',
                'description_id' => 'Seberapa baik respons menjawab pertanyaan/topik',
                'weight' => self::DEFAULT_WEIGHTS[self::CRITERION_RELEVANCE],
            ),
        );
    }
    
    /**
     * Get a single criterion by ID.
     *
     * @param string $criterionid The criterion ID
     * @return array|null Criterion data or null if not found
     */
    public static function get_criterion($criterionid) {
        $all = self::get_all_criteria();
        return isset($all[$criterionid]) ? $all[$criterionid] : null;
    }
    
    /**
     * Get default criteria configuration (all enabled with default weights).
     * Returns JSON-ready format for storage in database.
     *
     * @return string JSON-encoded criteria configuration
     */
    public static function get_default_config() {
        $config = array();
        foreach (self::get_all_criteria() as $criterion) {
            $config[$criterion['id']] = array(
                'enabled' => true,
                'weight' => $criterion['weight'],
                'name' => $criterion['name_id'],
                'description' => $criterion['description_id'],
            );
        }
        return json_encode($config);
    }
    
    /**
     * Convert criteria config array to format suitable for prompt building.
     * 
     * @param string|array $criteria_config JSON string or array of criteria config
     * @return array Array of enabled criteria with weights
     */
    public static function parse_criteria_config($criteria_config) {
        if (is_string($criteria_config)) {
            $config = json_decode($criteria_config, true);
        } else {
            $config = $criteria_config;
        }
        
        if (!is_array($config)) {
            // Return default if invalid
            $config = json_decode(self::get_default_config(), true);
        }
        
        // Filter to only enabled criteria
        $enabled = array();
        foreach ($config as $criterion_id => $criterion_data) {
            if (!empty($criterion_data['enabled'])) {
                if (isset($criterion_data['weight'])) {
                    $enabled[$criterion_id] = $criterion_data['weight'];
                } else if (isset($criterion_data['score']) && $criterion_data['score'] > 5) {
                    // Legacy or broken storage may contain numeric weight values in score field.
                    $enabled[$criterion_id] = $criterion_data['score'];
                } else {
                    $enabled[$criterion_id] = self::get_all_criteria()[$criterion_id]['weight'] ?? 0;
                }
            }
        }
        
        return $enabled;
    }

    /**
     * Parse max points per criterion from criteria configuration.
     * Falls back to weight if max_points is not defined.
     *
     * @param string|array $criteria_config JSON or array of criteria config
     * @return array Criterion ID => max points
     */
    public static function parse_criteria_max_points($criteria_config) {
        if (is_string($criteria_config)) {
            $config = json_decode($criteria_config, true);
        } else {
            $config = $criteria_config;
        }

        if (!is_array($config)) {
            $config = json_decode(self::get_default_config(), true);
        }

        $maxpoints = array();
        foreach ($config as $criterion_id => $criterion_data) {
            if (empty($criterion_data['enabled'])) {
                continue;
            }
            if (isset($criterion_data['max_points']) && is_numeric($criterion_data['max_points'])) {
                $maxpoints[$criterion_id] = (float)$criterion_data['max_points'];
            } else if (isset($criterion_data['weight']) && is_numeric($criterion_data['weight'])) {
                $maxpoints[$criterion_id] = (float)$criterion_data['weight'];
            } else {
                $maxpoints[$criterion_id] = self::get_all_criteria()[$criterion_id]['weight'] ?? 0;
            }
        }

        return $maxpoints;
    }
    
    /**
     * Normalize weights so they sum to 100.
     * 
     * @param array $weights Array with criterion ID as key and weight as value
     * @return array Normalized weights
     */
    public static function normalize_weights($weights) {
        $total = array_sum($weights);
        
        if ($total == 0) {
            // If all zero, use default equal distribution
            $count = count($weights);
            return array_fill_keys(array_keys($weights), 100 / $count);
        }
        
        $normalized = array();
        foreach ($weights as $criterion_id => $weight) {
            $normalized[$criterion_id] = round(($weight / $total) * 100, 2);
        }
        
        return $normalized;
    }
    
    /**
     * Get criteria names in user's language.
     * 
     * @param string $lang Language code ('en' or 'id')
     * @return array Array of criterion ID => criterion name
     */
    public static function get_criterion_names($lang = 'en') {
        $names = array();
        $key = ($lang === 'id') ? 'name_id' : 'name_en';
        
        foreach (self::get_all_criteria() as $criterion) {
            $names[$criterion['id']] = $criterion[$key];
        }
        
        return $names;
    }
    
    /**
     * Format criteria for display in prompt.
     * Converts criteria config to readable text for Gemini.
     * 
     * @param string|array $criteria_config JSON or array of criteria config
     * @param float $maxscore Maximum score for normalization
     * @return string Formatted criteria text for prompt
     */
    public static function format_for_prompt($criteria_config, $maxscore = 100) {
        $parsed = self::parse_criteria_config($criteria_config);
        $normalized = self::normalize_weights($parsed);
        
        if (empty($normalized)) {
            return '';
        }
        
        $text = "\nKriteria Penilaian:\n";
        
        foreach ($normalized as $criterion_id => $weight) {
            $criterion = self::get_criterion($criterion_id);
            if ($criterion) {
                $points = round(($weight / 100) * $maxscore, 2);
                $text .= "- " . $criterion['name_id'] . " ({$weight}% / {$points} poin): " 
                         . $criterion['description_id'] . "\n";
            }
        }
        
        return $text;
    }
    
    /**
     * Get 5-aspect rubric for SMK Indonesian essay evaluation.
     * Used for post-thesis-defense revisions.
     *
     * @return array Array with criterion ID as key, and array with name/max_points/description as value
     */
    public static function get_five_aspects_rubric() {
        return array(
            'relevansi_ketepatan_isi' => array(
                'id' => 'relevansi_ketepatan_isi',
                'name' => 'Relevansi dan Ketepatan Isi',
                'max_points' => 10,
                'description' => 'Jawaban langsung menjawab inti soal, semua poin utama dari kunci jawaban tercakup dengan benar',
            ),
            'kelengkapan_konsep' => array(
                'id' => 'kelengkapan_konsep',
                'name' => 'Kelengkapan Konsep',
                'max_points' => 8,
                'description' => 'Semua konsep atau poin yang diminta soal disebutkan dan dijelaskan dengan benar sesuai kunci',
            ),
            'kesesuaian_konteks' => array(
                'id' => 'kesesuaian_konteks',
                'name' => 'Kesesuaian dengan Konteks Soal',
                'max_points' => 6,
                'description' => 'Jawaban sepenuhnya mengacu pada konteks, data, atau ilustrasi yang diberikan dalam soal',
            ),
            'ketepatan_bahasa' => array(
                'id' => 'ketepatan_bahasa',
                'name' => 'Ketepatan Bahasa dan Struktur Kalimat',
                'max_points' => 4,
                'description' => 'Menggunakan bahasa baku, struktur kalimat benar, dan mudah dipahami',
            ),
            'ejaan_tanda_baca' => array(
                'id' => 'ejaan_tanda_baca',
                'name' => 'Ejaan dan Tanda Baca',
                'max_points' => 2,
                'description' => 'Ejaan dan tanda baca konsisten dan benar',
            ),
        );
    }
    
    /**
     * Get default config for 5-aspect rubric.
     * All aspects enabled with their maximum points as weights.
     *
     * @return array Configuration array ready for use
     */
    public static function get_five_aspects_default_config() {
        $config = array();
        foreach (self::get_five_aspects_rubric() as $aspect) {
            $config[$aspect['id']] = array(
                'enabled' => true,
                'weight' => $aspect['max_points'],
                'name' => $aspect['name'],
                'description' => $aspect['description'],
                'max_points' => $aspect['max_points'],
            );
        }
        return $config;
    }
    
    /**
     * Get default config for 5-aspect rubric as JSON.
     *
     * @return string JSON-encoded configuration
     */
    public static function get_five_aspects_default_config_json() {
        return json_encode(self::get_five_aspects_default_config());
    }
}
