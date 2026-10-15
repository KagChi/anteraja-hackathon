<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Product;
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

// The two installable PWAs: Anteraja Frozen for the sender, Anteraja Kurir for
// the courier. Each app is one client-side React SPA talking to the JSON API
// with its realm's bearer token, so the shell is public and the catch-all keeps
// every deep link working without the server knowing the SPA's route table.
Route::get("client/{path?}", fn () => view("pwa", ["appKey" => "client"]))
    ->where("path", ".*")
    ->name("client.app");
Route::get("courier/{path?}", fn () => view("pwa", ["appKey" => "courier"]))
    ->where("path", ".*")
    ->name("courier.app");

// The manifests are served from config/pwa.php rather than kept as static
// files, so the registry stays the one place an app's identity is written down.
Route::get("{app}.webmanifest", function (string $app) {
    $entry = config("pwa.apps.{$app}");
    abort_if(! is_array($entry), 404);

    return response()
        ->json(\App\Support\PwaManifest::for($entry))
        ->header("Content-Type", "application/manifest+json");
})->whereIn("app", ["client", "courier"])->name("pwa.manifest");

Route::prefix("admin")->name("admin.")->middleware("staff.role:ADMIN")->group(function () {
    // Filters travel in the query string so a Laporan drill-down and a shared
    // link both land on the queue with the same view applied.
    Route::get("shipment", fn (Request $request) => Inertia::render("admin/Shipment", [
        "date" => $request->query("date"),
        "status" => $request->query("status")
    ]))->name("shipment");
    Route::get("report", fn () => Inertia::render("admin/Report"))->name("report");
    // Validasi GPS: verdicts the server refused or flagged, plus the review
    // queue couriers raise from the field.
    Route::get("gps", fn () => Inertia::render("admin/GpsChecks"))->name("gps");
    // Pengaturan radius per service segment.
    Route::get("geofence", fn () => Inertia::render("admin/Geofence"))->name("geofence");
    // The kendala detail lives in the shipment modal, so the old screen is gone.
    Route::redirect("exception", "/admin/shipment");
});
