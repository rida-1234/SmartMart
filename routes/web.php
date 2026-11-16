<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/pos');

Route::middleware('auth')->group(function () {
    Route::get('/receipts/{sale}', [ReceiptController::class, 'show'])->name('receipts.show');
});
