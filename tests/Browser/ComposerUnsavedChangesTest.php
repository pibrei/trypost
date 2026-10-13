<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\Discord\DiscordClient;
use Illuminate\Support\Facades\Http;

function seedUnsavedChangesReel(): Post
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Instagram]);
    $post = Post::factory()->forAccount($account, ContentType::InstagramReel)->create([
        'user_id' => $user->id,
        'content' => 'Unsaved changes',
        'meta' => [],
    ]);
    test()->actingAs($user);

    return $post;
}

function waitForUnsavedChangesCondition(mixed $page, string $condition): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 300; i++) {
                if ({$condition}) return;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForUnsavedChangesTestId(mixed $page, string $testId): void
{
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"{$testId}\"]')?.getBoundingClientRect().height > 0");
}

test('closing an edited post without changes asks nothing', function () {
    $post = seedUnsavedChangesReel();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')
        ->assertMissing('@unsaved-changes-dialog')
        ->assertNoJavaScriptErrors();
});

test('closing an edited post with unsaved changes asks first, keeps it open on cancel and drops the changes on discard', function () {
    $post = seedUnsavedChangesReel();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->click('@instagram-share-to-feed')
        ->click('@composer-close');
    waitForUnsavedChangesTestId($page, 'unsaved-changes-dialog');

    $page->click('@unsaved-changes-keep');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"unsaved-changes-dialog\"]')");
    $page->assertVisible('@post-composer-dialog')
        ->click('@composer-close');
    waitForUnsavedChangesTestId($page, 'unsaved-changes-discard');
    $page->click('@unsaved-changes-discard');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')->assertNoJavaScriptErrors();
    expect($post->fresh()->meta)->toBe([]);
});

test('saving an edited post closes it without asking', function () {
    $post = seedUnsavedChangesReel();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->click('@instagram-share-to-feed')
        ->click('@composer-save-draft');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')->assertNoJavaScriptErrors();
    expect(data_get($post->fresh()->meta, 'share_to_feed'))->toBeFalse();
});

test('closing an untouched post of any network asks nothing, even when its settings fill in on their own', function (Platform $platform, ContentType $contentType, array $meta) {
    Http::fake([
        config('trypost.platforms.pinterest.api').'/boards*' => Http::response(['items' => [['id' => 'board_1', 'name' => 'Ideas']]]),
        '*' => Http::response([], 200),
    ]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => $platform,
        'access_token' => 'channel-token',
        'token_expires_at' => now()->addDays(20),
    ]);
    $post = Post::factory()->forAccount($account, $contentType)->create([
        'user_id' => $user->id,
        'content' => 'Untouched post',
        'meta' => $meta,
    ]);
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturn([['id' => '111', 'name' => 'general', 'type' => 0]]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'channel-settings-rows');
    $page->script('new Promise((resolve) => setTimeout(resolve, 1000))');
    $page->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')
        ->assertMissing('@post-composer-dialog')
        ->assertNoJavaScriptErrors();
})->with([
    'youtube without a title' => [Platform::YouTube, ContentType::YouTubeShort, []],
    'pinterest with a board' => [Platform::Pinterest, ContentType::PinterestPin, ['board_id' => 'board_1']],
    'tiktok' => [Platform::TikTok, ContentType::TikTokVideo, []],
    'google business' => [Platform::GoogleBusiness, ContentType::GoogleBusinessPost, []],
    'discord without a channel name' => [Platform::Discord, ContentType::DiscordMessage, ['channel_id' => '111']],
]);

test('closing an untouched post with media, a time or a queue slot asks nothing', function (ContentType $contentType, array $media, array $schedule) {
    $post = seedUnsavedChangesReel();
    $post->forceFill(['content_type' => $contentType, 'media' => $media, ...$schedule])->save();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'channel-settings-rows');
    $page->script('new Promise((resolve) => setTimeout(resolve, 1000))');
    $page->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')->assertNoJavaScriptErrors();
})->with([
    'reel video, scheduled' => [ContentType::InstagramReel, [[
        'id' => 'video-1', 'type' => 'video', 'path' => 'medias/clip.mp4', 'url' => 'https://example.com/clip.mp4',
        'mime_type' => 'video/mp4', 'original_filename' => 'clip.mp4', 'meta' => ['cover_offset_ms' => 1200],
    ]], ['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]],
    'carousel with tags, draft' => [ContentType::InstagramFeed, [
        ['id' => 'image-1', 'type' => 'image', 'path' => 'medias/a.jpg', 'url' => 'https://example.com/a.jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'a.jpg', 'meta' => ['user_tags' => [['username' => 'alice', 'x' => 0.5, 'y' => 0.5]]]],
        ['id' => 'image-2', 'type' => 'image', 'path' => 'medias/b.jpg', 'url' => 'https://example.com/b.jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'b.jpg'],
    ], []],
]);

test('switching only the schedule of an edited post asks before closing', function () {
    $post = seedUnsavedChangesReel();
    $post->forceFill(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()])->save();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'composer-schedule-trigger');
    $page->click('@composer-schedule-trigger');
    waitForUnsavedChangesTestId($page, 'composer-schedule-more-actions');
    $page->click('@composer-schedule-more-actions');
    waitForUnsavedChangesTestId($page, 'composer-schedule-now');
    $page->click('@composer-schedule-now');
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"composer-submit\"]')?.dataset.scheduleMode === 'now'");

    $page->click('@composer-close');
    waitForUnsavedChangesTestId($page, 'unsaved-changes-dialog');

    $page->assertVisible('@unsaved-changes-dialog')->assertNoJavaScriptErrors();
});

test('picking a discord channel stores its name for the preview', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Discord]);
    $post = Post::factory()->forAccount($account, ContentType::DiscordMessage)->create([
        'user_id' => $user->id,
        'content' => 'Release notes',
        'meta' => [],
    ]);
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturn([['id' => '111', 'name' => 'general', 'type' => 0]]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"channel-settings-rows\"] [role=\"combobox\"]:not([disabled])')");
    $page->assertNoJavaScriptErrors()
        ->script("document.querySelector('[data-testid=\"channel-settings-rows\"] [role=\"combobox\"]').click()");
    waitForUnsavedChangesCondition($page, "[...document.querySelectorAll('[role=\"option\"]')].some((option) => option.textContent.includes('general'))");
    $page->script("[...document.querySelectorAll('[role=\"option\"]')].find((option) => option.textContent.includes('general')).click()");
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"channel-settings-rows\"] [role=\"combobox\"]')?.textContent.includes('general')");

    $page->click('@composer-save-draft');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    expect($post->fresh()->meta)->toMatchArray(['channel_id' => '111', 'channel_name' => 'general']);
});

test('following an app link away from an edited post asks first and leaves only on discard', function () {
    $post = seedUnsavedChangesReel();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->click('@instagram-share-to-feed')
        ->script("document.querySelector('[data-testid=\"sidebar-logo\"]').click()");
    waitForUnsavedChangesTestId($page, 'unsaved-changes-dialog');

    $page->assertVisible('@post-composer-dialog')
        ->click('@unsaved-changes-keep');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"unsaved-changes-dialog\"]')");
    $page->assertVisible('@post-composer-dialog')
        ->script("document.querySelector('[data-testid=\"sidebar-logo\"]').click()");
    waitForUnsavedChangesTestId($page, 'unsaved-changes-discard');
    $page->click('@unsaved-changes-discard');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')->assertNoJavaScriptErrors();
    expect($post->fresh()->meta)->toBe([]);
});

function openUnsavedChangesFromList(Post $post): mixed
{
    $page = visit(route('app.posts.index', ['tab' => 'drafts']))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, "post-edit-{$post->id}");
    $page->click("@post-edit-{$post->id}");
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');

    return $page;
}

test('the browser back button on an edited post asks first, keeps the edit on cancel and goes back on discard', function () {
    $post = seedUnsavedChangesReel();
    $page = openUnsavedChangesFromList($post);

    $page->click('@instagram-share-to-feed');
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"instagram-share-to-feed\"]')?.getAttribute('aria-checked') === 'false'");
    $page->back();
    waitForUnsavedChangesTestId($page, 'unsaved-changes-dialog');

    $page->assertVisible('@post-composer-dialog')
        ->click('@unsaved-changes-keep');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"unsaved-changes-dialog\"]')");
    $page->assertVisible('@post-composer-dialog')
        ->assertAttribute('@instagram-share-to-feed', 'aria-checked', 'false');

    $page->back();
    waitForUnsavedChangesTestId($page, 'unsaved-changes-discard');
    $page->click('@unsaved-changes-discard');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')
        ->assertQueryStringMissing('edit')
        ->assertNoJavaScriptErrors();
    expect($post->fresh()->meta)->toBe([]);
});

test('the browser back button on an untouched post closes it without asking', function () {
    $post = seedUnsavedChangesReel();
    $page = openUnsavedChangesFromList($post);

    $page->back();
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')
        ->assertMissing('@unsaved-changes-dialog')
        ->assertNoJavaScriptErrors();
});

test('after saving an edited post the browser back button does not reopen it or ask', function () {
    $post = seedUnsavedChangesReel();
    $page = openUnsavedChangesFromList($post);

    $page->click('@instagram-share-to-feed')
        ->click('@composer-save-draft');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");
    $page->back();
    $page->script('new Promise((resolve) => setTimeout(resolve, 800))');

    $page->assertMissing('@post-composer-dialog')
        ->assertMissing('@unsaved-changes-dialog')
        ->assertQueryStringMissing('edit')
        ->assertNoJavaScriptErrors();
    expect(data_get($post->fresh()->meta, 'share_to_feed'))->toBeFalse();
});

test('after discarding an edited post the browser back button does not reopen it or ask', function () {
    $post = seedUnsavedChangesReel();
    $page = openUnsavedChangesFromList($post);

    $page->click('@instagram-share-to-feed')
        ->click('@composer-close');
    waitForUnsavedChangesTestId($page, 'unsaved-changes-discard');
    $page->click('@unsaved-changes-discard');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");
    $page->back();
    $page->script('new Promise((resolve) => setTimeout(resolve, 800))');

    $page->assertMissing('@post-composer-dialog')
        ->assertMissing('@unsaved-changes-dialog')
        ->assertNoJavaScriptErrors();
    expect($post->fresh()->meta)->toBe([]);
});

test('closing an untouched tiktok post asks nothing when the creator settings turn interactions off', function (Platform $platform, ContentType $contentType, array $meta, string $settledCondition) {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/post/publish/creator_info/query/' => Http::response(['data' => [
            'creator_nickname' => 'Tik',
            'creator_username' => 'tiktoker',
            'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'],
            'comment_disabled' => true,
            'duet_disabled' => true,
            'stitch_disabled' => true,
            'max_video_post_duration_sec' => 600,
        ]]),
        '*' => Http::response([], 200),
    ]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => $platform,
        'access_token' => 'channel-token',
        'token_expires_at' => now()->addDays(20),
    ]);
    $post = Post::factory()->forAccount($account, $contentType)->create([
        'user_id' => $user->id,
        'content' => 'Untouched post',
        'meta' => $meta,
    ]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'channel-settings-rows');
    waitForUnsavedChangesCondition($page, $settledCondition);
    $page->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')
        ->assertMissing('@post-composer-dialog')
        ->assertNoJavaScriptErrors();
})->with([
    'tiktok creator turns interactions off' => [Platform::TikTok, ContentType::TikTokVideo, ['allow_comments' => true, 'allow_duet' => true, 'allow_stitch' => true], "document.querySelector('[data-testid=\"tiktok-allow-comments\"]')?.disabled === true"],
]);

test('a music cover renewed after the post opened does not count as an unsaved change', function () {
    $pixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    Http::fake([
        config('trypost.platforms.instagram-facebook.graph_api').'/846373660299207*' => Http::response([
            'audio_id' => '846373660299207', 'title' => 'Summer Song', 'cover_artwork_thumbnail_uri' => "{$pixel}#fresh",
        ]),
        '*' => Http::response([], 200),
    ]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::InstagramFacebook]);
    $post = Post::factory()->forAccount($account, ContentType::InstagramReel)->create([
        'user_id' => $user->id,
        'content' => 'Reel with music',
        'meta' => ['audio' => ['id' => '846373660299207', 'title' => 'Summer Song', 'cover_url' => $pixel]],
    ]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-music-selected-cover');
    $page->script('new Promise((resolve) => setTimeout(resolve, 500))');
    $page->script("document.querySelector('[data-testid=\"instagram-music-selected-cover\"]').dispatchEvent(new Event('error'))");
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"instagram-music-selected-cover\"]')?.getAttribute('src')?.endsWith('#fresh')");
    $page->assertAttributeContains('@instagram-music-selected-cover', 'src', '#fresh')
        ->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')
        ->assertMissing('@post-composer-dialog')
        ->assertNoJavaScriptErrors();
});

test('after leaving an edited post through an app link the browser back button returns to the post once', function () {
    $post = seedUnsavedChangesReel();
    $page = openUnsavedChangesFromList($post);

    $page->click('@instagram-share-to-feed')
        ->script("document.querySelector('[data-testid=\"sidebar-logo\"]').click()");
    waitForUnsavedChangesTestId($page, 'unsaved-changes-discard');
    $page->click('@unsaved-changes-discard');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->back();
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->assertAttribute('@instagram-share-to-feed', 'aria-checked', 'true');

    $page->back();
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@post-composer-dialog')
        ->assertMissing('@unsaved-changes-dialog')
        ->assertQueryStringMissing('edit')
        ->assertNoJavaScriptErrors();
});

test('pressing escape on an edited post asks first', function () {
    $post = seedUnsavedChangesReel();

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'instagram-share-to-feed');
    $page->click('@instagram-share-to-feed')
        ->keys('@instagram-share-to-feed', 'Escape');
    waitForUnsavedChangesTestId($page, 'unsaved-changes-dialog');

    $page->assertVisible('@unsaved-changes-dialog')
        ->assertVisible('@post-composer-dialog')
        ->assertNoJavaScriptErrors();
});

test('closing a new post with text never asks, since it is kept as an autosaved draft', function () {
    $post = seedUnsavedChangesReel();
    $account = $post->socialAccount;

    $page = visit(route('app.posts.index'))->resize(1280, 900);
    waitForUnsavedChangesTestId($page, 'posts-new-post');
    $page->click('@posts-new-post');
    waitForUnsavedChangesTestId($page, 'composer-add-account');
    $page->click('@composer-add-account')
        ->click("@composer-account-option-{$account->id}")
        ->fill("@composer-caption-{$account->id}", 'A new idea')
        ->click('@composer-close');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertMissing('@unsaved-changes-dialog')
        ->assertMissing('@post-composer-dialog')
        ->assertNoJavaScriptErrors();
});

test('a discord channel missing from the list keeps its stored name', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('member'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Discord]);
    $post = Post::factory()->forAccount($account, ContentType::DiscordMessage)->create([
        'user_id' => $user->id,
        'content' => 'Release notes',
        'meta' => ['channel_id' => '999', 'channel_name' => 'announcements'],
    ]);
    $this->mock(DiscordClient::class)->shouldReceive('channels')->andReturn([['id' => '111', 'name' => 'general', 'type' => 0]]);
    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(1280, 900);
    waitForUnsavedChangesCondition($page, "document.querySelector('[data-testid=\"channel-settings-rows\"] [role=\"combobox\"]:not([disabled])')?.textContent.includes('announcements')");

    expect($page->script("document.querySelector('[data-testid=\"channel-settings-rows\"] [role=\"combobox\"]').textContent.trim()"))->toBe('#announcements');
    $page->click('@composer-save-draft');
    waitForUnsavedChangesCondition($page, "!document.querySelector('[data-testid=\"post-composer-dialog\"]')");

    $page->assertNoJavaScriptErrors();
    expect($post->fresh()->meta)->toMatchArray(['channel_id' => '999', 'channel_name' => 'announcements']);
});
