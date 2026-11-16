<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->color(fn (string $state) => $state === 'admin' ? 'warning' : 'gray'),
                IconColumn::make('is_active')->label('Can log in')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
