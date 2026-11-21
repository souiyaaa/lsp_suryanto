# Garage Rental App — Class Diagram (v1.0, 21 Nov 2026)

Paste **only the code block below** (from `classDiagram` to the last line) into https://mermaid.live, or view this file in any Markdown viewer with Mermaid support. If text outside the block is pasted as well, Mermaid draws stray boxes or fails to parse.

This diagram is generated from the code in `app/`, so the class and method names match the source. Method parameters are shortened where the full type adds nothing.

How to read it
- Namespaces group classes by layer: `Enums`, `Models`, `Services`, `Support`, `Requests`, `Controllers`, `Middleware`, `Traits`.
- `-->` is "uses or depends on", `--|>` is inheritance, `..|>` is "uses the trait", `"1" --> "*"` is a database relationship (see `docs/erd.md`).
- Derived values (number of days, rent, charging cost, kWh needed) are methods or accessors, never columns (3NF, see CLAUDE.md).
- Controllers never contain business rules: they validate through a FormRequest, upload the payment proof, and call one Service.

```mermaid
classDiagram
    direction TB

    %% ============================================================
    %% TRAITS
    %% ============================================================
    namespace Traits {
        class HasValues {
            <<trait>>
            +values() string[]$
        }
        class HasPaymentProofRule {
            <<trait>>
            #paymentProofRules() string[]
        }
        class ApiResponse {
            <<trait>>
            #successResponse(data, message, status) JsonResponse
            #createdResponse(data, message) JsonResponse
            #notFoundResponse(resource) JsonResponse
            #badRequestResponse(message) JsonResponse
            #serverErrorResponse(message) JsonResponse
            +errorResponse(message, status, errors) JsonResponse$
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
            +requiresPlate() bool
            +requiresBattery() bool
            +label() string
        }
        class GarageType {
            <<enumeration>>
            Sepeda
            Mobil
            MobilListrik
            +codePrefix() string
            +allowedVehicleTypes() VehicleType[]
            +allowsVehicle(VehicleType) bool
            +hasCharging() bool
            +label() string
        }
        class RentalType {
            <<enumeration>>
            Harian
            Bulanan
            +label() string
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
    %% MODELS (Eloquent, 5 tables)
    %% ============================================================
    namespace Models {
        class User {
            +bigint id
            +string name
            +string nik
            +string phone
            +string email
            +string password
            +UserRole role
            +UserStatus status
            +datetime ktp_verified_at
            +vehicles() HasMany
            +rentals() HasMany
            +findOwnRental(rentalId) Rental
            +scopePenyewa(query) Builder
            +isAdmin() bool
            +isApproved() bool
        }
        class Vehicle {
            +bigint id
            +bigint user_id
            +VehicleType type
            +string plate_number
            +string brand_model
            +decimal max_battery_kwh
            +user() BelongsTo
            +rentals() HasMany
        }
        class Garage {
            +bigint id
            +string code
            +GarageType type
            +int daily_price
            +int charging_price_per_kwh
            +bool is_used
            +MAX_GARAGES_PER_TYPE = 999
            +CODE_DIGITS = 3
            +rentals() HasMany
            +scopeOfType(query, GarageType) Builder
            +scopeFreeBetween(query, start, end) Builder
        }
        class Rental {
            +bigint id
            +bigint user_id
            +bigint vehicle_id
            +bigint garage_id
            +bigint parent_rental_id
            +RentalType rental_type
            +date start_date
            +date end_date
            +string payment_proof_url
            +user() BelongsTo
            +penyewa() BelongsTo
            +vehicle() BelongsTo
            +garage() BelongsTo
            +parentRental() BelongsTo
            +extensions() HasMany
            +chargingTransactions() HasMany
            +numberOfDays() int
            +isExtension() bool
            +scopeOriginals(query) Builder
            +scopeCoveringDate(query, date) Builder
            +scopeOverlapping(query, start, end) Builder
            +scopeInChainOf(query, originalRental) Builder
        }
        class ChargingTransaction {
            +bigint id
            +bigint rental_id
            +decimal current_kwh
            +string payment_proof_url
            +rental() BelongsTo
            +kwh_needed float
            +cost int
        }
    }

    %% ============================================================
    %% SERVICES (business logic)
    %% ============================================================
    namespace Services {
        class RentalService {
            +previewRental(Vehicle, Garage, rentalData) array
            +createRental(User, rentalData, paymentProofUrl) Rental
        }
        class ChargingService {
            +previewCharging(Vehicle, Garage, currentKwh) array
            +addSession(originalRental, currentKwh, paymentProofUrl) ChargingTransaction
            +recordSession(segment, currentKwh, paymentProofUrl) ChargingTransaction
            +findActiveSegment(originalRental) Rental
            +costOf(ChargingTransaction) int
        }
        class ExtensionService {
            +activeRentalsToday() Collection
            +currentSegment(originalRental) Rental
            +previewExtension(originalRental, RentalType, requestedDays) array
            +extend(originalRental, extensionData, paymentProofUrl) Rental
            +pickReplacementGarage(freeGarages) Garage
        }
        class AvailabilityService {
            +findFreeGarages(GarageType, start, end) Collection
            +isGarageFree(Garage, start, end) bool
            +isVehicleFree(Vehicle, start, end) bool
        }
        class GarageBatchService {
            +createBatch(GarageType, amount, dailyPrice, chargingPrice) Collection
        }
        class GarageStatusService {
            +refresh(Garage) void
            +refreshAll() void
        }
        class VerificationService {
            +approve(User) void
            +reject(User) void
        }
        class ReportService {
            +revenuePerRental() Collection
            +revenueOf(originalRental) int
            +rentOf(segment) int
            +totalRevenue() int
            +totalPenyewa() int
            +revenuePerMonth(year) Collection
            +revenuePerDay(year, month) Collection
            +revenuePerGarageType() Collection
            +revenuePerPenyewa() Collection
        }
        class PaymentProofUploader {
            -FOLDER = garage-rental/payment-proofs
            +MAX_PROOF_SIZE_KB = 2048
            +ALLOWED_MIMES = jpg, jpeg, png, webp
            +upload(UploadedFile) string
        }
        class RentalCalculator {
            +MONTHLY_PRICE_MULTIPLIER = 25
            +maxDailyDays(startDate) int
            +isValidDailyLength(startDate, numberOfDays) bool
            +calculateEndDate(startDate, RentalType, numberOfDays) CarbonImmutable
            +calculateRent(RentalType, dailyPrice, numberOfDays) int
            +isOverlapping(existingStart, existingEnd, newStart, newEnd) bool
        }
        class ChargingCalculator {
            +isValidCurrentCharge(currentKwh, maxCapacityKwh) bool
            +calculateKwhNeeded(maxCapacityKwh, currentKwh) float
            +calculateCost(kwhNeeded, pricePerKwh) int
        }
        class GarageCodeGenerator {
            +MAX_GARAGES_PER_TYPE = 999
            +remainingCapacity(existingCount) int
            +generateCodes(GarageType, existingCount, amount) string[]
        }
    }

    class PaymentProofUploadException {
        +USER_MESSAGE = Gagal mengunggah bukti pembayaran, coba lagi
    }
    class CloudinaryUploadApi {
        <<external SDK>>
        +upload(path, options) ApiResponse
    }

    %% ============================================================
    %% SUPPORT (formatting helpers)
    %% ============================================================
    namespace Support {
        class Rupiah {
            +format(int amount) string$
        }
        class DateFormatter {
            +format(CarbonInterface date) string$
        }
    }

    %% ============================================================
    %% FORM REQUESTS (validation)
    %% ============================================================
    class FormRequest {
        <<framework>>
        +authorize() bool
        +rules() array
    }
    namespace Requests {
        class RegisterRequest
        class LoginRequest
        class ProfileUpdateRequest
        class VehicleRequest
        class AvailableGaragesRequest
        class RentalRequest
        class RentalPreviewRequest
        class ChargingRequest
        class ChargingPreviewRequest
        class GarageBatchRequest
        class ExtensionRequest
        class ExtensionPreviewRequest
        class VerificationRequest
        class ReportRequest
    }

    %% ============================================================
    %% MIDDLEWARE
    %% ============================================================
    namespace Middleware {
        class EnsureUserIsAdmin {
            +handle(Request, next) Response
        }
        class EnsureUserIsApproved {
            +handle(Request, next) Response
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
            +__invoke(Request) RedirectResponse
        }
        class VehicleController {
            +index(Request) View
            +create() View
            +store(VehicleRequest) RedirectResponse
        }
        class RentalController {
            +index(Request) View
            +create(Request) View
            +store(RentalRequest) RedirectResponse
        }
        class RentalPreviewController {
            +garageTypes(Request) JsonResponse
            +availableGarages(AvailableGaragesRequest) JsonResponse
            +rentalPreview(RentalPreviewRequest) JsonResponse
            +chargingPreview(ChargingPreviewRequest) JsonResponse
        }
        class ChargingTransactionController {
            +create(Request, rentalId) View
            +store(ChargingRequest, rentalId) RedirectResponse
        }
        class ProfileController {
            +edit(Request) View
            +update(ProfileUpdateRequest) RedirectResponse
        }
        class AuthControllers {
            <<Breeze: login, register, password reset, email verification>>
        }
        class AdminDashboardController {
            +__invoke(ReportService) View
        }
        class VerificationController {
            +index() View
            +approve(VerificationRequest, User) RedirectResponse
            +reject(User) RedirectResponse
        }
        class GarageController {
            +index() View
            +create() View
            +store(GarageBatchRequest) RedirectResponse
        }
        class ExtensionController {
            +index() View
            +create(Rental) View
            +preview(ExtensionPreviewRequest) JsonResponse
            +store(ExtensionRequest, Rental) RedirectResponse
        }
        class ReportController {
            +__invoke(ReportRequest, ReportService) View
        }
    }

    %% ============================================================
    %% CONSOLE
    %% ============================================================
    class RefreshGarageStatusCommand {
        <<artisan: garages:refresh-status>>
        scheduled daily 00:00 Asia/Jakarta
    }

    %% ============================================================
    %% RELATIONSHIPS: database (see docs/erd.md)
    %% ============================================================
    User "1" --> "0..*" Vehicle : owns
    User "1" --> "0..*" Rental : books
    Vehicle "1" --> "0..*" Rental : is parked in
    Garage "1" --> "0..*" Rental : hosts
    Rental "0..1" --> "0..*" Rental : is extended by
    Rental "1" --> "0..*" ChargingTransaction : has

    %% ============================================================
    %% RELATIONSHIPS: enums and traits
    %% ============================================================
    User ..> UserRole
    User ..> UserStatus
    Vehicle ..> VehicleType
    Garage ..> GarageType
    Rental ..> RentalType
    GarageType ..> VehicleType : allowed vehicles
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
    HasPaymentProofRule ..> PaymentProofUploader : size and type constants

    %% ============================================================
    %% RELATIONSHIPS: services
    %% ============================================================
    RentalService --> RentalCalculator
    RentalService --> AvailabilityService
    RentalService --> ChargingService
    RentalService --> GarageStatusService
    ExtensionService --> RentalCalculator
    ExtensionService --> AvailabilityService
    ExtensionService --> GarageStatusService
    ChargingService --> ChargingCalculator
    ReportService --> RentalCalculator
    ReportService --> ChargingService
    GarageBatchService --> GarageCodeGenerator
    AvailabilityService ..> Garage : freeBetween scope
    AvailabilityService ..> Rental : overlapping scope
    GarageStatusService ..> Garage : writes is_used
    VerificationService ..> User : writes status
    RentalService ..> Rental : creates
    ExtensionService ..> Rental : creates extension
    ChargingService ..> ChargingTransaction : creates
    GarageBatchService ..> Garage : creates
    PaymentProofUploader ..> CloudinaryUploadApi : uploads
    PaymentProofUploader ..> PaymentProofUploadException : throws

    %% ============================================================
    %% RELATIONSHIPS: form requests (inheritance)
    %% ============================================================
    RegisterRequest --|> FormRequest
    LoginRequest --|> FormRequest
    ProfileUpdateRequest --|> FormRequest
    VehicleRequest --|> FormRequest
    AvailableGaragesRequest --|> FormRequest
    RentalRequest --|> FormRequest
    ChargingRequest --|> FormRequest
    GarageBatchRequest --|> FormRequest
    ExtensionRequest --|> FormRequest
    VerificationRequest --|> FormRequest
    ReportRequest --|> FormRequest
    RentalPreviewRequest --|> RentalRequest : minus payment_proof
    ChargingPreviewRequest --|> ChargingRequest : minus payment_proof, plus rental_id
    ExtensionPreviewRequest --|> ExtensionRequest : minus proof and garage, plus rental_id
    GarageBatchRequest ..> GarageCodeGenerator : max per type

    %% ============================================================
    %% RELATIONSHIPS: controllers
    %% ============================================================
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

    VehicleController ..> VehicleRequest
    VehicleController ..> Vehicle : creates through user relation
    RentalController ..> RentalRequest
    RentalController --> RentalService
    RentalController --> PaymentProofUploader
    RentalPreviewController ..> AvailableGaragesRequest
    RentalPreviewController ..> RentalPreviewRequest
    RentalPreviewController ..> ChargingPreviewRequest
    RentalPreviewController --> AvailabilityService
    RentalPreviewController --> RentalService
    RentalPreviewController --> RentalCalculator
    RentalPreviewController --> ChargingService
    ChargingTransactionController ..> ChargingRequest
    ChargingTransactionController --> ChargingService
    ChargingTransactionController --> PaymentProofUploader
    ProfileController ..> ProfileUpdateRequest
    AuthControllers ..> RegisterRequest
    AuthControllers ..> LoginRequest
    AdminDashboardController --> ReportService
    VerificationController ..> VerificationRequest
    VerificationController --> VerificationService
    GarageController ..> GarageBatchRequest
    GarageController --> GarageBatchService
    ExtensionController ..> ExtensionRequest
    ExtensionController ..> ExtensionPreviewRequest
    ExtensionController --> ExtensionService
    ExtensionController --> PaymentProofUploader
    ReportController ..> ReportRequest
    ReportController --> ReportService
    ReportController ..> ReportView
    RentalPreviewController ..> Rupiah
    ExtensionController ..> Rupiah
    ExtensionController ..> DateFormatter

    %% ============================================================
    %% RELATIONSHIPS: middleware and console
    %% ============================================================
    EnsureUserIsAdmin ..> User : isAdmin()
    EnsureUserIsApproved ..> User : isApproved()
    RefreshGarageStatusCommand --> GarageStatusService : refreshAll()
```

## Layer summary

| Layer | Classes | Rule |
|---|---|---|
| Models | `User`, `Vehicle`, `Garage`, `Rental`, `ChargingTransaction` | 5 tables. Derived values are methods or accessors, never columns. |
| Enums | `UserRole`, `UserStatus`, `VehicleType`, `GarageType`, `RentalType`, `ReportView` | One source for allowed values, shared by migrations (`values()`) and validation (`Rule::enum`). |
| Services | `RentalService`, `ChargingService`, `ExtensionService`, `AvailabilityService`, `GarageBatchService`, `GarageStatusService`, `VerificationService`, `ReportService`, `PaymentProofUploader` | All business rules. `garages.is_used` is written only by `GarageStatusService`. |
| Calculators / generator | `RentalCalculator`, `ChargingCalculator`, `GarageCodeGenerator` | Pure functions: no database, so they are unit-tested on their own. |
| Form requests | 14 classes | Validation only. Preview requests reuse the save rules through inheritance. |
| Controllers | 11 classes plus Breeze auth | Validate, upload the proof, call one service. No business rules. |
| Middleware | `EnsureUserIsAdmin`, `EnsureUserIsApproved` | Aliases `admin` and `approved` in `bootstrap/app.php`. |

## Notes for the report

- `Rental` is one class for both an original rental and an extension. An extension has `parent_rental_id` set to the original rental, never to the previous extension.
- A charging session belongs to the rental segment (original or extension) that covers its date, so its cost uses that segment's garage price.
- The payment proof is uploaded by the controller (`PaymentProofUploader`) before the service opens the database transaction. Only the returned URL is stored.
- `CloudinaryUploadApi` stands for `Cloudinary\Api\Upload\UploadApi` from the official SDK.
- `AuthControllers` is one box for the Breeze controllers (login, register, password reset, email verification); they are not shown one by one.
- `RefreshGarageStatusCommand` is the artisan closure command `garages:refresh-status` defined in `routes/console.php`, scheduled daily at 00:00 Asia/Jakarta.
