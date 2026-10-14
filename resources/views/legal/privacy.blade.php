@extends('legal.layout')

@section('title', 'Política de Privacidade / Privacy Policy')

@section('content')
    <!-- RASCUNHO: texto preparado para a revisão do app na Meta. Deve ser revisado pelo responsável (e, se possível, por assessoria jurídica) antes de ser considerado definitivo. -->

    <h1>Política de Privacidade</h1>
    <p class="meta">{{ $product }} · Atualizada em {{ $updatedAt }}</p>

    <p>
        Esta política explica como o <strong>{{ $product }}</strong>, operado por <strong>{{ $company }}</strong>@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif,
        trata dados pessoais quando você usa o serviço para planejar, agendar, publicar e medir conteúdo nas suas redes sociais.
        Em caso de dúvida, escreva para <a href="mailto:{{ $email }}">{{ $email }}</a>.
    </p>

    <h2>1. Quais dados tratamos</h2>
    <ul>
        <li><strong>Dados de conta:</strong> nome, e-mail, senha (armazenada com hash), idioma, fuso horário e preferências. Se você entrar com Google ou GitHub, recebemos o identificador e o e-mail dessa conta.</li>
        <li><strong>Tokens de acesso das redes conectadas:</strong> credenciais emitidas pelas plataformas quando você autoriza a conexão. Ficam criptografados no banco de dados e só são usados para executar o que você pediu.</li>
        <li><strong>Páginas e contas conectadas:</strong> identificador, nome, nome de usuário e foto das Páginas do Facebook, contas do Instagram e demais perfis que você escolhe conectar, além do identificador do seu usuário na plataforma.</li>
        <li><strong>Posts e mídias enviadas:</strong> textos, imagens, vídeos, documentos, rascunhos, agendamentos e configurações de publicação que você cria no serviço.</li>
        <li><strong>Métricas obtidas das APIs:</strong> dados de desempenho das publicações e das contas conectadas (por exemplo, alcance, impressões, curtidas, comentários, seguidores), quando a plataforma os disponibiliza.</li>
        <li><strong>Logs técnicos:</strong> endereço IP, navegador, data e hora de acesso e registros de erro, usados para segurança e diagnóstico.</li>
    </ul>

    <h2>2. Para que usamos</h2>
    <p>
        Usamos esses dados para agendar e publicar conteúdo nas contas que você conectou, por ordem sua, e para mostrar as métricas desse conteúdo.
        Também os usamos para manter a sua conta, prestar suporte, prevenir abuso e cumprir obrigações legais.
        Não vendemos dados pessoais e não usamos os dados obtidos das plataformas para publicidade.
    </p>

    <h2>3. Plataformas que você pode conectar</h2>
    <p>
        O serviço oferece integração com Facebook (Páginas), Instagram (via Facebook ou login do Instagram), Threads, LinkedIn (perfil e páginas),
        X, TikTok, YouTube, Pinterest, Bluesky, Mastodon, Telegram, Discord e Perfil da Empresa no Google. As integrações disponíveis podem variar.
        Cada conexão só acontece depois que você autoriza o acesso na própria plataforma.
    </p>

    <h2>4. Com quem compartilhamos</h2>
    <ul>
        <li><strong>Plataformas que você conecta</strong>, como a Meta (Facebook, Instagram e Threads): enviamos o conteúdo que você manda publicar e consultamos as métricas dele.</li>
        <li><strong>Provedores de infraestrutura e armazenamento</strong> (hospedagem, banco de dados, armazenamento de arquivos, envio de e-mail), que tratam os dados apenas para operar o serviço.</li>
        <li><strong>Provedores de inteligência artificial</strong>, somente quando você usa um recurso de IA (por exemplo, gerar ou reescrever um texto ou imagem). Enviamos apenas o necessário para aquela solicitação.</li>
        <li><strong>Serviços de mídia que você escolhe usar</strong> (por exemplo, Canva, Google Fotos ou bancos de imagens), quando você importa um arquivo deles.</li>
        <li><strong>Autoridades</strong>, quando exigido por lei ou ordem judicial.</li>
    </ul>

    <h2>5. Por quanto tempo guardamos</h2>
    <p>
        Guardamos os dados enquanto a sua conta existir. Ao desconectar um canal, apagamos os tokens dele e os posts daquele canal.
        Ao excluir a conta, apagamos os dados dela, salvo o que a lei obrigar a manter (por exemplo, registros de acesso e documentos fiscais pelo prazo legal).
        Logs técnicos são mantidos por tempo limitado.
    </p>

    <h2>6. Seus direitos (LGPD)</h2>
    <p>
        Nos termos da Lei Geral de Proteção de Dados (Lei 13.709/2018), você pode pedir confirmação de tratamento, acesso, correção,
        anonimização, portabilidade, eliminação dos dados, informação sobre compartilhamento e revogação do consentimento.
        Envie o pedido para <a href="mailto:{{ $email }}">{{ $email }}</a>. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).
    </p>

    <h2>7. Como revogar o acesso</h2>
    <ul>
        <li>No {{ $product }}: abra a lista de canais e desconecte a conta.</li>
        <li>No Facebook: Configurações e privacidade &gt; Configurações &gt; Integrações comerciais, e remova o app.</li>
        <li>No Instagram: Configurações &gt; Apps e sites (ou Integrações comerciais), e remova o app.</li>
    </ul>
    <p>Para apagar os seus dados, veja a página <a href="{{ route('legal.data-deletion') }}">Exclusão de dados</a>.</p>

    <h2>8. Segurança</h2>
    <p>Usamos conexões criptografadas (HTTPS), tokens criptografados em repouso e acesso restrito aos sistemas. Nenhum sistema é totalmente imune a falhas; se houver um incidente relevante, avisaremos conforme a lei.</p>

    <h2>9. Contato e alterações</h2>
    <p>
        Controlador: {{ $company }}@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif. Contato: <a href="mailto:{{ $email }}">{{ $email }}</a>.
        Podemos atualizar esta política; a data no topo indica a versão em vigor.
    </p>

    <hr>

    <section lang="en">
        <h1>English</h1>
        <h2>Privacy Policy</h2>
        <p class="meta">{{ $product }} · Last updated {{ $updatedAt }}</p>

        <p>
            This policy explains how <strong>{{ $product }}</strong>, operated by <strong>{{ $company }}</strong>@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif,
            handles personal data when you use the service to plan, schedule, publish and measure content on your social accounts.
            Questions: <a href="mailto:{{ $email }}">{{ $email }}</a>.
        </p>

        <h3>1. Data we process</h3>
        <ul>
            <li><strong>Account data:</strong> name, email, password (stored hashed), language, time zone and preferences. If you sign in with Google or GitHub, we receive that account's identifier and email.</li>
            <li><strong>Access tokens of connected networks:</strong> credentials the platforms issue when you authorize a connection. They are encrypted in the database and used only to carry out what you asked for.</li>
            <li><strong>Connected Pages and accounts:</strong> ID, name, username and picture of the Facebook Pages, Instagram accounts and other profiles you choose to connect, plus your user ID on that platform.</li>
            <li><strong>Posts and uploaded media:</strong> text, images, videos, documents, drafts, schedules and publishing settings you create in the service.</li>
            <li><strong>Metrics from the APIs:</strong> performance data of your posts and connected accounts (such as reach, impressions, likes, comments, followers) when the platform provides it.</li>
            <li><strong>Technical logs:</strong> IP address, browser, access time and error records, used for security and troubleshooting.</li>
        </ul>

        <h3>2. Why we use it</h3>
        <p>
            We use this data to schedule and publish content to the accounts you connected, at your request, and to show you how that content performs.
            We also use it to run your account, provide support, prevent abuse and meet legal obligations.
            We do not sell personal data and do not use platform data for advertising.
        </p>

        <h3>3. Platforms you can connect</h3>
        <p>
            The service integrates with Facebook (Pages), Instagram (via Facebook or Instagram login), Threads, LinkedIn (profile and pages),
            X, TikTok, YouTube, Pinterest, Bluesky, Mastodon, Telegram, Discord and Google Business Profile. Available integrations may vary.
            A connection is made only after you authorize it on the platform itself.
        </p>

        <h3>4. Who we share it with</h3>
        <ul>
            <li><strong>The platforms you connect</strong>, such as Meta (Facebook, Instagram and Threads): we send the content you ask us to publish and read its metrics.</li>
            <li><strong>Infrastructure and storage providers</strong> (hosting, database, file storage, email delivery), which process data only to run the service.</li>
            <li><strong>AI providers</strong>, only when you use an AI feature (for example, generating or rewriting text or an image). We send only what that request needs.</li>
            <li><strong>Media services you choose to use</strong> (for example, Canva, Google Photos or stock image libraries), when you import a file from them.</li>
            <li><strong>Authorities</strong>, when required by law or court order.</li>
        </ul>

        <h3>5. Retention</h3>
        <p>
            We keep data while your account exists. When you disconnect a channel, we delete its tokens and that channel's posts.
            When you delete your account, we delete its data, except what the law requires us to keep (such as access logs and tax records for the legal period).
            Technical logs are kept for a limited time.
        </p>

        <h3>6. Your rights (Brazil's LGPD)</h3>
        <p>
            Under Brazil's General Data Protection Law (Law 13,709/2018) you may request confirmation of processing, access, correction,
            anonymization, portability, deletion, information about sharing, and withdrawal of consent.
            Send your request to <a href="mailto:{{ $email }}">{{ $email }}</a>. You may also complain to the Brazilian Data Protection Authority (ANPD).
        </p>

        <h3>7. How to revoke access</h3>
        <ul>
            <li>In {{ $product }}: open your channels list and disconnect the account.</li>
            <li>On Facebook: Settings &amp; privacy &gt; Settings &gt; Business integrations, and remove the app.</li>
            <li>On Instagram: Settings &gt; Apps and websites (or Business integrations), and remove the app.</li>
        </ul>
        <p>To delete your data, see <a href="{{ route('legal.data-deletion') }}">Data deletion</a>.</p>

        <h3>8. Security</h3>
        <p>We use encrypted connections (HTTPS), tokens encrypted at rest and restricted system access. No system is immune to failure; if a relevant incident happens we will notify you as the law requires.</p>

        <h3>9. Contact and changes</h3>
        <p>
            Controller: {{ $company }}@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif. Contact: <a href="mailto:{{ $email }}">{{ $email }}</a>.
            We may update this policy; the date at the top shows the version in force.
        </p>
    </section>
@endsection
