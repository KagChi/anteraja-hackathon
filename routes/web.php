<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect("/", "/admin/shipment");

Route::prefix("admin")->name("admin.")->group(function () {
    Route::get("shipment", fn () => Inertia::render("admin/Shipment"))->name("shipment");
    Route::get("exception", fn () => Inertia::render("admin/Exception"))->name("exception");
    Route::get("report", fn () => Inertia::render("admin/Report"))->name("report");
    Route::get("history", fn () => Inertia::render("admin/History"))->name("history");
});

