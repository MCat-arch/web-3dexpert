<?php

return [
    'title' => 'Pembayaran',
    'meta_description' => 'Selesaikan pembayaran QRIS untuk kursus atau acara 3D Expert.',
    'breadcrumb' => 'Pembayaran',

    'kind_event' => 'Pendaftaran Acara',
    'kind_course' => 'Pendaftaran Kursus',

    'summary_heading' => 'Ringkasan Pesanan',
    'order_id' => 'Order ID',
    'amount' => 'Total Pembayaran',
    'status' => 'Status',
    'method' => 'Metode',
    'method_qris' => 'QRIS',
    'created_at' => 'Dibuat',
    'expires_at' => 'Batas Pembayaran',
    'reference' => 'Kode Tiket',
    'customer' => 'Pemesan',
    'event_when' => 'Waktu Acara',
    'event_where' => 'Lokasi',

    'qr_heading' => 'Pindai QRIS',
    'qr_instruction' => 'Buka aplikasi bank atau e-wallet Anda, pilih QRIS, lalu pindai kode di bawah ini.',
    'qr_alt' => 'Kode QRIS pembayaran',
    'qr_waiting' => 'Menyiapkan kode QRIS...',
    'qr_unavailable_title' => 'Pembayaran QRIS belum tersedia',
    'qr_unavailable_body' => 'Kode QR belum dapat dibuat. Silakan muat ulang halaman ini beberapa saat lagi atau hubungi admin untuk menyelesaikan pembayaran.',
    'contact_admin' => 'Hubungi Admin',
    'retry_charge' => 'Muat Ulang Halaman',

    'countdown_label' => 'Selesaikan pembayaran dalam',
    'countdown_note' => 'Kursi atau akses Anda ditahan selama :minutes menit. Setelah waktu habis, pesanan dibatalkan otomatis.',
    'countdown_expired' => 'Waktu pembayaran habis.',

    'status_pending' => 'Menunggu Pembayaran',
    'status_settlement' => 'Pembayaran Berhasil',
    'status_expired' => 'Kedaluwarsa',
    'status_cancelled' => 'Dibatalkan',
    'status_failed' => 'Gagal',
    'status_refunded' => 'Dikembalikan',

    'pending_title' => 'Menunggu pembayaran',
    'pending_body' => 'Pesanan Anda sudah dibuat dan pembayaran belum kami terima.',

    'settled_title' => 'Pembayaran berhasil',
    'settled_body' => 'Terima kasih. Pembayaran Anda sudah kami terima dan akses/pendaftaran Anda sudah aktif.',
    'settled_note' => 'Simpan Order ID ini sebagai bukti pembayaran.',
    'success_action' => 'Lanjutkan',

    'expired_title' => 'Pembayaran kedaluwarsa',
    'expired_body' => 'Batas waktu pembayaran sudah lewat. Jika pendaftaran acara, kursi Anda sudah dilepas otomatis.',
    'cancelled_title' => 'Pembayaran dibatalkan',
    'cancelled_body' => 'Pembayaran ini sudah dibatalkan. Anda dapat membuat pesanan baru jika masih ingin melanjutkan.',
    'failed_title' => 'Pembayaran gagal',
    'failed_body' => 'Pembayaran tidak berhasil diproses. Anda dapat mencoba lagi dengan membuat pesanan baru.',
    'refunded_title' => 'Pembayaran dikembalikan',
    'refunded_body' => 'Pembayaran ini sudah dikembalikan. Hubungi admin jika Anda membutuhkan bantuan.',

    'retry_action' => 'Kembali dan Coba Lagi',
    'back_action' => 'Kembali',
    'secure_note' => 'Pembayaran diproses oleh Midtrans. 3D Expert tidak menyimpan data kartu atau PIN Anda.',
];
