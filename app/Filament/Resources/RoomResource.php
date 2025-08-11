<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\RelationManagers;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Repeater::make('Blocks')
                    ->relationship('blocks')
                    ->columnSpanFull()
                    ->orderColumn()
                    ->schema([
                        Forms\Components\Group::make([
                            TextInput::make('name'),
                            TextInput::make('row')
                                ->required()
                                ->numeric()
                                ->maxLength(255),
                            TextInput::make('row_count')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('default_seat_count')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('rotate')
                                ->options([
                                    0 => '0°',
                                    90 => '90°',
                                    180 => '180°',
                                    270 => '270°',
                                ])
                                ->default(0),
                        ])->columns(5),
                        Repeater::make('Rows')
                            ->relationship('rows')
                            ->schema([
                                TextInput::make('name')->disabled(),
                                TextInput::make('seat_count')
                                    ->numeric()
                                    ->default(1),
                                Forms\Components\Select::make('align')
                                    ->options([
                                        'left' => 'Left',
                                        'center' => 'Center',
                                        'right' => 'Right',
                                    ])
                            ])
                            ->orderColumn()
                            ->columns(3),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('floor_plan_editor')
                    ->label('Floor Plan Editor')
                    ->icon('heroicon-o-squares-plus')
                    ->url(fn ($record) => route('floor-plan.editor', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BlocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
