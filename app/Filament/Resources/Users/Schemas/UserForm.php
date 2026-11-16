<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')
                    ->label('Login email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(6)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                Select::make('role')
                    ->options([User::ROLE_ADMIN => 'Owner / Admin', User::ROLE_CASHIER => 'Cashier'])
                    ->default(User::ROLE_CASHIER)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Can log in')
                    ->default(true)
                    ->disabled(fn (?User $record) => $record?->is(auth()->user()) ?? false),
            ]);
    }
}
