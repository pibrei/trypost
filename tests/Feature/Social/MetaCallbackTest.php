<?php

declare(strict_types=1);

use App\Enums\MetaDataDeletionRequest\Status as DeletionStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\AnalyticsPublication;
use App\Models\MetaDataDeletionRequest;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('services.facebook.client_secret', 'facebook-secret');
    config()->set('services.instagram.client_secret', 'instagram-secret');
    config()->set('services.threads.client_secret', 'threads-secret');
    config()->set('app.url', 'https://social.example.test');
});

/**
 * @param  array<string, mixed>  $payload
 */
function metaCallbackSignedRequest(array $payload, string $secret = 'facebook-secret'): string
{
    $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $encodedPayload = $encode(json_encode(['algorithm' => 'HMAC-SHA256', 'issued_at' => 1700000000, ...$payload]));

    return $encode(hash_hmac('sha256', $encodedPayload, $secret, true)).'.'.$encodedPayload;
}

function metaCallbackFacebookPage(Workspace $workspace, string $facebookUserId, string $pageId): SocialAccount
{
    return SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
        'platform_user_id' => $pageId,
        'meta' => ['page_id' => $pageId, 'user_id' => $facebookUserId, 'user_token' => 'user-token'],
    ]);
}

test('a data deletion request removes only the channels of that meta user', function () {
    $workspace = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();

    $page = metaCallbackFacebookPage($workspace, 'fb-user-1', 'page-1');
    $pageElsewhere = metaCallbackFacebookPage($otherWorkspace, 'fb-user-1', 'page-9');
    $instagramViaPage = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::InstagramFacebook,
        'meta' => ['page_id' => 'page-1', 'page_name' => 'Page'],
    ]);
    $instagramWithUser = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::InstagramFacebook,
        'meta' => ['page_id' => 'page-7', 'user_id' => 'fb-user-1'],
    ]);

    $someoneElse = metaCallbackFacebookPage($workspace, 'fb-user-2', 'page-2');
    $samePageOtherWorkspace = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::InstagramFacebook,
        'meta' => ['page_id' => 'page-1'],
    ]);
    $linkedin = SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id, 'platform_user_id' => 'fb-user-1']);

    $post = Post::factory()->forAccount($page)->create();
    $keptPost = Post::factory()->forAccount($someoneElse)->create();
    $publication = AnalyticsPublication::factory()->create([
        'workspace_id' => $workspace->id,
        'network' => Platform::Facebook->network(),
        'platform_user_id' => 'page-1',
        'platform' => Platform::Facebook,
    ]);

    $response = $this->post(route('meta.data-deletion'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1']),
    ]);

    $deletion = MetaDataDeletionRequest::query()->sole();

    $response->assertOk()->assertExactJson([
        'url' => "https://social.example.test/data-deletion/status/{$deletion->confirmation_code}",
        'confirmation_code' => $deletion->confirmation_code,
    ]);

    foreach ([$page, $pageElsewhere, $instagramViaPage, $instagramWithUser] as $removed) {
        $this->assertModelMissing($removed);
    }

    foreach ([$someoneElse, $samePageOtherWorkspace, $linkedin] as $kept) {
        $this->assertModelExists($kept);
    }

    $this->assertModelMissing($post);
    $this->assertModelExists($keptPost);
    $this->assertModelMissing($publication);

    expect($deletion->status)->toBe(DeletionStatus::Completed)
        ->and($deletion->meta_user_id)->toBe('fb-user-1')
        ->and($deletion->completed_at)->not->toBeNull()
        ->and(collect($deletion->affected_accounts)->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$page->id, $pageElsewhere->id, $instagramViaPage->id, $instagramWithUser->id])->sort()->values()->all());
});

test('a repeated data deletion request for the same user reuses its confirmation code', function () {
    $workspace = Workspace::factory()->create();
    $page = metaCallbackFacebookPage($workspace, 'fb-user-1', 'page-1');

    $first = $this->post(route('meta.data-deletion'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1']),
    ])->assertOk();

    $second = $this->post(route('meta.data-deletion'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1']),
    ])->assertOk();

    expect($second->json('confirmation_code'))->toBe($first->json('confirmation_code'))
        ->and(MetaDataDeletionRequest::query()->count())->toBe(1)
        ->and(MetaDataDeletionRequest::query()->sole()->affected_accounts)->toHaveCount(1);

    $this->assertModelMissing($page);

    $this->get($second->json('url'))->assertOk()->assertSee('Concluída');
});

test('a data deletion request for a user without channels still answers with a code', function () {
    $this->post(route('meta.data-deletion'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'nobody']),
    ])->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

    expect(MetaDataDeletionRequest::query()->sole()->affected_accounts)->toBe([]);
});

test('a request signed with the instagram app secret removes the direct instagram account', function () {
    $instagram = SocialAccount::factory()->instagram()->create(['platform_user_id' => 'ig-user-1']);
    $facebookSameId = metaCallbackFacebookPage(Workspace::factory()->create(), 'ig-user-1', 'page-1');

    $this->post(route('meta.data-deletion'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'ig-user-1'], 'instagram-secret'),
    ])->assertOk();

    $this->assertModelMissing($instagram);
    $this->assertModelExists($facebookSameId);
});

test('the meta callbacks reject an invalid signed request', function (string $route, ?string $signedRequest) {
    $page = metaCallbackFacebookPage(Workspace::factory()->create(), 'fb-user-1', 'page-1');

    $this->post(route($route), array_filter(['signed_request' => $signedRequest]))->assertBadRequest();

    $this->assertModelExists($page);
    expect(MetaDataDeletionRequest::query()->count())->toBe(0);
})->with([
    'deletion, wrong secret' => fn () => ['meta.data-deletion', metaCallbackSignedRequest(['user_id' => 'fb-user-1'], 'not-the-secret')],
    'deletion, missing' => ['meta.data-deletion', null],
    'deletion, malformed' => ['meta.data-deletion', 'not-a-signed-request'],
    'deletion, tampered payload' => fn () => ['meta.data-deletion', explode('.', metaCallbackSignedRequest(['user_id' => 'fb-user-1']))[0].'.'.rtrim(strtr(base64_encode(json_encode(['algorithm' => 'HMAC-SHA256', 'user_id' => 'fb-user-2'])), '+/', '-_'), '=')],
    'deletion, wrong algorithm' => fn () => ['meta.data-deletion', metaCallbackSignedRequest(['user_id' => 'fb-user-1', 'algorithm' => 'HMAC-SHA1'])],
    'deletion, no user' => fn () => ['meta.data-deletion', metaCallbackSignedRequest([])],
    'deauthorize, wrong secret' => fn () => ['meta.deauthorize', metaCallbackSignedRequest(['user_id' => 'fb-user-1'], 'not-the-secret')],
]);

test('the meta callbacks refuse every request while no app secret is configured', function () {
    config()->set('services.facebook.client_secret', null);
    config()->set('services.instagram.client_secret', null);
    config()->set('services.threads.client_secret', null);

    $this->post(route('meta.deauthorize'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1'], ''),
    ])->assertBadRequest();
});

test('deauthorize disconnects the channels of that meta user and wipes their tokens', function () {
    $workspace = Workspace::factory()->create();
    $page = metaCallbackFacebookPage($workspace, 'fb-user-1', 'page-1');
    $someoneElse = metaCallbackFacebookPage($workspace, 'fb-user-2', 'page-2');
    $post = Post::factory()->forAccount($page)->create();

    $this->post(route('meta.deauthorize'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1']),
    ])->assertOk();

    $page->refresh();
    $someoneElse->refresh();

    expect($page->status)->toBe(Status::Disconnected)
        ->and($page->access_token)->toBe('')
        ->and($page->refresh_token)->toBeNull()
        ->and(data_get($page->meta, 'user_token'))->toBeNull()
        ->and(data_get($page->meta, 'user_id'))->toBe('fb-user-1')
        ->and($someoneElse->status)->toBe(Status::Connected)
        ->and(data_get($someoneElse->meta, 'user_token'))->toBe('user-token');

    $this->assertModelExists($post);

    $this->post(route('meta.deauthorize'), [
        'signed_request' => metaCallbackSignedRequest(['user_id' => 'fb-user-1']),
    ])->assertOk();
});
