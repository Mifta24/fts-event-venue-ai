# FTS Event Venue AI

Situs venue acara interaktif dengan **AI Event Planner**. Tamu menjelajahi venue seperti menyusun rundown panggung dari satu *cue* ke *cue* berikutnya (foyer, ruang acara, fasilitas, informasi venue, permintaan acara, tim event) sambil mengobrol dengan planner AI. Planner menjawab dari data venue yang terkontrol, mengecek ketersediaan tanggal dan tarif ruang, membuat permintaan acara, dan menyerahkan percakapan ke staf bila perlu. Staf mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa venue. Setiap venue punya halaman sendiri di `/{venueSlug}`.

## Fitur

### Sisi tamu
- **Rundown sebagai navigasi**: setiap bagian adalah satu cue (F foyer, 01 ruang acara, 02 fasilitas, 03 informasi venue, 04 permintaan acara, 05 tim event). Menu berupa panel rundown, header menampilkan *cue display* yang menghitung maju/mundur, dan perpindahan halaman ditandai tirai beludru yang menutup dan membuka. Di ponsel, panel rundown menjadi strip tombol cue.
- **Ruang acara**: tiap ruang punya jenis (indoor, semi-terbuka, terbuka), luas, tinggi plafon, lantai, kapasitas per susunan (banquet, teater, kelas, boardroom, cocktail), inklusi (panggung, sound, bridal suite, dst.), opsi katering in-house, dan tarif per hari acara.
- **Tarif acara**: diskon hari kerja (semua hari acara Senin–Kamis) dan diskon multi-hari (mulai 3 hari) per venue; bila keduanya berlaku, yang lebih besar dipakai. Dihitung di satu tempat sehingga wizard, AI Planner, dan total booking selalu sama. Permintaan online sampai 14 hari acara.
- **Katering**: harga per tamu per hari acara, ikut dihitung di penawaran bila dipilih.
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Event Planner**
  - Tahu cue mana yang sedang dilihat tamu (ruang atau fasilitas yang dibuka, formulir permintaan yang sedang diisi), jadi bisa menjawab "ruang ini".
  - Menjawab dalam bahasa yang ditulis tamu.
  - Menampilkan kartu ruang (kapasitas, luas, total dengan diskon) langsung di dalam chat.
- **Permintaan acara**: wizard lima langkah (tanggal, acara, ruang, data tamu, ringkasan) dengan penawaran harga per ruang dan tanggal, lalu kirim permintaan (status awal `pending`).
  - Tanggal dipilih lewat kalender dalam bahasa tamu; acara satu hari cukup mengetuk tanggal yang sama dua kali. Hari yang sudah penuh dicoret, dan rentang tidak bisa melewati hari yang penuh (data dari `GET /{venueSlug}/reservation/availability`).
  - Ruang yang tidak muat untuk jumlah tamu pada susunan yang dipilih dinonaktifkan.

### Desain
Tema "Gala Night": ungu-malam pekat, emas champagne, dan kertas ivory seperti kartu program. Judul memakai Playfair Display (miring emas untuk kata kunci), isi memakai DM Sans, label bergaya cue sheet memakai DM Mono. Foto ballroom, taman, dan rooftop dari Unsplash.

### Sisi admin (`/admin`)
- Dashboard ringkasan: permintaan menunggu lebih dari 24 jam, handover terbuka, acara terkonfirmasi 14 hari ke depan, dan hari terbooking 30 hari ke depan. Panel admin bisa dipakai dari ponsel.
- CRUD **ruang** (termasuk kapasitas per susunan, katering, gambar, dan tanggal & tarif).
- CRUD **knowledge items**, basis pengetahuan yang menjadi sumber jawaban planner (aturan venue, fasilitas, katering, parkir & transportasi, FAQ).
- **Tanggal & tarif** per ruang (menu Spaces → Dates & rates): buka rentang tanggal, atur jumlah ruang identik dan harga per hari acara. Jumlah ruang tidak bisa diturunkan di bawah yang sudah dibooking. Tampilan bulanan berupa kalender.
- **Pengaturan venue** (khusus `owner`): kontak, jam load-in dan jam malam, bahasa dan zona waktu, diskon hari kerja dan multi-hari, serta status halaman publik (draft atau published).
- Daftar **booking** dan ubah statusnya. Alurnya `pending` → `confirmed` atau `cancelled`, dan `confirmed` → `cancelled`. `cancelled` bersifat final (tanggalnya sudah dikembalikan ke inventori). Ruang yang masih punya booking aktif tidak bisa dihapus.
- **Handover**: percakapan yang diserahkan planner ke staf. Staf bisa membalas langsung ke tamu dan menandainya selesai.
- Peran pengguna per venue: `owner` dan `staff`.

### Notifikasi email
- **Ke staf** (semua anggota aktif venue): permintaan acara baru dan handover baru dari planner.
- **Ke tamu** yang memberi alamat email: tanda terima permintaan, lalu pemberitahuan saat booking dikonfirmasi atau dibatalkan, dalam bahasa tamu (`id`, `en`, `ja`). Tamu yang memilih WhatsApp atau telepon dihubungi langsung oleh staf.
- Email dikirim lewat queue, jadi worker harus jalan (`composer dev` sudah menyertakannya; di produksi jalankan `php artisan queue:work`). Atur `MAIL_*` di `.env`. Bawaannya `MAIL_MAILER=log`, yaitu email hanya ditulis ke log.

## Cara kerja AI Event Planner

Kode ada di [app/Services/Planner/](app/Services/Planner).

- **[PlannerService](app/Services/Planner/PlannerService.php)** menjalankan satu giliran percakapan. Ia memanggil endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama yang di-host sendiri) dan menjalankan loop tool-calling (maksimal 6 putaran per giliran). Riwayat percakapan disimpan di database. Request dikirim dengan `reasoning_effort=none`, dan seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[VenuePlannerTools](app/Services/Planner/VenuePlannerTools.php)** berisi alat yang bisa dipanggil model. Semua fakta venue, ruang, harga, dan ketersediaan harus lewat sini, karena model sendiri tidak dipercaya menyimpan data apa pun.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base venue |
  | `search_spaces` | Mencari ruang sesuai tanggal acara, jumlah tamu, susunan, dan jenis ruang, lengkap dengan ketersediaan dan total harga setelah diskon |
  | `get_space_detail` | Detail satu ruang, termasuk kapasitas per susunan, katering, dan diskon |
  | `check_availability` | Cek ketersediaan dan total harga (dengan katering dan diskon) untuk tanggal tertentu |
  | `create_booking_request` | Membuat permintaan acara |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Planner/ContentGuard.php)** adalah pengaman deterministik di kode. Pesan yang kasar, bersifat seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model. Balasan model yang masih memuat kata-kata tersebut juga diganti.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga atau placeholder yang tidak berasal dari tool akan ditantang, supaya model tidak mengarang harga.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `large_event`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, dan `low_confidence`.

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

Seeder membuat venue demo **Aurelia Event Hall** (slug `aurelia-event-hall`) di Senayan, Jakarta Pusat: lima ruang (Grand Ballroom, Crystal Pavilion, Garden Terrace, Skyline Rooftop, Executive Meeting Suite), tanggal dan tarif 180 hari (tarif akhir pekan 20% lebih tinggi), diskon hari kerja 15% dan multi-hari 10%, serta knowledge base venue (load-in, pembayaran, vendor, panggung & sound, bridal suite, katering, parkir, transportasi, FAQ).

### Menjalankan

```bash
composer dev
```

Perintah ini menjalankan semua proses development lewat `php artisan dev`. Daftar prosesnya bisa dilihat dengan `php artisan dev:list`.

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Venue demo | `http://localhost:8000/aurelia-event-hall` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftsvenue.test
password : password
```

### Konfigurasi LLM

Atur di `.env`:

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman venue tetap jalan tetapi chat planner tidak bisa menjawab.

Kalau perlu, ubah juga `APP_NAME`, `APP_URL`, dan `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

### Mencoba planner dari terminal

```bash
php artisan planner:chat aurelia-event-hall --locale=id
```

Pilihan `--locale` adalah `id`, `en`, atau `ja`. Cara ini berguna untuk menguji loop tool-calling tanpa membuka browser.

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{venueSlug}` | Foyer (cue F) |
| GET | `/{venueSlug}/spaces`, `/spaces/{spaceSlug}` | Direktori dan detail ruang acara (cue 01) |
| GET | `/{venueSlug}/facilities`, `/facilities/{id}` | Fasilitas dan layanan (cue 02) |
| GET | `/{venueSlug}/info`, `/reservation`, `/staff` | Informasi venue (03), permintaan acara (04), tim event (05) |
| GET | `/{venueSlug}/reservation/availability` | Hari yang masih punya ruang kosong, untuk kalender |
| POST | `/{venueSlug}/reservation/quote` | Hitung harga, termasuk katering dan diskon acara |
| POST | `/{venueSlug}/reservation` | Kirim permintaan acara |
| POST | `/{venueSlug}/planner/start` | Mulai percakapan |
| POST | `/{venueSlug}/planner/message` | Kirim pesan ke planner |
| GET | `/{venueSlug}/planner/history` | Riwayat percakapan |

Endpoint planner dan reservasi dibatasi laju (rate limit). Pembatas planner didefinisikan di [AppServiceProvider](app/Providers/AppServiceProvider.php), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/     planner:chat
  Http/Controllers/     halaman tamu, chat, reservasi, dan Admin/
  Models/               Venue, Space, SpaceInventory, Booking, Conversation, HandoverRequest, ...
  Services/Planner/     PlannerService, VenuePlannerTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (venue, ruang, inventori tanggal, knowledge, percakapan, booking, handover)
  seeders/              DemoVenueSeeder
resources/
  js/                   stage, narrator, planner, reservation, date-picker, sound
  views/                halaman tamu (venue/), admin (admin/), komponen
tests/
  Feature/ dan Unit/
```

## Pengujian

```bash
composer test
```

Atau jalankan satu berkas:

```bash
php artisan test --compact tests/Feature/PlannerChatTest.php
```

Cakupan tes: akses admin, chat planner, tool booking (termasuk diskon hari kerja/multi-hari dan filter kapasitas), permintaan acara, status booking, notifikasi email, tanggal & tarif admin, pengaturan venue, semua cue beserta panel rundown, dan `ContentGuard`. GitHub Actions (`.github/workflows/ci.yml`) menjalankan Pint dan seluruh tes di setiap push ke `master` dan pull request.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan venue Anda sendiri, lalu isi `weekday_discount_percent` dan `multiday_discount_percent` bila ingin memberi tarif acara khusus.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`.
- Jalankan queue worker dan isi `MAIL_*` dengan SMTP asli, atau notifikasi email tidak akan terkirim.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons planner melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).
