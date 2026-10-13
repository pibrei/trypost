<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\SocialAccount\ListDiscordChannels;
use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;

/**
 * What a post's meta names from its channel's own data, filled in on every
 * create and update so the web, the API and MCP store the same thing from
 * an id alone. One arm per network that needs it.
 *
 * A lookup that fails or does not find the id keeps what the meta already
 * had: saving never fails over a display name.
 */
class ChannelMeta
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function fillFromAccount(SocialAccount $account, array $meta): array
    {
        return match ($account->platform) {
            Platform::Discord => self::withDiscordChannelName($account, $meta),
            default => $meta,
        };
    }

    /**
     * The Discord channel name the preview shows, read from the server's
     * channel list (cached for a few minutes).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function withDiscordChannelName(SocialAccount $account, array $meta): array
    {
        $channelId = data_get($meta, 'channel_id');

        if (blank($channelId)) {
            return $meta;
        }

        $channel = collect(rescue(fn (): array => ListDiscordChannels::execute($account), [], report: false))
            ->firstWhere('id', (string) $channelId);
        $name = data_get($channel, 'name');

        return filled($name) ? [...$meta, 'channel_name' => $name] : $meta;
    }
}
