# Garage Rental App — Class Diagram (v2.0, 22 Nov 2026)

Paste **only the code block below** (from `classDiagram` to the last line) into https://mermaid.live, or view this file in any Markdown viewer with Mermaid support. If text outside the block is pasted as well, Mermaid draws stray boxes or fails to parse.

This diagram is written from the code in `app/`, so class, attribute and operation names match the source.

## How to read it

- **Anatomy.** Attributes are `visibility name: Type`. Operations are `visibility operation(parameter: Type): ReturnType`. A constant shows its value, for example `-MIN_CURRENT_KWH: int = 0`.
- **Visibility** matches the code: `+` public, `-` private, `#` protected. A private constant is shown with `-`.
- **Classes, not objects.** No sample data. The only values shown are constants.
- **Not a table list.** Models show business attributes, behaviour and associations. Database ids, foreign-key columns and timestamps are in `docs/erd.md`.
- **Derived values** have a slash: `/kwhNeeded` and `/cost` are derived from other data and are not columns (3NF, see CLAUDE.md). They are protected `Attribute` methods in the code, read like public properties.
- **Business constraints** (no overlapping rentals, 999 garages per type, and so on) are in yellow notes, separate from multiplicity.
- **Six relationship types:**

| Symbol | Type | Used for |
|---|---|---|
| `--\|>` | Generalization | A subclass "is a" its parent and only adds |
| `..\|>` | Realization | A class realizes a trait (PHP has no interface of our own) |
| `*--` | Composition | The part cannot exist without the whole |
| `o--` | Aggregation | The whole has parts that keep their own life |
| `-->` | Association | A class holds a reference to another and uses it over time |
| `..>` | Dependency | A class only uses another briefly (parameter, creates, throws, reads a constant) |

- Multiplicity is written at both ends of every association, composition and aggregation.

**Left out on purpose** (framework boilerplate, as the module allows): `$fillable`, `$hidden`, `casts()`, constructors (constructor injection is drawn as an association), Eloquent relationship methods such as `vehicles()` (drawn as the association line with its role name), database ids, timestamps and foreign-key columns.

```mermaid
classDiagram
    direction TB

    %% ============================================================
    %% FRAMEWORK CLASSES (parents we extend)
    %% ============================================================
    class Model {
        <<framework: Eloquent>>
    }
    class Authenticatable {
        <<framework: Laravel>>
    }
    class FormRequest {
        <<framework: Laravel>>
        +authorize(): bool
        +rules(): array
    }
    class RuntimeException {
        <<framework: PHP>>
    }

    %% ============================================================
    %% TRAITS
    %% ============================================================
    namespace Traits {
        class HasValues {
            <<trait>>
            +values()$: array
        }
        class HasPaymentProofRule {
            <<trait>>
            #paymentProofRules(): array
        }
        class ApiResponse {
            <<trait>>
            #successResponse(data: mixed, message: string, status: int): JsonResponse
            #createdResponse(data: mixed, message: string): JsonResponse
            #notFoundResponse(resource: string): JsonResponse
            #badRequestResponse(message: string): JsonResponse
            #serverErrorResponse(message: string): JsonResponse
            +errorResponse(message: string, status: int, errors: mixed)$: JsonResponse
        }
    }

    %% ============================================================
    %% ENUMS
    %% ============================================================
    namespace Enums {
        class UserRole {
            <<enumeration>>
            Admin
            Penyewa
        }
        class UserStatus {
            <<enumeration>>
            Pending
            Approved
            Rejected
        }
        class VehicleType {
            <<enumeration>>
            Sepeda
            SepedaMotor
            Mobil
            MobilListrik
            +requiresPlate(): bool
            +requiresBattery(): bool
            +label(): string
        }
        class GarageType {
            <<enumeration>>
            Sepeda
            Mobil
            MobilListrik
            +codePrefix(): string
            +allowedVehicleTypes(): array~VehicleType~
            +allowsVehicle(vehicleType: VehicleType): bool
            +hasCharging(): bool
            +label(): string
        }
        class RentalType {
            <<enumeration>>
            Harian
            Bulanan
            +label(): string
        }
        class ReportView {
            <<enumeration>>
            Monthly
            Daily
            GarageType
            Penyewa
        }
    }

    %% ============================================================
    %% MODELS (behaviour and associations, not database columns)
    %% ============================================================
    namespace Models {
        class User {
            +name: string
            +nik: string
            +phone: string
            +email: string
            +password: string
            +role: UserRole
            +status: UserStatus
            +ktp_verified_at: ?datetime
            +findOwnRental(rentalId: int): Rental
            +scopePenyewa(query: Builder): Builder
            +isAdmin(): bool
            +isApproved(): bool
        }
        class Vehicle {
            +type: VehicleType
            +plate_number: ?string
            +brand_model: string
            +max_battery_kwh: ?float
        }
        class Garage {
            +MAX_GARAGES_PER_TYPE: int = 999
            +CODE_DIGITS: int = 3
            +code: string
            +type: GarageType
            +daily_price: int
            +charging_price_per_kwh: ?int
            +is_used: bool
            +scopeOfType(query: Builder, garageType: GarageType): Builder
            +scopeFreeBetween(query: Builder, startDate: CarbonInterface, endDate: CarbonInterface): Builder
        }
        class Rental {
            +rental_type: RentalType
            +start_date: date
            +end_date: date
            +payment_proof_url: string
            +numberOfDays(): int
            +isExtension(): bool
            +setAttribute(key: string, value: mixed): mixed
            +scopeOriginals(query: Builder): Builder
            +scopeCoveringDate(query: Builder, date: CarbonInterface): Builder
            +scopeOverlapping(query: Builder, startDate: CarbonInterface, endDate: CarbonInterface): Builder
            +scopeInChainOf(query: Builder, originalRental: Rental): Builder
        }
        class ChargingTransaction {
            +current_kwh: float
            +payment_proof_url: string
            +/kwhNeeded: float
            +/cost: int
        }
    }

    %% ============================================================
    %% SERVICES (business logic)
    %% ============================================================
    namespace Services {
        class RentalService {
            +previewRental(vehicle: Vehicle, garage: Garage, rentalData: array): array
            +createRental(penyewa: User, rentalData: array, paymentProofUrl: string): Rental
            -ensureBookable(vehicle: Vehicle, garage: Garage, startDate: CarbonImmutable, endDate: CarbonImmutable): void
        }
        class ChargingService {
            +previewCharging(vehicle: Vehicle, garage: Garage, currentKwh: float): array
            +addSession(originalRental: Rental, currentKwh: float, paymentProofUrl: string): ChargingTransaction
            +recordSession(segment: Rental, currentKwh: float, paymentProofUrl: string): ChargingTransaction
            +findActiveSegment(originalRental: Rental): ?Rental
            +costOf(chargingTransaction: ChargingTransaction): int
        }
        class ExtensionService {
            +activeRentalsToday(): Collection
            +paginateActiveRentalsToday(perPage: int): LengthAwarePaginator
            +currentSegment(originalRental: Rental): Rental
            +previewExtension(originalRental: Rental, rentalType: RentalType, requestedDays: ?int): array
            +extend(originalRental: Rental, extensionData: array, paymentProofUrl: string): Rental
            +pickReplacementGarage(freeGarages: Collection): ?Garage
            -activeRentalsTodayQuery(): Builder
            -resolveGarage(currentGarage: Garage, startDate: CarbonImmutable, endDate: CarbonImmutable): Garage
            -ensureConfirmedGarage(preview: array, confirmedGarageId: int): Garage
            -ensureActiveToday(originalRental: Rental): void
        }
        class AvailabilityService {
            +findFreeGarages(garageType: GarageType, startDate: CarbonImmutable, endDate: CarbonImmutable): Collection
            +isGarageFree(garage: Garage, startDate: CarbonImmutable, endDate: CarbonImmutable): bool
            +isVehicleFree(vehicle: Vehicle, startDate: CarbonImmutable, endDate: CarbonImmutable): bool
        }
        class GarageBatchService {
            +createBatch(garageType: GarageType, amount: int, dailyPrice: int, chargingPricePerKwh: ?int): Collection
        }
        class GarageStatusService {
            +refresh(garage: Garage): void
            +refreshAll(): void
        }
        class VerificationService {
            +approve(penyewa: User): void
            +reject(penyewa: User): void
            -ensurePending(penyewa: User): void
        }
        class ReportService {
            +revenuePerRental(): Collection
            +revenueOf(originalRental: Rental): int
            +rentOf(segment: Rental): int
            +totalRevenue(): int
            +totalPenyewa(): int
            +revenuePerMonth(year: int): Collection
            +revenuePerDay(year: int, month: int): Collection
            +revenuePerGarageType(): Collection
            +revenuePerPenyewa(): Collection
            -chargingRevenueOf(segment: Rental): int
            -summarise(rows: Collection, groupKey: callable): Collection
        }
        class PaymentProofUploader {
            -FOLDER: string = garage-rental/payment-proofs
            +MAX_PROOF_SIZE_KB: int = 2048
            +ALLOWED_MIMES: array = jpg, jpeg, png, webp
            +upload(paymentProof: UploadedFile): string
            -api(): UploadApi
        }
        class RentalCalculator {
            +MONTHLY_PRICE_MULTIPLIER: int = 25
            -MIN_DAILY_DAYS: int = 1
            +maxDailyDays(startDate: CarbonInterface): int
            +isValidDailyLength(startDate: CarbonInterface, numberOfDays: int): bool
            +calculateEndDate(startDate: CarbonImmutable, rentalType: RentalType, numberOfDays: ?int): CarbonImmutable
            +calculateRent(rentalType: RentalType, dailyPrice: int, numberOfDays: int): int
            +isOverlapping(existingStart: CarbonInterface, existingEnd: CarbonInterface, newStart: CarbonInterface, newEnd: CarbonInterface): bool
        }
        class ChargingCalculator {
            -MIN_CURRENT_KWH: int = 0
            +isValidCurrentCharge(currentKwh: float, maxCapacityKwh: float): bool
            +calculateKwhNeeded(maxCapacityKwh: float, currentKwh: float): float
            +calculateCost(kwhNeeded: float, chargingPricePerKwh: int): int
        }
        class GarageCodeGenerator {
            +remainingCapacity(existingCount: int): int
            +generateCodes(garageType: GarageType, existingCount: int, amount: int): array~string~
        }
    }

    class PaymentProofUploadException {
        +USER_MESSAGE: string = Gagal mengunggah bukti pembayaran, coba lagi
        +construct(message: string, previous: ?Throwable)
    }
    class CloudinaryUploadApi {
        <<external SDK>>
        +upload(file: string, options: array): ApiResponse
    }

    %% ============================================================
    %% SUPPORT (formatting helpers)
    %% ============================================================
    namespace Support {
        class Rupiah {
            -PREFIX: string = Rp
            +format(amount: int)$: string
        }
        class DateFormatter {
            -DISPLAY_FORMAT: string = d M Y
            +format(date: CarbonInterface)$: string
        }
    }

    %% ============================================================
    %% FORM REQUESTS (validation)
    %% ============================================================
    namespace Requests {
        class RegisterRequest {
            +rules(): array
        }
        class LoginRequest {
            +authorize(): bool
            +rules(): array
            +authenticate(): void
            +ensureIsNotRateLimited(): void
            +throttleKey(): string
        }
        class ProfileUpdateRequest {
            +rules(): array
        }
        class VehicleRequest {
            -MAX_PLATE_LENGTH: int = 15
            +authorize(): bool
            #prepareForValidation(): void
            +rules(): array
        }
        class AvailableGaragesRequest {
            +authorize(): bool
            +rules(): array
        }
        class RentalPreviewRequest {
            +authorize(): bool
            +rules(): array
        }
        class RentalRequest {
            +rules(): array
        }
        class ChargingPreviewRequest {
            +authorize(): bool
            +rules(): array
        }
        class ChargingRequest {
            #prepareForValidation(): void
            +rules(): array
        }
        class ExtensionPreviewRequest {
            +authorize(): bool
            +rules(): array
        }
        class ExtensionRequest {
            #prepareForValidation(): void
            +rules(): array
        }
        class GarageBatchRequest {
            +authorize(): bool
            #prepareForValidation(): void
            +rules(): array
        }
        class VerificationRequest {
            +authorize(): bool
            +rules(): array
        }
        class ReportRequest {
            +authorize(): bool
            +rules(): array
        }
    }

    %% ============================================================
    %% MIDDLEWARE
    %% ============================================================
    namespace Middleware {
        class EnsureUserIsAdmin {
            +handle(request: Request, next: Closure): Response
        }
        class EnsureUserIsApproved {
            +handle(request: Request, next: Closure): Response
        }
    }

    %% ============================================================
    %% CONTROLLERS
    %% ============================================================
    namespace Controllers {
        class Controller {
            <<abstract>>
        }
        class HomeRedirectController {
            +invoke(request: Request): RedirectResponse
        }
        class VehicleController {
            +index(request: Request): View
            +create(): View
            +store(request: VehicleRequest): RedirectResponse
        }
        class RentalController {
            +index(request: Request): View
            +create(request: Request): View
            +store(request: RentalRequest): RedirectResponse
        }
        class RentalPreviewController {
            +garageTypes(request: Request): JsonResponse
            +availableGarages(request: AvailableGaragesRequest): JsonResponse
            +rentalPreview(request: RentalPreviewRequest): JsonResponse
            +chargingPreview(request: ChargingPreviewRequest): JsonResponse
        }
        class ChargingTransactionController {
            +create(request: Request, rentalId: int): View
            +store(request: ChargingRequest, rentalId: int): RedirectResponse
        }
        class ProfileController {
            +edit(request: Request): View
            +update(request: ProfileUpdateRequest): RedirectResponse
        }
        class AuthControllers {
            <<Breeze: login, register, password reset, email verification>>
        }
        class AdminDashboardController {
            +invoke(reportService: ReportService): View
        }
        class VerificationController {
            +index(): View
            +approve(request: VerificationRequest, user: User): RedirectResponse
            +reject(user: User): RedirectResponse
        }
        class GarageController {
            +index(): View
            +create(): View
            +store(request: GarageBatchRequest): RedirectResponse
        }
        class ExtensionController {
            +index(): View
            +create(rental: Rental): View
            +preview(request: ExtensionPreviewRequest): JsonResponse
            +store(request: ExtensionRequest, rental: Rental): RedirectResponse
        }
        class ReportController {
            +invoke(request: ReportRequest, reportService: ReportService): View
            -paginate(rows: Collection, request: Request, perPage: int): LengthAwarePaginator
        }
    }

    %% ============================================================
    %% CONSOLE
    %% ============================================================
    class RefreshGarageStatusCommand {
        <<artisan command>>
    }

    %% ============================================================
    %% BUSINESS CONSTRAINTS (separate from multiplicity)
    %% ============================================================
    note for Rental "Constraints\nNo two rentals of the same garage overlap in [start_date, end_date)\nNo two rentals of the same vehicle overlap\nend_date is exclusive: the garage is free again on it\nAn extension points to the original rental, never to the previous extension\nDaily rental: 1 to (days in start month - 1) days. Monthly: days in start month"
    note for Garage "Constraints\nAt most 999 garages per type, code = prefix + 3 digits\nis_used is written only by GarageStatusService.refresh()\nAvailability always queries rentals, never is_used\nPrices are set per batch and never edited"
    note for Vehicle "Constraints\nplate_number is unique, except for sepeda\nmax_battery_kwh is required only for mobil listrik"
    note for ChargingTransaction "Constraints\nOnly for a mobil listrik in an E garage\n0 <= current_kwh < the vehicle max_battery_kwh, charged to full\nBelongs to the segment that covers the session date"
    note for User "Constraints\nnik and email are unique\nA rejected NIK cannot sign up again\nOnly an approved penyewa can rent"
    note for RefreshGarageStatusCommand "Signature garages:refresh-status, scheduled daily at 00:00 Asia/Jakarta"

    %% ============================================================
    %% GENERALIZATION (is a, the child only adds)
    %% ============================================================
    Authenticatable --|> Model
    User --|> Authenticatable
    Vehicle --|> Model
    Garage --|> Model
    Rental --|> Model
    ChargingTransaction --|> Model
    PaymentProofUploadException --|> RuntimeException

    RegisterRequest --|> FormRequest
    LoginRequest --|> FormRequest
    ProfileUpdateRequest --|> FormRequest
    VehicleRequest --|> FormRequest
    AvailableGaragesRequest --|> FormRequest
    RentalPreviewRequest --|> FormRequest
    ChargingPreviewRequest --|> FormRequest
    ExtensionPreviewRequest --|> FormRequest
    GarageBatchRequest --|> FormRequest
    VerificationRequest --|> FormRequest
    ReportRequest --|> FormRequest
    RentalRequest --|> RentalPreviewRequest : plus payment_proof
    ChargingRequest --|> ChargingPreviewRequest : plus payment_proof
    ExtensionRequest --|> ExtensionPreviewRequest : plus garage_id and payment_proof

    HomeRedirectController --|> Controller
    VehicleController --|> Controller
    RentalController --|> Controller
    RentalPreviewController --|> Controller
    ChargingTransactionController --|> Controller
    ProfileController --|> Controller
    AdminDashboardController --|> Controller
    VerificationController --|> Controller
    GarageController --|> Controller
    ExtensionController --|> Controller
    ReportController --|> Controller
    AuthControllers --|> Controller

    %% ============================================================
    %% REALIZATION (a class realizes a trait)
    %% ============================================================
    UserRole ..|> HasValues
    UserStatus ..|> HasValues
    VehicleType ..|> HasValues
    GarageType ..|> HasValues
    RentalType ..|> HasValues
    ReportView ..|> HasValues
    Controller ..|> ApiResponse
    RentalRequest ..|> HasPaymentProofRule
    ChargingRequest ..|> HasPaymentProofRule
    ExtensionRequest ..|> HasPaymentProofRule

    %% ============================================================
    %% COMPOSITION AND AGGREGATION (ownership and lifecycle)
    %% ============================================================
    User "1" o-- "0..*" Vehicle : owns
    Rental "0..1" *-- "0..*" Rental : extensions
    Rental "1" *-- "0..*" ChargingTransaction : charging sessions

    %% ============================================================
    %% ASSOCIATION: domain
    %% ============================================================
    User "1" --> "0..*" Rental : books
    Vehicle "1" --> "0..*" Rental : is parked in
    Garage "1" --> "0..*" Rental : hosts

    %% ============================================================
    %% ASSOCIATION: collaborators held by a class
    %% ============================================================
    RentalService "1" --> "1" RentalCalculator
    RentalService "1" --> "1" AvailabilityService
    RentalService "1" --> "1" ChargingService
    RentalService "1" --> "1" GarageStatusService
    ExtensionService "1" --> "1" RentalCalculator
    ExtensionService "1" --> "1" AvailabilityService
    ExtensionService "1" --> "1" GarageStatusService
    ChargingService "1" --> "1" ChargingCalculator
    ReportService "1" --> "1" RentalCalculator
    ReportService "1" --> "1" ChargingService
    GarageBatchService "1" --> "1" GarageCodeGenerator
    PaymentProofUploader "1" --> "1" CloudinaryUploadApi : uploads through

    RentalController "1" --> "1" RentalService
    RentalController "1" --> "1" PaymentProofUploader
    RentalPreviewController "1" --> "1" AvailabilityService
    RentalPreviewController "1" --> "1" RentalService
    RentalPreviewController "1" --> "1" RentalCalculator
    RentalPreviewController "1" --> "1" ChargingService
    ChargingTransactionController "1" --> "1" ChargingService
    ChargingTransactionController "1" --> "1" PaymentProofUploader
    VerificationController "1" --> "1" VerificationService
    GarageController "1" --> "1" GarageBatchService
    ExtensionController "1" --> "1" ExtensionService
    ExtensionController "1" --> "1" PaymentProofUploader

    %% ============================================================
    %% DEPENDENCY (brief use: parameter, creates, throws, reads a constant)
    %% ============================================================
    User ..> UserRole
    User ..> UserStatus
    Vehicle ..> VehicleType
    Garage ..> GarageType
    Rental ..> RentalType
    GarageType ..> VehicleType : allowed vehicles
    HasPaymentProofRule ..> PaymentProofUploader : size and type constants
    GarageBatchRequest ..> Garage : max per type
    GarageCodeGenerator ..> Garage : max per type and code digits

    RentalService ..> Rental : creates
    ExtensionService ..> Rental : creates extension
    ChargingService ..> ChargingTransaction : creates
    GarageBatchService ..> Garage : creates
    AvailabilityService ..> Garage : freeBetween scope
    AvailabilityService ..> Rental : overlapping scope
    GarageStatusService ..> Garage : writes is_used
    VerificationService ..> User : writes status
    PaymentProofUploader ..> PaymentProofUploadException : throws
    RentalCalculator ..> RentalType
    ChargingTransactionController ..> GarageType : hasCharging guard

    VehicleController ..> VehicleRequest
    VehicleController ..> Vehicle : creates through user
    RentalController ..> RentalRequest
    RentalPreviewController ..> AvailableGaragesRequest
    RentalPreviewController ..> RentalPreviewRequest
    RentalPreviewController ..> ChargingPreviewRequest
    ChargingTransactionController ..> ChargingRequest
    ProfileController ..> ProfileUpdateRequest
    AuthControllers ..> RegisterRequest
    AuthControllers ..> LoginRequest
    VerificationController ..> VerificationRequest
    GarageController ..> GarageBatchRequest
    ExtensionController ..> ExtensionRequest
    ExtensionController ..> ExtensionPreviewRequest
    ReportController ..> ReportRequest
    ReportController ..> ReportView
    AdminDashboardController ..> ReportService : parameter
    ReportController ..> ReportService : parameter
    RentalPreviewController ..> Rupiah
    RentalPreviewController ..> DateFormatter
    ExtensionController ..> Rupiah
    ExtensionController ..> DateFormatter

    EnsureUserIsAdmin ..> User : isAdmin()
    EnsureUserIsApproved ..> User : isApproved()
    RefreshGarageStatusCommand ..> GarageStatusService : refreshAll()
```

## Why each relationship is drawn that way

The rule for every choice is the same: ask who owns whom and what happens when the whole ends.

| Relationship | Type | Rule that decides it |
|---|---|---|
| Rental to its extensions | Composition | An extension has no meaning without its original rental, so when the whole ends the parts end with it. Multiplicity `0..1` on the whole end because an original rental has no parent |
| Rental to its charging sessions | Composition | A charging session only exists as part of one rental segment and is priced from that segment's garage |
| User to Vehicle | Aggregation | A user has vehicles, but a vehicle keeps its own identity and rental history. In v1 nothing is deleted because deleting would erase revenue |
| User, Vehicle, Garage to Rental | Association | Each is a separate thing with its own life. A rental points to them but owns none of them |
| Service to calculator or other service | Association | The class holds a reference given by the container and uses it over time. Neither owns the other, and there is no whole and part, so it is not an aggregation |
| Controller to service or uploader | Association | Same rule: held through the constructor and used in every action |
| Controller to FormRequest, Rupiah, DateFormatter | Dependency | Used only briefly, as a method parameter or a helper call |
| Service creates Rental, ChargingTransaction, Garage | Dependency | The service creates it inside one method and does not keep it |
| Uploader throws exception | Dependency | The exception exists only for the moment of the failure |
| Enum, trait use | Realization or dependency | An enum realizes the shared `HasValues` behaviour. A class that only uses an enum as a type depends on it |
| Subclass to parent | Generalization | "Is a" test: a save request is a preview request plus a payment proof. The child only adds |

## Layer summary

| Layer | Classes | Rule |
|---|---|---|
| Models | `User`, `Vehicle`, `Garage`, `Rental`, `ChargingTransaction` | 5 tables. Derived values are operations or derived attributes, never columns |
| Enums | `UserRole`, `UserStatus`, `VehicleType`, `GarageType`, `RentalType`, `ReportView` | One source for allowed values, shared by migrations (`values()`) and validation (`Rule::enum`) |
| Services | `RentalService`, `ChargingService`, `ExtensionService`, `AvailabilityService`, `GarageBatchService`, `GarageStatusService`, `VerificationService`, `ReportService`, `PaymentProofUploader` | All business rules. `garages.is_used` is written only by `GarageStatusService` |
| Calculators and generator | `RentalCalculator`, `ChargingCalculator`, `GarageCodeGenerator` | Pure functions: no database, so they are unit-tested on their own |
| Form requests | 14 classes | Validation only. Each save request extends its preview request and only adds fields |
| Controllers | 11 classes plus Breeze auth | Validate, upload the proof, call services. A controller may run a simple guard or a read-only preview, but no business calculation |
| Middleware | `EnsureUserIsAdmin`, `EnsureUserIsApproved` | Aliases `admin` and `approved` in `bootstrap/app.php` |

## Traceability: requirement to class and operation to test

Requirement sections are from `docs/requirements.md`. Test names starting with TC are the test case numbers used in the feature tests.

| Requirement | Class and operation | Tests |
|---|---|---|
| §2.1 Vehicle fits garage type | `GarageType::allowsVehicle`, `allowedVehicleTypes` | `Unit/GarageTypeTest` |
| §2.2 Garage batch, codes, max 999 | `GarageBatchService::createBatch`, `GarageCodeGenerator::generateCodes`, `remainingCapacity`, `Garage::MAX_GARAGES_PER_TYPE` | `Unit/Services/GarageBatchServiceTest`, `Unit/GarageCodeGeneratorTest`, `Unit/GarageTest`, `Feature/GarageBatchTest` |
| §2.3 Vehicles | `VehicleRequest::rules`, `VehicleController::store` | `Feature/VehicleTest`, `Unit/VehicleTest` |
| §3 Registration and verification | `RegisterRequest::rules`, `VerificationService::approve`, `reject` | `Feature/RegistrationTest`, `Feature/VerificationTest`, `Unit/Services/VerificationServiceTest` |
| §4 Days-in-month rule, end date, rent | `RentalCalculator::maxDailyDays`, `isValidDailyLength`, `calculateEndDate`, `calculateRent` | `Unit/RentalCalculatorTest` |
| §4 Overlap and availability | `RentalCalculator::isOverlapping`, `AvailabilityService::findFreeGarages`, `isGarageFree`, `isVehicleFree`, `Garage::scopeFreeBetween`, `Rental::scopeOverlapping` | `Unit/RentalCalculatorTest`, `Unit/Services/AvailabilityServiceTest`, `Feature/RentalTest` (TC-21, TC-22) |
| §4 New rental with proof | `RentalService::previewRental`, `createRental`, `PaymentProofUploader::upload`, `RentalController::store` | `Unit/Services/RentalServiceTest`, `Unit/Services/PaymentProofUploaderTest`, `Feature/RentalTest` (TC-23, TC-24, TC-25) |
| §4 Used status | `GarageStatusService::refresh`, `refreshAll`, `RefreshGarageStatusCommand` | `Unit/Services/GarageStatusServiceTest`, `Feature/RefreshGarageStatusCommandTest`, `Feature/RentalTest` (TC-11-01) |
| §5 Extension and slot conflict | `ExtensionService::activeRentalsToday`, `previewExtension`, `extend`, `pickReplacementGarage` | `Unit/Services/ExtensionServiceTest`, `Unit/PickReplacementGarageTest`, `Feature/ExtensionTest` (TC-27 to TC-33) |
| §6 Charging | `ChargingCalculator::*`, `ChargingService::addSession`, `previewCharging`, `ChargingTransaction::/cost` | `Unit/ChargingCalculatorTest`, `Unit/Services/ChargingServiceTest`, `Unit/ChargingTransactionTest`, `Feature/ChargingTest` (TC-34, TC-35) |
| §8 Reports | `ReportService::revenuePerMonth`, `revenuePerDay`, `revenuePerGarageType`, `revenuePerPenyewa`, `totalRevenue`, `totalPenyewa` | `Unit/Services/ReportServiceTest`, `Feature/ReportTest` |
| §1 Roles and access | `EnsureUserIsAdmin::handle`, `EnsureUserIsApproved::handle` | `Feature/AccessTest` |

Every public service, calculator and generator operation above has at least one test, as CLAUDE.md requires.

## Notes for the report

- `Rental` is one class for both an original rental and an extension. An extension has its parent set to the original rental, never to the previous extension.
- A charging session belongs to the rental segment (original or extension) that covers its date, so its cost uses that segment's garage price.
- The payment proof is uploaded by the controller (`PaymentProofUploader`) before the service opens the database transaction. Only the returned URL is stored.
- `CloudinaryUploadApi` stands for `Cloudinary\Api\Upload\UploadApi` from the official SDK.
- `AuthControllers` is one box for the Breeze controllers (login, register, password reset, email verification).
- `__construct` and `__invoke` are written as `construct` and `invoke` in the diagram because Mermaid treats double underscores as formatting.
- No sequence diagram exists yet. If one is added, every message must be an operation listed on the receiving class here.
