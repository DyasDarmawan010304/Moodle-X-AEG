# Penilaian Essay dengan AI (Essay AI Grader)

Plugin **local** Moodle untuk membantu guru menilai jawaban essay menggunakan AI. Plugin membuat saran nilai dan umpan balik berdasarkan kriteria yang dikonfigurasi untuk tugas, lalu menyediakan alur bagi guru untuk meninjau hasilnya.

> **Status:** Beta (`1.9.2`). Hasil AI adalah saran, bukan pengganti penilaian profesional guru. Periksa hasil sebelum digunakan sebagai nilai akhir.

## Fitur

- Mengantrekan submission tugas Moodle untuk diproses otomatis.
- Mendukung submission teks online serta ekstraksi teks best-effort dari berkas `.txt`, `.md`, `.docx`, `.pdf`, dan gambar.
- Menggunakan kunci jawaban, panduan rubrik, skor maksimum, dan kriteria yang diatur per tugas.
- Menyediakan enam kriteria bawaan: isi, organisasi, tata bahasa, kosakata, ejaan, dan relevansi.
- Menyimpan status, skor, feedback, dan rincian penilaian untuk ditinjau guru.
- Menyediakan dashboard, halaman pemantauan, riwayat, dan halaman tinjauan penilaian.
- Mendukung pemrosesan antrean melalui scheduled task Moodle dan pengaturan pemrosesan saat submission dikirim.
- Dapat mengirim nilai dan feedback ke gradebook/feedback tugas jika opsi publikasi otomatis diaktifkan.
- Menyediakan pengaturan pembatasan laju permintaan, retry, logging, caching, pelacakan biaya, dan analytics.

## Persyaratan

- Moodle **4.3 atau lebih baru**.
- PHP dan ekstensi yang dibutuhkan instalasi Moodle.
- Koneksi keluar dari server Moodle ke Google Gemini API untuk menggunakan Gemini.
- Untuk ekstraksi gambar: Tesseract OCR harus terpasang dan dapat dijalankan oleh PHP; paket bahasa OCR yang dipilih juga harus tersedia.

Plugin menyatakan kompatibel mulai Moodle versi `2023100900` dan berstatus beta. Pastikan juga cron Moodle berjalan agar antrean dan scheduled task dapat diproses.

## Instalasi

1. Salin folder plugin ke `<direktori-moodle>/local/essay_ai_grader`.
2. Masuk sebagai administrator Moodle dan buka **Site administration → Notifications** untuk menyelesaikan instalasi atau upgrade database.
3. Buka **Site administration → Plugins → Local plugins → Penilaian Essay dengan AI** untuk mengatur penyedia dan opsi plugin.
4. Pastikan cron Moodle aktif. Scheduled task plugin dijadwalkan berjalan setiap menit.

## Konfigurasi

### 1. Atur penyedia AI

Pada halaman pengaturan plugin:

- Pilih penyedia AI.
- Untuk Gemini, masukkan **Gemini API key** dan pilih model.
- Pengaturan juga menampilkan URL dan tag model Ollama.

Koneksi API dan kredensial harus dikonfigurasi serta dapat diakses dari server Moodle. Jangan menaruh API key di repositori atau membagikannya kepada pengguna yang tidak berwenang.

> **Catatan implementasi:** Source plugin saat ini menyertakan client Gemini. Walaupun opsi Ollama tampil di pengaturan, client Ollama tidak tersedia di source tree ini dan pemilihan penyedianya belum terhubung konsisten ke pemroses penilaian. Gunakan Gemini; jangan menganggap Ollama sudah berfungsi.

### 2. Atur opsi penilaian

Pengaturan plugin mencakup:

- aktivasi penilaian otomatis untuk tugas baru;
- pemrosesan segera saat submission masuk;
- publikasi nilai dan feedback otomatis;
- skor maksimum default;
- OCR dan bahasa Tesseract;
- batas permintaan, retry, logging, caching, pelacakan biaya, dan analytics.

Periksa opsi publikasi otomatis sebelum mengaktifkannya. Jika guru harus meninjau dahulu, nonaktifkan publikasi nilai dan feedback otomatis.

### 3. Aktifkan dan konfigurasi pada tugas

1. Buat atau edit aktivitas **Assignment**.
2. Pada bagian **Penilaian AI Essay**, aktifkan penilaian AI.
3. Atur skor maksimum, kriteria beserta bobotnya, kunci jawaban, dan panduan rubrik sesuai kebutuhan.
4. Simpan tugas.
5. Kirim submission uji dan periksa hasilnya melalui dashboard atau halaman tinjauan.

Guru memerlukan izin Moodle yang sesuai untuk mengonfigurasi dan meninjau penilaian. Hak akses ditentukan melalui capability `local/essay_ai_grader:configure` dan `local/essay_ai_grader:review`.

## Pemrosesan submission

Submission yang dibuat atau diperbarui akan masuk antrean apabila penilaian AI telah diaktifkan pada tugas. Secara default, antrean diproses oleh scheduled task Moodle; opsi pemrosesan saat submit dapat mengaktifkan pemrosesan langsung juga.

Teks online diproses terlebih dahulu. Jika tidak ada teks online, plugin mencoba membaca berkas submission berikut:

| Jenis | Cara pemrosesan |
|---|---|
| `.txt`, `.md` | Membaca teks berkas |
| `.docx` | Membaca isi dokumen Word |
| `.pdf` | Ekstraksi teks best-effort; hasil dapat kosong untuk PDF hasil scan |
| Gambar (`jpg`, `jpeg`, `png`, `webp`, `bmp`, `tif`, `tiff`) | OCR best-effort menggunakan Tesseract |

Berkas kosong atau lebih besar dari **5 MB** dilewati. OCR bergantung pada instalasi Tesseract, path binary, dan bahasa yang tersedia di server. Teks essay yang dikirim untuk pemrosesan model dibatasi hingga sekitar **3.500 karakter**; teks lebih panjang dapat terpotong.

## Meninjau hasil

Gunakan dashboard plugin untuk melihat tugas yang mengaktifkan penilaian AI. Dari sana, guru dapat membuka konfigurasi tugas dan halaman tinjauan. Hasil umumnya mencakup saran nilai, feedback, dan skor per kriteria. Guru dapat menerima atau mengubah saran sesuai alur penilaian yang tersedia.

## Privasi dan penggunaan AI

Essay dan konteks penilaian yang diperlukan—misalnya kunci jawaban atau rubrik—dapat dikirim ke penyedia AI yang dikonfigurasi. Sebelum digunakan dengan data siswa:

- tinjau kebijakan privasi, retensi, dan pemrosesan data penyedia AI;
- pastikan penggunaan sesuai kebijakan institusi dan ketentuan perlindungan data yang berlaku;
- informasikan kepada siswa jika diwajibkan oleh kebijakan institusi;
- batasi akses API key dan akses halaman penilaian;
- tinjau hasil AI sebelum menetapkan atau memublikasikan nilai.

## Diagnostik

- Pastikan API key dan model benar serta server Moodle dapat mengakses penyedia AI.
- Pastikan cron Moodle berjalan dan scheduled task pemrosesan tersedia di **Site administration → Server → Tasks → Scheduled tasks**.
- Untuk submission berkas, periksa format, ukuran (maksimum 5 MB per berkas), dan apakah teks dapat diekstrak.
- Untuk OCR, periksa path Tesseract dan kode bahasa OCR pada pengaturan plugin.
- Gunakan halaman **Monitor** dan **Assessment History** untuk membantu memeriksa antrean serta hasil pemrosesan.

## Pengembangan

Komponen utama plugin:

- `classes/assessment_manager.php`: ekstraksi teks dan pengelolaan penilaian.
- `classes/task/process_assessments.php`: pemrosesan antrean terjadwal.
- `classes/criteria_config_form.php` dan `classes/default_criteria.php`: konfigurasi kriteria.
- `classes/gemini/gemini_client.php`: integrasi Gemini.
- `db/observer.php` dan `db/events.php`: penanganan event tugas dan submission.
- `db/install.xml`: skema tabel plugin.

## Lisensi

Plugin menggunakan GNU GPL versi 3 atau yang lebih baru, mengikuti header lisensi pada source code.
