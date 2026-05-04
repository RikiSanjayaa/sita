<?php

namespace App\Providers;

use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentTimezone;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Resend\Client as ResendClient;
use Resend\Contracts\Client as ResendClientContract;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureResendClient();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureResendClient(): void
    {
        $this->app->extend(ResendClientContract::class, function (mixed $client): mixed {
            $apiKey = config('resend.api_key') ?? config('services.resend.key');

            if (! is_string($apiKey) || $apiKey === '') {
                return $client;
            }

            $caFile = $this->resolveResendCaFile();
            $guzzleConfig = [];

            if ($caFile !== null) {
                $guzzleConfig['verify'] = $caFile;
            }

            return new ResendClient(
                new HttpTransporter(
                    new GuzzleClient($guzzleConfig),
                    BaseUri::from((string) (env('RESEND_BASE_URL') ?: 'api.resend.com')),
                    Headers::withAuthorization(ApiKey::from($apiKey)),
                ),
            );
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        FilamentTimezone::set(WitaDateTime::TIMEZONE);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function resolveResendCaFile(): ?string
    {
        $configuredPath = config('services.resend.ca_file');

        if (is_string($configuredPath) && $configuredPath !== '' && is_file($configuredPath)) {
            return $configuredPath;
        }

        $userProfile = $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE');

        if (! is_string($userProfile) || $userProfile === '') {
            return null;
        }

        $herdCaFile = $userProfile.DIRECTORY_SEPARATOR.'.config'.DIRECTORY_SEPARATOR.'herd'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'cacert.pem';

        return is_file($herdCaFile) ? $herdCaFile : null;
    }
}
