<?php

namespace App\Filament\Concerns;

/**
 * Restricts a Filament resource or page to admin (owner) users.
 * Cashiers only see the POS, customers and their own sales.
 */
trait AdminOnly
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
