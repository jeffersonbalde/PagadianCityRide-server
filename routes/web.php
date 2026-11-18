<?php

use App\Models\DriverDocument;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// The photo of one document of a driver application, for the admins who review it in the admin website.
Route::get('/admin/driver-documents/{document}', function (Request $request, DriverDocument $document) {
    abort_unless($request->user()?->role === UserRole::Admin, 403);

    return Storage::response($document->path, null, ['Cache-Control' => 'private, no-store']);
})->name('admin.driver-documents.show');
