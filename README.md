# FTS Apartment AI

Situs serviced apartment interaktif dengan **AI Concierge**. Tamu menjelajahi gedung seperti naik lift dari lantai ke lantai (lobi, unit hunian, fasilitas bersama, informasi gedung, permintaan sewa, tim apartemen) sambil mengobrol dengan concierge AI. Concierge menjawab dari data apartemen yang terkontrol, mengecek ketersediaan unit dan tarif menginap jangka panjang, membuat permintaan sewa, dan menyerahkan percakapan ke staf bila perlu. Staf mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa gedung apartemen. Setiap apartemen punya halaman sendiri di `/{apartmentSlug}`.

## Fitur

### Sisi tamu
- **Gedung sebagai navigasi**: setiap bagian adalah satu lantai (L lobi, 02 unit hunian, 03 fasilitas bersama, 04 informasi gedung, 05 permintaan sewa, 06 tim apartemen). Menu berupa panel tombol lift, header menampilkan layar lantai yang menghitung naik/turun, dan perpindahan halaman ditandai pintu lift yang menutup dan membuka. Di ponsel, panel lift menjadi strip tombol lantai.
- **Unit apartemen**: tiap tipe unit punya denah (studio atau jumlah kamar tidur, kamar mandi), rentang lantai, luas, perabot, dan perkiraan tarif bulanan.
- **Tarif menginap lama**: diskon mingguan (mulai 7 malam) dan bulanan (mulai 28 malam) per apartemen, diterapkan di satu tempat sehingga wizard, AI Concierge, dan total booking selalu sama. Permintaan online sampai 90 malam.
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Concierge**
  - Tahu di lantai mana tamu berada (unit atau fasilitas yang sedang dilihat, form permintaan sewa yang sedang diisi), jadi bisa menjawab "unit ini".
  - Menjawab dalam bahasa yang ditulis tamu.
  - Menampilkan kartu unit (denah, luas, total dengan diskon) langsung di dalam chat.
- **Permintaan sewa**: quote harga per unit dan tanggal (termasuk diskon menginap lama), lalu kirim permintaan (status awal `pending`).

### Desain
Tema "Skyline Residence": tinta biru malam, kertas beton, dan aksen hijau-lime seperti papan penunjuk gedung. Font Space Grotesk dan IBM Plex Mono (label bergaya signage). Foto kota dan interior apartemen dari Unsplash.

### Sisi admin (`/admin`)
- Dashboard ringkasan.
- CRUD **tipe unit** (termasuk denah, lantai, gambar, dan inventori).
- CRUD **knowledge items**, yaitu basis pengetahuan yang menjadi sumber jawaban concierge (aturan gedung, fasilitas, layanan, transportasi, FAQ).
- Daftar **booking** dan ubah statusnya (`pending`, `confirmed`, `cancelled`).
- **Handover**: percakapan yang diserahkan concierge ke staf. Staf bisa membalas langsung ke tamu dan menandainya selesai.
- Peran pengguna per apartemen: `owner` dan `staff`.

## Cara kerja AI Concierge

Kode ada di [app/Services/Concierge/](app/Services/Concierge).

- **[ConciergeService](app/Services/Concierge/ConciergeService.php)** menjalankan satu giliran percakapan. Ia memanggil endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama yang di-host sendiri) dan menjalankan loop tool-calling (maksimal 6 putaran per giliran). Riwayat percakapan disimpan di database. Request dikirim dengan `reasoning_effort=none` agar model tidak melakukan fase berpikir panjang. Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[ApartmentConciergeTools](app/Services/Concierge/ApartmentConciergeTools.php)** berisi alat yang bisa dipanggil model. Semua fakta gedung, unit, harga, dan ketersediaan harus lewat sini, karena model sendiri tidak dipercaya menyimpan data apa pun.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base apartemen |
  | `search_units` | Mencari unit sesuai tanggal, jumlah penghuni, dan jumlah kamar tidur, lengkap dengan ketersediaan dan total harga setelah diskon |
  | `get_unit_detail` | Detail satu tipe unit, termasuk denah, lantai, dan tarif menginap lama |
  | `check_availability` | Cek ketersediaan dan total harga (dengan diskon) untuk tanggal tertentu |
  | `create_booking_request` | Membuat permintaan sewa |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Concierge/ContentGuard.php)** adalah pengaman deterministik di kode. Pesan yang kasar, bersifat seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model. Balasan model yang masih memuat kata-kata tersebut juga diganti. Pola dibuat sempit supaya pertanyaan apartment yang wajar tidak ikut terblokir.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga atau placeholder yang tidak berasal dari tool akan ditantang, supaya model tidak mengarang harga.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `group_booking`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, dan `low_confidence`.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4 untuk frontend, dengan JavaScript vanilla (tanpa framework) di `resources/js/`
- LLM lokal lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

### Prasyarat
PHP 8.3+ dengan ekstensi SQLite, Composer, dan Node.js 20.19+ (atau 22.12+) dengan npm. Untuk fitur chat AI, Anda juga perlu server LLM yang kompatibel dengan OpenAI (lihat bagian berikutnya).

### Instalasi

```bash
composer setup
```

Perintah itu menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat `APP_KEY`, menjalankan migrasi, lalu `npm install` dan `npm run build`.

Isi data demo (hanya jalan di environment `local` dan `testing`):

```bash
php artisan db:seed
```

Seeder membuat serviced apartment demo **FTS Apartment AI** (slug `fts-apartment-ai`) di Senopati, Jakarta Selatan: empat tipe unit (Studio Urban, 1 Bedroom Executive, 2 Bedroom Family, 3 Bedroom Penthouse), inventori 180 hari, diskon mingguan 10% dan bulanan 25%, serta knowledge base gedung (aturan, utilitas, deposit, hewan peliharaan, fasilitas, MRT).

### Menjalankan

```bash
composer dev
```

Perintah ini menjalankan semua proses development lewat `php artisan dev`. Daftar prosesnya bisa dilihat dengan `php artisan dev:list`.

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Apartment demo | `http://localhost:8000/fts-apartment-ai` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftsapartment.test
password : password
```

### Konfigurasi LLM

Atur di `.env`:

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman apartemen tetap jalan tetapi chat concierge tidak bisa menjawab.

Kalau perlu, ubah juga `APP_NAME`, `APP_URL`, dan `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

### Mencoba concierge dari terminal

```bash
php artisan concierge:chat fts-apartment-ai --locale=id
```

Pilihan `--locale` adalah `id`, `en`, atau `ja`. Cara ini berguna untuk menguji loop tool-calling tanpa membuka browser.

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{apartmentSlug}` | Lobi (lantai L) |
| GET | `/{apartmentSlug}/units`, `/units/{unitSlug}` | Direktori dan detail tipe unit (lantai 02) |
| GET | `/{apartmentSlug}/facilities`, `/facilities/{id}` | Fasilitas bersama (lantai 03) |
| GET | `/{apartmentSlug}/info`, `/reservation`, `/staff` | Informasi gedung (04), permintaan sewa (05), tim apartemen (06) |
| POST | `/{apartmentSlug}/reservation/quote` | Hitung harga, termasuk diskon menginap lama |
| POST | `/{apartmentSlug}/reservation` | Kirim permintaan sewa |
| POST | `/{apartmentSlug}/concierge/start` | Mulai percakapan |
| POST | `/{apartmentSlug}/concierge/message` | Kirim pesan ke concierge |
| GET | `/{apartmentSlug}/concierge/history` | Riwayat percakapan |

Endpoint concierge dan reservasi dibatasi laju (rate limit). Pembatas concierge didefinisikan di [AppServiceProvider](app/Providers/AppServiceProvider.php), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/     concierge:chat
  Http/Controllers/     halaman tamu, chat, reservasi, dan Admin/
  Models/               Apartment, UnitType, Booking, Conversation, HandoverRequest, ...
  Services/Concierge/   ConciergeService, ApartmentConciergeTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (apartemen, tipe unit, knowledge, percakapan, booking, handover, denah & diskon menginap lama)
  seeders/              DemoApartmentSeeder
resources/
  js/                   stage, narrator, concierge, reservation, sound
  views/                halaman tamu (apartment/), admin (admin/), komponen
tests/
  Feature/ dan Unit/
```

## Pengujian

```bash
composer test
```

Atau jalankan satu berkas:

```bash
php artisan test --compact tests/Feature/ConciergeChatTest.php
```

Cakupan tes: akses admin, chat concierge, tool booking (termasuk diskon menginap lama dan filter kamar tidur), permintaan sewa, semua lantai dan panel lift, dan `ContentGuard`.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan apartemen Anda sendiri, lalu isi `weekly_discount_percent` dan `monthly_discount_percent` bila ingin memberi tarif menginap lama.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`. Pada setup saat ini endpoint LLM diakses lewat Tailscale.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons concierge melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).
