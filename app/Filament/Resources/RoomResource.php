<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                
                Section::make('Room Layout')
                    ->description('Configure the stage position for this room')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('stage_x')
                                    ->label('Stage X Position')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Grid column position (0-based)'),
                                TextInput::make('stage_y')
                                    ->label('Stage Y Position')  
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Grid row position (0-based)'),
                            ]),
                    ]),

                Section::make('Blocks')
                    ->description('Add and configure seating blocks for this room')
                    ->schema([
                        Repeater::make('blocks')
                            ->relationship()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->label('Block Name')
                                            ->placeholder('e.g., Block A, VIP Section'),
                                        
                                        Select::make('rotation')
                                            ->label('Orientation')
                                            ->options([
                                                0 => '↑ Up (0°)',
                                                90 => '→ Right (90°)',
                                                180 => '↓ Down (180°)',
                                                270 => '← Left (270°)',
                                            ])
                                            ->default(0),
                                    ]),

                                Fieldset::make('Grid Position')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('position_x')
                                                    ->label('X Position')
                                                    ->numeric()
                                                    ->default(-1)
                                                    ->helperText('Grid column (-1 = unplaced)'),
                                                TextInput::make('position_y')
                                                    ->label('Y Position')
                                                    ->numeric()
                                                    ->default(-1)
                                                    ->helperText('Grid row (-1 = unplaced)'),
                                            ]),
                                    ]),

                                Fieldset::make('Quick Block Creation')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('quick_rows')
                                                    ->label('Number of Rows')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(50)
                                                    ->placeholder('e.g., 10')
                                                    ->helperText('Creates rows automatically')
                                                    ->live(),
                                                
                                                TextInput::make('quick_seats_per_row')
                                                    ->label('Seats per Row')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(100)
                                                    ->placeholder('e.g., 25')
                                                    ->helperText('Creates A-Z seats automatically')
                                                    ->live(),

                                                Actions::make([
                                                    FormAction::make('generate_block')
                                                        ->label('Generate Block')
                                                        ->icon('heroicon-o-sparkles')
                                                        ->color('success')
                                                        ->action(function (Get $get, Set $set, $state) {
                                                            $rows = (int) $get('quick_rows');
                                                            $seatsPerRow = (int) $get('quick_seats_per_row');
                                                            
                                                            if ($rows > 0 && $seatsPerRow > 0) {
                                                                static::generateBlockStructure($get, $set, $rows, $seatsPerRow);
                                                            }
                                                        })
                                                        ->visible(fn (Get $get) => $get('quick_rows') && $get('quick_seats_per_row')),
                                                ])
                                            ]),
                                    ]),

                                Repeater::make('rows')
                                    ->relationship()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label('Row Name')
                                                    ->placeholder('e.g., Row 1, A, Front'),
                                                
                                                TextInput::make('sort')
                                                    ->label('Sort Order')
                                                    ->numeric()
                                                    ->default(0),
                                            ]),

                                        Repeater::make('seats')
                                            ->relationship()
                                            ->schema([
                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('label')
                                                            ->required()
                                                            ->label('Seat Label')
                                                            ->placeholder('A, B, C, etc.'),
                                                        
                                                        TextInput::make('number')
                                                            ->label('Seat Number')
                                                            ->numeric()
                                                            ->default(1),
                                                        
                                                        TextInput::make('sort')
                                                            ->label('Sort Order')
                                                            ->numeric()
                                                            ->default(0),
                                                    ]),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New Seat')
                                            ->collapsed()
                                            ->cloneable()
                                            ->collapsible(),
                                    ])
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New Row')
                                    ->collapsed()
                                    ->cloneable()
                                    ->collapsible(),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New Block')
                            ->collapsed()
                            ->cloneable()
                            ->collapsible()
                            ->reorderable('sort')
                            ->orderColumn('sort'),
                    ]),
            ]);
    }

    protected static function generateBlockStructure(Get $get, Set $set, int $rows, int $seatsPerRow): void
    {
        $rowsData = [];
        
        for ($rowIndex = 1; $rowIndex <= $rows; $rowIndex++) {
            $seatsData = [];
            
            for ($seatIndex = 1; $seatIndex <= $seatsPerRow; $seatIndex++) {
                // Convert seat index to letter (A, B, C, ..., Z, AA, AB, etc.)
                $seatLabel = static::numberToLetter($seatIndex);
                
                $seatsData[] = [
                    'label' => $seatLabel,
                    'number' => $seatIndex,
                    'sort' => $seatIndex,
                ];
            }
            
            $rowsData[] = [
                'name' => "Row {$rowIndex}",
                'sort' => $rowIndex,
                'seats' => $seatsData,
            ];
        }
        
        $set('rows', $rowsData);
    }

    public static function numberToLetter(int $number): string
    {
        $result = '';
        while ($number > 0) {
            $number--; // Make it 0-based
            $result = chr(65 + ($number % 26)) . $result;
            $number = intval($number / 26);
        }
        return $result;
    }

    protected static function createQuickBlock(Room $room, array $data): void
    {
        // Create the block
        $block = $room->blocks()->create([
            'name' => $data['block_name'],
            'rotation' => $data['rotation'],
            'position_x' => -1, // Unplaced by default
            'position_y' => -1, // Unplaced by default
            'sort' => $room->blocks()->count() + 1,
        ]);

        // Create rows and seats
        for ($rowIndex = 1; $rowIndex <= $data['rows']; $rowIndex++) {
            $row = $block->rows()->create([
                'name' => "Row {$rowIndex}",
                'sort' => $rowIndex,
            ]);

            // Create seats for this row
            for ($seatIndex = 1; $seatIndex <= $data['seats_per_row']; $seatIndex++) {
                $seatLabel = static::numberToLetter($seatIndex);
                
                $row->seats()->create([
                    'label' => $seatLabel,
                    'number' => $seatIndex,
                    'sort' => $seatIndex,
                ]);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('blocks_count')
                    ->label('Blocks')
                    ->counts('blocks')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('total_seats')
                    ->label('Total Seats')
                    ->getStateUsing(function ($record) {
                        return $record->blocks()
                            ->withCount(['rows as seats_count' => function ($query) {
                                $query->join('seats', 'rows.id', '=', 'seats.row_id')
                                      ->selectRaw('count(seats.id)');
                            }])
                            ->get()
                            ->sum('seats_count');
                    })
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('stage_position')
                    ->label('Stage Position')
                    ->getStateUsing(fn ($record) => "({$record->stage_x}, {$record->stage_y})")
                    ->toggleable(),
                    
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
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Room $record): string => route('filament.admin.resources.rooms.edit', $record)),
                
                Action::make('layout_editor')
                    ->label('Edit Layout')
                    ->icon('heroicon-o-map')
                    ->url(fn (Room $record): string => route('admin.rooms.layout', $record))
                    ->openUrlInNewTab(),
                    
                Action::make('quick_add_block')
                    ->label('Quick Add Block')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->schema([
                        TextInput::make('block_name')
                            ->label('Block Name')
                            ->required()
                            ->placeholder('e.g., Block A, VIP Section'),
                            
                        Select::make('rotation')
                            ->label('Orientation')
                            ->options([
                                0 => '↑ Up (0°)',
                                90 => '→ Right (90°)',
                                180 => '↓ Down (180°)',
                                270 => '← Left (270°)',
                            ])
                            ->default(0)
                            ->required(),
                            
                        TextInput::make('rows')
                            ->label('Number of Rows')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->default(10),
                            
                        TextInput::make('seats_per_row')
                            ->label('Seats per Row')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->default(25),
                    ])
                    ->action(function (Room $record, array $data) {
                        static::createQuickBlock($record, $data);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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