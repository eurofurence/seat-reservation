<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Exports\BookingExporter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name on Reservation')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->columnSpanFull()
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextInputColumn::make('name')->label('Booking Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('User Name')->sortable()->searchable(),
                Tables\Columns\SelectColumn::make('type')->options([
                    'con_guest' => 'Con Guest',
                    'function' => 'Function (Daily etc.)',
                    'event_guests' => 'Event Guest',
                    'staff' => 'Staff',
                ])->sortable(),
                TextColumn::make('seat.row.block.name')->sortable(),
                TextColumn::make('seat.row.name')->sortable(),
                TextColumn::make('seat.name')->sortable(),
                Tables\Columns\CheckboxColumn::make('ticket_given'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()->exporter(BookingExporter::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
