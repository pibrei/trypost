<?php

declare(strict_types=1);

use App\Mail\PostNoteAdded;
use App\Models\Post;
use App\Models\PostNote;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

/**
 * Poll browser-side until the testid element has laid out (width/height > 0).
 * These Pest browser assertions do not auto-wait, so settle async UI first.
 */
function waitForPostNotesTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector(sel);
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

test('adding a note from the popover shows it and emails the other members', function () {
    Mail::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $teammate = User::factory()->create();
    $workspace->members()->attach($teammate->id, membershipPivot('member'));

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'hello team',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));

    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-input');

    $page->assertScript('(() => { const field = document.querySelector("[data-testid=note-composer]").getBoundingClientRect(); const send = document.querySelector("[data-testid=note-send]").getBoundingClientRect(); return Math.abs((field.top + field.height / 2) - (send.top + send.height / 2)) <= 1 && send.right <= field.right; })()', true);

    $page->fill('@note-input', 'Please check the @image before Friday');
    $page->click('@note-send');
    waitForPostNotesTestId($page, 'note-body');

    $page->assertScript('(() => { const list = document.querySelector("[data-testid=notes-list]"); return list.scrollWidth <= list.clientWidth; })()', true);

    $page->assertSeeIn('@note-body', 'Please check the @image before Friday')
        ->assertVisible('@note-actions')
        ->assertMissing('@note-react')
        ->assertMissing('@note-reply')
        ->assertNoJavaScriptErrors();

    expect(PostNote::query()->where('post_id', $post->id)->value('body'))
        ->toBe('Please check the @image before Friday');

    Mail::assertQueued(PostNoteAdded::class, fn (PostNoteAdded $mail) => $mail->hasTo($teammate->email));
    Mail::assertNotQueued(PostNoteAdded::class, fn (PostNoteAdded $mail) => $mail->hasTo($user->email));
});

test('the note composer enables send only with text and sends on Enter but not Shift+Enter', function () {
    Mail::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'hello team',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));

    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'notes-empty');

    $page->assertVisible('@notes-empty')
        ->assertMissing("@post-notes-count-{$post->id}")
        ->assertDisabled('@note-send');

    $page->fill('@note-input', '   ');
    $page->assertDisabled('@note-send');

    $page->fill('@note-input', 'First line');
    $page->assertEnabled('@note-send');

    $page->keys('@note-input', ['Shift+Enter']);

    expect($page->script("document.querySelector('[data-testid=\"note-input\"]').value"))->toBe("First line\n");
    expect(PostNote::query()->where('post_id', $post->id)->exists())->toBeFalse();

    $page->keys('@note-input', ['Enter']);
    waitForPostNotesTestId($page, 'note-body');

    $page->assertSeeIn('@note-body', 'First line')
        ->assertValue('@note-input', '')
        ->assertDisabled('@note-send')
        ->assertSeeIn("@post-notes-count-{$post->id}", '1')
        ->assertVisible("@post-notes-filled-icon-{$post->id}")
        ->assertNoJavaScriptErrors();

    expect(PostNote::query()->where('post_id', $post->id)->value('body'))->toBe('First line');
});

test('a note timestamp follows the display zone of the list', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'zone check']);
    $createdAt = now()->subMonth()->setTime(10, 0);
    PostNote::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts', 'tz' => 'America/Sao_Paulo']));
    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-created-at');

    expect($page->script('document.querySelector(\'[data-testid="note-created-at"]\').getAttribute("title")'))
        ->toBe($createdAt->setTimezone('America/Sao_Paulo')->format('F j, Y g:i A'));
    $page->assertNoJavaScriptErrors();
});

test('a note created an hour ago reads as past for a user whose zone is not the browser zone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'relative check']);
    PostNote::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));
    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-created-at');

    expect(trim((string) $page->script('document.querySelector(\'[data-testid="note-created-at"]\').textContent')))
        ->toBe('an hour ago');
    $page->assertNoJavaScriptErrors();
});

test('the notes toggle says whether it shows or hides the notes', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'hello team']);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));
    waitForPostNotesTestId($page, "post-notes-trigger-{$post->id}");

    $page->hover("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, "post-notes-tooltip-{$post->id}");
    $page->assertSeeIn("@post-notes-tooltip-{$post->id}", __('notes.show'));

    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-input');
    $page->hover('@note-input')->hover("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, "post-notes-tooltip-{$post->id}");

    $page->assertSeeIn("@post-notes-tooltip-{$post->id}", __('notes.hide'))
        ->assertNoJavaScriptErrors();
});

test('the notes count follows the thread when it opens and when a note is deleted', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'hello team']);
    PostNote::factory()->create(['post_id' => $post->id, 'user_id' => $user->id, 'body' => 'First']);
    $this->actingAs($user);

    $page = visit(route('app.posts.index', ['tab' => 'drafts']));
    waitForPostNotesTestId($page, "post-notes-count-{$post->id}");
    $page->assertSeeIn("@post-notes-count-{$post->id}", '1');

    PostNote::factory()->create(['post_id' => $post->id, 'user_id' => $user->id, 'body' => 'Second']);
    $page->click("@post-notes-trigger-{$post->id}");
    waitForPostNotesTestId($page, 'note-body');
    $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 100; i++) {
                if (document.querySelectorAll('[data-testid="note-item"]').length === 2) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
    $page->assertSeeIn("@post-notes-count-{$post->id}", '2');

    $page->hover('@note-item')->click('@note-actions');
    waitForPostNotesTestId($page, 'note-delete');
    $page->click('@note-delete');
    $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 100; i++) {
                if (document.querySelectorAll('[data-testid="note-item"]').length === 1) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);

    $page->assertSeeIn("@post-notes-count-{$post->id}", '1')->assertNoJavaScriptErrors();
});
