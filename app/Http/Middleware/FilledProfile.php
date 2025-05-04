<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;

class FilledProfile
{
    public function handle($request, \Closure $next)
    {
        $user = Auth::user();

        if (! $user->hasFilledProfile()) {
            return redirect()
                ->route('cabinet.profile.show')
                ->with('error', 'Please fill out your profile and/or verify your phone to access this section.');
        }

        return $next($request);
    }
}
