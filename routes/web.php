<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get("/", fn () => Inertia::render("Home"))->name("home");

Route::redirect("/admin", "/admin/shipment");
Route::redirect("/courier", "/courier/tasks");
Route::redirect("/client", "/client/orders");
Route::redirect("/client/tracking", "/client/orders");

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
    Route::get("orders", fn () => Inertia::render("client/Orders"))->name("orders");
    Route::get("history", fn () => Inertia::render("client/History"))->name("history");
    Route::get("profile", fn () => Inertia::render("client/Profile"))->name("profile");
    Route::get("tracking/{resi}", fn (string $resi) => Inertia::render("client/Tracking", [
        "resi" => $resi
    ]))->name("tracking");
    Route::get("schedule/{resi}", fn (string $resi) => Inertia::render("client/Schedule", [
        "resi" => $resi
    ]))->name("schedule");
    Route::get("issue/{resi}", fn (string $resi) => Inertia::render("client/Issue", [
        "resi" => $resi
    ]))->name("issue");
});
