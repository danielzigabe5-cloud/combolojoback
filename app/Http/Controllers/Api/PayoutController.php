use App\Http\Controllers\Api\PayoutController;

Route::middleware(['auth:sanctum', 'owner'])->prefix('owner')->group(function () {
    // ... ቀደም ብለው የነበሩ ራውቶች
    
    Route::get('/payouts', [PayoutController::class, 'index']);
    Route::post('/withdraw', [PayoutController::class, 'withdraw']);
});