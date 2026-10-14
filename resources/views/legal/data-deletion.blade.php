@extends('legal.layout')

@section('title', 'Exclusão de dados / Data deletion')

@section('content')
    <!-- RASCUNHO: texto preparado para a revisão do app na Meta. Deve ser revisado pelo responsável antes de ser considerado definitivo. -->

    <h1>Exclusão de dados</h1>
    <p class="meta">{{ $product }} · Atualizada em {{ $updatedAt }}</p>

    <p>Você pode remover os dados que o {{ $product }} guarda sobre as suas contas a qualquer momento, de três formas:</p>

    <h2>1. Desconectar canais no {{ $product }}</h2>
    <ol>
        <li>Entre no {{ $product }} e abra a lista de canais.</li>
        <li>No canal que deseja remover, escolha <strong>Desconectar</strong> e confirme.</li>
        <li>Os tokens de acesso daquele canal são apagados na hora, junto com os posts e as mídias dele.</li>
    </ol>

    <h2>2. Remover o app no Facebook ou no Instagram</h2>
    <ol>
        <li>No Facebook, abra <strong>Configurações e privacidade &gt; Configurações &gt; Integrações comerciais</strong>.</li>
        <li>Encontre o {{ $product }}, selecione <strong>Remover</strong> e confirme. No Instagram, o caminho é <strong>Configurações &gt; Apps e sites</strong>.</li>
        <li>Para pedir também a exclusão dos dados, use a opção de solicitar a exclusão exibida pela Meta ao remover o app.</li>
    </ol>
    <p>
        <strong>Como funciona o pedido automático:</strong> quando você remove o app, a Meta nos avisa automaticamente e paramos de usar os tokens daquela conta.
        Quando você pede a exclusão dos dados, a Meta envia uma solicitação assinada ao nosso servidor. Verificamos a assinatura, apagamos os canais do Facebook e do Instagram
        ligados ao seu usuário (tokens, posts, mídias e métricas desses canais) e devolvemos um <strong>código de confirmação</strong> com um link para acompanhar o pedido.
    </p>

    <h2>3. Pedir a exclusão total da conta por e-mail</h2>
    <p>
        Para apagar a conta inteira (dados de cadastro, workspaces, posts e mídias), envie um e-mail para <a href="mailto:{{ $email }}">{{ $email }}</a>
        a partir do endereço cadastrado, com o assunto "Exclusão de dados". Respondemos em até 15 dias. O titular da conta também pode excluí-la em <strong>Configurações &gt; Perfil</strong>.
        Mantemos apenas o que a lei obrigar a guardar, pelo prazo legal.
    </p>

    <hr>

    <section lang="en">
        <h1>English</h1>
        <h2>Data deletion</h2>
        <p class="meta">{{ $product }} · Last updated {{ $updatedAt }}</p>

        <p>You can remove the data {{ $product }} keeps about your accounts at any time, in three ways:</p>

        <h3>1. Disconnect channels in {{ $product }}</h3>
        <ol>
            <li>Sign in to {{ $product }} and open your channels list.</li>
            <li>On the channel you want to remove, choose <strong>Disconnect</strong> and confirm.</li>
            <li>That channel's access tokens are deleted immediately, together with its posts and media.</li>
        </ol>

        <h3>2. Remove the app on Facebook or Instagram</h3>
        <ol>
            <li>On Facebook, open <strong>Settings &amp; privacy &gt; Settings &gt; Business integrations</strong>.</li>
            <li>Find {{ $product }}, select <strong>Remove</strong> and confirm. On Instagram, go to <strong>Settings &gt; Apps and websites</strong>.</li>
            <li>To also request deletion of your data, use the deletion request option Meta shows when you remove the app.</li>
        </ol>
        <p>
            <strong>How the automatic request works:</strong> when you remove the app, Meta notifies us automatically and we stop using that account's tokens.
            When you request data deletion, Meta sends a signed request to our server. We verify the signature, delete the Facebook and Instagram channels
            linked to your user (tokens, posts, media and those channels' metrics) and return a <strong>confirmation code</strong> with a link to track the request.
        </p>

        <h3>3. Request full account deletion by email</h3>
        <p>
            To delete your whole account (sign-up data, workspaces, posts and media), email <a href="mailto:{{ $email }}">{{ $email }}</a>
            from your registered address with the subject "Data deletion". We reply within 15 days. The account owner can also delete it under <strong>Settings &gt; Profile</strong>.
            We keep only what the law requires, for the legal period.
        </p>
    </section>
@endsection
