<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\CreatePostTool;
use App\Mcp\Tools\Post\UpdatePostTool;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Social\Discord\DiscordClient;

beforeEach(function () {
    ['user' => $this->user, 'workspace' => $this->workspace, 'token' => $this->token] = parityContext();
    $this->discord = SocialAccount::factory()->discord()->create(['workspace_id' => $this->workspace->id]);
});

/**
 * Creates a Discord draft through one surface with only the channel id.
 *
 * @param  array<string, mixed>  $meta
 */
function discordChannelNameCreate(string $surface, array $meta): Post
{
    $before = Post::query()->pluck('id')->all();
    $destination = ['social_account_id' => test()->discord->id, 'content_type' => ContentType::DiscordMessage->value, 'meta' => $meta];

    match ($surface) {
        'web' => test()->actingAs(test()->user)->post(route('app.posts.store'), ['status' => 'draft', 'content' => 'Release notes', 'destinations' => [$destination]])->assertSessionHasNoErrors(),
        'api' => test()->withHeaders(parityApi(test()->token))->postJson(route('api.posts.store'), ['status' => 'draft', 'content' => 'Release notes', ...$destination])->assertCreated(),
        'mcp' => TryPostServer::actingAs(test()->user)->tool(CreatePostTool::class, ['status' => 'draft', 'content' => 'Release notes', ...$destination])->assertOk(),
    };

    return Post::query()->whereNotIn('id', $before)->sole();
}

/**
 * @param  array<string, mixed>  $meta
 */
function discordChannelNameUpdate(string $surface, Post $post, array $meta): void
{
    $payload = ['status' => Status::Draft->value, 'content' => $post->content, 'content_type' => ContentType::DiscordMessage->value, 'meta' => $meta];

    match ($surface) {
        'web' => test()->actingAs(test()->user)->putJson(route('app.posts.update', $post), [...$payload, 'media' => []])->assertSessionHasNoErrors(),
        'api' => test()->withHeaders(parityApi(test()->token))->putJson(route('api.posts.update', $post), $payload)->assertOk(),
        'mcp' => TryPostServer::actingAs(test()->user)->tool(UpdatePostTool::class, ['post_id' => $post->id, ...$payload])->assertOk(),
    };
}

test('a discord post created or edited with only the channel id stores its name on the web, api and mcp', function (string $surface) {
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturn([
        ['id' => '111', 'name' => 'general'],
        ['id' => '222', 'name' => 'announcements'],
    ]);

    $post = discordChannelNameCreate($surface, ['channel_id' => '111']);
    expect(data_get($post->meta, 'channel_name'))->toBe('general');

    auth()->forgetGuards();
    discordChannelNameUpdate($surface, $post, ['channel_id' => '222']);
    expect(data_get($post->fresh()->meta, 'channel_name'))->toBe('announcements');
})->with(['web', 'api', 'mcp']);

test('a discord channel name sent by the client is replaced by the server list name', function (string $surface) {
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturn([['id' => '111', 'name' => 'general']]);

    $post = discordChannelNameCreate($surface, ['channel_id' => '111', 'channel_name' => 'stale']);

    expect(data_get($post->meta, 'channel_name'))->toBe('general');
})->with(['web', 'api', 'mcp']);

test('a discord channel missing from the list or a failing lookup keeps the stored name and still saves', function (string $surface, Closure $channels) {
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturnUsing($channels);
    $post = Post::factory()->forAccount($this->discord, ContentType::DiscordMessage)->create([
        'user_id' => $this->user->id,
        'content' => 'Release notes',
        'meta' => ['channel_id' => '999', 'channel_name' => 'old-channel'],
    ]);

    discordChannelNameUpdate($surface, $post, ['channel_id' => '999']);

    expect($post->fresh()->meta)->toMatchArray(['channel_id' => '999', 'channel_name' => 'old-channel']);
})->with(['web', 'api', 'mcp'])->with([
    'channel not in the list' => [fn () => fn (): array => [['id' => '111', 'name' => 'general']]],
    'discord unavailable' => [fn () => fn (): never => throw new PlatformUnavailableException('Discord channel lookup failed (500).', 500)],
]);

test('only discord posts look up a channel name', function () {
    $this->mock(DiscordClient::class)->shouldNotReceive('channels');
    $linkedin = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::LinkedIn]);

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), [
        'status' => 'draft',
        'content' => 'Hello',
        'social_account_id' => $linkedin->id,
        'content_type' => ContentType::LinkedInPost->value,
        'meta' => ['channel_id' => '111'],
    ])->assertCreated();
});
