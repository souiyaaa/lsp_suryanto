# Garage Rental App — Code Textbook

A guided tour of the code, written for someone who knows basic PHP but has not seen this project (an assessor, a classmate, or you in three months). Read it top to bottom once, then use the table of contents as a map.

Related documents: [requirements.md](requirements.md) (what the app must do), [erd.md](erd.md) (the tables), [class-diagram.md](class-diagram.md) (the classes), [demo-guideline.md](demo-guideline.md) (how to run it).

## Contents

1. The big picture
2. Folder map
3. One request, start to finish
4. Database and models
5. Enums
6. Services (the business logic)
7. Controllers
8. Form requests (validation)
9. Middleware, error handling and the JSON envelope
10. Views and the front end
11. The scheduler and `is_used`
12. Tests
13. Worked example: renting an electric-car garage
14. Where do I change…? (cheat sheet)
15. Glossary

---

## 1. The big picture

The app lets people rent garages by the day or by the month.

- **Admin** (one seeded account): verifies new users, creates garages in batches, extends rentals, reads reports.
- **Penyewa** (the renter): signs up, waits for approval, registers vehicles, rents garages, pays for charging sessions.

Stack: Laravel (PHP 8.4) + SQLite + Blade views + Tailwind CSS (via Vite) + Alpine.js, tested with PHPUnit, running on Laravel Herd. Payment-proof images go to Cloudinary; the database stores only the returned URL.

Four rules from `CLAUDE.md` shape almost every file:

| Rule                                      | What it means in the code                                                |
| ----------------------------------------- | ------------------------------------------------------------------------ |
| Business logic lives in Services          | Controllers stay thin; the maths and rules are in `app/Services`.        |
| Validation lives in FormRequests          | `app/Http/Requests` checks input shape before a controller runs.         |
| Derived values are never columns (3NF)    | Number of days, rent and charging cost are methods, not database fields. |
| `garages.is_used` is written by one class | Only `GarageStatusService` writes it. Availability checks never read it. |

Dates are half-open ranges **[start, end)**: a garage is occupied on the start date and free again _on_ the end date. Money is whole Rupiah stored as integers.

---

## 2. Folder map

```
app/
  Enums/                 fixed value lists (GarageType, RentalType, ...)
  Exceptions/            PaymentProofUploadException
  Http/
    Controllers/         thin: validate -> call a service -> return a view/redirect
      Admin/             admin-only controllers
      Auth/              Breeze login/register/password controllers
    Middleware/          EnsureUserIsAdmin, EnsureUserIsApproved
    Requests/            FormRequests (validation rules)
      Admin/ Auth/
  Models/                Eloquent models: User, Vehicle, Garage, Rental, ChargingTransaction
  Services/              business logic
    Calculators/         pure maths (no database)
    Generators/          garage code generation
  Support/               tiny helpers: Rupiah, DateFormatter
  Traits/                HasValues, HasPaymentProofRule, ApiResponse
bootstrap/app.php        middleware aliases and exception rendering
config/                  app.php (timezone), services.php (Cloudinary)
database/
  migrations/ factories/ seeders/
docs/                    this textbook, requirements, ERD, class diagram
resources/views/         Blade templates
routes/
  web.php                every URL
  console.php            the artisan command and the schedule
tests/
  Feature/               HTTP-level tests
  Unit/                  models, calculators, services
```

---

## 3. One request, start to finish

Take "a penyewa saves a new rental" (`POST /rentals`):

1. **Route** — `routes/web.php` matches `Route::resource('rentals', RentalController::class)` inside the `auth` and `approved` middleware groups. A guest is redirected to login; a pending user is redirected to `/status`.
2. **FormRequest** — `RentalRequest` validates the input (vehicle, dates, image). If it fails, Laravel redirects back with the errors. The controller never runs.
3. **Controller** — `RentalController::store` uploads the image through `PaymentProofUploader` (this happens _before_ the database transaction), then calls `RentalService::createRental`.
4. **Service** — `RentalService` re-checks the rules, calculates the dates and price, and saves everything inside one `DB::transaction`.
5. **Models** — `Rental::create(...)` writes the row.
6. **Response** — the controller redirects to `rentals.index` with a `success` flash message, which `<x-alert />` displays.

The pattern is the same everywhere: **Route → Middleware → FormRequest → Controller → Service → Model → View.**

---

## 4. Database and models

Five tables (see [erd.md](erd.md) for the diagram).

| Table          | Model                 | Purpose                                                         |
| -------------- | --------------------- | --------------------------------------------------------------- |
| `users`        | `User`                | Admin and penyewa. `role` and `status` are enums.               |
| `vehicles`     | `Vehicle`             | A penyewa's vehicles. `max_battery_kwh` only for electric cars. |
| `garages`      | `Garage`              | One row per garage. Price is stored on each garage.             |
| `rentals`      | `Rental`              | Original rentals **and** extensions (see below).                |
| `charging_trx` | `ChargingTransaction` | One row per paid charging session.                              |

### 4.1 The self-referencing `rentals` table

An extension is not a separate table. It is another `rentals` row whose `parent_rental_id` points at the **original** rental. An original has `parent_rental_id = null`. Extensions always point to the original, never to the previous extension. The set "original + its extensions" is called a **chain**, and each row in it is a **segment**.

```
rentals
id  garage_id  start       end         parent_rental_id
10  E001       2026-11-21  2026-11-24  null      <- original
11  E001       2026-11-24  2026-11-27  10        <- extension 1
12  E003       2026-11-27  2026-11-30  10        <- extension 2 (slot changed)
```

Each segment keeps its own `garage_id`, which is how a slot change is recorded.

### 4.2 Derived values are accessors, not columns

`Rental::numberOfDays()` returns `start_date->diffInDays(end_date)`. Rent is computed by `RentalCalculator`; charging cost by `ChargingService::costOf()`. Nothing like "total" or "days" is stored, so the data cannot disagree with itself. The trade-off is documented in requirements §8: if prices could change later, the price would have to be copied onto each transaction.

### 4.3 Query scopes on `Rental`

Scopes are reusable query fragments. They are the _only_ place the date SQL is written.

| Scope                       | Meaning                                                          |
| --------------------------- | ---------------------------------------------------------------- |
| `coveringDate($date)`       | `start_date <= date AND end_date > date` — occupied on that day. |
| `overlapping($start, $end)` | `start_date < end AND end_date > start` — the overlap test.      |
| `inChainOf($original)`      | the original plus its extensions.                                |
| `originals()`               | only rows with no parent.                                        |

`Rental::setAttribute` also converts Carbon values to plain `Y-m-d` strings. A Carbon object would be saved with a `00:00:00` time part and break the string comparisons in the scopes above.

`Garage` has matching scopes `ofType()` and `freeBetween()` used by `AvailabilityService`.

### 4.4 Mass assignment

`Rental` lists its writable columns with the `#[Fillable([...])]` attribute. `User.role`, `User.status` and `Garage.is_used` are deliberately **not** fillable, so a crafted form cannot make someone an admin or approve themselves. Services use `forceFill()` where they must change those fields.

---

## 5. Enums

Enums in `app/Enums` replace magic strings. Each is a PHP backed enum cast by the model.

| Enum          | Cases                                            | Notes                                                                 |
| ------------- | ------------------------------------------------ | --------------------------------------------------------------------- |
| `UserRole`    | admin, penyewa                                   |                                                                       |
| `UserStatus`  | pending, approved, rejected                      |                                                                       |
| `VehicleType` | sepeda, sepeda_motor, mobil, mobil_listrik       |                                                                       |
| `GarageType`  | sepeda, mobil, mobil_listrik                     | `codePrefix()` (S/C/E), `hasCharging()`, `allowsVehicle()`, `label()` |
| `RentalType`  | harian, bulanan                                  |                                                                       |
| `ReportView`  | per month, per day, per garage type, per penyewa | selects the report                                                    |

`GarageType::allowsVehicle()` encodes the compatibility table: a Sepeda garage accepts bicycles and motorbikes, a Mobil garage accepts cars and electric cars, and a Mobil Listrik garage accepts only electric cars. The `HasValues` trait gives enums a `values()` helper used in validation `in:` rules.

---

## 6. Services (the business logic)

Everything the app _decides_ happens here. Each public method has a PHPUnit test in `tests/Unit/Services`.

### 6.1 `RentalCalculator` (pure maths)

No database, easy to test.

- `maxDailyDays($start)` = days in the start date's month − 1.
- `isValidDailyLength($start, $n)` = `1 <= n <= maxDailyDays`.
- `calculateEndDate($start, $type, $n)` = start + n (daily) or start + days-in-month (monthly).
- `calculateRent($type, $dailyPrice, $days)` = `days × price` (daily) or `25 × price` (monthly).
- `isOverlapping(...)` = `existing.start < new.end AND new.start < existing.end`.

Examples: 21 Nov monthly → ends 21 Dec (30 days, rent 25 × price). A daily rental starting in November may be 1–29 days.

### 6.2 `ChargingCalculator` (pure maths)

- `isValidCurrentCharge(current, max)` = `0 <= current < max`.
- `calculateKwhNeeded(max, current)` = `max − current`. The system always charges to 100%.
- `calculateCost(kwh, pricePerKwh)` = `round(kwh × price)` as whole Rupiah.

Example: 60 kWh battery at 15 kWh and Rp 2.500/kWh → 45 kWh → Rp 112.500.

### 6.3 `AvailabilityService`

Answers "is this free for [start, end)?" by querying `rentals`. It never reads `is_used`.

- `findFreeGarages($type, $start, $end)` — garages of that type with no overlapping rental, cheapest first (ties by code).
- `isGarageFree($garage, $start, $end)` and `isVehicleFree($vehicle, $start, $end)`.

### 6.4 `RentalService`

- `previewRental($vehicle, $garage, $data)` — validates compatibility and day count, computes end date, days, rent and (for electric garages) the first charging session. Returns an array. The preview endpoint and `createRental` both call it, so **the total shown equals the total saved**.
- `createRental($penyewa, $data, $proofUrl)` — loads the vehicle _through the user's relation_ (so you can only rent with your own vehicle), previews, then in one transaction: checks the garage and vehicle are still free (`ensureBookable`), creates the rental, records the first charging session if the garage is electric (same proof URL), and refreshes the garage status.

### 6.5 `ChargingService`

- `previewCharging` — validates the current kWh and returns kWh needed and cost.
- `addSession($originalRental, $kwh, $proofUrl)` — only allowed while the chain is active today and the active segment is in an electric garage; otherwise a 404.
- `recordSession` — creates the `charging_trx` row.
- `findActiveSegment` — the segment covering today, so the session is billed at _that_ garage's price.
- `costOf($transaction)` — computes cost when shown; nothing is stored.

### 6.6 `ExtensionService` (admin only)

- `activeRentalsToday()` / `paginateActiveRentalsToday()` — originals whose chain covers today. Both share one private query.
- `currentSegment($original)` — the segment with the latest end date; the next extension starts there.
- `previewExtension(...)` — start = current end date; applies the same day rules as a new rental; checks the vehicle is free; then `resolveGarage`.
- `resolveGarage` — keep the current garage if it is free for the range. Otherwise ask `AvailabilityService` for free garages of the same type and let `pickReplacementGarage` choose the cheapest (random among equal prices). If none, the extension is blocked with a validation error.
- `extend(...)` — inside a transaction, recomputes the preview (the slot may have been taken since), confirms the garage the admin agreed to, creates the extension row, and refreshes the garage status.

The admin sees a confirmation popup (fed by the preview endpoint) when the slot changes; the penyewa sees a yellow notice on My Rentals.

### 6.7 `GarageBatchService` and `GarageCodeGenerator`

`createBatch` runs in a transaction: counts existing garages of the type, rejects an amount beyond `999 − existing`, generates codes (`S001`, `C012`, `E003`...) and creates garages with the batch's prices. Only electric garages keep a charging price. `GarageCodeGenerator` is pure: `remainingCapacity()` and `generateCodes()`.

### 6.8 `VerificationService`

`approve` sets status to approved and stamps `ktp_verified_at`; `reject` sets rejected. Both refuse users who are not pending. A rejected NIK cannot register again because NIK is unique.

### 6.9 `GarageStatusService`

Owns `garages.is_used`. See chapter 11.

### 6.10 `PaymentProofUploader`

The only class that talks to Cloudinary. `upload()` returns the `secure_url`. Any failure becomes a `PaymentProofUploadException` with a friendly message. Controllers call it **before** opening a database transaction, so a failed upload saves nothing.

### 6.11 `ReportService`

- `revenuePerRental()` — one row per **original** rental with its total revenue (rent of every segment + all charging). Revenue is credited to the original's start date.
- `revenueOf`, `rentOf`, `totalRevenue`, `totalPenyewa`.
- `revenuePerMonth($year)`, `revenuePerDay($year, $month)`, `revenuePerGarageType()`, `revenuePerPenyewa()` — group those rows with `summarise()`: rental count (originals only, extensions add revenue but not a rental) and revenue.

---

## 7. Controllers

Controllers do three things: take validated input, call a service, return a response.

| Controller                       | Actions                                                                                            |
| -------------------------------- | -------------------------------------------------------------------------------------------------- |
| `HomeRedirectController`         | After login, sends admin → admin dashboard, pending/rejected → status page, approved → My Rentals. |
| `VehicleController`              | list (paginated), create, store.                                                                   |
| `RentalController`               | list (paginated, with extensions and charging eager-loaded), create, store.                        |
| `ChargingTransactionController`  | add a charging session to an active electric rental.                                               |
| `RentalPreviewController`        | JSON endpoints: garage types for a vehicle, available garages, rental preview, charging preview.   |
| `Admin\AdminDashboardController` | totals and today's slot status.                                                                    |
| `Admin\VerificationController`   | list pending users, approve, reject.                                                               |
| `Admin\GarageController`         | list (paginated), create batch.                                                                    |
| `Admin\ExtensionController`      | list, form, preview JSON, store.                                                                   |
| `Admin\ReportController`         | the four reports; pages the keyed result by hand with `LengthAwarePaginator`.                      |
| `ProfileController`, `Auth\*`    | Laravel Breeze defaults.                                                                           |

The welcome page is a closure in `routes/web.php`: guests see `welcome.blade.php`; a logged-in user is redirected to `/dashboard`, which `HomeRedirectController` forwards to the right home.

---

## 8. Form requests (validation)

Each form has a class in `app/Http/Requests`. Examples:

- `RentalRequest` — vehicle, rental type, start date, days, garage, current kWh, payment proof.
- `VehicleRequest` — plate required except for sepeda (a sepeda never keeps a plate), battery only for electric cars.
- `Admin\GarageBatchRequest` — type, amount, prices.
- `Admin\VerificationRequest` — the "KTP checked" checkbox.
- `Auth\RegisterRequest` — NIK, name, phone, email, password, unique checks.

`HasPaymentProofRule` defines the image rule once (required, image, jpg/jpeg/png/webp, max 2048 KB) so every upload form agrees with `PaymentProofUploader`.

---

## 9. Middleware, error handling and the JSON envelope

**Middleware** (aliases registered in `bootstrap/app.php`):

- `admin` → `EnsureUserIsAdmin`: anyone who is not admin gets 403.
- `approved` → `EnsureUserIsApproved`: pending/rejected users are redirected to `/status`.

**JSON API.** Preview endpoints live under `/api/...`. Because the path starts with `api/`, the exception handlers in `bootstrap/app.php` answer in one envelope:

```json
{
    "success": false,
    "message": "Validation failed",
    "data": null,
    "errors": { "...": ["..."] }
}
```

Handled cases: validation (422), not found (404), upload failure (502), any other error (500, with the real message only when `APP_DEBUG=true`). Web requests keep the normal Laravel behaviour and the custom 404/500 pages. The envelope is built by the `ApiResponse` trait; `public/js/api.js` (`apiRequest`, `showAlert`) is the browser side.

---

## 10. Views and the front end

- **Layouts**: `components/layouts/app.blade.php` (starter layout), `layouts/app.blade.php` + `layouts/navigation.blade.php` (Breeze layout used by `<x-page>`), `layouts/guest.blade.php` (login/register).
- **`<x-page title="...">`** wraps a page with the header and an optional `actions` slot (the top-right button).
- **Navigation** switches by role: admin sees Dashboard / Verifikasi / Garasi / Perpanjangan / Laporan; approved penyewa sees Sewa Saya / Kendaraan Saya; pending users see Status.
- **Logo**: `components/application-logo.blade.php` is an inline SVG garage icon coloured through `fill-current`.
- **Pagination**: list pages call `$items->links()`. Laravel's default Tailwind pagination view is picked up because `tailwind.config.js` scans `vendor/laravel/framework/src/Illuminate/Pagination/resources/views`. Every list pages by 10 rows.
- **Interactivity**: Alpine.js (`x-data`, `x-show`) for small things such as the report form; `fetch` through `api.js` for live price previews and the extension confirmation popup.
- **Formatting helpers**: `Rupiah::format(112500)` → `Rp 112.500`; `DateFormatter::format($date)` → `21 Nov 2026` (Indonesian month names; timezone `Asia/Jakarta` in `config/app.php`).

Vite builds `resources/css/app.css` and `resources/js/app.js` into `public/build`. Run `npm run build` after editing CSS, or `npm run dev` while developing.

---

## 11. The scheduler and `is_used`

`garages.is_used` is a deliberate shortcut: a cached "occupied today" flag that the assignment asks for. The `rentals` table remains the truth.

- `GarageStatusService::refresh($garage)` sets the flag from "does any rental cover today?". It is called inside the transaction that saves a rental or extension.
- `refreshAll()` resets every garage and then flags the ones with a rental covering today, in one transaction.
- `php artisan garages:refresh-status` runs `refreshAll()`. It is scheduled in `routes/console.php`: `Schedule::command('garages:refresh-status')->dailyAt('00:00')->timezone('Asia/Jakarta')`.
- Locally the scheduler only runs while `php artisan schedule:work` is open.

Why a nightly job? A rental booked in advance becomes "used" when its start date arrives, and a finished rental frees its garage at midnight, with no one saving anything. Availability checks ignore the flag, so a stale flag can never cause a double booking.

---

## 12. Tests

Run everything with `php artisan test`. Layout:

- `tests/Unit` — calculators, enums, models and every service method.
- `tests/Feature` — HTTP flows: registration, verification, vehicles, rentals, charging, extensions, reports, access control, pagination and the welcome page.
- `tests/Concerns/MocksPaymentProofUploader.php` — replaces Cloudinary with a fake so tests never hit the network.

Conventions: `RefreshDatabase` gives each test a clean SQLite database; factories (`UserFactory::admin()`, `RentalFactory::between()`, `extensionOf()`) build data; `travelTo()` freezes "today" so date rules are deterministic.

---

## 13. Worked example: renting an electric-car garage

Budi has a Hyundai Ioniq 5 (60 kWh). Today is 21 Nov 2026. He rents garage E001 (Rp 50.000/day, Rp 2.500/kWh), monthly, with 15 kWh in the battery.

1. He picks the vehicle; `RentalPreviewController::garageTypes` offers only _Mobil Listrik_ and _Mobil_ garages, and `availableGarages` lists free ones with their own prices.
2. `RentalCalculator`: end date = 21 Nov + 30 days = **21 Dec**; rent = 25 × 50.000 = **Rp 1.250.000**.
3. `ChargingCalculator`: needed = 60 − 15 = 45 kWh; cost = 45 × 2.500 = **Rp 112.500**.
4. Total shown before saving: **Rp 1.362.500**.
5. On save, one transaction creates the rental, the first charging row (same proof URL) and refreshes E001 to _used_.
6. Later, on My Rentals, Budi taps **Bayar Pengecasan** to add another session; it is billed at the active segment's garage price.
7. On 20 Dec the admin extends it. If E001 is booked from 21 Dec, the system picks the cheapest free electric garage, warns the admin in a popup, and Budi sees "Mulai 21 Des 2026 slot Anda E003".

---

## 14. Where do I change…?

| I want to change                          | Go to                                                                      |
| ----------------------------------------- | -------------------------------------------------------------------------- |
| The monthly multiplier (25) or day limits | `Services/Calculators/RentalCalculator.php`                                |
| How charging is priced                    | `Services/Calculators/ChargingCalculator.php`                              |
| Which vehicles fit which garage           | `Enums/GarageType::allowsVehicle()`                                        |
| Extension slot-conflict logic             | `Services/ExtensionService.php` (`resolveGarage`, `pickReplacementGarage`) |
| A validation rule                         | the matching class in `Http/Requests`                                      |
| Rows per page                             | the `paginate(10)` call in the controller (or the `$perPage` argument)     |
| Who can open a page                       | `routes/web.php` middleware groups                                         |
| The nightly refresh time                  | `routes/console.php`                                                       |
| The logo                                  | `resources/views/components/application-logo.blade.php`                    |
| The welcome page                          | `resources/views/welcome.blade.php`                                        |
| App name                                  | `APP_NAME` in `.env`                                                       |

---

## 15. Glossary

- **Penyewa** — renter. **Garasi** — garage. **Sewa** — rental. **Perpanjangan** — extension.
- **Harian / Bulanan** — daily / monthly rental.
- **Segment** — one row of a rental chain (the original or one extension).
- **Chain** — an original rental plus all its extensions.
- **[start, end)** — start date is occupied, end date is free again.
- **kWh** — kilowatt-hour, the unit of charging.
- **WIB** — Western Indonesia Time, `Asia/Jakarta`.
- **3NF** — third normal form: no column that can be derived from other columns.
- **Envelope** — the fixed JSON shape `{ success, message, data, errors }`.
