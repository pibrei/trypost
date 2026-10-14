<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Actions\Post\DeleteChannelPosts;
use App\Actions\Post\Queue\ReflowChannelQueue;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Exceptions\Post\QueueBusyException;
use App\Models\AnalyticsAccountDailySnapshot;
use App\Models\AnalyticsPublication;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acts on Meta's deauthorize and data deletion callbacks for one Meta user,
 * across every workspace.
 *
 * Facebook Pages and Instagram via Facebook keep the authorizing Facebook
 * user in `meta.user_id`. Legacy Instagram connections without that id are
 * found through a Facebook Page of that user in the same workspace.
 * Instagram (direct login) and Threads use their own `platform_user_id`.
 */
class ForgetMetaUser
{
    /**
     * Removes every matching channel the way a manual disconnect does (its posts
     * and media, then the account and its tokens) and, unlike a disconnect, also
     * the analytics collected for that identity, since Meta asked for its data.
     *
     * @param  list<Platform>  $platforms
     * @return array{removed: list<array{id: string, platform: string}>, failed: int}
     */
    public static function erase(string $metaUserId, array $platforms): array
    {
        $removed = [];
        $failed = 0;

        foreach (self::accounts($metaUserId, $platforms) as $account) {
            try {
                ReflowChannelQueue::withLock([$account->id], fn () => DB::transaction(function () use ($account): void {
                    DeleteChannelPosts::forAccount($account);
                    self::deleteAnalytics($account);

                    $account->delete();
                }));

                $removed[] = ['id' => $account->id, 'platform' => $account->platform->value];
            } catch (QueueBusyException $exception) {
                Log::warning('Meta data deletion could not remove a channel yet', [
                    'account_id' => $account->id,
                    'error' => $exception->getMessage(),
                ]);

                $failed++;
            }
        }

        return ['removed' => $removed, 'failed' => $failed];
    }

    /**
     * Marks every matching channel disconnected and wipes its credentials, keeping
     * the channel and its posts so the workspace can reconnect it.
     *
     * @param  list<Platform>  $platforms
     */
    public static function revoke(string $metaUserId, array $platforms): int
    {
        $accounts = self::accounts($metaUserId, $platforms);

        foreach ($accounts as $account) {
            $account->forceFill([
                'status' => Status::Disconnected,
                'error_message' => 'The app was removed from the Meta account.',
                'disconnected_at' => $account->disconnected_at ?? now(),
                'access_token' => '',
                'refresh_token' => null,
                'token_expires_at' => null,
                'meta' => Arr::except($account->meta ?? [], ['user_token']),
            ])->save();
        }

        return $accounts->count();
    }

    private static function deleteAnalytics(SocialAccount $account): void
    {
        foreach ([AnalyticsPublication::class, AnalyticsAccountDailySnapshot::class] as $model) {
            $model::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('network', $account->platform->network())
                ->where('platform_user_id', $account->platform_user_id)
                ->delete();
        }
    }

    /**
     * @param  list<Platform>  $platforms
     * @return Collection<int, SocialAccount>
     */
    public static function accounts(string $metaUserId, array $platforms): Collection
    {
        if ($metaUserId === '' || $platforms === []) {
            return new Collection;
        }

        $facebookPages = in_array(Platform::Facebook, $platforms, true) || in_array(Platform::InstagramFacebook, $platforms, true)
            ? SocialAccount::query()
                ->where('platform', Platform::Facebook)
                ->where('meta->user_id', $metaUserId)
                ->get()
            : new Collection;

        $accounts = in_array(Platform::Facebook, $platforms, true) ? $facebookPages : new Collection;

        if (in_array(Platform::InstagramFacebook, $platforms, true)) {
            $accounts = $accounts->merge(SocialAccount::query()
                ->where('platform', Platform::InstagramFacebook)
                ->where(function (Builder $query) use ($metaUserId, $facebookPages): void {
                    $query->where('meta->user_id', $metaUserId);

                    foreach ($facebookPages as $page) {
                        $pageId = data_get($page->meta, 'page_id');

                        if (filled($pageId)) {
                            $query->orWhere(fn (Builder $samePage) => $samePage
                                ->whereNull('meta->user_id')
                                ->where('workspace_id', $page->workspace_id)
                                ->where('meta->page_id', (string) $pageId));
                        }
                    }
                })
                ->get());
        }

        $byOwnId = array_values(array_filter(
            $platforms,
            fn (Platform $platform): bool => in_array($platform, [Platform::Instagram, Platform::Threads], true),
        ));

        if ($byOwnId !== []) {
            $accounts = $accounts->merge(SocialAccount::query()
                ->whereIn('platform', $byOwnId)
                ->where('platform_user_id', $metaUserId)
                ->get());
        }

        return $accounts->unique('id')->values();
    }
}
