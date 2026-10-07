# Audit Optimasi dan Duplikasi

Dokumen ini merangkum penelusuran source aplikasi Laravel dan dokumentasi proyek. Isinya adalah temuan statis serta saran tindak lanjut, bukan hasil load test. Tidak ada perubahan kode, aset, route, atau database yang dilakukan sebagai bagian dari audit ini.

## Ringkasan

Prioritas tertinggi adalah memperbaiki route yang dipanggil oleh view tetapi belum terdaftar, karena ini sudah menimbulkan error 500. Untuk performa homepage, perhatian utama adalah query portfolio yang mengambil semua record meskipun hanya sebagian yang langsung ditampilkan, serta fallback gambar yang berpotensi memicu N+1 query. Strategi cache homepage juga tidak konsisten antara komentar dan implementasi.

Pemeriksaan hash menemukan salinan aset identik yang mengonsumsi ruang disk. Angka ini **bukan** pengukuran penggunaan RAM. Hasilnya tidak membuktikan bahwa duplikasi file adalah penyebab memori habis ketika menjalankan `app:import-media-assets`; proses import perlu diukur tersendiri.

## Temuan dan rekomendasi

### 1. Route view yang belum tersedia — prioritas kritis

Route publik yang ditemukan pada source mencakup `home` dan `media.show`, tetapi sejumlah Blade view memanggil nama route lain:

| Route yang dipanggil | Lokasi pemanggilan | Dampak |
|---|---|---|
| `contact.create` | [home.blade.php](resources/views/home.blade.php), [header.blade.php](resources/views/partials/header.blade.php), [show.blade.php](resources/views/portfolio/show.blade.php) | Sudah menyebabkan `RouteNotFoundException`. CTA kontak seharusnya menuju WhatsApp yang dikonfigurasi sesuai keputusan proyek, bukan ke form yang tidak tersedia. |
| `careers.index` | [header.blade.php](resources/views/partials/header.blade.php) | Header dapat gagal dirender selama nama route belum tersedia. |
| `portfolio.index` | [home.blade.php](resources/views/home.blade.php), [show.blade.php](resources/views/portfolio/show.blade.php) | Link katalog/breadcrumb dapat melempar exception. |
| `portfolio.show` | [home.blade.php](resources/views/home.blade.php), [show.blade.php](resources/views/portfolio/show.blade.php) | Link detail dan metadata berbagi dapat melempar exception. |

**Saran:** cocokkan seluruh pemanggilan `route(...)` di Blade dengan `php artisan route:list`. Putuskan tujuan yang benar untuk tiap link; untuk kontak, pakai URL WhatsApp dari pengaturan. Setelah perubahan route/view di masa mendatang, lakukan smoke test homepage dan setiap halaman publik yang dirender.

### 2. Portfolio homepage dimuat tanpa batas — prioritas tinggi

[HomeController.php](app/Http/Controllers/HomeController.php) mengambil semua portfolio published yang bukan unggulan, walaupun homepage hanya menampilkan batch awal (`PORTFOLIO_VISIBLE` adalah 16) dan sisanya disediakan untuk perilaku “load more”. Memuat gambar dengan `loading="lazy"` menunda unduhan browser, tetapi tidak membatasi jumlah model yang diambil atau markup yang dirender server.

**Saran:** batasi query awal ke item yang benar-benar ditampilkan dan sediakan pagination/load-more yang benar-benar digunakan bila katalog perlu berlanjut. Pertahankan hitungan total secara terpisah hanya jika UI membutuhkannya. Ukur jumlah portfolio aktual dan ukuran respons sebelum memilih strategi final.

Homepage juga menjalankan tiga query terpisah untuk value proposition grup benefit, process, dan deliverable. Secara teknis data dapat diambil sekali lalu dikelompokkan di aplikasi, tetapi penggabungan baru layak bila profiling menunjukkan manfaat dan jumlah datanya tetap wajar.

### 3. Fallback gambar portfolio berpotensi menjadi N+1 — prioritas tinggi

[portfolio-card.blade.php](resources/views/components/portfolio-card.blade.php) memakai `coverImage` terlebih dahulu, lalu fallback ke `$portfolio->images->first()`. Query homepage melakukan eager-load `coverImage`, tetapi fallback `images` tidak terlihat ikut di-eager-load. Untuk setiap portfolio tanpa cover, akses fallback berpotensi menambah query.

**Saran:** pastikan hubungan yang digunakan fallback sudah dimuat secara eager, atau ubah pemilihan gambar agar memakai hubungan yang memang sudah dimuat. Verifikasi dengan data yang mencakup portfolio tanpa cover dan hitung query, bukan hanya dengan data ideal.

### 4. Komentar cache dan invalidasi homepage tidak selaras — prioritas sedang

Komentar di [HomeController.php](app/Http/Controllers/HomeController.php) menyebut payload homepage dicache sebagai satu unit, tetapi `payload()` melakukan query saat dipanggil dan tidak membungkus hasilnya dengan cache lintas-request. [ContentObserver.php](app/Observers/ContentObserver.php) menghapus key `home.{locale}`, sementara audit source tidak menemukan penulis key tersebut.

Accessor [Settings.php](app/Support/Settings.php) memoisasi settings selama satu request dan menghapus cache key settings saat flush. Itu menghindari query settings berulang dalam request, tetapi bukan cache payload homepage.

**Saran:** pilih satu perilaku yang disengaja: implementasikan cache payload dengan invalidasi yang mencakup perubahan konten dan locale, atau hapus klaim/invalidasi homepage yang tidak terpakai. Jangan menambahkan cache payload sebelum strategi invalidasi dan perilaku locale diuji.

### 5. Duplikasi penyajian logo dan ikon — prioritas sedang

Header menggunakan aset publik secara langsung, sedangkan footer/komponen lain menyajikan media lewat route media. Dalam pengukuran hash, ditemukan aset publik yang byte-identik dengan file di private media; logo juga memiliki salinan/path lebih dari satu. Dampaknya dapat berupa penyimpanan ganda dan beberapa URL untuk konten sama, bukan bukti bahwa seluruh file dimuat ke RAM Laravel pada satu request.

**Saran:** tentukan satu jalur URL kanonis untuk logo dan ikon. Sebelum menghapus atau memindah file, cocokkan path dengan row database media, konfigurasi, serta seluruh referensi di view. File yang memiliki path logis berbeda mungkin tetap dibutuhkan walaupun isi binernya identik.

### 6. File scaffold yang mungkin tidak aktif — prioritas rendah

`resources/views/welcome.blade.php` berukuran sekitar 72 KB dan tampak seperti view scaffold; route root aktif menggunakan `HomeController`, bukan view tersebut. `resources/views/pages/coming-soon.blade.php` kosong. Temuan ini belum membuktikan file aman dihapus.

**Saran:** telusuri pemanggilan view, route, dan konfigurasi sebelum menghapus atau mengubah file. Jika tidak digunakan, catat keputusan pembersihan terpisah agar tidak tercampur dengan optimasi runtime.

## Pengukuran aset dan duplikasi

Perhitungan berdasarkan hash SHA-256 atas file gambar dan PDF di `public/images` dan `storage/app/media`:

| Ukuran | Hasil |
|---|---:|
| Jumlah file dalam cakupan | 82 |
| Total ukuran di disk | 188,16 MiB |
| Ukuran konten unik (satu salinan per hash) | 172,51 MiB |
| Penyimpanan ekstra akibat file identik dalam cakupan | 15,64 MiB |
| Grup hash yang berisi lebih dari satu file | 35 |
| File publik yang memiliki pasangan identik di private media | 27 file / sekitar 2,81 MiB |

Angka dibulatkan dan hanya mencakup direktori serta tipe file yang disebutkan. “Duplikasi ekstra” menghitung salinan file identik setelah satu file per hash dipertahankan. Ini adalah ukuran **disk**, bukan jumlah byte yang dialokasikan ke RAM saat request web atau proses import.

**Peringatan:** sejumlah hash cocok lintas folder seperti `hero` dan `portfolio`. Jangan menghapus hanya berdasarkan kecocokan hash; validasi penggunaan setiap path terhadap database dan source terlebih dahulu.

## Pemeriksaan duplikasi kode

- Tidak ada bukti terverifikasi bahwa dua file PHP aplikasi memiliki isi byte-per-byte yang sama.
- Nama method seperti `scopePublished`, `scopeOrdered`, `scopeActive`, `casts`, dan `booted` berulang pada beberapa model. Kesamaan nama saja **bukan** duplikasi fungsional: scope Eloquent dapat menerapkan kondisi berbeda pada tabel/model berbeda.
- `ExampleTest` muncul sebagai nama class pada Feature dan Unit test. Nama tersebut berada pada konteks/namespace test yang berbeda dan bukan bukti konflik class.
- Audit ini tidak membenarkan penggabungan helper atau scope semata-mata untuk mengurangi jumlah baris. Periksa perilaku, kondisi query, dan pemanggil sebelum refactor.

## Konsistensi task dan dokumentasi

- [task.md](task.md) memuat item CTA header menuju WhatsApp dan item lain yang lebih luas untuk semua CTA kontak/konsultasi. Pastikan kedua item tidak dihitung sebagai hasil implementasi yang terpisah bila cakupannya sama.
- [docs/plans/README.md](docs/plans/README.md) menandai P0–P9 “Not started”, sedangkan catatan [P9-hardening-signoff.md](docs/plans/P9-hardening-signoff.md) menyatakan beberapa fase telah dibangun dan butir kontak di `docs/02` sudah dibersihkan. Selaraskan status rencana dengan kondisi aktual; jangan menganggap status checklist sebagai bukti source belum ada atau sudah selesai tanpa verifikasi.
- Sisa contact-submission yang sebelumnya dicatat pada P9 tampak sudah ditandai selesai dan telah dihapus dari item 5.2/5.11 di `docs/02-implementation-plan-and-tasks.md`. Pertahankan keputusan D12: kontak diarahkan ke WhatsApp, tanpa membuat contact form.

## Urutan tindak lanjut yang disarankan

1. **Pulihkan rendering halaman:** cocokkan route yang dipanggil view dengan route terdaftar, lalu ganti CTA kontak dengan WhatsApp terkonfigurasi. Uji homepage, header, halaman karier, katalog, dan detail portfolio.
2. **Batasi beban katalog homepage:** ambil hanya portfolio yang tampil, implementasikan mekanisme load-more/pagination bila diperlukan, dan perbaiki eager loading fallback gambar.
3. **Tentukan kontrak cache homepage:** cache atau tanpa cache, tetapi samakan implementasi, komentar, dan invalidasinya.
4. **Kanonisasi URL aset:** pilih jalur penyajian logo/ikon setelah semua referensi dan kebutuhan akses private diperiksa.
5. **Rapikan status dokumentasi dan lakukan pembersihan aman:** sinkronkan status P0–P9; evaluasi file scaffold dan duplikasi media setelah memastikan tidak ada referensi yang masih aktif.

## Validasi sebelum menyatakan optimasi selesai

- Jalankan `php artisan route:list` dan bandingkan nama route dengan pemanggilan `route(...)` di semua Blade view publik.
- Dengan database lokal yang representatif, ukur jumlah portfolio published, jumlah query homepage, query tambahan untuk portfolio tanpa cover, dan waktu render/respons.
- Uji hasil pagination/load-more serta pastikan konten tidak hilang dan hitungan total tetap benar.
- Setelah strategi cache dipilih, uji perubahan konten dan pengaturan locale untuk memastikan invalidasi tepat.
- Audit pemetaan path media ke database sebelum menghapus salinan file; bandingkan hash lagi setelah pembersihan.
- Ukur penggunaan memori command import secara terpisah dari ukuran aset di disk. Bundle build yang tercatat saat audit (CSS 56.493 byte; JS 76.952 byte) juga tidak menjelaskan pemakaian RAM command import.

**Batas audit:** tidak dilakukan load test, profiling PHP, penghitungan query pada database aktif, atau audit lengkap referensi media di database. Rekomendasi yang bergantung pada data aktual harus diverifikasi pada lingkungan yang mewakili produksi.
