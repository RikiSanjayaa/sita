<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\SimpleXlsxReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UserExcelImportService
{
    public function __construct(
        private readonly SimpleXlsxReader $xlsxReader,
        private readonly UserProvisioningService $userProvisioningService,
    ) {}

    /**
     * @param  array{import_type: string, program_studi_id: int|string|null}  $options
     * @return array{processed: int, imported: int, failed: int, failures: array<int, string>}
     */
    public function import(UploadedFile $file, array $options, User $actor): array
    {
        $rows = $this->xlsxReader->rows($file->getRealPath());

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['File Excel kosong atau tidak memiliki data yang dapat dibaca.'],
            ]);
        }

        $header = array_map(fn(string $value): string => $this->normalizeHeader($value), array_shift($rows));

        if ($header === []) {
            throw ValidationException::withMessages([
                'file' => ['Baris header pada file Excel tidak ditemukan.'],
            ]);
        }

        $importType = (string) ($options['import_type'] ?? '');
        $programStudiId = (int) ($options['program_studi_id'] ?? 0);

        if (! in_array($importType, AppRole::uiValues(), true)) {
            throw ValidationException::withMessages([
                'import_type' => ['Tipe import tidak valid.'],
            ]);
        }

        if ($programStudiId < 1 || ! ProgramStudi::query()->whereKey($programStudiId)->exists()) {
            throw ValidationException::withMessages([
                'program_studi_id' => ['Program studi wajib dipilih sebelum import dilakukan.'],
            ]);
        }

        $processed = 0;
        $imported = 0;
        $failures = [];

        foreach ($rows as $rowIndex => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $processed++;
            $lineNumber = $rowIndex + 2;

            try {
                $data = $this->mapRow($header, $row, $importType, $programStudiId, $actor);

                DB::transaction(function () use ($data): void {
                    $user = User::query()->firstOrNew([
                        'email' => (string) $data['email'],
                    ]);

                    $user->forceFill([
                        'name' => (string) $data['name'],
                        'email' => (string) $data['email'],
                        'password' => Hash::make((string) $data['password']),
                    ])->save();

                    $this->userProvisioningService->syncRoleAndProfiles($user, $data);
                });

                $imported++;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())
                    ->flatten()
                    ->implode(' ');

                $failures[] = "Baris {$lineNumber}: {$message}";
            } catch (RuntimeException $exception) {
                $failures[] = "Baris {$lineNumber}: {$exception->getMessage()}";
            }
        }

        return [
            'processed' => $processed,
            'imported' => $imported,
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $header, array $row, string $importType, int $programStudiId, User $actor): array
    {
        $raw = [];

        foreach ($header as $index => $column) {
            $raw[$column] = trim((string) ($row[$index] ?? ''));
        }

        $role = $raw['role'] !== '' ? strtolower($raw['role']) : $importType;

        $data = [
            'name' => $raw['nama'] ?? ($raw['name'] ?? ''),
            'email' => $raw['email'] ?? '',
            'phone_number' => $raw['no_hp'] ?? ($raw['phone_number'] ?? ''),
            'role' => $role,
            'password' => $raw['password'] ?? '',
            'nim' => $raw['nim'] ?? '',
            'angkatan' => $raw['angkatan'] ?? '',
            'concentration' => $raw['konsentrasi'] ?? ($raw['concentration'] ?? ''),
            'nik' => $raw['nik'] ?? '',
            'supervision_quota' => $raw['kuota_bimbingan'] ?? ($raw['supervision_quota'] ?? ''),
            'prodi' => $programStudiId,
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:mahasiswa,dosen,admin'],
            'password' => ['required', 'string', 'min:8'],
            'nim' => ['nullable', 'required_if:role,mahasiswa', 'string', 'max:255'],
            'angkatan' => ['nullable', 'required_if:role,mahasiswa', 'integer', 'between:1990,2100'],
            'concentration' => ['nullable', 'required_if:role,mahasiswa', 'required_if:role,dosen', 'string', 'max:255'],
            'nik' => ['nullable', 'required_if:role,dosen', 'string', 'max:255'],
            'supervision_quota' => ['nullable', 'integer', 'min:1'],
            'prodi' => ['required', 'integer'],
        ], [
            'nim.required_if' => 'NIM wajib diisi untuk role mahasiswa.',
            'angkatan.required_if' => 'Angkatan wajib diisi untuk role mahasiswa.',
            'concentration.required_if' => 'Konsentrasi wajib diisi untuk role mahasiswa atau dosen.',
            'nik.required_if' => 'NIK wajib diisi untuk role dosen.',
        ]);

        $validated = $validator->validate();

        if ($validated['role'] === AppRole::Admin->value && ! $actor->hasRole(AppRole::SuperAdmin)) {
            throw ValidationException::withMessages([
                'role' => ['Hanya super admin yang dapat mengimpor akun admin.'],
            ]);
        }

        return Arr::map($validated, fn(mixed $value): mixed => $value === '' ? null : $value);
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return collect($row)
            ->map(fn(string $value): string => trim($value))
            ->filter()
            ->isEmpty();
    }

    private function normalizeHeader(string $header): string
    {
        return str($header)
            ->lower()
            ->replace([' ', '-'], '_')
            ->replace('.', '')
            ->toString();
    }
}
