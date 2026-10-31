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

/**
 * Indonesian language strings for Essay AI Grader plugin.
 *
 * @package   local_essay_ai_grader
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Penilaian Essay dengan AI';
$string['plugindesc'] = 'Penilaian essay otomatis menggunakan Gemini AI';

// Settings.
$string['provider'] = 'Penyedia AI';
$string['provider_desc'] = 'Pilih backend AI yang digunakan untuk penilaian essay';
$string['provider_gemini'] = 'Google Gemini API';
$string['provider_ollama'] = 'Ollama (lokal/self-hosted)';
$string['auto_enable_new_assignments'] = 'Aktifkan otomatis assignment baru';
$string['auto_enable_new_assignments_desc'] = 'Otomatis membuat dan mengaktifkan konfigurasi AI grading saat activity assignment baru dibuat';
$string['auto_publish_grade'] = 'Publikasikan nilai AI otomatis ke gradebook';
$string['auto_publish_grade_desc'] = 'Saat penilaian AI selesai, skor AI otomatis disimpan ke gradebook assignment';
$string['auto_publish_feedback'] = 'Publikasikan feedback AI otomatis ke kolom feedback';
$string['auto_publish_feedback_desc'] = 'Saat penilaian AI selesai, otomatis publikasikan analisis LLM dan skor kriteria ke kolom feedback assignment, terlihat untuk siswa';
$string['criteria_breakdown'] = 'Rincian Kriteria';

$string['gemini_settings'] = 'Pengaturan Gemini AI';
$string['gemini_settings_desc'] = 'Konfigurasi pengaturan API Gemini untuk evaluasi essay';
$string['gemini_api_key'] = 'Kunci API Gemini';
$string['gemini_api_key_desc'] = 'Masukkan kunci API Google Gemini Anda';
$string['gemini_model'] = 'Model Gemini';
$string['gemini_model_desc'] = 'Pilih model Gemini yang akan digunakan untuk penilaian essay';

$string['ollama_settings'] = 'Pengaturan Ollama';
$string['ollama_settings_desc'] = 'Konfigurasi endpoint dan model Ollama lokal';
$string['ollama_url'] = 'URL Dasar Ollama';
$string['ollama_url_desc'] = 'URL server Ollama, contoh http://127.0.0.1:11434';
$string['ollama_model'] = 'Model Ollama';
$string['ollama_model_desc'] = 'Tag model yang digunakan, contoh llama3.1:8b';
$string['default_max_score'] = 'Skor Maksimal Default';
$string['default_max_score_desc'] = 'Skor maksimal default untuk essay (default: 100)';
$string['auto_grade_on_submit'] = 'Penilaian Otomatis Saat Submit';
$string['auto_grade_on_submit_desc'] = 'Nilai essay secara otomatis ketika siswa submit (memerlukan scheduled task diaktifkan)';
$string['ocr_enabled'] = 'Aktifkan OCR untuk file gambar';
$string['ocr_enabled_desc'] = 'Jika aktif, sistem akan mencoba membaca teks dari file gambar (jpg/png) menggunakan Tesseract OCR.';
$string['tesseract_path'] = 'Path Tesseract (Opsional)';
$string['tesseract_path_desc'] = 'Kosongkan untuk memakai perintah "tesseract" dari PATH. Isi path penuh jika perlu, contoh C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
$string['ocr_languages'] = 'Bahasa OCR';
$string['ocr_languages_desc'] = 'Kode bahasa Tesseract, contoh ind+eng';

$string['max_score_label'] = 'Skor Maksimal';
$string['answer_key_label'] = 'Kunci Jawaban / Jawaban yang Diharapkan';
$string['rubric_guide_label'] = 'Panduan Rubrik';
$string['enable_ai_grading'] = 'Aktifkan Penilaian AI Essay';

$string['eval_grammar'] = 'Tata Bahasa & Struktur Kalimat';
$string['eval_grammar_desc'] = 'Periksa tata bahasa, ejaan, dan kejelasan kalimat';
$string['eval_content'] = 'Kualitas Konten';
$string['eval_content_desc'] = 'Nilai relevansi, akurasi, dan kedalaman konten';
$string['eval_organization'] = 'Organisasi & Struktur Essay';
$string['eval_organization_desc'] = 'Periksa organisasi, alur, dan struktur essay';

// Pages.
$string['reviewassessments'] = 'Tinjau Penilaian AI';
$string['title_review_assessments'] = 'Tinjau Penilaian yang Dihasilkan AI';
$string['ai_grading_not_enabled'] = 'Penilaian AI belum diaktifkan untuk tugas ini';

// Assessment status.
$string['status_pending'] = 'Menunggu';
$string['status_processing'] = 'Sedang Memproses...';
$string['status_completed'] = 'Selesai';
$string['status_error'] = 'Error';

// Assessment feedback.
$string['ai_score'] = 'Saran Nilai AI';
$string['ai_feedback'] = 'Umpan Balik AI';
$string['teacher_feedback'] = 'Umpan Balik Guru';
$string['teacher_score'] = 'Nilai Akhir';
$string['accept_grade'] = 'Terima Nilai';
$string['reject_grade'] = 'Tolak & Edit';
$string['edit_grade'] = 'Ubah Nilai';
$string['edit_grade_help'] = 'Sesuaikan nilai dan umpan balik opsional sebelum menyimpan ke gradebook';
$string['criteria_scores'] = 'Skor Kriteria';
$string['criteria_subtotal'] = 'Subtotal Kriteria (Raw)';
$string['llm_raw_score'] = 'Skor Mentah LLM (Sebelum Normalisasi)';
$string['llm_raw_response'] = 'Lihat Raw Response LLM';
$string['improvement_suggestions'] = 'Saran Perbaikan';
$string['improvement_suggestions_help'] = 'Berikut adalah saran spesifik dari AI untuk membantu Anda meningkatkan essay Anda di masa depan.';

// Messages.
$string['grade_accepted'] = 'Nilai diterima dan disimpan';
$string['grade_rejected'] = 'Nilai ditolak. Anda dapat mengedit nilai secara manual';
$string['grade_updated'] = 'Nilai berhasil diperbarui dan disimpan ke gradebook';
$string['assessment_error'] = 'Error saat penilaian: {$a}';
$string['no_assessments'] = 'Tidak ada penilaian yang menunggu';

// Email.
$string['assessment_ready_subject'] = 'Penilaian AI Siap Ditinjau - {$a}';
$string['assessment_ready_body'] = 'Penilaian AI siap ditinjau. Tugas: {$a->assignmentname}, Siswa: {$a->studentname}';

// Scheduled tasks.
$string['task_process_assessments'] = 'Proses penilaian essay yang menunggu dengan Gemini AI';

// Configuration page.
$string['enable_ai_grading'] = 'Aktifkan Penilaian AI untuk tugas ini';
$string['max_score_label'] = 'Skor Maksimal';
$string['eval_criteria_label'] = 'Kriteria Evaluasi';
$string['custom_prompt_label'] = 'Prompt Evaluasi Custom (Opsional)';
$string['custom_prompt_help'] = 'Kosongkan untuk menggunakan prompt default. Anda dapat menyesuaikan instruksi evaluasi di sini.';
$string['answer_key_label'] = 'Kunci Jawaban / Poin Diharapkan (Opsional)';
$string['answer_key_help'] = 'Isi poin-poin jawaban ideal agar AI membandingkan jawaban siswa dengan acuan tersebut.';
$string['rubric_guide_label'] = 'Panduan Rubrik (Opsional)';
$string['rubric_guide_help'] = 'Tentukan aspek dan bobot nilai, misalnya: konten 50%, kualitas argumen 30%, bahasa 20%.';
$string['btn_save'] = 'Simpan Konfigurasi';
$string['btn_cancel'] = 'Batal';
$string['configsaved'] = 'Konfigurasi berhasil disimpan';

// Error messages.
$string['apikey_not_configured'] = 'Kunci API Gemini belum dikonfigurasi. Silakan konfigurasi di pengaturan plugin.';
$string['essaytext_empty'] = 'Teks essay kosong';
$string['api_call_failed'] = 'Gagal memanggil API Gemini. Silakan periksa kunci API Anda dan coba lagi.';
$string['submission_not_found'] = 'Pengumpulan tidak ditemukan';
$string['file_not_supported'] = 'Jenis pengumpulan file belum didukung. Silakan gunakan pengumpulan teks online.';

// Dashboard.
$string['dashboarddesc'] = 'Kelola semua tugas dengan penilaian AI yang diaktifkan';
$string['noaiassignments'] = 'Belum ada tugas dengan penilaian AI yang diaktifkan';
$string['assessment'] = 'Tugas';
$string['course'] = 'Kursus';
$string['assessments'] = 'Penilaian';
$string['pending'] = 'Menunggu Peninjauan';

// Konfigurasi Kriteria Custom (BARU - April 2026)
$string['enable_ai_grading_custom'] = 'Aktifkan Penilaian AI dengan Kriteria Custom';
$string['enable_ai_grading_custom_help'] = 'Ketika diaktifkan, memungkinkan Anda mengonfigurasi kriteria evaluasi custom, bobot, dan konteks soal untuk penilaian AI tugas ini.';

$string['evaluation_criteria'] = 'Konfigurasi Kriteria Evaluasi';
$string['evaluation_criteria_help'] = 'Pilih kriteria mana yang ingin dievaluasi dan tetapkan bobot untuk setiap kriteria (total akan dinormalisasi menjadi 100%). 6 kriteria tersedia: Kualitas Isi, Organisasi & Struktur, Tata Bahasa, Kosakata, Ejaan & Tanda Baca, dan Relevansi dengan Topik.';

$string['criterion_content'] = 'Kualitas Isi';
$string['criterion_content_desc'] = 'Kualitas, akurasi, dan relevansi konten; kedalaman ide';

$string['criterion_organization'] = 'Organisasi & Struktur';
$string['criterion_organization_desc'] = 'Alur logis, struktur jelas, transisi antar ide';

$string['criterion_grammar'] = 'Tata Bahasa & Struktur Kalimat';
$string['criterion_grammar_desc'] = 'Tata bahasa benar, variasi kalimat, sintaksis tepat';

$string['criterion_vocabulary'] = 'Kosakata & Pilihan Kata';
$string['criterion_vocabulary_desc'] = 'Penggunaan kosakata yang tepat, beragam, dan presisi';

$string['criterion_spelling'] = 'Ejaan & Tanda Baca';
$string['criterion_spelling_desc'] = 'Ejaan, tanda baca, dan kapitalisasi yang benar';

$string['criterion_relevance'] = 'Relevansi dengan Topik/Soal';
$string['criterion_relevance_desc'] = 'Seberapa baik respons menjawab pertanyaan/topik';

$string['criteria_weight'] = 'Bobot %';
$string['criteria_select'] = 'Pilih';
$string['criteria_weight_note'] = 'Catatan: Bobot akan dinormalisasi menjadi 100% jika tidak jumlahnya tidak tepat 100%.';
$string['criteria_weight_normalization_note'] = 'Catatan: Bobot akan dinormalisasi otomatis menjadi 100%';

$string['question_answer_reference'] = 'Referensi Soal & Jawaban (Opsional)';
$string['question_answer_reference_help'] = 'Sediakan teks soal dan jawaban yang diharapkan untuk membantu membimbing evaluasi AI. Ini membantu AI memahami konteks dan ekspektasi.';

$string['question_text'] = 'Teks Soal / Prompt';
$string['question_text_help'] = 'Masukkan soal atau prompt asli yang diberikan kepada siswa. Ini membantu AI memahami konteks dan menilai seberapa baik siswa menjawabnya.';

$string['expected_answer'] = 'Jawaban Diharapkan / Kunci Jawaban';
$string['expected_answer_help'] = 'Secara opsional sediakan jawaban ideal atau poin kunci. Ini membantu AI menilai respons siswa terhadap referensi dan mengidentifikasi poin atau konsep yang hilang.';

$string['max_score'] = 'Skor Maksimal';
$string['max_score_help'] = 'Poin maksimal yang tersedia untuk tugas essay ini (default: 100).';

$string['custom_prompt'] = 'Instruksi Custom Tambahan';
$string['custom_prompt_help'] = 'Tambahkan instruksi tambahan untuk evaluator AI, seperti area penekanan khusus, instruksi untuk topik tertentu, atau pertimbangan penilaian lainnya.';

$string['other_ai_settings'] = 'Pengaturan Penilaian AI Lainnya';

// Manajemen Kriteria Default (BARU - April 2026)
$string['manage_criteria'] = 'Kelola Kriteria Evaluasi Default';
$string['manage_criteria_help'] = 'Konfigurasi bobot default untuk 6 kriteria evaluasi. Bobot ini akan digunakan sebagai default untuk semua assignment essay baru. Assignment individual dapat override default ini.';
$string['criteria_weights_updated'] = 'Bobot kriteria berhasil diupdate';
$string['changes_saved'] = 'Perubahan berhasil disimpan';
$string['action'] = 'Tindakan';
$string['configure'] = 'Konfigurasi';
$string['review'] = 'Tinjau';
$string['adminsettings'] = 'Pengaturan Admin';$string['configure_ai_grading'] = 'Konfigurasi Penilaian AI';
$string['viewdashboard'] = 'Lihat Dashboard';
$string['dashboard'] = 'Dashboard';
$string['monitor_title'] = 'Monitor AI Grading';
$string['monitor_task_status'] = 'Status Scheduled Task';
$string['monitor_queue_status'] = 'Status Antrian';
$string['monitor_latest'] = 'Penilaian Terbaru';
$string['monitor_run_queue_now'] = 'Jalankan Antrian AI Sekarang';
$string['monitor_queue_run_done'] = 'Proses antrian AI telah dijalankan.';
$string['monitor_reset_stuck_done'] = 'Reset penilaian yang tersendat kembali ke status pending';
$string['monitor_reset_all_errors_done'] = 'Reset semua penilaian dengan error kembali ke status pending';
$string['monitor_task_not_found'] = 'Scheduled task tidak ditemukan. Periksa registrasi task plugin.';

// Performance levels for criteria display
$string['excellent'] = 'Sangat Baik';
$string['good'] = 'Baik';
$string['fair'] = 'Cukup';
$string['poor'] = 'Kurang';

// Criteria display
$string['criterion'] = 'Kriteria';
$string['description'] = 'Deskripsi';
$string['weight'] = 'Bobot';
$string['score'] = 'Skor';
$string['points'] = 'Poin';
$string['total'] = 'Total';

$string['quicklinks_title'] = 'Akses Cepat';
$string['quicklinks_desc'] = 'Buka halaman operasional tanpa harus mencari menu secara manual.';

// Student Feedback View - LLM Score & Feedback Display
$string['title_view_feedback'] = 'Lihat Umpan Balik & Nilai AI';
$string['llm_score'] = 'Nilai Penilaian AI';
$string['llm_feedback'] = 'Umpan Balik & Komentar AI';
$string['score_label'] = 'Nilai Anda';
$string['score_explanation'] = 'Nilai ini dihasilkan oleh analisis AI terhadap essay Anda. Silakan tinjau umpan balik di bawah untuk saran spesifik tentang cara meningkatkan pekerjaan Anda. Guru Anda dapat meninjau dan menyesuaikan nilai ini.';
$string['no_assessment_yet'] = 'Penilaian Belum Tersedia';
$string['no_feedback'] = 'Tidak ada umpan balik yang tersedia saat ini.';
$string['assessment_pending'] = 'Essay Anda sedang dinilai...';
$string['assessment_pending_message'] = 'Essay Anda telah dikirimkan dan sedang diproses oleh sistem penilaian AI. Umpan balik dan nilai akan muncul di sini setelah penilaian selesai.';
$string['check_back_later'] = 'Silakan kembali dalam beberapa saat untuk melihat hasil Anda.';
$string['assignment_info'] = 'Informasi Tugas';
$string['submitted'] = 'Dikumpulkan';
$string['assessment_info'] = 'Informasi Penilaian';
$string['assessed'] = 'Dinilai Pada';
$string['status'] = 'Status';
$string['about_ai_feedback'] = 'Tentang Penilaian AI Ini';
$string['ai_feedback_explanation'] = 'Penilaian ini dihasilkan oleh AI (Gemini) berdasarkan konten essay Anda. Umpan balik memberikan komentar konstruktif untuk membantu Anda meningkatkan tulisan Anda. Guru Anda dapat meninjau dan memberikan komentar tambahan atau menyesuaikan nilai.';
$string['criteria'] = 'Kriteria Evaluasi';
$string['criterion'] = 'Kriteria';
$string['score'] = 'Nilai';
$string['criteria_scores'] = 'Rincian Nilai Berdasarkan Kriteria';
$string['no_assessment'] = 'Tidak Ada Penilaian yang Tersedia';

// Teacher Review Section
$string['teacher_review'] = 'Tinjauan Penilaian Guru';
$string['teacher_review_desc'] = 'Tinjau dan validasi penilaian AI sebelum menyelesaikan nilai siswa. Anda dapat menerima nilai AI atau membuat penyesuaian sesuai kebutuhan.';
$string['review_assessment'] = 'Tinjau Penilaian Ini';

// FASE 2: Capabilities (for role definitions)
$string['essay_ai_grader:viewanalytics'] = 'Lihat dashboard analitik';
$string['essay_ai_grader:viewcosts'] = 'Lihat data pelacakan biaya';
$string['essay_ai_grader:bulkoperations'] = 'Lakukan operasi penilaian massal';
$string['essay_ai_grader:managecache'] = 'Kelola cache plugin';
