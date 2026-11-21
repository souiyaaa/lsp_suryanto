# Garage Rental App — Locked Requirements (v1.2, FINAL)

Source: official BNSP assignment PDF (FR.IA.04A, 21 Nov 2026) + NB notes + clarification sessions with Surya (21 Nov 2026).
Where this doc and the PDF body differ, this doc wins; every deviation is logged in §9 so it can be justified in the report.

Changelog
- v1.1 (11:06): no price master. Price is entered per garage-creation batch and stored on each garage. No price editing in v1.
- v1.2 (11:11): extension slot conflict auto-assigns the cheapest free garage, and the extension uses the new garage's price. No charging speed/duration. No price snapshot on transactions; revenue is computed from the garage's price. All questions closed.

## 0. Stack & constraints
- Laravel + SQLite + Blade, PHPUnit, run on Herd. Monorepo.
- Images (payment proofs) → Cloudinary API; DB stores the URL only.
- Timezone: Asia/Jakarta. "Today" = server date in WIB.
- Deadline: finish today, at the latest tomorrow. Check-ins with the assessor: Sat 21 Nov 17:00 WIB, Sun 22 Nov 08:00 WIB. Demo: 10 minutes on a laptop.

## 1. Roles
- **Admin**: a single seeded account, no sign-up.
- **Penyewa**: signs up. Status is pending → approved / rejected. Pending or rejected users can log in but see only their status and cannot rent.

## 2. Master data
### 2.1 Garage types and vehicle compatibility
| Garage type | Code prefix | Allowed vehicles |
|---|---|---|
| Sepeda | S | sepeda, sepeda motor |
| Mobil (bensin) | C | mobil, mobil listrik (no charging) |
| Mobil Listrik | E | mobil listrik only (charging required) |

### 2.2 Garage creation (Admin, batch)
- A "batch" is one run of the **Buat Garasi** form. Inputs:
  - garage type
  - amount
  - **daily price** (harga sewa harian)
  - **charging price per kWh**, only when the type is Mobil Listrik
- The batch's price(s) are stored on **every garage created in that batch**. Different batches of the same type can have different prices.
- **There is no price master and no price editing in v1.** Garage prices never change.
- IDs are generated as prefix + 3-digit increment per type: S001…, C001…, E001…
- **Max 999 per type, cumulative.** The amount can't go over 999 − existing count for that type.

### 2.3 Vehicles (Penyewa → "My Vehicles" page)
- Fields: type (sepeda / sepeda motor / mobil / mobil listrik), plate number (required and **unique**, except for sepeda, which has none), brand & model, **max battery capacity in kWh** (mobil listrik only).
- A user can have many vehicles. A vehicle can't be in two active or overlapping rentals.

## 3. Registration & verification
- Sign-up fields: NIK, full name, phone, email (used as username), password. NIK and email are unique.
- Admin verification: the admin checks the KTP manually (a checkbox, with no KTP image) and then **approves or rejects** the user.
- A rejected NIK can't sign up again.

## 4. New rental (Penyewa)
- One rental = one garage = one vehicle. Renting several garages means several transactions.
- Inputs: vehicle (from My Vehicles), rental type (Harian / Bulanan), start date (**defaults to today, can be any future date, no limit**), number of days (daily only), garage type (filtered by vehicle), garage (picked from the available list, **showing each garage's own price**).
- **Days-in-month rule:** D = number of days in the **start date's month**.
  - Daily: 1 ≤ N ≤ D − 1. End = start + N. Rent = N × the chosen garage's daily price.
  - Monthly: exactly 1 month. End = start + D. Rent = 25 × the chosen garage's daily price.
  - Examples: 21 Nov monthly → 21 Dec. 10 Feb monthly → 10 Feb + 28. Nov-booked Dec start: daily max 30, monthly 31 days.
- **End date semantics:** the garage is occupied over [start, end), so it is free again **on** the end date.
- **Availability:** the list shows only garages of the chosen type with **no overlapping booking anywhere in [start, end)**. Overlap test: existing.start < new.end AND new.start < existing.end.
- **Payment proof:** the penyewa uploads an image of it. The system works out and shows the total before saving. There is no admin payment approval: the rental is active as soon as it's saved.
- **Status 'used'** is computed: a garage is *used* on a given day if a rental or extension covers that day. There's no check-out feature.
- If the garage is Mobil Listrik, the **first charging session is required** as part of the new rental (see §6).

## 5. Extension (Admin only)
- The list shows garages with an active rental **today**. The admin picks one and chooses Harian (N days) or Bulanan, and the new end date is filled in automatically.
- **Every extension follows the user rules** (§4) for its own segment: daily N ≤ D − 1, monthly = D days, where D is the days-in-month of the **segment start month** (= the current end date). The admin can extend again with no limit on how many times.
- Extend only, with no shortening. The admin cannot create new rentals.
- Fee = the same pricing as §4 (rent only, no charging), using the daily price of the garage the segment is on.
- **Slot conflict:** if the current garage is booked by someone else during the extension range:
  - The system **auto-assigns** another garage of the same type that is free for the whole range, picking the **cheapest daily price**. If several have the same price, it picks one at random.
  - The extension fee uses the **new garage's** daily price.
  - The admin sees a popup warning that the slot will change to <code> at <price>, and confirms or cancels.
  - The penyewa sees a notice on My Rentals ("from <date> your slot is <code>").
  - If no garage of that type is free for the whole range, the extension is **blocked**.
- **Payment proof for an extension is uploaded by the admin.**

## 6. Charging (Mobil Listrik in an E garage only)
- Charging is a **separate transaction** from the rental.
- The first session is required at rental creation. While the rental is active, the penyewa can add **more sessions** from My Rentals.
- Input for each session: the **current charge (kWh)**, which must be less than the vehicle's max capacity. The system always charges to 100%.
- Formulas:
  - kWh needed = max capacity − current charge
  - **Cost = kWh needed × the garage's charging price per kWh**
  - Example: 60 kWh max, 15 kWh current, Rp 2.500/kWh → 45 kWh, **Rp 112.500**.
- No charging speed or duration: the system only counts kWh.
- The penyewa uploads the payment proof for each charging session.
- A mobil listrik in a C garage has no charging fields.

## 7. Penyewa pages
- My Vehicles (create/list), New Rental, My Rentals (read-only: rentals, extensions, slot-change notices, charging sessions, plus a button to add a charging session on active E rentals).

## 8. Admin pages and reports (on screen only, no export)
- Dashboard: **total revenue**, **total penyewa**, and the **current status of every slot** (used/free today, by type).
- Garage list: read-only, with today's status and each garage's price.
- Reports ("hasil persewaan") show the accumulated number of rentals and revenue:
  - Per month in a selected year
  - Per day in a selected month
  - Per garage type (all-time total)
  - Per penyewa (all-time total)
- Revenue = rent + extensions + charging, all credited to the **rental's start date**.
- Prices and totals are **not stored on transactions**. Revenue is computed from the garage's price (which can't change in v1) × the stored days/kWh.
  - Known limitation: if price editing is added in a later version, this must change to storing the price on each transaction.

## 9. Deviations from the PDF body (justify in the report)
1. Max daily days: "≤ 30" → days in the start month − 1 (NB5).
2. Start date: "always today" → defaults to today, advance booking with no limit (NB4).
3. Charging: the user enters the current charge per session, and charging is a separate repeatable transaction (NB2 + client decision).
4. Extension may move to the cheapest free garage of the same type if the current one is booked (client decision).
5. Payment proof: user uploads it for new rentals and charging, admin for extensions (NB10).
6. Image storage on Cloudinary (NB8).

## 10. Pending confirmation
- None. Spec locked 21 Nov 2026, 11:11 WIB.

## 11. Deliverables
- Working web app + GitHub repo the assessor can access.
- Report: use-case diagram, class diagram, ERD, explanation of the libraries used, feature screenshots, unit test scenarios, GitHub link.
- 10-minute live demo.
