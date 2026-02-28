<?php

namespace App\Filament\Resources\ThesisSubmissions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ThesisSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('student_user_id')
                    ->numeric(),
                TextEntry::make('program_studi')
                    ->placeholder('-'),
                TextEntry::make('title_id'),
                TextEntry::make('title_en')
                    ->placeholder('-'),
                TextEntry::make('proposal_summary')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('submitted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('approved_by')
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
