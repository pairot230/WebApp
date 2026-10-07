<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\DormController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\ScoreHistoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\TemporaryLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Member\ComplaintController;
use App\Http\Controllers\Member\FinanceController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\QrController;
use App\Http\Controllers\Member\RepairRequestController;
use App\Http\Controllers\SuperAdmin\AdminRoleController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [GoogleLoginController::class, 'create'])->name('login');
    Route::post('/auth/temporary', [TemporaryLoginController::class, 'store'])
        ->middleware('throttle:10,1')->name('auth.temporary');
    Route::post('/auth/google', [GoogleLoginController::class, 'store'])
        ->middleware('throttle:10,1')->name('auth.google');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleLoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'active', 'role:superadmin,admin,user'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/me', [ProfileController::class, 'show'])->name('member.profile');
    Route::get('/me/qr', [QrController::class, 'show'])->name('member.qr');
    Route::get('/me/qr/image', [QrController::class, 'image'])->name('member.qr.image');
    Route::get('/me/scores', [App\Http\Controllers\Member\ScoreHistoryController::class, 'index'])->name('member.scores');
    Route::get('/activities', [App\Http\Controllers\Member\ActivityController::class, 'index'])->name('member.activities');
    Route::resource('complaints', ComplaintController::class)
        ->only(['index', 'create', 'store', 'show'])->names('member.complaints');
    Route::resource('repairs', RepairRequestController::class)
        ->only(['index', 'create', 'store', 'show'])->names('member.repairs');
    Route::get('/finance', [FinanceController::class, 'index'])->name('member.finance');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'role:superadmin,admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('dorms', DormController::class);
    Route::resource('buildings', BuildingController::class);
    Route::resource('floors', FloorController::class);
    Route::resource('rooms', RoomController::class);
    Route::resource('activities', ActivityController::class);
    Route::resource('complaints', App\Http\Controllers\Admin\ComplaintController::class)->only(['index', 'show', 'update']);
    Route::resource('repairs', App\Http\Controllers\Admin\RepairRequestController::class)->only(['index', 'show', 'update']);
    Route::resource('finance', App\Http\Controllers\Admin\FinanceController::class)->except('destroy');
    Route::post('/finance/{finance}/void', [App\Http\Controllers\Admin\FinanceController::class, 'void'])->name('finance.void');
    Route::get('/activities/{activity}/attendances', [AttendanceController::class, 'index'])->name('activities.attendances.index');
    Route::post('/activities/{activity}/attendances/preview', [AttendanceController::class, 'preview'])->middleware('throttle:60,1')->name('activities.attendances.preview');
    Route::post('/activities/{activity}/attendances', [AttendanceController::class, 'store'])->middleware('throttle:60,1')->name('activities.attendances.store');
    Route::get('/users/{user}/scores', [ScoreHistoryController::class, 'index'])->name('users.scores.index');
    Route::post('/users/{user}/scores', [ScoreHistoryController::class, 'store'])->name('users.scores.store');
});

Route::prefix('superadmin')->name('superadmin.')->middleware(['auth', 'active', 'role:superadmin'])->group(function () {
    Route::get('/admins', [AdminRoleController::class, 'index'])->name('admins.index');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
    Route::patch('/users/{user}/role', [AdminRoleController::class, 'update'])->name('users.role');
});
