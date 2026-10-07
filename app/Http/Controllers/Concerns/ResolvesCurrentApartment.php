<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Apartment;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCurrentApartment
{
    protected function currentApartment(Request $request): Apartment
    {
        $apartment = $request->user()->currentApartment();

        if (! $apartment) {
            throw new HttpException(403, 'No apartment is linked to this account yet.');
        }

        return $apartment;
    }
}
