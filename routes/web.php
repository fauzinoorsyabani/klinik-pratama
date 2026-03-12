<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─────────────────────────────────────────────────────────────────
//  Public / Landing
// ─────────────────────────────────────────────────────────────────
Route::get("/", function () {
    return Inertia::render("Welcome", [
        "canLogin" => Route::has("login"),
        "canRegister" => Route::has("register"),
        "laravelVersion" => Application::VERSION,
        "phpVersion" => PHP_VERSION,
    ]);
})->name("home");

// ─────────────────────────────────────────────────────────────────
//  Authenticated – all roles
// ─────────────────────────────────────────────────────────────────
Route::middleware(["auth", "verified"])->group(function () {
    // Dashboard
    Route::get("/dashboard", [DashboardController::class, "index"])->name(
        "dashboard",
    );

    // Profile (own)
    Route::get("/profile", [ProfileController::class, "edit"])->name(
        "profile.edit",
    );
    Route::patch("/profile", [ProfileController::class, "update"])->name(
        "profile.update",
    );
    Route::delete("/profile", [ProfileController::class, "destroy"])->name(
        "profile.destroy",
    );

    // ─────────────────────────────────────────────────────────────
    //  Patient Management  (admin, registration staff)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,registration")->group(function () {
        Route::resource("patients", PatientController::class);
        Route::get("patients/{patient}/history", [
            PatientController::class,
            "history",
        ])->name("patients.history");
    });

    // ─────────────────────────────────────────────────────────────
    //  Registration / Queue  (admin, registration staff)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,registration")->group(function () {
        Route::resource("registrations", RegistrationController::class);
        Route::post("registrations/{registration}/cancel", [
            RegistrationController::class,
            "cancel",
        ])->name("registrations.cancel");
        Route::get("queue/today", [
            RegistrationController::class,
            "today",
        ])->name("queue.today");
    });

    // ─────────────────────────────────────────────────────────────
    //  Vital Signs  (admin, nurse / waiting-room staff)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,nurse")->group(function () {
        Route::get("vital-signs", [VitalSignController::class, "index"])->name(
            "vital-signs.index",
        );
        Route::get("vital-signs/{registration}/create", [
            VitalSignController::class,
            "create",
        ])->name("vital-signs.create");
        Route::post("vital-signs/{registration}", [
            VitalSignController::class,
            "store",
        ])->name("vital-signs.store");
        Route::get("vital-signs/{vitalSign}/edit", [
            VitalSignController::class,
            "edit",
        ])->name("vital-signs.edit");
        Route::patch("vital-signs/{vitalSign}", [
            VitalSignController::class,
            "update",
        ])->name("vital-signs.update");
    });

    // ─────────────────────────────────────────────────────────────
    //  Medical Examination  (admin, doctor)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,doctor")->group(function () {
        Route::get("medical-records", [
            MedicalRecordController::class,
            "index",
        ])->name("medical-records.index");
        Route::get("medical-records/{registration}/create", [
            MedicalRecordController::class,
            "create",
        ])->name("medical-records.create");
        Route::post("medical-records/{registration}", [
            MedicalRecordController::class,
            "store",
        ])->name("medical-records.store");
        Route::get("medical-records/{medicalRecord}", [
            MedicalRecordController::class,
            "show",
        ])->name("medical-records.show");
        Route::get("medical-records/{medicalRecord}/edit", [
            MedicalRecordController::class,
            "edit",
        ])->name("medical-records.edit");
        Route::patch("medical-records/{medicalRecord}", [
            MedicalRecordController::class,
            "update",
        ])->name("medical-records.update");

        // Prescriptions (within a medical record)
        Route::get("medical-records/{medicalRecord}/prescription/create", [
            PrescriptionController::class,
            "create",
        ])->name("prescriptions.create");
        Route::post("medical-records/{medicalRecord}/prescription", [
            PrescriptionController::class,
            "store",
        ])->name("prescriptions.store");
        Route::get("prescriptions/{prescription}/edit", [
            PrescriptionController::class,
            "edit",
        ])->name("prescriptions.edit");
        Route::patch("prescriptions/{prescription}", [
            PrescriptionController::class,
            "update",
        ])->name("prescriptions.update");
    });

    // ─────────────────────────────────────────────────────────────
    //  Pharmacy  (admin, pharmacist)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,pharmacist")->group(function () {
        Route::get("pharmacy", [PharmacyController::class, "index"])->name(
            "pharmacy.index",
        );
        Route::get("pharmacy/{prescription}", [
            PharmacyController::class,
            "show",
        ])->name("pharmacy.show");
        Route::patch("pharmacy/{prescription}/status", [
            PharmacyController::class,
            "updateStatus",
        ])->name("pharmacy.update-status");

        // Medicine stock management
        Route::resource("medicines", MedicineController::class);
        Route::post("medicines/{medicine}/adjust-stock", [
            MedicineController::class,
            "adjustStock",
        ])->name("medicines.adjust-stock");
    });

    // ─────────────────────────────────────────────────────────────
    //  Transactions / Billing  (admin, registration staff)
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin,registration")->group(function () {
        Route::resource("transactions", TransactionController::class)->only([
            "index",
            "show",
        ]);
        Route::patch("transactions/{transaction}/pay", [
            TransactionController::class,
            "pay",
        ])->name("transactions.pay");
        Route::get("transactions/{transaction}/receipt", [
            TransactionController::class,
            "receipt",
        ])->name("transactions.receipt");
    });

    // ─────────────────────────────────────────────────────────────
    //  Admin-only – Master Data & User Management
    // ─────────────────────────────────────────────────────────────
    Route::middleware("role:admin")
        ->prefix("admin")
        ->name("admin.")
        ->group(function () {
            // Doctors
            Route::resource("doctors", DoctorController::class);
            Route::patch("doctors/{doctor}/toggle-active", [
                DoctorController::class,
                "toggleActive",
            ])->name("doctors.toggle-active");

            // Services / Tariffs
            Route::resource("services", ServiceController::class);

            // User Management
            Route::resource("users", UserController::class);
            Route::patch("users/{user}/toggle-active", [
                UserController::class,
                "toggleActive",
            ])->name("users.toggle-active");

            // Reports
            Route::get("reports", [ReportController::class, "index"])->name(
                "reports.index",
            );
            Route::get("reports/daily", [
                ReportController::class,
                "daily",
            ])->name("reports.daily");
            Route::get("reports/monthly", [
                ReportController::class,
                "monthly",
            ])->name("reports.monthly");
            Route::get("reports/export", [
                ReportController::class,
                "export",
            ])->name("reports.export");
        });
});

require __DIR__ . "/auth.php";
