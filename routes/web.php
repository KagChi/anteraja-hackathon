<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Product;
use App\Http\Controllers\Courier\MapController;
use App\Http\Resources\Client\ProductResource;

// The marketing landing page: the anteraja.id home page rebuilt around the
// cold-chain fleet, with the three consoles reachable from the footer.
Route::get("/", function () {
    $highlights = Product::query()
        ->where("is_available", true)
        ->inCatalogOrder()
        ->limit(4)
        ->get();

    return Inertia::render("Landing", [
        "products" => ProductResource::collection($highlights)->resolve(),
    ]);
})->name("home");

// The blog is written in resources/js/data/posts.ts: one source of truth for
// both the index and the detail page, so the route only names the slug.
Route::get("blog", fn () => Inertia::render("Blog/Index"))->name("blog.index");
Route::get("blog/{slug}", fn (string $slug) => Inertia::render("Blog/Show", [
    "slug" => $slug,
]))->where("slug", "[a-z0-9-]+")->name("blog.show");

// The admin console signs in through the JSON API, but the page has to exist on
// the web side: the API answers with a 401 and cannot render a screen.
Route::get("login", function (Request $request) {
    if ($request->user() !== null) {
        return redirect("/admin/shipment");
    }

    return Inertia::render("Auth/Login");
})->name("login");

Route::redirect("/admin", "/admin/shipment");

// The courier app's Leaflet map. NativePHP has no native map element, so the
// delivery screen embeds a php-mode webview that renders these pages from the
// device's own PHP runtime. The page polls the fix route, which reads the file
// cache the native Delivery component writes as the device streams GPS.
Route::get("courier/map/{resi}", [MapController::class, "show"])
    ->where("resi", "[A-Za-z0-9-]{4,32}")
    ->name("courier.map");
Route::get("courier/map/{resi}/fix", [MapController::class, "fix"])
    ->where("resi", "[A-Za-z0-9-]{4,32}")
    ->name("courier.map.fix");

Route::prefix("admin")->name("admin.")->middleware("staff.role:ADMIN")->group(function () {
    // Filters travel in the query string so a Laporan drill-down and a shared
    // link both land on the queue with the same view applied.
    Route::get("shipment", fn (Request $request) => Inertia::render("admin/Shipment", [
        "date" => $request->query("date"),
        "status" => $request->query("status")
    ]))->name("shipment");
    Route::get("report", fn () => Inertia::render("admin/Report"))->name("report");
    Route::get("history", fn (Request $request) => Inertia::render("admin/History", [
        "q" => $request->query("q"),
        "status" => $request->query("status")
    ]))->name("history");
    // Validasi GPS: verdicts the server refused or flagged, plus the review
    // queue couriers raise from the field.
    Route::get("gps", fn () => Inertia::render("admin/GpsChecks"))->name("gps");
    // Pengaturan radius per service segment.
    Route::get("geofence", fn () => Inertia::render("admin/Geofence"))->name("geofence");
    // The kendala detail lives in the shipment modal, so the old screen is gone.
    Route::redirect("exception", "/admin/shipment");
});
