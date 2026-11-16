<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('category')
                    ->required()
                    ->datalist(['Rent', 'Electricity', 'Salary', 'Transport', 'Repairs', 'Packaging', 'Other']),
                TextInput::make('amount')->required()->numeric()->minValue(0.01),
                DatePicker::make('date')->required()->default(now()),
                TextInput::make('note')->maxLength(255),
            ]);
    }
}
