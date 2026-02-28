<?php

namespace App\Filament\Resources\Sempros\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SemproInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('thesis_submission_id')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('scheduled_for')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('location')
                    ->placeholder('-'),
                TextEntry::make('mode'),
                TextEntry::make('revision_due_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
