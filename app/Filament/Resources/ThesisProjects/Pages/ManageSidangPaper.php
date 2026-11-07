<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Http\Controllers\SidangPaperController;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Policies\SidangPaperPolicy;
use App\Services\SidangPaperService;
use App\Services\SidangScoreService;
use App\Support\AcademicTerminology;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class ManageSidangPaper extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ThesisProjectResource::class;

    protected string $view = 'filament.resources.thesis-projects.pages.manage-sidang-paper';

    #[Locked]
    public int $defenseId;

    #[Locked]
    public bool $fromQueue = false;

    public array $skData = [];

    public array $scoreData = [];

    public function mount(int|string $record, int|string $defense): void
    {
        abort_unless(ctype_digit((string) $defense), 404);
        $this->record = $this->resolveRecord($record);
        $this->defenseId = (int) $defense;
        $this->fromQueue = request()->query('dari') === 'daftar';
        $this->authorizePaperAccess();
        $this->skForm->fill(['sidang_sk' => $this->defense->sidang_sk]);
        $this->fillScores();
    }

    public function hydrate(): void
    {
        $this->authorizePaperAccess();
    }

    protected function authorizePaperAccess(): void
    {
        abort_unless(ThesisProjectResource::canView($this->getRecord()), 403);
        abort_unless(app(SidangPaperPolicy::class)->manage(Auth::user(), (int) $this->getRecord()->program_studi_id), 403);
    }

    #[Computed]
    public function defense(): ThesisDefense
    {
        return $this->getRecord()->sidangDefenses()
            ->with('project.programStudi', 'titleVersion', 'examiners.lecturer.dosenProfile', 'examiners.scoreRecordedBy', 'examiners.paperVerifiedBy')
            ->findOrFail($this->defenseId);
    }

    public function getTitle(): string
    {
        return 'Berkas '.AcademicTerminology::forProject($this->getRecord())['finalExam'].' #'.$this->defense->attempt_no.' - '.$this->getRecord()->student->name;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->student->mahasiswaProfile?->nim;
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResourceUrl('index', ['tab' => ListThesisProjects::SIDANG_PAPER_TAB]) => 'Berkas Sidang',
            $this->getResourceUrl('view') => $this->getRecord()->student->name,
            'Berkas #'.$this->defense->attempt_no,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Kembali')->color('gray')->icon('heroicon-m-arrow-left')
                ->url($this->fromQueue ? $this->getResourceUrl('index', ['tab' => ListThesisProjects::SIDANG_PAPER_TAB]) : $this->getResourceUrl('view')),
            Action::make('print')->label('Unduh Paket Sidang (.docx)')->icon('heroicon-m-printer')
                ->url(route('sidang.paper.print', $this->defenseId))->disabled(! $this->canPrint()),
            ActionGroup::make(array_map(fn (string $key, string $label): Action => Action::make('print_'.$key)
                ->label($label)->url(route('sidang.paper.print', ['defense' => $this->defenseId, 'lembar' => $key]))
                ->disabled(! $this->canPrint()), array_keys(SidangPaperController::FORMS), array_values(SidangPaperController::FORMS)))
                ->label('Unduh per lembar')->button()->color('gray'),
        ];
    }

    public function details(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail sidang')->columns(2)->schema([
                TextEntry::make('title')->label('Judul')->state($this->defense->titleVersion?->title_id ?? '-')->columnSpanFull(),
                TextEntry::make('schedule')->label('Jadwal')->state(WitaDateTime::format($this->defense->scheduled_for)),
                TextEntry::make('location')->label('Lokasi')->state($this->defense->location ?? '-'),
                TextEntry::make('status')->label('Status')->state(fn (): string => match ($this->defense->status) {
                    'scheduled' => 'Terjadwal', 'awaiting_finalization' => 'Menunggu finalisasi',
                    'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => 'Belum terjadwal',
                })->badge(),
                TextEntry::make('summary')->label('Nilai tersimpan')->state(function (): string {
                    $summary = app(SidangScoreService::class)->summary($this->defense);

                    if ($summary === null) {
                        return 'Belum lengkap';
                    }

                    return $this->formatScore($summary['value']).' ('.$summary['grade'].')';
                }),
            ]),
        ]);
    }

    public function skForm(Schema $schema): Schema
    {
        return $schema->statePath('skData')->components([
            Section::make('Nomor SK')->schema([
                TextInput::make('sidang_sk')->label('Nomor SK')->maxLength(150)
                    ->helperText('Gunakan nomor SK pembimbing dan penguji dari Adum.')->disabled(! $this->editable()),
            ])->footerActions([$this->saveSkAction()]),
        ]);
    }

    public function scoreForm(Schema $schema): Schema
    {
        $weights = app(SidangScoreService::class)->summary($this->defense)['settings']
            ?? SidangSettings::forProgram($this->defense->project->programStudi);
        $fields = [];
        foreach (SidangPaperService::ROLES as $role => $label) {
            $fields[] = Grid::make(3)->schema([
                Select::make('scores.'.$role.'.panel_id')->label($label.' ('.$weights[$role].'%)')->required()->native(false)
                    ->options(fn (Get $get): array => $this->defense->examiners
                        ->filter(fn (ThesisDefenseExaminer $member): bool => ($role === 'supervisor' ? $member->isSupervisorRole() : $member->role === 'examiner')
                            && collect($get('scores'))->except($role)->every(fn (array $other): bool => (int) ($other['panel_id'] ?? 0) !== $member->id))
                        ->mapWithKeys(fn (ThesisDefenseExaminer $member): array => [$member->id => $member->lecturer?->name ?? '-'])->all())
                    ->live()->afterStateUpdated(function ($state, Set $set) use ($role): void {
                        $member = $this->defense->examiners->find((int) $state);
                        $set('scores.'.$role.'.value', $member?->score);
                        $set('scores.'.$role.'.expected_value', $member?->score);
                    })->columnSpan(2),
                TextInput::make('scores.'.$role.'.value')->label('Nilai '.mb_strtolower($label))
                    ->numeric()->minValue(0)->maxValue(100)->step('0.01')->rules(['decimal:0,2'])->required(),
                Hidden::make('scores.'.$role.'.expected_value'),
                TextEntry::make('history_'.$role)->hiddenLabel()->state(fn (Get $get): string => $this->scoreHistory((int) $get('scores.'.$role.'.panel_id')))->columnSpanFull(),
            ])->disabled(! $this->editable() || ! $this->canEnterScores());
        }

        return $schema->statePath('scoreData')->components([
            Section::make('Nilai dari berkas')
                ->description($this->canEnterScores()
                    ? 'Masukkan nilai total pada lembar masing-masing dosen. Nilai yang sudah tercatat ditampilkan otomatis.'
                    : 'Input dari berkas membutuhkan dua penguji dan minimal satu pembimbing pada panel. Periksa susunan panel pada jadwal sidang.')
                ->schema($fields)->footerActions([
                    $this->saveScoresAction(),
                    Action::make('finalize')->label('Finalisasi Sidang')->color('gray')
                        ->url($this->getResourceUrl('view', ['action' => 'complete_sidang']))
                        ->visible(fn (): bool => $this->editable()
                            && $this->defense->status === 'awaiting_finalization'
                            && $this->getRecord()->latestSidangDefense?->id === $this->defenseId),
                ]),
        ]);
    }

    public function saveSkAction(): Action
    {
        return Action::make('saveSk')->label('Simpan nomor SK')->disabled(! $this->editable())
            ->action(function (): void {
                $this->persist($this->skForm->getState());
                unset($this->defense);
            });
    }

    public function saveScoresAction(): Action
    {
        return Action::make('saveScores')->label(fn (): string => $this->defense->status === 'awaiting_finalization' ? 'Simpan Koreksi / Pencocokan' : 'Simpan untuk Finalisasi')
            ->disabled(! $this->editable() || ! $this->canEnterScores())
            ->requiresConfirmation()->modalHeading('Konfirmasi nilai dari berkas')
            ->modalSubmitActionLabel('Konfirmasi Simpan Nilai')->modalCancelActionLabel('Batal')
            ->mountUsing(fn () => $this->scoreForm->getState())
            ->modalDescription(function (): HtmlString {
                $lines = [e($this->getRecord()->student->name).' - '.e($this->getRecord()->student->mahasiswaProfile?->nim)];
                $weights = SidangSettings::forProgram($this->defense->project->programStudi);
                $preview = 0;
                foreach (SidangPaperService::ROLES as $role => $label) {
                    $entry = $this->scoreData['scores'][$role] ?? [];
                    $member = $this->defense->examiners->find((int) ($entry['panel_id'] ?? 0));
                    $value = isset($entry['value']) ? $this->formatScore((float) $entry['value']) : '-';
                    $lines[] = e($label.' - '.($member?->lecturer?->name ?? '-').' - '.$value);
                    $preview += (float) ($entry['value'] ?? 0) * $weights[$role];
                }
                $lines[] = 'Pratinjau nilai akhir '.$this->formatScore(round($preview / 100, 4));
                $lines[] = 'Cocokkan dengan lembar penilaian sebelum menyimpan.';

                return new HtmlString(implode('<br>', $lines));
            })
            ->action(function (): void {
                $this->persist($this->scoreForm->getState());
                unset($this->defense);
                $this->fillScores();
            });
    }

    protected function formatScore(float $value): string
    {
        return number_format($value, round($value, 2) === $value ? 2 : 4, ',', '.');
    }

    protected function persist(array $data): void
    {
        try {
            app(SidangPaperService::class)->record($this->defense, Auth::user(), $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[str_starts_with($field, 'scores.') ? 'scoreData.'.$field : $field] = $messages;
            }
            Notification::make()->title('Data belum disimpan')->body($exception->getMessage())->danger()->send();
            throw ValidationException::withMessages($errors);
        }
        Notification::make()->title('Data berkas sidang disimpan')->success()->send();
    }

    protected function fillScores(): void
    {
        $ids = app(SidangScoreService::class)->panelIds($this->defense);
        $scores = [];
        foreach (array_keys(SidangPaperService::ROLES) as $role) {
            $member = $this->defense->examiners->find($ids[$role] ?? null);
            $scores[$role] = ['panel_id' => $member?->id, 'value' => $member?->score, 'expected_value' => $member?->score];
        }
        $this->scoreForm->fill(['scores' => $scores]);
    }

    protected function scoreHistory(int $panelId): string
    {
        $member = $this->defense->examiners->find($panelId);
        if (! $member || $member->score === null) {
            return 'Belum ada nilai.';
        }
        $source = $member->scoreRecordedBy
            ? ($member->score_source === 'paper' ? 'Dari berkas' : 'Dari portal Dosen').' oleh '.$member->scoreRecordedBy->name.' - '.WitaDateTime::format($member->score_recorded_at)
            : 'Riwayat penginput nilai lama belum tercatat.';

        return $source.' | '.($member->paper_verified_at
            ? 'Cocok dengan berkas - '.$member->paperVerifiedBy?->name.' - '.WitaDateTime::format($member->paper_verified_at)
            : 'Belum dicocokkan dengan berkas.');
    }

    protected function editable(): bool
    {
        return app(SidangScoreService::class)->editable($this->defense);
    }

    protected function canPrint(): bool
    {
        return $this->defense->scheduled_for !== null && $this->defense->status !== 'cancelled';
    }

    protected function canEnterScores(): bool
    {
        return $this->defense->examiners->where('role', 'examiner')->count() >= 2
            && $this->defense->examiners->contains(fn (ThesisDefenseExaminer $member): bool => $member->isSupervisorRole());
    }
}
