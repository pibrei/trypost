<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\SocialAccount\ForgetMetaUser;
use App\Enums\MetaDataDeletionRequest\Status;
use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Models\MetaDataDeletionRequest;
use App\Services\Social\Meta\SignedRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meta's Deauthorize and Data Deletion Request callbacks. Both post a
 * `signed_request`; whichever configured app secret signed it tells which
 * platforms the user id belongs to.
 */
class MetaCallbackController extends Controller
{
    /**
     * App secret config key => platforms whose user ids that app issues.
     *
     * @var array<string, list<Platform>>
     */
    private const APPS = [
        'services.facebook.client_secret' => [Platform::Facebook, Platform::InstagramFacebook],
        'services.instagram.client_secret' => [Platform::Instagram],
        'services.threads.client_secret' => [Platform::Threads],
    ];

    public function dataDeletion(Request $request): JsonResponse
    {
        [$metaUserId, $platforms] = $this->verifiedCaller($request);

        $result = ForgetMetaUser::erase($metaUserId, $platforms);

        $deletion = MetaDataDeletionRequest::query()
            ->where('meta_user_id', $metaUserId)
            ->latest()
            ->first() ?? new MetaDataDeletionRequest([
                'confirmation_code' => Str::lower(Str::random(32)),
                'meta_user_id' => $metaUserId,
                'affected_accounts' => [],
            ]);

        $completed = data_get($result, 'failed') === 0;

        $deletion->fill([
            'status' => $completed ? Status::Completed : Status::Failed,
            'affected_accounts' => [...($deletion->affected_accounts ?? []), ...data_get($result, 'removed')],
            'completed_at' => $completed ? ($deletion->completed_at ?? now()) : null,
        ])->save();

        $base = rtrim((string) config('app.url'), '/');
        $path = route('legal.data-deletion.status', $deletion->confirmation_code, absolute: false);

        return response()->json([
            'url' => "{$base}{$path}",
            'confirmation_code' => $deletion->confirmation_code,
        ]);
    }

    public function deauthorize(Request $request): JsonResponse
    {
        [$metaUserId, $platforms] = $this->verifiedCaller($request);

        ForgetMetaUser::revoke($metaUserId, $platforms);

        return response()->json(['success' => true]);
    }

    /**
     * @return array{0: string, 1: list<Platform>}
     */
    private function verifiedCaller(Request $request): array
    {
        $signedRequest = $request->input('signed_request');
        $metaUserId = null;
        $platforms = [];

        if (is_string($signedRequest)) {
            foreach (self::APPS as $secretKey => $appPlatforms) {
                $payload = SignedRequest::verify($signedRequest, (string) config($secretKey));
                $userId = data_get($payload, 'user_id');

                if ((is_string($userId) || is_int($userId)) && (string) $userId !== '' && ($metaUserId === null || $metaUserId === (string) $userId)) {
                    $metaUserId = (string) $userId;
                    $platforms = [...$platforms, ...$appPlatforms];
                }
            }
        }

        abort_if($metaUserId === null, Response::HTTP_BAD_REQUEST, 'Invalid signed_request.');

        return [$metaUserId, $platforms];
    }
}
