<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Package;
use App\Models\Staff;
use Illuminate\Auth\Access\Response;

final class PackagePolicy
{
    /**
     * Determine whether the staff (courier) can access the package.
     */
    public function view(Staff $staff, Package $package): Response
    {
        if ($staff->isAdmin()) {
            return Response::allow();
        }

        if ($staff->isCourier() && $package->courier_id === $staff->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound('Paket tidak ditemukan.');
    }

    /**
     * Determine whether the courier can update/act upon the package.
     */
    public function update(Staff $staff, Package $package): Response
    {
        return $this->view($staff, $package);
    }
}
