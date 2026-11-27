<?php

namespace App\Http\Responses;

use App\Filament\Pages\Pos;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/** After login, open the billing screen (or the page the user was trying to reach). */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->intended(Pos::getUrl());
    }
}
