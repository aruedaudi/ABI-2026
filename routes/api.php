<?php
use App\Http\Controllers\Api\ProjectApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ContentVersionController;
use App\Http\Controllers\InvestigationLineController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResearchGroupController;
use App\Http\Controllers\ThematicAreaController;
use App\Http\Controllers\VersionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// These endpoints are consumed exclusively by the AJAX/fetch calls made from the
// "research_staff" area of the app (contents, versions, content-versions, etc. —
// see the equivalent, already-protected pages in routes/web.php). They previously
// had no authentication at all, so anyone who could reach /api/* directly could
// read, create, update or delete records. 'web' restores session/cookie handling
// (required for the 'auth' guard and CSRF checks to work on these endpoints),
// 'auth' requires a logged-in user, and 'role:research_staff' matches the same
// role restriction already enforced on the Blade pages that call these routes.
Route::middleware(['web', 'auth', 'role:research_staff'])->name('api.')->group(function () {
    Route::apiResource('research-groups', ResearchGroupController::class);
    Route::apiResource('programs', ProgramController::class);
    Route::apiResource('investigation-lines', InvestigationLineController::class);
    Route::apiResource('thematic-areas', ThematicAreaController::class);
    Route::apiResource('contents', ContentController::class);
    Route::apiResource('versions', VersionController::class);
    Route::apiResource('content-versions', ContentVersionController::class);
    Route::get('projects/meta', [ProjectApiController::class, 'meta'])->name('projects.meta');
    Route::post('projects/{project}/restore', [ProjectApiController::class, 'restore'])->name('projects.restore');
    Route::apiResource('projects', ProjectApiController::class);

});