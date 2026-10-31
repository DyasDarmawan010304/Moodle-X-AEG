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
 * Prompt Sourcer
 * Builds assessment prompts using official standards from Kemendikbud, Kabupaten, or Research sources.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_sourcer {
    
    /**
     * Build prompt with official standards basis.
     *
     * Priority order:
     * 1. Kemendikbud (Ministry of Education) standard
     * 2. Kabupaten/Kota (Regional) standard  
     * 3. Artikel/Jurnal (Research) standard
     * 4. Teacher-provided guidance
     * 5. Default criteria
     *
     * @param int $assignmentid Assignment ID
     * @param array $criteria User-provided criteria
     * @param float $maxscore Maximum score
     * @param array $guidance Teacher guidance (custom prompt, answer key, rubric)
     * @param string $essaytext The essay text to evaluate
     * @return string Complete prompt with standard basis
     */
    public static function build_prompt_with_standards($assignmentid, $criteria = array(), $maxscore = 100, $guidance = array(), $essaytext = '') {
        // Get official standard (with fallback hierarchy)
        $standard = assessment_standard::get_standard($assignmentid);
        
        $standard_basis = '';
        if ($standard['found']) {
            $standard_basis = self::format_standard_section($standard);
        }
        
        // Build prompt
        $prompt = self::build_base_prompt(
            $criteria,
            $maxscore,
            $guidance,
            $standard_basis,
            $standard['found'] ? $standard['source'] : null,
            $essaytext
        );
        
        return $prompt;
    }
    
    /**
     * Format standard as text section for prompt.
     *
     * @param array $standard Standard data
     * @return string Formatted standard section
     */
    private static function format_standard_section($standard) {
        $source_label = $standard['source_label'];
        $name = $standard['standard_name'];
        $description = $standard['description'];
        
        $section = "\n=== STANDAR EVALUASI RESMI ===\n";
        $section .= "Sumber: {$source_label}\n";
        $section .= "Standar: {$name}\n";
        if (!empty($description)) {
            $section .= "Deskripsi: {$description}\n";
        }
        
        // Add criteria from standard
        if (!empty($standard['criteria'])) {
            $section .= "\nKriteria (dari standar resmi):\n";
            foreach ($standard['criteria'] as $criterion => $description) {
                $section .= "- {$criterion}: {$description}\n";
            }
        }
        
        // Add rubric if available
        if (!empty($standard['rubric'])) {
            $section .= "\nPedoman Rubrik (dari standar resmi):\n";
            $section .= $standard['rubric'] . "\n";
        }
        
        // Add grading scale if available
        if (!empty($standard['grade_scale'])) {
            $section .= "\nSkala Penilaian (dari standar resmi):\n";
            foreach ($standard['grade_scale'] as $range => $label) {
                $section .= "- {$range}: {$label}\n";
            }
        }
        
        if (!empty($standard['reference'])) {
            $section .= "\nReferensi: " . $standard['reference'] . "\n";
        }
        
        $section .= "=============================\n";
        
        return $section;
    }
    
    /**
     * Build prompt with 5-aspect rubric for essay evaluation.
     * 
     * Rubric aspects (total 30 points):
     * 1. Relevansi dan Ketepatan Isi (0–10)
     * 2. Kelengkapan Konsep (0–8)
     * 3. Kesesuaian dengan Konteks Soal (0–6)
     * 4. Ketepatan Bahasa dan Struktur Kalimat (0–4)
     * 5. Ejaan dan Tanda Baca (0–2)
     *
     * @param string $soal The question/prompt text
     * @param string $kunci_jawaban The reference/expected answer key
     * @param string $jawaban_siswa The student's answer/essay
     * @return string Complete prompt for Gemini with JSON output format
     */
    public static function build_prompt_with_five_aspects_rubric($soal, $kunci_jawaban, $jawaban_siswa) {
        $prompt = <<<'PROMPT'
Kamu adalah guru Bahasa Indonesia yang berpengalaman dan bertugas menilai jawaban esai siswa SMK secara objektif dan konsisten. Tugasmu adalah mengevaluasi jawaban siswa berdasarkan soal dan kunci jawaban yang diberikan, bukan berdasarkan standar tulisan ideal secara umum.

Soal:
{soal}

Kunci Jawaban Referensi:
{kunci_jawaban}

Rubrik Penilaian:
1. Relevansi dan Ketepatan Isi (0–10)
Skor 9–10: Jawaban langsung menjawab inti soal, semua poin utama dari kunci jawaban tercakup dengan benar
Skor 6–8: Jawaban relevan dan sebagian besar poin tercakup, ada satu atau dua poin yang kurang lengkap
Skor 3–5: Jawaban hanya menyentuh sebagian inti soal, beberapa poin penting tidak disebutkan atau melenceng
Skor 1–2: Jawaban sangat tidak relevan atau hanya menyebut topik secara sangat umum tanpa substansi
Skor 0: Jawaban kosong, tidak dapat dibaca, atau sama sekali tidak menjawab soal

2. Kelengkapan Konsep (0–8)
Skor 7–8: Semua konsep atau poin yang diminta soal disebutkan dan dijelaskan dengan benar sesuai kunci
Skor 4–6: Sebagian besar konsep disebutkan, satu atau dua poin tidak dijelaskan atau kurang tepat
Skor 2–3: Hanya menyebut sebagian kecil konsep yang diminta atau penjelasannya sangat dangkal
Skor 0–1: Tidak ada konsep yang benar atau jawaban tidak menunjukkan pemahaman terhadap materi

3. Kesesuaian dengan Konteks Soal (0–6)
Skor 5–6: Jawaban sepenuhnya mengacu pada konteks, data, atau ilustrasi yang diberikan dalam soal
Skor 3–4: Jawaban sebagian menggunakan konteks soal, ada bagian yang diabaikan atau diganti dengan contoh lain
Skor 1–2: Jawaban mengabaikan sebagian besar konteks soal dan menggunakan informasi yang tidak diminta
Skor 0: Jawaban sama sekali tidak mengacu pada konteks soal

4. Ketepatan Bahasa dan Struktur Kalimat (0–4)
Skor 4: Menggunakan bahasa baku, struktur kalimat benar, dan mudah dipahami
Skor 2–3: Ada beberapa kesalahan bahasa atau struktur kalimat tapi tidak mengganggu pemahaman
Skor 1: Banyak kesalahan bahasa yang cukup mengganggu pemahaman
Skor 0: Bahasa sangat tidak baku atau tidak dapat dipahami

5. Ejaan dan Tanda Baca (0–2)
Skor 2: Ejaan dan tanda baca konsisten dan benar
Skor 1: Ada beberapa kesalahan ejaan atau tanda baca yang tidak mengganggu pemahaman
Skor 0: Banyak kesalahan ejaan dan tanda baca yang mengganggu

Aturan Penilaian:
- Nilai setiap aspek secara independen berdasarkan deskriptor di atas
- Bandingkan jawaban siswa dengan kunci jawaban referensi sebagai acuan utama
- Jika jawaban siswa kosong atau hanya terdiri dari satu kata yang tidak relevan, berikan skor 0 untuk semua aspek
- Jika jawaban hanya satu atau dua kalimat dan tidak menyebutkan kunci jawaban, beri nilai maksimal 5/30
- Jangan beri nilai tinggi jika tidak ada penjelasan atau contoh yang relevan
- Berikan alasan detail bila skor rendah; jangan paksakan nilai sempurna
- Berikan feedback singkat per aspek dalam Bahasa Indonesia yang jelas, spesifik, dan konstruktif
- Total skor adalah penjumlahan dari semua aspek dengan rentang 0–30

Jawaban Siswa:
{jawaban_siswa}

Berikan output hanya dalam format JSON berikut tanpa teks apapun di luar JSON:
{
  "score": <nilai_numerik_0_ke_30>,
  "summary_feedback": "<umpan_balik_keseluruhan_singkat>",
  "criteria_scores": {
      "relevansi_ketepatan_isi": {"skor": <0..10>, "feedback": "..."},
      "kelengkapan_konsep": {"skor": <0..8>, "feedback": "..."},
      "kesesuaian_konteks": {"skor": <0..6>, "feedback": "..."},
      "ketepatan_bahasa": {"skor": <0..4>, "feedback": "..."},
      "ejaan_tanda_baca": {"skor": <0..2>, "feedback": "..."}
  },
  "detailed_feedback": {
      "relevansi_ketepatan_isi": "...",
      "kelengkapan_konsep": "...",
      "kesesuaian_konteks": "...",
      "ketepatan_bahasa": "...",
      "ejaan_tanda_baca": "..."
  },
  "improvement_suggestions": [
      "<saran_1>",
      "<saran_2>",
      "<saran_3>"
  ]
}
- Gunakan nilai bilangan bulat atau desimal dalam rentang yang tepat bagi setiap aspek.
- Jangan gunakan skala 1..5 jika rubrik telah menentukan rentang 0..10, 0..8, 0..6, 0..4, atau 0..2.
- Total skor HARUS sama dengan jumlah semua skor aspek dalam "criteria_scores".
- Jangan keluarkan teks apapun di luar JSON yang valid.
PROMPT;

        $prompt = str_replace('{soal}', trim($soal), $prompt);
        $prompt = str_replace('{kunci_jawaban}', trim($kunci_jawaban), $prompt);
        $prompt = str_replace('{jawaban_siswa}', trim($jawaban_siswa), $prompt);

        return $prompt;
    }

    /**
     * Build prompt with custom criteria configuration and question context.
     * 
     * This method is called when assignment has custom criteria config
     * (6 default criteria: Content, Organization, Grammar, Vocabulary, Spelling, Relevance).
     * Includes question text and expected answer as context for the AI evaluator.
     *
     * @param int $assignmentid Assignment ID
     * @param string|array $criteria_config JSON or array of criteria configuration
     * @param float $maxscore Maximum score
     * @param array $guidance Teacher guidance (customprompt, questionText, expectedAnswer)
     * @param string $essaytext The essay to evaluate
     * @return string Complete prompt for Gemini
     */
    public static function build_prompt_with_custom_criteria($assignmentid, $criteria_config, $maxscore = 100, 
                                                             $guidance = array(), $essaytext = '') {
        global $DB;
        
        // Get config from database to get question text and expected answer
        $config = criteria_config_form::get_config_for_assignment($assignmentid);
        
        $question_text = ($config && !empty($config->question_text)) ? $config->question_text : '';
        $expected_answer = ($config && !empty($config->expected_answer)) ? $config->expected_answer : '';
        
        // Parse and format criteria
        $criteria_format = self::format_custom_criteria($criteria_config, $maxscore);
        
        // Build question context section
        $question_context = '';
        if (!empty($question_text)) {
            $question_context .= "\n=== SOAL YANG AKAN DIEVALUASI ===\n";
            $question_context .= trim($question_text) . "\n";
            $question_context .= "================================\n";
        }
        
        if (!empty($expected_answer)) {
            $question_context .= "\n=== JAWABAN YANG DIHARAPKAN / REFERENSI ===\n";
            $question_context .= trim($expected_answer) . "\n";
            $question_context .= "=========================================\n";
            $question_context .= "bandingkan jawaban siswa dengan jawaban yang diharapkan di atas. ";
            $question_context .= "Identifikasi poin-poin kunci yang ada dalam jawaban siswa dan yang belum ada. ";
            $question_context .= "Berikan feedback yang menunjukkan persentase cakupan poin-poin penting.]\n";
        }
        
        // Build teacher guidance section
        $teacherguidance = '';
        if (!empty($guidance['customprompt'])) {
            $teacherguidance .= "\n=== INSTRUKSI GURU TAMBAHAN ===\n";
            $teacherguidance .= trim($guidance['customprompt']) . "\n";
            $teacherguidance .= "==============================\n";
        }
        
        if (!empty($guidance['answerkey'])) {
            $teacherguidance .= "\n=== KUNCI JAWABAN REFERENSI ===\n";
            $teacherguidance .= trim($guidance['answerkey']) . "\n";
            $teacherguidance .= "==============================\n";
        }
        
        if (!empty($guidance['rubricguide'])) {
            $teacherguidance .= "\n=== PANDUAN RUBRIK PENILAIAN ===\n";
            $teacherguidance .= trim($guidance['rubricguide']) . "\n";
            $teacherguidance .= "================================\n";
        }

        $teacherguidance .= "\n=== VALIDASI JAWABAN (jalankan PERTAMA sebelum menilai) ===\n";
        $teacherguidance .= "Periksa kondisi berikut sebelum memberi skor apapun:\n";
        $teacherguidance .= "- [V1] Jika jawaban adalah fragmen kalimat tanpa subjek dan predikat lengkap, skor total maksimal 10% dari {$maxscore}.\n";
        $teacherguidance .= "- [V2] Jika jawaban hanya menyalin ulang kalimat soal tanpa modifikasi berarti, skor total maksimal 5% dari {$maxscore}.\n";
        $teacherguidance .= "- [V3] Jika soal meminta membuat teks utuh (surat, paragraf, teks iklan) namun jawaban tidak membentuk teks yang selesai, skor total maksimal 20% dari {$maxscore}.\n";
        $teacherguidance .= "- [V4] Jika jawaban membahas konsep yang berbeda dari yang diminta soal (contoh: menjawab fungsi iklan padahal soal menanya kaidah kebahasaan), skor total maksimal 10% dari {$maxscore}.\n";
        $teacherguidance .= "Aturan validasi ini LEBIH DIPRIORITASKAN dari semua aturan penilaian di bawahnya.\n";
        $teacherguidance .= "==========================================================\n";

        $teacherguidance .= "\n=== ATURAN PENILAIAN ===\n";
        $teacherguidance .= "- Bandingkan jawaban siswa dengan kunci jawaban sebagai acuan utama.\n";
        $teacherguidance .= "- Jangan lakukan interpretasi charitabel: nilai apa yang tertulis, bukan apa yang mungkin dimaksud siswa.\n";
        $teacherguidance .= "- Jika soal berisi dua pertanyaan berbeda dan jawaban hanya menjawab satu, skor maksimal 50% dari {$maxscore}.\n";
        $teacherguidance .= "- Skor penuh hanya jika jawaban mencakup semua poin kunci secara eksplisit dan akurat.\n";
        $teacherguidance .= "=======================\n";
        
        // Get enabled criteria weights for JSON format
        $parsed_criteria = default_criteria::parse_criteria_config($criteria_config);
        $criteria_json_format = self::build_criteria_json_format($parsed_criteria, $maxscore);
        
        $prompt = <<<PROMPT
Kamu adalah guru Bahasa Indonesia yang berpengalaman dan bertugas menilai jawaban esai siswa SMK secara objektif dan konsisten. 
Tugasmu adalah mengevaluasi jawaban siswa berdasarkan soal dan kunci jawaban yang diberikan, 
bukan berdasarkan standar tulisan ideal secara umum.

Tugas Anda adalah mengevaluasi esai siswa di bawah ini menggunakan kriteria evaluasi khusus dan 
pertanyaan serta jawaban yang diharapkan.

=== VALIDASI JAWABAN (WAJIB, jalankan SEBELUM menilai) ===
Sebelum memberi skor apapun, periksa apakah jawaban memenuhi 
syarat minimum berikut:

[V1] Jika jawaban adalah fragmen kalimat tanpa subjek dan predikat
     lengkap: skor total MAKSIMAL 10% dari {$maxscore}.
[V2] Jika jawaban hanya menyalin ulang kalimat soal tanpa perubahan
     berarti: skor total MAKSIMAL 5% dari {$maxscore}.
[V3] Jika soal meminta membuat teks utuh (surat, paragraf, iklan)
     namun jawaban tidak membentuk teks yang selesai: skor total 
     MAKSIMAL 20% dari {$maxscore}.
[V4] Jika jawaban membahas konsep yang berbeda dari yang diminta soal
     (contoh: menjawab fungsi iklan padahal soal menanya kaidah 
     kebahasaan): skor total MAKSIMAL 10% dari {$maxscore}.
[V5] Jika jawaban hanya 1-2 kalimat pendek dan tidak menyebut 
     satupun poin kunci secara eksplisit: skor total MAKSIMAL 
     5/{$maxscore}.

Aturan validasi ini LEBIH DIPRIORITASKAN dari semua aturan 
penilaian di bawahnya. Jika salah satu kondisi terpenuhi, 
terapkan batas skor tersebut dan jangan beri nilai lebih tinggi
meskipun ada aspek lain yang tampak memenuhi kriteria.
==========================================================

=== KRITERIA EVALUASI ===
{$criteria_format['criteria_text']}
{$criteria_format['weights_text']}
=======================

{$question_context}
{$teacherguidance}

{text_preprocessor::format_for_prompt($essaytext, array('include_metadata' => true, 'delimiter_style' => 'standard'))}

Berikan output hanya dalam format JSON berikut tanpa teks apapun di luar JSON:
{
  "score": <nilai_numerik_0_ke_{$maxscore}>,
  "summary_feedback": "<umpan_balik_keseluruhan_singkat>",
  "criteria_scores": {
        {$criteria_json_format['score_fields']}
  },
  "detailed_feedback": {
        {$criteria_json_format['feedback_fields']}
  },
  "improvement_suggestions": [
      "<saran_1>",
      "<saran_2>",
      "<saran_3>"
  ]
}
Aturan penilaian (WAJIB):
{$criteria_json_format['scoring_rules']}
- Nilai setiap aspek secara independen berdasarkan deskriptor rubrik di atas
- Bandingkan jawaban siswa dengan kunci jawaban referensi sebagai acuan utama
- Jika jawaban siswa kosong atau hanya terdiri dari satu kata yang tidak relevan, berikan skor 0 untuk semua aspek
- Skor diberikan berdasarkan jumlah poin kunci dari kunci jawaban yang berhasil dijawab dengan benar. 
- Jika soal berisi dua pertanyaan berbeda dan hanya satu dijawab: skor maksimal 50% dari {$maxscore} 
- Jawaban yang tidak menyebut satupun poin kunci mendapat skor maksimal 20% dari total skor aspek tersebut.
- Skor 0 pada suatu aspek diperbolehkan jika jawaban tidak memenuhi kriteria aspek tersebut sama sekali
- Skor penuh hanya jika jawaban mencakup semua poin kunci secara eksplisit dan akurat
- Jangan lakukan interpretasi charitabel: nilai apa yang tertulis, bukan apa yang mungkin dimaksud siswa
- Berikan feedback singkat per aspek dalam Bahasa Indonesia yang jelas, spesifik, dan konstruktif
- Total skor adalah penjumlahan dari semua aspek sesuai struktur criteria_scores
- Gunakan nilai bilangan bulat atau desimal dalam rentang yang tepat bagi setiap aspek.
- Total skor HARUS sama dengan jumlah semua skor aspek dalam "criteria_scores".
- Gunakan nama kriteria persis seperti yang tercantum pada bagian KRITERIA EVALUASI.
- Jangan keluarkan teks apapun di luar JSON yang valid.

PROMPT;

        return $prompt;
    }
    
    /**
     * Format custom criteria for display in prompt.
     *
     * @param string|array $criteria_config JSON or array of criteria config
     * @param float $maxscore Maximum score
     * @return array Array with 'criteria_text' and 'weights_text' keys
     */
    private static function format_custom_criteria($criteria_config, $maxscore) {
        $raw_config = $criteria_config;
        if (is_string($raw_config)) {
            $raw_config = json_decode($raw_config, true) ?? array();
        }
        if (!is_array($raw_config)) {
            $raw_config = array();
        }

        $all_criteria = default_criteria::get_all_criteria();
        $criteria_text = '';
        $weights_text = '';
        $total_weight = 0;
        foreach ($raw_config as $criterion_id => $criterion_cfg) {
            if (empty($criterion_cfg['enabled'])) {
                continue;
            }
            $weight = isset($criterion_cfg['weight']) ? (int)$criterion_cfg['weight'] : 0;
            $total_weight += $weight;
        }

        if ($total_weight <= 0) {
            $raw_config = json_decode(default_criteria::get_default_config(), true) ?? array();
            $total_weight = array_sum(default_criteria::parse_criteria_config($raw_config));
        }

        foreach ($raw_config as $criterion_id => $criterion_cfg) {
            if (empty($criterion_cfg['enabled'])) {
                continue;
            }

            $criterion = isset($all_criteria[$criterion_id]) ? $all_criteria[$criterion_id] : null;
            $name = $criterion ? $criterion['name_id'] : (!empty($criterion_cfg['name']) ? $criterion_cfg['name'] : $criterion_id);
            $description = $criterion ? $criterion['description_id'] : (!empty($criterion_cfg['description']) ? $criterion_cfg['description'] : '');

            $weight = isset($criterion_cfg['weight']) ? (int)$criterion_cfg['weight'] : 0;
            $max_points = isset($criterion_cfg['max_points']) ? (int)$criterion_cfg['max_points'] : $weight;
            if ($max_points <= 0) {
                $max_points = max(1, $weight);
            }

            $criteria_text .= "- {$name} (0..{$max_points}):\n";
            $criteria_text .= "  {$description}\n";

            if (!empty($criterion_cfg['score_levels'])) {
                $lines = explode("\n", trim($criterion_cfg['score_levels']));
                $criteria_text .= "  Level skor:\n";
                foreach ($lines as $line) {
                    $criteria_text .= "    " . trim($line) . "\n";
                }
            }

            $criteria_text .= "\n";
            $weights_text .= "- {$name}: {$max_points} poin\n";
        }

        return array(
            'criteria_text' => trim($criteria_text),
            'weights_text' => trim($weights_text),
        );
    }
    
    /**
     * Build JSON format specification for criteria scores and feedback.
     *
     * @param array $enabled_criteria List of enabled criterion IDs
     * @param float $maxscore Maximum score
     * @return array Array with 'score_fields', 'feedback_fields', 'scoring_rules'
     */
    private static function build_criteria_json_format($weighted_criteria, $maxscore) {
        $score_fields = '';
        $feedback_fields = '';
        $enabled_criteria = array_keys($weighted_criteria);

        $first = true;
        foreach ($enabled_criteria as $criterion_id) {
            if (!$first) {
                $score_fields .= ",\n        ";
                $feedback_fields .= ",\n        ";
            }
            $first = false;

            $max_points = max(1, (int)$weighted_criteria[$criterion_id]);
            $score_fields .= "\"{$criterion_id}\": <0..{$max_points}>";
            $feedback_fields .= "\"{$criterion_id}\": \"<specific_feedback_for_this_criterion>\"";
        }

        $criteria_json = json_encode($enabled_criteria);
        $total_weight = array_sum($weighted_criteria);

        $scoring_rules = "- Kriteria yang diaktifkan: {$criteria_json}\n";
        if ($total_weight === (int)$maxscore && $total_weight > 0) {
            $scoring_rules .= "- Setiap skor kriteria harus berada dalam rentang 0..nilai maksimum poin aspek sesuai bobot masing-masing.\n";
            $scoring_rules .= "- Jika jawaban siswa tidak kosong, setiap aspek minimal harus bernilai 1, kecuali jawaban benar-benar tidak relevan atau kosong.\n";
            $scoring_rules .= "- Total skor adalah jumlah semua skor kriteria dan HARUS sama dengan {$maxscore}.\n";
            $scoring_rules .= "- Jangan gunakan skala 1..5 jika rubrik telah menentukan rentang poin khusus.\n";
        } else {
            $scoring_rules .= "- Setiap skor kriteria harus berada dalam rentang 0..5 dan dihitung berdasarkan persentase bobot kriteria.\n";
            $scoring_rules .= "- Jika jawaban siswa tidak kosong, setiap aspek minimal harus bernilai 1, kecuali jawaban benar-benar tidak relevan atau kosong.\n";
            $scoring_rules .= "- Total \"score\" HARUS sama dengan jumlah semua nilai terweighted dan diskalakan ke maksimum {$maxscore}.\n";
            $scoring_rules .= "- Jangan keluarkan skor kriteria di luar rentang 0..5.\n";
        }
        $scoring_rules .= "- Jangan keluarkan skor kriteria di luar rentang yang diinstruksikan.\n";

        return array(
            'score_fields' => $score_fields,
            'feedback_fields' => $feedback_fields,
            'scoring_rules' => $scoring_rules,
        );
    }

    /**
     * Build base prompt with standard basis and teacher guidance.
     *
     * @param array $criteria Criteria
     * @param float $maxscore Max score
     * @param array $guidance Teacher guidance
     * @param string $standard_basis Formatted standard section
     * @param string|null $standard_source Source of standard (for logging)
     * @param string $essaytext The essay text to evaluate
     * @return string Complete prompt
     */
    private static function build_base_prompt($criteria, $maxscore, $guidance, $standard_basis, $standard_source, $essaytext = '') {
        // Build criteria text
        $criteriatext = '';
        $activecriteria = array();
        
        if (!empty($criteria)) {
            $criteriatext = "\nKriteria Evaluasi:\n";
            if (isset($criteria['grammar']) && $criteria['grammar']) {
                $criteriatext .= "- Tata Bahasa & Struktur Kalimat\n";
                $activecriteria[] = 'grammar';
            }
            if (isset($criteria['content']) && $criteria['content']) {
                $criteriatext .= "- Kualitas Konten (relevansi, akurasi, kedalaman, cakupan poin kunci dari kunci jawaban)\n";
                $activecriteria[] = 'content';
            }
            if (isset($criteria['organization']) && $criteria['organization']) {
                $criteriatext .= "- Organisasi & Struktur (alur, kohesi, kejelasan)\n";
                $activecriteria[] = 'organization';
            }
        }
        
        $isholistic = empty($activecriteria);
        $criterioncount = count($activecriteria);
        $percriterionmax = $criterioncount > 0 ? round(((float)$maxscore / $criterioncount), 2) : (float)$maxscore;
        $criteriajson = json_encode($activecriteria);

        $criteriaformat = $isholistic
            ? "\"criteria_scores\": {},\n    \"detailed_feedback\": {}"
            : "\"criteria_scores\": {\n        \"grammar\": <score_if_evaluated>,\n        \"content\": <score_if_evaluated>,\n        \"organization\": <score_if_evaluated>\n    },\n    \"detailed_feedback\": {\n        \"grammar\": \"<feedback>\",\n        \"content\": \"<feedback>\",\n        \"organization\": \"<feedback>\"\n    }";

        $scoringrules = $isholistic
            ? "- Ini adalah mode holistik: pertahankan \"criteria_scores\" dan \"detailed_feedback\" sebagai objek JSON kosong.\n- Tetapkan \"score\" langsung pada skala 0..{$maxscore}."
            : "- Kunci kriteria yang diaktifkan adalah tepat: {$criteriajson}\n- Gunakan HANYA kunci kriteria yang diaktifkan dalam \"criteria_scores\" dan \"detailed_feedback\".\n- Setiap skor kriteria harus berada dalam rentang 0..{$percriterionmax}\n- Setiap skor kriteria harus menggunakan pembanding tetap ini untuk mengurangi keacakan:\n  0%, 25%, 50%, 75%, atau 100% dari {$percriterionmax}\n- \"Skor\" akhir HARUS sama dengan jumlah semua nilai numerik dalam \"criteria_scores\".\n- Jangan keluarkan skor kriteria di luar 0..{$percriterionmax}.";
        
        // Build teacher guidance section
        $teacherguidance = '';
        
        if (!empty($guidance['answerkey'])) {
            $teacherguidance .= "\nKunci Jawaban Referensi (dari guru):\n" . trim($guidance['answerkey']) . "\n";
            $teacherguidance .= "\n[PANDUAN PENILAIAN: Saat menilai kriteria 'Kualitas Konten', ";
            $teacherguidance .= "bandingkan jawaban siswa dengan kunci jawaban di atas. ";
            $teacherguidance .= "Identifikasi poin-poin kunci yang ada dan yang belum ada dalam jawaban siswa.]\n";
        }

        if (!empty($guidance['rubricguide'])) {
            $teacherguidance .= "\nPedoman Rubrik Penilaian (dari guru):\n" . trim($guidance['rubricguide']) . "\n";
        }

        if (!empty($guidance['customprompt'])) {
            $teacherguidance .= "\nInstruksi Guru Tambahan:\n" . trim($guidance['customprompt']) . "\n";
        }

        $teacherguidance .= "\n=== ATURAN KHUSUS PENILAIAN ===\n";
        $teacherguidance .= "- Jika jawaban hanya satu atau dua kalimat dan tidak menyebutkan kunci jawaban, berikan skor maksimal 5/{$maxscore}.\n";
        $teacherguidance .= "- Jangan beri nilai tinggi jika tidak ada penjelasan atau contoh yang relevan.\n";
        $teacherguidance .= "- Berikan alasan detail bila skor rendah; jangan paksakan nilai sempurna.\n";
        $teacherguidance .= "===============================\n";
        
        // Build complete prompt
        $instruction = "Berdasarkan standar pendidikan resmi";
        if ($standard_source) {
            $instruction .= " dari " . assessment_standard::get_source_label($standard_source);
        }
        $instruction .= ", nilai esai siswa berikut dan berikan:\n"
            . "1. Skor dari 0 hingga {$maxscore}\n"
            . "2. Umpan balik terperinci untuk setiap kriteria evaluasi\n"
            . "3. Saran perbaikan yang spesifik";
        
        $prompt = <<<PROMPT
Anda adalah penilai esai ahli dengan pengetahuan tentang standar pendidikan resmi.

{$instruction}

{$standard_basis}
{$criteriatext}
{$teacherguidance}

Berikan penilaian Anda dalam format JSON berikut:
{
    "score": <nilai_numerik>,
    "summary_feedback": "<umpan_balik_keseluruhan_singkat>",
    {$criteriaformat},
    "improvement_suggestions": [
        "<saran_1>",
        "<saran_2>",
        "<saran_3>"
    ]
}

Aturan penilaian (wajib):
{$scoringrules}
- Jangan keluarkan teks apa pun di luar JSON yang valid.

{text_preprocessor::format_for_prompt($essaytext, array('include_metadata' => true, 'delimiter_style' => 'standard'))}

Hanya berikan JSON yang valid sebagai output, tanpa teks tambahan.
PROMPT;
        
        return $prompt;
    }
    
    /**
     * Get prompt source information for logging.
     *
     * @param int $assignmentid
     * @return array Source information
     */
    public static function get_prompt_source_info($assignmentid) {
        $standard = assessment_standard::get_standard($assignmentid);
        
        if ($standard['found']) {
            return array(
                'source' => $standard['source'],
                'source_label' => $standard['source_label'],
                'standard_name' => $standard['standard_name'],
                'author' => $standard['author'] ?? 'Unknown'
            );
        }
        
        return array(
            'source' => 'default',
            'source_label' => 'Default/Custom',
            'standard_name' => 'No official standard assigned',
            'author' => 'System'
        );
    }
}
