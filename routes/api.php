<?php

use App\Http\Controllers\AngkatanController;
use App\Http\Controllers\ProdiController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\PeriodeMagangController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MahasiswaController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\MagangController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\DokumenMagangController;
use App\Http\Controllers\JadwalPresentasiController;
use App\Http\Controllers\DokumenNilaiMitraController;
use App\Http\Controllers\DosbingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FotoMagangController;
use App\Http\Controllers\NilaiMitraController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\DashboardController;



/*
|--------------------------------------------------------------------------
| User Route
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth.api:sanctum');


Route::prefix('user')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(UserController::class)->group(function () {
        Route::get('/', 'getUser');
        Route::put('/password', 'password');
        // Route::post('/', 'store');
        // Route::get('/{id}', 'show');
        // Route::put('/{id}', 'update');
        // Route::delete('/{id}', 'destroy');
    });


/*
|--------------------------------------------------------------------------
| Auth Route
|--------------------------------------------------------------------------
*/
Route::prefix('auth')
    ->controller(AuthController::class)->group(function () {
        Route::post('/login', 'login');
        Route::post('/register', 'register');

    });

Route::prefix('auth')
    ->middleware(['auth.api:sanctum'])
    ->controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
    });


/*
|--------------------------------------------------------------------------
| Mahasiswa Routes
|--------------------------------------------------------------------------
*/
Route::prefix('mahasiswa')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(MahasiswaController::class)->group(function () {
        Route::post('/update', 'update');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/show', 'show');
        Route::get('/{id}', 'showDetail');
        Route::delete('/{id}', 'destroy');

        // Custom
        Route::post('/import', 'import');
    });


/*
|--------------------------------------------------------------------------
| Mitra Routes
|--------------------------------------------------------------------------
*/
Route::prefix('mitra')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(MitraController::class)->group(function () {
        Route::get('/jumlah', 'jumlahMitra');
        Route::get('/magang/{id}', 'getMitraByMagang');
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('master-data.manager');
        Route::get('/show', 'show');
        Route::put('/{id}', 'update')->middleware('master-data.manager');
        Route::delete('/{id}', 'destroy')->middleware('master-data.manager');
    });


/*
|--------------------------------------------------------------------------
| Master Tahun Ajaran and Internship Period Routes
|--------------------------------------------------------------------------
*/
Route::prefix('tahun-ajaran')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(TahunAjaranController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('master-period.super-admin');
        Route::put('/{id}', 'update')->middleware('master-period.super-admin');
    });

Route::prefix('periode-magang')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(PeriodeMagangController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('master-period.super-admin');
        Route::put('/{id}', 'update')->middleware('master-period.super-admin');
    });


/*
|--------------------------------------------------------------------------
| Magang Routes
|--------------------------------------------------------------------------
*/
Route::prefix('magang')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(MagangController::class)->group(function () {
        // Route::get('/aktif/jumlah', 'jumlahMagangAktif');
        Route::get('/', 'index');
        Route::get('/export/excel',  'exportExcel');
        Route::get('/export/zip', 'exportZip');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::get('/{id}/detail', 'showDetail');
        Route::post('/{id}', 'update');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });


/*
|--------------------------------------------------------------------------
| Logbook Routes
|--------------------------------------------------------------------------
*/
Route::prefix('logbook')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(LogbookController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });


/*
|--------------------------------------------------------------------------
| Dokumen Magang Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dokumen-magang')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(DokumenMagangController::class)->group(function () {
        Route::get('/magang/{id}', 'getDocMagangByMagang');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::match(['post', 'put'], '/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });


/*
|--------------------------------------------------------------------------
| Jadwal Presentasi Routes
|--------------------------------------------------------------------------
*/
Route::prefix('jadwal-presentasi')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(JadwalPresentasiController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/advisees', 'advisees');
        Route::get('/{id}/peserta', 'peserta');
        Route::get('/{id}/mahasiswa-eligible', 'eligiblePeserta');
        Route::post('/{id}/peserta', 'tambahPeserta');
        Route::delete('/{id}/peserta/{magangId}', 'hapusPeserta');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });




/*
|--------------------------------------------------------------------------
| Dosbing Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dosbing')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(DosbingController::class)->group(function () {
        Route::get('/magang/{id}', 'getDosbingByMagangId');
        Route::get('/jumlah', 'jumlahDosbing');
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('master-data.manager');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update')->middleware('master-data.manager');
        Route::delete('/{id}', 'destroy')->middleware('master-data.manager');
    });


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
// Route::prefix('admin')
//     ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
//     ->controller(AdminController::class)
//     ->group(function () {
//         Route::get('/', 'index');
//         Route::post('/', 'store');
//         Route::get('/{id}', 'show');
//         Route::put('/{id}', 'update');
//         Route::delete('/{id}', 'destroy');
//     });

/*
|--------------------------------------------------------------------------
| Foto Magang Routes
|--------------------------------------------------------------------------
*/
Route::prefix('foto-magang')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(FotoMagangController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

/*
|--------------------------------------------------------------------------
| Penilaian Mitra Routes
|--------------------------------------------------------------------------
*/
Route::prefix('penilaian-mitra')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(NilaiMitraController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });



/**
 * -----------------------------------------------------------------------
 * Angakatan Routes
 * -----------------------------------------------------------------------
 */

Route::prefix('angkatan')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(AngkatanController::class)
    ->group(function () {
        Route::get('/', 'show');
    });

Route::prefix('prodi')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(ProdiController::class)
    ->group(function () {
        Route::get('/', 'index');
    });


/**
 * -----------------------------------------------------------------------
 * Dashboard Routes
 * -----------------------------------------------------------------------
 */

Route::prefix('dashboard')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])
    ->controller(DashboardController::class)
    ->group(function () {
        Route::get('/admin', 'fetchDashboardDataAdmin');
        Route::get('/mitra', 'fetchDashboardDataMitra');
        Route::get('/mahasiswa', 'fetchDashboardDataMahasiswa');
    });


/**
 * -----------------------------------------------------------------------
 * Laporan Routes
 * -----------------------------------------------------------------------
 */
Route::prefix('laporan')
    ->middleware(['auth.api:sanctum', 'role:admin,mahasiswa'])
    ->controller(LaporanController::class)
    ->group(function(){
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::match(['put', 'post'], '/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
