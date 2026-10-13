<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Services\Social\Discord\DiscordClient;

/**
 * The meta a post stores for its channel, on every create and update: the
 * format cleaned up (`PostPlatformMetaRules::normalize`), then what the meta
 * names from the channel's own data filled in, so the web, the API and MCP
 * store the same thing from an id alone. One arm per network that needs it.
 *
 * A lookup that fails or does not find the id keeps what the meta already
 * had: saving never fails over a display name.
 */
class PostMeta
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $stored  the meta the post had before this save
     * @return array<string, mixed>
     */
    public static function forChannel(SocialAccount $account, array $meta, array $stored = []): array
    {
        $meta = PostPlatformMetaRules::normalize($meta);

        return match ($account->platform) {
            Platform::Discord => self::withDiscordChannelName($account, $meta, $stored),
            default => $meta,
        };
    }

    /**
     * The Discord channel name the preview shows, read from the server's
     * channel list cached by the composer, the API, MCP or a publish. Saving
     * never asks Discord: it runs inside the post's transaction and queue
     * lock. Without a cached list the name sent is kept, except a name left
     * from the previous channel when the channel changed.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private static function withDiscordChannelName(SocialAccount $account, array $meta, array $stored): array
    {
        $channelId = data_get($meta, 'channel_id');

        if (blank($channelId)) {
            return $meta;
        }

        $channel = collect(app(DiscordClient::class)->cachedChannels((string) $account->platform_user_id) ?? [])
            ->firstWhere('id', (string) $channelId);

        if (filled($name = data_get($channel, 'name'))) {
            return [...$meta, 'channel_name' => $name];
        }

        $keptFromPreviousChannel = (string) data_get($stored, 'channel_id') !== (string) $channelId
            && data_get($meta, 'channel_name') === data_get($stored, 'channel_name');

        return $keptFromPreviousChannel ? array_diff_key($meta, ['channel_name' => true]) : $meta;
    }
}
