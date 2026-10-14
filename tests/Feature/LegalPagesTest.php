<?php

declare(strict_types=1);

use App\Models\MetaDataDeletionRequest;

test('the legal pages render on the server without signing in', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=utf-8')
        ->assertSee('Sify Social')
        ->assertSee('English')
        ->assertSee('RASCUNHO', escape: false)
        ->assertDontSee('data-page=', escape: false);
})->with(['legal.privacy', 'legal.terms', 'legal.data-deletion']);

test('the legal pages show the configured operator', function () {
    config()->set('trypost.legal.company_name', 'Acme Ltda');
    config()->set('trypost.legal.contact_email', 'privacy@acme.test');
    config()->set('trypost.legal.cnpj', '00.000.000/0001-00');

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Acme Ltda')
        ->assertSee('privacy@acme.test')
        ->assertSee('CNPJ 00.000.000/0001-00');
});

test('the legal pages leave the CNPJ out when it is not configured', function () {
    config()->set('trypost.legal.cnpj', '');

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertDontSee('CNPJ');
});

test('the data deletion status page shows the request state without personal data', function () {
    $deletion = MetaDataDeletionRequest::factory()->create([
        'meta_user_id' => '1234567890',
        'affected_accounts' => [['id' => 'a', 'platform' => 'facebook'], ['id' => 'b', 'platform' => 'instagram-facebook']],
    ]);

    $this->get(route('legal.data-deletion.status', $deletion->confirmation_code))
        ->assertOk()
        ->assertSee($deletion->confirmation_code)
        ->assertSee('Concluída')
        ->assertSee('Completed')
        ->assertSee('Canais removidos: 2')
        ->assertDontSee('1234567890');
});

test('a pending data deletion request reads as in progress', function () {
    $deletion = MetaDataDeletionRequest::factory()->failed()->create();

    $this->get(route('legal.data-deletion.status', $deletion->confirmation_code))
        ->assertOk()
        ->assertSee('Em andamento')
        ->assertSee('In progress');
});

test('an unknown confirmation code is not found', function () {
    $this->get(route('legal.data-deletion.status', 'doesnotexist'))->assertNotFound();
});
