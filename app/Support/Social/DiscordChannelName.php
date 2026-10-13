<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Actions\SocialAccount\ListDiscordChannels;
use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;

/**
 * The name a Discord post shows for its channel, read from the server's
 * channel list (cached) whenever a post is saved, so the web, the API and
 * MCP store the same name from the channel id alone. A channel missing from
 * the list, or Discord failing, keeps the name the post already had: saving
 * never fails over a display name.
 */
class DiscordChannelName
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function fill(?SocialAccount $account, array $meta): array
    {
        $channelId = data_get($meta, 'channel_id');

        if ($account?->platform !== Platform::Discord || blank($channelId)) {
            return $meta;
        }

        $channel = collect(rescue(fn (): array => ListDiscordChannels::execute($account), [], report: false))
            ->firstWhere('id', (string) $channelId);
        $name = data_get($channel, 'name');

        return filled($name) ? [...$meta, 'channel_name' => $name] : $meta;
    }
}
