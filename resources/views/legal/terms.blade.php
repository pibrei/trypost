@extends('legal.layout')

@section('title', 'Termos de Uso / Terms of Service')

@section('content')
    <!-- RASCUNHO: texto preparado para a revisão do app na Meta. Deve ser revisado pelo responsável (e, se possível, por assessoria jurídica) antes de ser considerado definitivo. -->

    <h1>Termos de Uso</h1>
    <p class="meta">{{ $product }} · Atualizados em {{ $updatedAt }}</p>

    <p>
        Estes termos regem o uso do <strong>{{ $product }}</strong>, operado por <strong>{{ $company }}</strong>@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif.
        Ao criar uma conta ou usar o serviço, você concorda com eles e com a <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>.
    </p>

    <h2>1. O serviço</h2>
    <p>O {{ $product }} permite planejar, agendar, publicar e medir conteúdo nas contas de redes sociais que você conecta. Publicamos somente o que você cria ou aprova, nas contas e horários que você escolhe.</p>

    <h2>2. Uso aceitável</h2>
    <p>Você se compromete a não usar o serviço para:</p>
    <ul>
        <li>publicar conteúdo ilegal, enganoso, difamatório, discriminatório, que viole direitos de terceiros ou que incentive violência;</li>
        <li>enviar spam, automatizar interações falsas ou manipular métricas;</li>
        <li>se passar por outra pessoa ou organização;</li>
        <li>acessar contas, Páginas ou dados sem autorização;</li>
        <li>tentar burlar limites técnicos, explorar falhas ou prejudicar o funcionamento do serviço.</li>
    </ul>

    <h2>3. Sua responsabilidade</h2>
    <p>
        Você é responsável pelo conteúdo que cria, agenda e publica, e por ter autorização para administrar as contas e Páginas que conecta.
        Também é responsável por manter sua senha em sigilo e pelas ações feitas pelos membros que você convida para o seu workspace.
    </p>

    <h2>4. Termos das plataformas</h2>
    <p>
        Ao conectar uma rede social, você também deve cumprir os termos e políticas dela (por exemplo, os Termos da Meta, as Diretrizes da Comunidade do Instagram
        e as Políticas das Páginas do Facebook). As plataformas podem limitar, alterar ou encerrar suas APIs; quando isso acontecer, algumas funções podem deixar de funcionar.
    </p>

    <h2>5. Disponibilidade</h2>
    <p>
        Buscamos manter o serviço disponível e seguro, mas ele é fornecido "no estado em que se encontra", sem garantia de funcionamento ininterrupto.
        Uma publicação pode falhar por motivos fora do nosso controle (instabilidade, recusa ou mudança de regras da plataforma, token expirado); nesses casos avisamos no app quando possível.
        Na extensão permitida pela lei, não respondemos por lucros cessantes ou danos indiretos.
    </p>

    <h2>6. Encerramento</h2>
    <p>
        Você pode desconectar canais e encerrar sua conta a qualquer momento. Podemos suspender ou encerrar contas que violem estes termos, a lei ou as regras das plataformas,
        avisando quando possível. Após o encerramento, os dados são tratados conforme a Política de Privacidade.
    </p>

    <h2>7. Alterações e lei aplicável</h2>
    <p>Podemos atualizar estes termos; a data no topo indica a versão em vigor. Aplica-se a lei brasileira.</p>

    <h2>8. Contato</h2>
    <p>{{ $company }}@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif · <a href="mailto:{{ $email }}">{{ $email }}</a></p>

    <hr>

    <section lang="en">
        <h1>English</h1>
        <h2>Terms of Service</h2>
        <p class="meta">{{ $product }} · Last updated {{ $updatedAt }}</p>

        <p>
            These terms govern your use of <strong>{{ $product }}</strong>, operated by <strong>{{ $company }}</strong>@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif.
            By creating an account or using the service you agree to them and to the <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
        </p>

        <h3>1. The service</h3>
        <p>{{ $product }} lets you plan, schedule, publish and measure content on the social accounts you connect. We publish only what you create or approve, to the accounts and at the times you choose.</p>

        <h3>2. Acceptable use</h3>
        <p>You agree not to use the service to:</p>
        <ul>
            <li>publish content that is illegal, misleading, defamatory, discriminatory, infringes the rights of others or incites violence;</li>
            <li>send spam, automate fake interactions or manipulate metrics;</li>
            <li>impersonate another person or organization;</li>
            <li>access accounts, Pages or data without authorization;</li>
            <li>circumvent technical limits, exploit vulnerabilities or disrupt the service.</li>
        </ul>

        <h3>3. Your responsibility</h3>
        <p>
            You are responsible for the content you create, schedule and publish, and for being authorized to manage the accounts and Pages you connect.
            You are also responsible for keeping your password secret and for the actions of the members you invite to your workspace.
        </p>

        <h3>4. Platform terms</h3>
        <p>
            When you connect a social network you must also comply with its terms and policies (for example, Meta's Terms, Instagram's Community Guidelines
            and Facebook's Pages policies). Platforms may limit, change or shut down their APIs; when that happens some features may stop working.
        </p>

        <h3>5. Availability</h3>
        <p>
            We aim to keep the service available and secure, but it is provided "as is", without a guarantee of uninterrupted operation.
            A post may fail for reasons outside our control (outages, platform rejections or rule changes, expired tokens); when possible we tell you in the app.
            To the extent permitted by law, we are not liable for lost profits or indirect damages.
        </p>

        <h3>6. Termination</h3>
        <p>
            You may disconnect channels and close your account at any time. We may suspend or terminate accounts that violate these terms, the law or platform rules,
            with notice when possible. After termination, data is handled as described in the Privacy Policy.
        </p>

        <h3>7. Changes and governing law</h3>
        <p>We may update these terms; the date at the top shows the version in force. Brazilian law applies.</p>

        <h3>8. Contact</h3>
        <p>{{ $company }}@if ($cnpj !== '') (CNPJ {{ $cnpj }})@endif · <a href="mailto:{{ $email }}">{{ $email }}</a></p>
    </section>
@endsection
