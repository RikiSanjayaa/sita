<?php

use App\Jobs\SendBrowserNotification;
use App\Models\User;
use App\Notifications\BrowserPushNotification;
use App\Services\RealtimeNotificationService;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;

const WEB_PUSH_TEST_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/';

beforeEach(function () {
    config([
        'webpush.enabled' => true,
        'webpush.vapid.subject' => 'mailto:admin@example.test',
        'webpush.vapid.public_key' => 'public-key',
        'webpush.vapid.private_key' => 'private-key',
        'queue.default' => 'sync',
    ]);
});

/**
 * Replace only the HTTP transport; the real WebPushChannel and ReportHandler still run.
 *
 * @param  array<string, int>  $statusByEndpoint
 * @return object{sent: array<int, array{endpoint: string, payload: array<string, mixed>, options: array<string, mixed>}>}
 */
function fakeWebPushTransport(array $statusByEndpoint = []): object
{
    $recorder = new class
    {
        /** @var array<int, array{endpoint: string, payload: array<string, mixed>, options: array<string, mixed>}> */
        public array $sent = [];
    };

    $transport = Mockery::mock(WebPush::class);
    $transport->shouldReceive('queueNotification')->andReturnUsing(
        function ($subscription, $payload = null, $options = []) use ($recorder): void {
            $recorder->sent[] = [
                'endpoint' => $subscription->getEndpoint(),
                'payload' => json_decode((string) $payload, true, flags: JSON_THROW_ON_ERROR),
                'options' => $options,
            ];
        },
    );
    $transport->shouldReceive('flush')->andReturnUsing(function () use ($recorder, $statusByEndpoint) {
        $reports = [];

        foreach ($recorder->sent as $item) {
            $status = $statusByEndpoint[$item['endpoint']] ?? 201;
            $reports[] = new MessageSentReport(
                new PsrRequest('POST', $item['endpoint']),
                new PsrResponse($status),
                $status < 300,
                'status '.$status,
            );
        }

        return (fn () => yield from $reports)();
    });

    app()->when(WebPushChannel::class)->needs(WebPush::class)->give(fn () => $transport);

    return $recorder;
}

function webPushUser(array $attributes = [], string $endpoint = 'device-1'): User
{
    $user = User::factory()->create(['browser_notifications_enabled' => true, ...$attributes]);
    webPushSubscribe($user, $endpoint);

    return $user;
}

function webPushSubscribe(User $user, string $endpoint): PushSubscription
{
    $subscription = new PushSubscription([
        'endpoint' => WEB_PUSH_TEST_ENDPOINT.$endpoint,
        'public_key' => 'BP'.str_repeat('A', 85),
        'auth_token' => str_repeat('B', 22),
        'content_encoding' => 'aes128gcm',
    ]);
    $subscription->forceFill([
        'subscribable_type' => $user->getMorphClass(),
        'subscribable_id' => $user->getKey(),
    ])->save();

    return $subscription;
}

function notifyForWebPush(User $user, string $key, string $url, array $extra = []): void
{
    app(RealtimeNotificationService::class)->notifyUser($user, $key, [
        'title' => 'Detail rahasia',
        'description' => 'Budi Santoso mengirim pesan: nilai sidang rahasia.',
        'url' => $url,
        'icon' => 'message-square',
        'createdAt' => now()->toIso8601String(),
        ...$extra,
    ]);
}

function queuedPushJobs(): int
{
    return DB::table('jobs')->where('payload', 'like', '%SendBrowserNotification%')->count();
}

function runPushJob(User $user, string $key, string $url): void
{
    $stored = $user->notifications()->latest()->first();
    (new SendBrowserNotification($user->id, (string) $stored?->id, $key, $url))->handle();
}

test('academic notifications persist for database and echo and queue an immediate async push', function () {
    $user = webPushUser();

    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');

    $stored = $user->notifications()->sole();
    expect($stored->data['preferenceKey'])->toBe('statusTugasAkhir')
        ->and($stored->data['title'])->toBe('Detail rahasia');

    // QUEUE_CONNECTION is sync, yet the push sits on the database queue and has not run.
    expect(queuedPushJobs())->toBe(1);
    $row = DB::table('jobs')->first();
    expect($row->queue)->toBe('default')
        ->and((int) $row->available_at)->toBeLessThanOrEqual(now()->getTimestamp());
});

test('the real channel sends only generic content with academic tag and ttl', function () {
    $transport = fakeWebPushTransport();
    $user = webPushUser();
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir?x=1', [
        'title' => 'Penguji rahasia: Dr. Rahasia Nama',
        'description' => 'Dr. Rahasia Nama menguji Budi pada sidang tertutup.',
    ]);

    runPushJob($user, 'statusTugasAkhir', '/tugas-akhir?x=1');

    $stored = $user->notifications()->sole();
    expect($transport->sent)->toHaveCount(1);
    $push = $transport->sent[0];
    expect($push['payload'])->toMatchArray([
        'title' => 'SITA',
        'body' => 'Ada pembaruan di SITA. Buka aplikasi untuk melihatnya.',
        'tag' => 'notification:'.$stored->id,
        'data' => ['url' => '/tugas-akhir?x=1', 'userId' => $user->id, 'notificationId' => $stored->id],
    ])->and($push['options']['TTL'])->toBe(3600)
        ->and(json_encode($push['payload']))->not->toContain('Rahasia')->not->toContain('Budi')->not->toContain($user->name);
});

test('academic notifications are not debounced', function () {
    $user = webPushUser();

    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
    notifyForWebPush($user, 'jadwalBimbingan', '/tugas-akhir');

    expect(queuedPushJobs())->toBe(3);
});

test('all seven preference keys can queue a push and respect their own preference', function () {
    foreach (User::NOTIFICATION_PREFERENCE_KEYS as $key) {
        $user = webPushUser(endpoint: 'k-'.$key);
        notifyForWebPush($user, $key, '/dashboard');

        $off = webPushUser(['notification_preferences' => [$key => false]], 'off-'.$key);
        notifyForWebPush($off, $key, '/dashboard');

        expect($off->notifications()->count())->toBe(0);
    }

    expect(queuedPushJobs())->toBe(count(User::NOTIFICATION_PREFERENCE_KEYS));
});

test('chat bursts queue one delayed unique job per user and chat url', function () {
    $user = webPushUser();

    notifyForWebPush($user, 'pesanBaru', '/mahasiswa/pesan?thread=1');
    notifyForWebPush($user, 'pesanBaru', '/mahasiswa/pesan?thread=1');
    notifyForWebPush($user, 'pesanBaru', '/mahasiswa/pesan?thread=1');
    notifyForWebPush($user, 'pesanBaru', '/mahasiswa/pesan?thread=2');

    expect($user->notifications()->count())->toBe(4)
        ->and(queuedPushJobs())->toBe(2);

    $delay = (int) DB::table('jobs')->min('available_at') - now()->getTimestamp();
    expect($delay)->toBeGreaterThanOrEqual(59)->toBeLessThanOrEqual(61);
});

test('a chat burst delivered by the queue worker pushes once for the latest unread message', function () {
    $transport = fakeWebPushTransport();
    $user = webPushUser();

    foreach (range(1, 3) as $second) {
        notifyForWebPush($user, 'pesanBaru', '/dosen/pesan?thread=9');
    }
    // Same-second burst: only the time-ordered id tells which one is the latest.
    $user->notifications()->update(['created_at' => now()]);
    $latest = $user->notifications()->orderByDesc('id')->first();

    $this->travel(61)->seconds();
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect($transport->sent)->toHaveCount(1);
    $push = $transport->sent[0];
    expect($push['options']['TTL'])->toBe(900)
        ->and($push['payload']['tag'])->toBe('chat:'.$user->id.':'.substr(sha1('/dosen/pesan?thread=9'), 0, 16))
        ->and($push['payload']['data']['notificationId'])->toBe($latest->id)
        ->and(json_encode($push['payload']))->not->toContain('Budi');
    expect(queuedPushJobs())->toBe(0);
});

test('execution skips read or deleted notifications and picks the latest remaining unread chat', function () {
    $transport = fakeWebPushTransport();
    $user = webPushUser();
    notifyForWebPush($user, 'pesanBaru', '/dosen/pesan?thread=3');
    notifyForWebPush($user, 'pesanBaru', '/dosen/pesan?thread=3');
    [$older, $newer] = $user->notifications()->orderBy('id')->get()->all();
    expect($newer->id)->toBeGreaterThan($older->id);

    $newer->markAsRead();
    runPushJob($user, 'pesanBaru', '/dosen/pesan?thread=3');
    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['payload']['data']['notificationId'])->toBe($older->id);

    $older->delete();
    runPushJob($user, 'pesanBaru', '/dosen/pesan?thread=3');
    expect($transport->sent)->toHaveCount(1);

    $user->notifications()->delete();
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
    $user->notifications()->update(['read_at' => now()]);
    runPushJob($user, 'statusTugasAkhir', '/tugas-akhir');
    expect($transport->sent)->toHaveCount(1);
});

test('a push older than its ttl is dropped instead of surfacing a stale backlog', function () {
    $transport = fakeWebPushTransport();
    $user = webPushUser();
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');

    $this->travel(3601)->seconds();
    runPushJob($user, 'statusTugasAkhir', '/tugas-akhir');

    expect($transport->sent)->toBeEmpty();
});

test('feature flag account flag preference and missing subscription gate creation and execution', function () {
    $transport = fakeWebPushTransport();

    $noSubscription = User::factory()->create(['browser_notifications_enabled' => true]);
    notifyForWebPush($noSubscription, 'statusTugasAkhir', '/tugas-akhir');
    $accountOff = webPushUser(['browser_notifications_enabled' => false], 'acct');
    notifyForWebPush($accountOff, 'statusTugasAkhir', '/tugas-akhir');
    expect(queuedPushJobs())->toBe(0)
        ->and($noSubscription->notifications()->count())->toBe(1)
        ->and($accountOff->notifications()->count())->toBe(1);

    $user = webPushUser(endpoint: 'gated');
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
    expect(queuedPushJobs())->toBe(1);

    // State changes between enqueue and execution.
    $user->update(['browser_notifications_enabled' => false]);
    runPushJob($user, 'statusTugasAkhir', '/tugas-akhir');
    $user->update(['browser_notifications_enabled' => true, 'notification_preferences' => ['statusTugasAkhir' => false]]);
    runPushJob($user->fresh(), 'statusTugasAkhir', '/tugas-akhir');
    $user->update(['notification_preferences' => []]);
    config(['webpush.enabled' => false]);
    runPushJob($user->fresh(), 'statusTugasAkhir', '/tugas-akhir');
    config(['webpush.enabled' => true]);
    $user->pushSubscriptions()->delete();
    runPushJob($user->fresh(), 'statusTugasAkhir', '/tugas-akhir');

    expect($transport->sent)->toBeEmpty();

    config(['webpush.enabled' => false]);
    $disabled = webPushUser(endpoint: 'flag-off');
    notifyForWebPush($disabled, 'statusTugasAkhir', '/tugas-akhir');
    expect(queuedPushJobs())->toBe(1);
});

test('a push job never reveals another users notification', function () {
    $transport = fakeWebPushTransport();
    $owner = webPushUser(endpoint: 'owner');
    $other = webPushUser(endpoint: 'other');
    notifyForWebPush($owner, 'statusTugasAkhir', '/tugas-akhir');
    $ownerNotification = $owner->notifications()->sole();

    (new SendBrowserNotification($other->id, $ownerNotification->id, 'statusTugasAkhir', '/tugas-akhir'))->handle();

    expect($transport->sent)->toBeEmpty();
});

test('rolled back transactions create neither a notification nor a push', function () {
    $user = webPushUser();

    try {
        DB::transaction(function () use ($user): void {
            notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect($user->notifications()->count())->toBe(0)
        ->and(queuedPushJobs())->toBe(0);

    DB::transaction(function () use ($user): void {
        notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');
        expect(queuedPushJobs())->toBe(0);
    });

    expect($user->notifications()->count())->toBe(1)
        ->and(queuedPushJobs())->toBe(1);
});

test('expired subscriptions are removed by the package report handler and others kept', function () {
    $user = webPushUser(endpoint: 'gone');
    webPushSubscribe($user, 'alive');
    $stranger = webPushUser(endpoint: 'stranger');
    $transport = fakeWebPushTransport([WEB_PUSH_TEST_ENDPOINT.'gone' => 410]);
    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');

    runPushJob($user, 'statusTugasAkhir', '/tugas-akhir');

    expect($transport->sent)->toHaveCount(2)
        ->and(PushSubscription::query()->pluck('endpoint')->all())
        ->toEqualCanonicalizing([WEB_PUSH_TEST_ENDPOINT.'alive', WEB_PUSH_TEST_ENDPOINT.'stranger']);
});

test('unsafe urls never reach the push payload', function (string $url) {
    expect(BrowserPushNotification::safeUrl($url))->toBe('/');
})->with(['https://evil.test/x', '//evil.test', '/\\evil.test', "/a\nb", 'javascript:alert(1)', '']);

test('the web push channel is not used outside the queued job', function () {
    $transport = fakeWebPushTransport();
    $user = webPushUser();

    notifyForWebPush($user, 'statusTugasAkhir', '/tugas-akhir');

    expect($transport->sent)->toBeEmpty();
});
