<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('floor_plan')
                ->label('Floor Plan Editor')
                ->icon('heroicon-o-rectangle-group')
                ->color('info')
                ->url(fn (): string => route('floor-plan.edit', ['room' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
