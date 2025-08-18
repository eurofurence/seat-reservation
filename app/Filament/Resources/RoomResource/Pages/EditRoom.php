<?php

namespace App\Filament\Resources\RoomResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('floor_plan')
                ->label('Floor Plan Editor')
                ->icon('heroicon-o-rectangle-group')
                ->color('info')
                ->url(fn (): string => route('admin.rooms.layout', ['room' => $this->record]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
