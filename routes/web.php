<?php

use App\Models\DriverDocument;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// The pages that Google Play asks for. They are public, with no sign-in.
Route::view('/privacy-policy', 'legal.privacy')->name('legal.privacy');
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/account-deletion', 'legal.account-deletion')->name('legal.account-deletion');

// The photo of one document of a driver application, for the admins who review it in the admin website.
Route::get('/admin/driver-documents/{document}', function (Request $request, DriverDocument $document) {
    abort_unless($request->user()?->role === UserRole::Admin, 403);

    return Storage::response($document->path, null, ['Cache-Control' => 'private, no-store']);
})->name('admin.driver-documents.show');
