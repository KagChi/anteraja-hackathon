<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get("/", fn () => Inertia::render("Home"))->name("home");

Route::redirect("/admin", "/admin/shipment");
Route::redirect("/courier", "/courier/tasks");
Route::redirect("/client", "/client/tracking");

Route::prefix("admin")->name("admin.")->group(function () {
    Route::get("shipment", fn (Request $request) => Inertia::render("admin/Shipment", [
        "q" => $request->query("q")
    ]))->name("shipment");
    Route::get("exception", fn () => Inertia::render("admin/Exception"))->name("exception");
    Route::get("report", fn () => Inertia::render("admin/Report"))->name("report");
    Route::get("history", fn () => Inertia::render("admin/History"))->name("history");
});

Route::prefix("courier")->name("courier.")->group(function () {
    Route::get("tasks", fn () => Inertia::render("courier/Tasks"))->name("tasks");
    Route::get("delivery", fn () => Inertia::render("courier/Delivery"))->name("delivery");
    Route::get("proof", fn () => Inertia::render("courier/Proof"))->name("proof");
    Route::get("issue", fn () => Inertia::render("courier/Issue"))->name("issue");
    Route::get("history", fn () => Inertia::render("courier/History"))->name("history");
    Route::get("profile", fn () => Inertia::render("courier/Profile"))->name("profile");
});

Route::prefix("client")->name("client.")->group(function () {
    Route::get("tracking", fn () => Inertia::render("client/Tracking"))->name("tracking");
    Route::get("schedule", fn () => Inertia::render("client/Schedule"))->name("schedule");
    Route::get("issue", fn () => Inertia::render("client/Issue"))->name("issue");
});
