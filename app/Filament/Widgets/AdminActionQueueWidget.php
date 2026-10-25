<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminActionQueueService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminActionQueueWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.admin-action-queue';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    /**
     * @return Collection<int, array{key: string, label: string, description: string, action: string, color: string, icon: string, count: int, samples: string, url: string}>
     */
    public function getItems(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        return app(AdminActionQueueService::class)->items($user);
    }
}
