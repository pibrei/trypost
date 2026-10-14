@extends('legal.layout')

@section('title', 'Status da exclusão / Deletion status')

@section('robots', 'noindex, nofollow')

@section('content')
    <h1>Status da solicitação de exclusão</h1>
    <p class="meta">Código de confirmação: <code data-testid="confirmation-code">{{ $deletion->confirmation_code }}</code></p>

    <p class="status" data-testid="deletion-status">
        @if ($completed)
            Concluída
        @else
            Em andamento
        @endif
    </p>

    <ul>
        <li>Recebida em: {{ $deletion->created_at->utc()->format('Y-m-d H:i') }} UTC</li>
        @if ($completed && $deletion->completed_at)
            <li>Concluída em: {{ $deletion->completed_at->utc()->format('Y-m-d H:i') }} UTC</li>
        @endif
        <li>Canais removidos: {{ $removedChannels }}</li>
    </ul>

    <p>
        @if ($completed)
            Os canais do Facebook e do Instagram ligados a este pedido foram removidos, com seus tokens de acesso, posts, mídias e métricas.
        @else
            Ainda há dados sendo removidos. Se o status não mudar em alguns dias, escreva para <a href="mailto:{{ $email }}">{{ $email }}</a> informando o código acima.
        @endif
    </p>

    <hr>

    <section lang="en">
        <h2>Deletion request status</h2>
        <p class="status">{{ $completed ? 'Completed' : 'In progress' }}</p>
        <ul>
            <li>Received: {{ $deletion->created_at->utc()->format('Y-m-d H:i') }} UTC</li>
            @if ($completed && $deletion->completed_at)
                <li>Completed: {{ $deletion->completed_at->utc()->format('Y-m-d H:i') }} UTC</li>
            @endif
            <li>Channels removed: {{ $removedChannels }}</li>
        </ul>
        <p>
            @if ($completed)
                The Facebook and Instagram channels linked to this request were removed, along with their access tokens, posts, media and metrics.
            @else
                Some data is still being removed. If the status does not change within a few days, email <a href="mailto:{{ $email }}">{{ $email }}</a> with the code above.
            @endif
        </p>
    </section>
@endsection
