<?php

return [
    'eyebrow' => 'Lacak Proyek',
    'title' => 'Lacak Progres Proyek',
    'meta_description' => 'Masukkan kode proyek dan nomor WhatsApp atau email Anda untuk melihat progres terkini.',

    'code' => 'Kode proyek',
    'code_hint' => 'Contoh: 3DX-7K2M9QX4. Kode ini kami kirimkan saat proyek dimulai.',
    'verification' => 'Nomor WhatsApp atau email',
    'verification_hint' => 'Gunakan kontak yang Anda daftarkan pada proyek ini.',
    'submit' => 'Lacak',
    'form_title' => 'Cek progres proyek Anda',
    'form_intro' => 'Masukkan kode proyek dan salah satu kontak yang terdaftar. Keduanya harus cocok.',

    /*
     * SATU pesan untuk ketiga kemungkinan kegagalan: kode tidak ada, kontak
     * tidak cocok, dan proyek dibatalkan/disembunyikan.
     *
     * Jangan dipecah menjadi pesan yang berbeda. Pesan "proyek sudah
     * dibatalkan" akan mengonfirmasi bahwa kodenya BENAR, dan itu justru sinyal
     * yang ingin kita tahan (lihat App\Support\ProjectLookup).
     */
    'not_found' => 'Data proyek tidak ditemukan. Periksa kembali kode proyek dan kontak Anda, atau hubungi kami.',

    'result_title' => 'Progres Proyek',
    'project_title' => 'Proyek',
    'current_stage' => 'Tahap saat ini',
    'status' => 'Status',
    'started_at' => 'Mulai',
    'estimated_completion' => 'Perkiraan selesai',
    'date_completed' => 'Selesai',
    'dates' => 'Tanggal',
    'timeline' => 'Riwayat Progres',
    'no_updates' => 'Belum ada catatan progres.',

    'overall_progress' => 'Progres Keseluruhan',
    'attachments' => 'Lampiran Progres',
    'attachments_note' => 'Berkas terbaru dari tim untuk Anda periksa.',
    'download' => 'Unduh',

    'final_heading' => 'Hasil Akhir',
    'final_completed_on' => 'Selesai pada :date',
    'final_default_note' => 'Proyek ini sudah selesai. Berkas akhirnya tersedia di bawah ini.',
    'final_no_files' => 'Belum ada berkas akhir yang diunggah. Hubungi admin bila Anda membutuhkannya.',

    'status_in_progress' => 'Sedang dikerjakan',
    'status_on_hold' => 'Ditunda',
    'status_completed' => 'Selesai',
    'status_cancelled' => 'Dibatalkan',

    'update_pending' => 'Belum mulai',
    'update_in_progress' => 'Berjalan',
    'update_done' => 'Selesai',

    'help' => 'Tidak menemukan proyek Anda?',
    'help_cta' => 'Hubungi kami via WhatsApp',
];
