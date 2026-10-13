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

Route::prefix("kurir")->name("kurir.")->group(function () {
    Route::get("tasks", fn () => Inertia::render("kurir/Tasks"))->name("tasks");
    Route::get("delivery", fn () => Inertia::render("kurir/Delivery"))->name("delivery");
    Route::get("proof", fn () => Inertia::render("kurir/Proof"))->name("proof");
    Route::get("issue", fn () => Inertia::render("kurir/Issue"))->name("issue");
});

