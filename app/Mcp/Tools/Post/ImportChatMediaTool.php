<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Dto\ImportedFile;
use App\Dto\RemoteFile;
use App\Enums\Media\Type as MediaType;
use App\Http\Resources\Api\UploadResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Workspace;
use App\Services\Media\RemoteMediaImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Import an image attached to or generated in this ChatGPT conversation into the current workspace. Pass the conversation file in file; ChatGPT supplies its temporary download_url and file_id. Do not pass a /mnt/data path or invent a URL. The TryPost server downloads the image itself, so no local curl upload is needed. JPEG, PNG, GIF, WebP and supported HEIC/HEIF images are accepted after inspecting the actual bytes, within the server image size and pixel limits. Guarded redirects are followed; private-network addresses are blocked. A download must finish within 20 seconds. Returns a temporary upload_token: use it in create-post-tool, create-posts-tool, update-post-tool or attach-media-from-upload-tool before expires_at. This tool does not create, schedule or publish a post. If the download URL has expired, provide a fresh conversation file reference and retry.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld]
class ImportChatMediaTool extends Tool
{
    use AuthorizesMcpTool;

    protected ?array $meta = [
        'openai/fileParams' => ['file'],
    ];

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace($request, 'createPost');

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $validated = $request->validate([
            'file' => ['required', 'array:download_url,file_id,mime_type,file_name'],
            'file.download_url' => ['required', 'string', 'url:https', 'max:16384'],
            'file.file_id' => ['required', 'string', 'max:512'],
            'file.mime_type' => ['sometimes', 'string', 'max:255'],
            'file.file_name' => ['sometimes', 'string', 'max:1024'],
        ]);

        $imported = app(RemoteMediaImporter::class)->import(
            $workspace,
            new RemoteFile(
                data_get($validated, 'file.download_url'),
                data_get($validated, 'file.file_name', ''),
            ),
            [MediaType::Image],
            timeoutSeconds: 20,
            deadline: now()->addSeconds(20),
            followRedirects: true,
        );

        if ($imported->media === null) {
            return Response::error(match ($imported->failure) {
                ImportedFile::TYPE_NOT_ALLOWED => __('posts.composer.media_sources.errors.type_not_allowed'),
                ImportedFile::TOO_LARGE => __('posts.composer.media_sources.errors.too_large'),
                default => 'Unable to download this image. Its file reference may have expired, or the URL may be unreachable or blocked. Supply a fresh conversation file reference and retry.',
            });
        }

        return Response::structured((new UploadResource($imported->media))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->object(fn ($file) => [
                'download_url' => $file->string()->required()->description('Temporary HTTPS download URL supplied by ChatGPT for the conversation file.'),
                'file_id' => $file->string()->required()->description('Conversation file identifier supplied by ChatGPT.'),
                'mime_type' => $file->string()->description('Optional MIME type supplied by ChatGPT; the server verifies the actual bytes.'),
                'file_name' => $file->string()->description('Optional original filename supplied by ChatGPT.'),
            ])->withoutAdditionalProperties()->required()->description('Image attached to or generated in the conversation. Pass the file reference itself, not a local path.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'upload_token' => $schema->string()->required()->description('Single-use token to attach this image to a post in the current workspace.'),
            'type' => $schema->string()->enum(['image'])->required(),
            'mime_type' => $schema->string()->required(),
            'size' => $schema->integer()->required(),
            'original_filename' => $schema->string()->required(),
            'url' => $schema->string()->required(),
            'expires_at' => $schema->string()->required()->description('Expiry of the temporary upload, in ISO 8601 format.'),
        ];
    }
}
