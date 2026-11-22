# Garage Rental App — Local Demo Guideline

Follow this once the day before the demo, then use the **Pre-demo checklist** (section 6) right before presenting. Everything runs on a laptop with Laravel Herd.

## 1. Prerequisites

| Need | How to check |
|---|---|
| Laravel Herd (PHP 8.4) | Herd menu bar icon is running |
| Composer | `composer --version` |
| Node.js + npm | `node -v && npm -v` |
| A Cloudinary account | needed for payment-proof uploads (free tier is fine) |
| Internet during the demo | Cloudinary uploads and the Bunny font need it |

The project lives in `~/Herd/lsp-suryanto`, so Herd serves it at **http://lsp-suryanto.test** automatically. (`php artisan serve` also works and uses http://localhost:8000.)

## 2. First-time setup

Run from the project folder.

```bash
cd ~/Herd/lsp-suryanto
composer install
npm install
cp .env.example .env          # skip if .env already exists
php artisan key:generate      # skip if APP_KEY is already set
```

Edit `.env`:

```dotenv
APP_NAME="Garasi Sewa"
APP_URL=http://lsp-suryanto.test
ADMIN_PASSWORD=choose-a-password       # required, the seeder refuses to run without it
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

Find the Cloudinary URL on the Cloudinary dashboard under *Settings → API Keys* (copy the "API environment variable").

Then create the database and data:

```bash
touch database/database.sqlite        # only if the file does not exist
php artisan migrate
php artisan db:seed
npm run build
```

`db:seed` is safe to run more than once. It creates:

| Account | Email | Password | Status |
|---|---|---|---|
| Admin | `admin@garasi.test` | the `ADMIN_PASSWORD` you set | approved |
| Penyewa (with 4 vehicles) | `penyewa@garasi.test` | `password` | approved |
| Penyewa (for the verification demo) | `pending@garasi.test` | `password` | pending |

It also creates 9 demo garages: S001–S003, C001–C003, E001–E003 with different prices.

## 3. Run it

Open **two terminals** in the project folder and leave both open during the demo.

```bash
# Terminal 1: the nightly job (refreshes garage used/free status)
php artisan schedule:work

# Terminal 2: only if you are editing CSS/JS live; otherwise skip
npm run dev
```

With Herd the web server is already running. If you are not using Herd, add a third terminal with `php artisan serve`.

Open the site. You should see the **welcome page** with *Masuk* and *Daftar* buttons.

## 4. Verify before trusting it

```bash
php artisan test
```

All tests must pass. Tests use a separate in-memory/temporary database and a fake uploader, so they never touch your demo data or Cloudinary.

Then refresh today's garage status by hand:

```bash
php artisan garages:refresh-status
```

## 5. Suggested 10-minute demo script

| Min | Step | Where |
|---|---|---|
| 0–1 | Show the welcome page, then **Daftar**: register a new penyewa (NIK 16 digits). Log in: they only see the status page. | `/` |
| 1–2.5 | Log in as admin → **Verifikasi**: tick *KTP sudah dicek*, **Setujui** the new user. Show a rejected case with `pending@garasi.test` if time allows. | admin |
| 2.5–3.5 | **Garasi → Buat Garasi**: create a batch (e.g. 3 × Mobil Listrik, daily price, price per kWh). Show codes continue after E003. | admin |
| 3.5–4.5 | Log in as `penyewa@garasi.test` → **Kendaraan Saya**: add a vehicle. | penyewa |
| 4.5–7 | **Sewa Baru**: electric car, monthly, current charge 15 kWh. Point out the live total (rent + charging), the garage list with each garage's own price, and upload a payment proof image. | penyewa |
| 7–8 | **Sewa Saya**: show the rental and the first charging session; use **Bayar Pengecasan** for another one. | penyewa |
| 8–9 | Admin → **Perpanjangan**: extend the rental; if you booked a conflicting rental beforehand, show the slot-change popup and the penyewa's notice. | admin |
| 9–10 | Admin **Dashboard** (revenue, penyewa count, slot status) and **Laporan** (per month, per day, per type, per penyewa). | admin |

Tip for the slot-conflict moment: before the demo, create a second rental on the same garage type that starts on the date the first one ends.

Use a small JPG/PNG (under 2 MB) as the payment proof. Allowed: jpg, jpeg, png, webp.

## 6. Pre-demo checklist

- [ ] Herd is running and the site opens at the welcome page
- [ ] `php artisan schedule:work` is running in a terminal
- [ ] `php artisan garages:refresh-status` was run today
- [ ] `php artisan test` passes
- [ ] `npm run build` was run after the last CSS/view change (otherwise styles look broken)
- [ ] Cloudinary URL works: upload one proof as a rehearsal
- [ ] Admin and penyewa accounts log in successfully
- [ ] A test proof image is on the desktop
- [ ] Laptop is charged and on stable Wi-Fi; notifications are off

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| Page looks unstyled, or *Unable to locate file in Vite manifest* | Run `npm run build` (or `npm run dev`). |
| *Set ADMIN_PASSWORD in .env before seeding* | Add `ADMIN_PASSWORD=...` to `.env`, then `php artisan db:seed`. |
| *No application encryption key* | `php artisan key:generate`. |
| *Unable to open the database file* | `touch database/database.sqlite`, then `php artisan migrate`. |
| Upload says *Gagal mengunggah bukti pembayaran* | Check `CLOUDINARY_URL`, internet access, and that the image is under 2 MB. Then `php artisan config:clear`. |
| `.env` edits are ignored | `php artisan config:clear`. |
| Garages show the wrong used/free status | `php artisan garages:refresh-status`. |
| Dates look off by a day | The app uses `Asia/Jakarta`; check the laptop clock and timezone. |
| Want a clean slate | `php artisan migrate:fresh --seed` (**deletes all data**, then recreates the demo data). |
| Port 8000 busy (`artisan serve`) | `php artisan serve --port=8001`. |

## 8. Notes

- Nothing in this setup pushes to GitHub.
- `.env` holds secrets (admin password, Cloudinary key). Never commit it.
