<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MetaDataDeletionRequest\Status;
use App\Models\MetaDataDeletionRequest;
use Illuminate\Contracts\View\View;

/**
 * Privacy, terms and data deletion pages, rendered on the server without the
 * SPA so app reviewers and crawlers read them without JavaScript.
 */
class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', $this->operator());
    }

    public function terms(): View
    {
        return view('legal.terms', $this->operator());
    }

    public function dataDeletion(): View
    {
        return view('legal.data-deletion', $this->operator());
    }

    public function dataDeletionStatus(string $code): View
    {
        $deletion = MetaDataDeletionRequest::query()
            ->where('confirmation_code', $code)
            ->firstOrFail();

        return view('legal.data-deletion-status', [
            ...$this->operator(),
            'deletion' => $deletion,
            'completed' => $deletion->status === Status::Completed,
            'removedChannels' => count($deletion->affected_accounts ?? []),
        ]);
    }

    /**
     * @return array{product: string, company: string, email: string, cnpj: string, updatedAt: string}
     */
    private function operator(): array
    {
        return [
            'product' => (string) config('trypost.legal.product_name'),
            'company' => (string) config('trypost.legal.company_name'),
            'email' => (string) config('trypost.legal.contact_email'),
            'cnpj' => (string) config('trypost.legal.cnpj'),
            'updatedAt' => (string) config('trypost.legal.updated_at'),
        ];
    }
}
