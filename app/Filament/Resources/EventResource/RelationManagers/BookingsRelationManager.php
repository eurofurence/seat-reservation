<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Actions\ExportAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Exports\BookingExporter;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name on Reservation')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('comment')
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
                TextInputColumn::make('name')->label('Booking Name')->sortable()->searchable(),
                TextColumn::make('user.name')->label('User Name')->sortable()->searchable(),
                SelectColumn::make('type')->options([
                    'con_guest' => 'Con Guest',
                    'function' => 'Function (Daily etc.)',
                    'event_guests' => 'Event Guest',
                    'staff' => 'Staff',
                ])->sortable(),
                TextColumn::make('seat.row.block.name')->sortable(),
                TextColumn::make('seat.row.name')->sortable(),
                TextColumn::make('seat.name')->sortable(),
                CheckboxColumn::make('ticket_given'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExportAction::make()->exporter(BookingExporter::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
