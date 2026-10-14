<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\AttachMediaFromUploadTool;
use App\Mcp\Tools\Post\ImportChatMediaTool;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Http\HostResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->freezeTime();
    Storage::fake();
    config(['trypost.security.allow_private_network' => false]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('member'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->chatFile = [
        'download_url' => 'https://93.184.216.34/chat-image?temporary-signature=private',
        'file_id' => 'file_test_chat_image',
        'mime_type' => 'image/png',
        'file_name' => 'chat-art.png',
    ];
});

test('declares the ChatGPT file contract and output schema on the tool descriptor', function () {
    $tool = app(ImportChatMediaTool::class)->toArray();
    $file = data_get($tool, 'inputSchema.properties.file');

    expect(data_get($tool, '_meta.openai/fileParams'))->toBe(['file'])
        ->and(data_get($tool, 'inputSchema.required'))->toBe(['file'])
        ->and($file['type'])->toBe('object')
        ->and(array_keys($file['properties']))->toBe(['download_url', 'file_id', 'mime_type', 'file_name'])
        ->and($file['required'])->toBe(['download_url', 'file_id'])
        ->and($file['additionalProperties'])->toBeFalse()
        ->and(data_get($tool, 'outputSchema.required'))->toContain('upload_token', 'expires_at')
        ->and(data_get($tool, 'annotations.readOnlyHint'))->toBeFalse()
        ->and(data_get($tool, 'annotations.destructiveHint'))->toBeFalse()
        ->and(data_get($tool, 'annotations.openWorldHint'))->toBeTrue();
});

test('imports a conversation image without a client upload and returns an attachable token', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')), 200, ['Content-Type' => 'application/octet-stream'])]);
    $token = null;

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$token) {
            $token = $json->toArray()['upload_token'];
            $json->where('type', 'image')
                ->where('mime_type', 'image/png')
                ->where('original_filename', 'chat-art.png')
                ->where('expires_at', now()->addHours((int) config('trypost.media.upload_retention_hours'))->toIso8601String())
                ->has('url')->has('size')->etc();
        });

    $media = Media::query()->sole();
    expect(Str::isUuid($token))->toBeTrue()
        ->and($media->workspace_id)->toBe($this->workspace->id)
        ->and($media->collection)->toBe(Media::COLLECTION_UPLOADS)
        ->and($media->upload_token)->toBe($token);
    Storage::assertExists($media->path);
    expect(Post::query()->count())->toBe(0);

    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'status' => Status::Draft]);
    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUploadTool::class, ['post_id' => $post->id, 'upload_token' => $token])
        ->assertOk();

    expect($post->fresh()->status)->toBe(Status::Draft)
        ->and($post->fresh()->media)->toHaveCount(1)
        ->and($media->fresh()->post_id)->toBe($post->id)
        ->and($media->fresh()->upload_token)->toBeNull();

    $otherPost = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUploadTool::class, ['post_id' => $otherPost->id, 'upload_token' => $token])
        ->assertHasErrors();
});

test('accepts only the required file properties and derives a filename from the real type', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')))]);

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => array_intersect_key($this->chatFile, array_flip(['download_url', 'file_id']))])
        ->assertOk();

    expect(Media::query()->sole()->original_filename)->toBe('download.png');
});

test('does not trust MIME hints or filenames when identifying the image', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')), 200, ['Content-Type' => 'text/html'])]);
    $file = [...$this->chatFile, 'mime_type' => 'application/pdf', 'file_name' => '../../art.png'];

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $file])->assertOk();

    expect(Media::query()->sole()->mime_type)->toBe('image/png')
        ->and(Media::query()->sole()->original_filename)->toBe('art.png');
});

test('refuses missing or invalid conversation file references before downloading', function (array $arguments) {
    Http::fake();

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, $arguments)->assertHasErrors();

    Http::assertNothingSent();
    expect(Media::query()->count())->toBe(0);
})->with([
    'missing file' => [[]],
    'local path' => [['file' => '/mnt/data/art.png']],
    'missing id' => [['file' => ['download_url' => 'https://93.184.216.34/image']]],
    'missing URL' => [['file' => ['file_id' => 'file_test']]],
    'HTTP URL' => [['file' => ['download_url' => 'http://93.184.216.34/image', 'file_id' => 'file_test']]],
    'unexpected credentials' => [['file' => ['download_url' => 'https://93.184.216.34/image', 'file_id' => 'file_test', 'headers' => ['Authorization' => 'secret']]]],
]);

test('requires authentication at the MCP endpoint', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    config(['passport.public_key' => openssl_pkey_get_details($key)['key']]);

    $this->postJson('/mcp/trypost', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'import-chat-media-tool', 'arguments' => ['file' => $this->chatFile]],
    ])->assertUnauthorized();

    expect(Media::query()->count())->toBe(0);
});

test('requires permission to create posts in the current workspace before downloading', function () {
    Http::fake();
    $outsider = workspaceOutsider($this->workspace);

    TryPostServer::actingAs($outsider)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertHasErrors();

    Http::assertNothingSent();
});

test('never imports into a workspace supplied in the tool arguments', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')))]);
    $foreignWorkspace = Workspace::factory()->create();

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => $this->chatFile, 'workspace_id' => $foreignWorkspace->id])
        ->assertOk();

    expect(Media::query()->sole()->workspace_id)->toBe($this->workspace->id);
});

test('blocks private network URLs without making an HTTP request', function (string $url) {
    Http::fake();

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => [...$this->chatFile, 'download_url' => $url]])
        ->assertHasErrors();

    Http::assertNothingSent();
    expect(Media::query()->count())->toBe(0);
})->with(['https://127.0.0.1/art.png', 'https://169.254.169.254/latest/meta-data', 'https://[::1]/art.png']);

test('blocks a public hostname when DNS resolves to a private address', function () {
    Http::fake();
    $this->mock(HostResolver::class)->shouldReceive('addresses')->with('files.example.test')->andReturn(['127.0.0.1']);

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => [...$this->chatFile, 'download_url' => 'https://files.example.test/image']])
        ->assertHasErrors();

    Http::assertNothingSent();
});

test('follows a public redirect while keeping the image in the current workspace', function () {
    Http::fake([
        'https://93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://93.184.216.35/image']),
        'https://93.184.216.35/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png'))),
    ]);

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertOk();

    expect(Media::query()->sole()->workspace_id)->toBe($this->workspace->id);
});

test('blocks a redirect to an internal address before requesting it', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://127.0.0.1/private'])]);

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertHasErrors();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '127.0.0.1'));
    expect(Media::query()->count())->toBe(0);
});

test('refuses unsupported bytes despite an image MIME hint', function (string $body) {
    Http::fake(['https://93.184.216.34/*' => Http::response($body, 200, ['Content-Type' => 'image/png'])]);

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertHasErrors();

    expect(Media::query()->count())->toBe(0);
})->with(['<html>Not an image</html>', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"]);

test('refuses images above the byte limit', function () {
    config(['trypost.media.max_size_mb.image' => 1]);
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')).str_repeat('x', 1024 * 1024))]);

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertHasErrors();

    expect(Media::query()->count())->toBe(0);
});

test('refuses images above the pixel limit before storing them', function () {
    config(['trypost.media.image_max_pixels' => 0]);
    Http::fake(['https://93.184.216.34/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')))]);

    TryPostServer::actingAs($this->user)->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])->assertHasErrors();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});

test('an expired download returns an actionable error without disclosing its signed URL', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response('expired', 403)]);

    TryPostServer::actingAs($this->user)
        ->tool(ImportChatMediaTool::class, ['file' => $this->chatFile])
        ->assertHasErrors(['Unable to download this image. Its file reference may have expired, or the URL may be unreachable or blocked. Supply a fresh conversation file reference and retry.']);

    expect(Media::query()->count())->toBe(0);
});
