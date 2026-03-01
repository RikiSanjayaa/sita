<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMentorshipAssignment extends ViewRecord
{
  protected static string $resource = MentorshipAssignmentResource::class;

  protected function getHeaderActions(): array
  {
    return [
      EditAction::make(),
    ];
  }
}
