<?php
/**
 * includes/ui.php — pequenos auxiliares de apresentação.
 *
 * Separado de auth.php de propósito: aquele arquivo cuida de sessão,
 * autenticação e regras de gamificação; aqui mora só o que é visual.
 */

/**
 * Devolve um ícone em SVG, desenhado na própria página.
 *
 * São ícones de traço, no estilo dos wireframes. Ficam embutidos no HTML em
 * vez de virem de uma biblioteca externa por três motivos: o projeto não
 * depende de CDN para a interface aparecer certa, funcionam offline (o XAMPP
 * do time nem sempre tem internet) e herdam a cor do texto por
 * `stroke="currentColor"`, o que deixa os estados de menu simples no CSS.
 */
function icone(string $nome, int $tamanho = 20): string
{
    $formas = [
        'trilhas'  => '<path d="M9 20l-5.4 2.3A1 1 0 0 1 2 21.4V6.6a1 1 0 0 1 .6-.9L9 3m0 17 6-3m-6 3V3m6 14 5.4 2.3a1 1 0 0 0 1.6-.9V4.6a1 1 0 0 0-.6-.9L15 1m0 16V1m0 0L9 3"/>',
        'ranking'  => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6m12 5h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22m10 0c0-1.76-.85-3.25-2.03-3.79-.5-.23-.97-.66-.97-1.21v-2.34M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'loja'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6ZM3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'perfil'   => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'admin'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
        'sair'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'sino'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
        'fogo'     => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/>',
        'estrela'  => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/>',
        'camadas'  => '<path d="m12 2 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'xp'       => '<path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/>',
        'email'    => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'usuario-mais' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'inicio'   => '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2h-4v-7H9v7H5a2 2 0 0 1-2-2Z"/>',
        'terminal' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m7 10 3 2-3 2M13 15h4"/>',
        'bug'      => '<rect width="8" height="12" x="8" y="7" rx="4"/><path d="M12 7V4M9 4l1.5 2M15 4l-1.5 2M8 11H4M20 11h-4M8 15H5l-1 2M16 15h3l1 2M12 11v8"/>',
        'painel'   => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'escudo'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'chevron'  => '<path d="m9 18 6-6-6-6"/>',
        'lapis'    => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'camiseta' => '<path d="M20.4 6.6 16 3a4 4 0 0 1-8 0L3.6 6.6a1 1 0 0 0-.2 1.3l2 3a1 1 0 0 0 1.4.3L8 10v10a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V10l1.2 1.2a1 1 0 0 0 1.4-.3l2-3a1 1 0 0 0-.2-1.3Z"/>',
        'medalha'  => '<circle cx="12" cy="15" r="6"/><path d="M8.2 10.4 6 3h4l2 4 2-4h4l-2.2 7.4"/>',
        'mapa'     => '<path d="M9 20l-5.4 2.3A1 1 0 0 1 2 21.4V6.6a1 1 0 0 1 .6-.9L9 3m0 17 6-3m-6 3V3m6 14 5.4 2.3a1 1 0 0 0 1.6-.9V4.6a1 1 0 0 0-.6-.9L15 1m0 16V1m0 0L9 3"/>',
        'aspas'    => '<path d="M3 21c3 0 7-1 7-8V5H3v8h4c0 4-2 5-4 5Zm11 0c3 0 7-1 7-8V5h-7v8h4c0 4-2 5-4 5Z" fill="currentColor"/>',
        'moeda'    => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.4-.9-1.4-1.5-2.5-1.5-1.5 0-2.5.8-2.5 2s1 1.7 2.5 2 2.5.8 2.5 2-1 2-2.5 2c-1.1 0-2.1-.6-2.5-1.5M12 6.5V8m0 8v1.5"/>',
        'alvo'     => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'cadeado'  => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'seta'     => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'voltar'   => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'play'     => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'codigo'   => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
        'lampada'  => '<path d="M9 18h6M10 22h4M15.1 14a5 5 0 1 0-6.2 0c.7.6 1.1 1.2 1.1 2h4c0-.8.4-1.4 1.1-2Z"/>',
        'livro'    => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'prova'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 15l2 2 4-4"/>',
        'olho'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'olho-cortado' => '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M6.61 6.61A18.5 18.5 0 0 0 2 12s3 8 10 8a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24M2 2l20 20"/>',
        'relogio'  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'lixeira'  => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/>',
        'arquivo'  => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/>',
        'calendario' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'relatorio'  => '<path d="M3 3v18h18"/><path d="m7 15 4-5 3 3 5-7"/>',
        'busca'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    ];

    $forma = $formas[$nome] ?? $formas['alvo'];

    return '<svg class="icone" width="' . $tamanho . '" height="' . $tamanho . '"'
         . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $forma . '</svg>';
}

/**
 * Título de exibição para o nível do aluno, como nos wireframes
 * ("Dev Aprendiz"). É só enfeite: quem manda no progresso é o XP.
 */
function tituloDoNivel(int $nivel): string
{
    return match (true) {
        $nivel <= 1  => 'Dev Iniciante',
        $nivel <= 3  => 'Dev Aprendiz',
        $nivel <= 6  => 'Dev Praticante',
        $nivel <= 9  => 'Dev Avançado',
        default      => 'Dev Mestre',
    };
}

/**
 * Aparência do bloco de ícone de uma trilha: um rótulo curto e uma cor.
 *
 * Os wireframes usam os logotipos de HTML, CSS e JavaScript. Aqui os blocos
 * são desenhados com CSS e um rótulo curto — assim o projeto não depende de
 * arquivos de marca de terceiros, que têm licença própria e não podem ser
 * redistribuídos junto de um TCC.
 *
 * O código vem de `trilhas.icone`; trilhas sem ícone caem no padrão.
 */
function aparenciaDaTrilha(?string $codigo): array
{
    $mapa = [
        'logica' => ['rotulo' => '</>', 'cor' => '#ff6b00'],
        'js'     => ['rotulo' => 'JS',  'cor' => '#f7df1e'],
        'loop'   => ['rotulo' => '↻',   'cor' => '#a855f7'],
        'html'   => ['rotulo' => '#',   'cor' => '#e34f26'],
        'css'    => ['rotulo' => '{ }', 'cor' => '#2965f1'],
        'funcao' => ['rotulo' => 'fx',  'cor' => '#38bdf8'],
        'prova'  => ['rotulo' => '✓',   'cor' => '#00d26a'],
    ];

    return $mapa[$codigo ?? ''] ?? ['rotulo' => '</>', 'cor' => '#8a93a6'];
}


/**
 * Bloco de ícone da trilha, desenhado em SVG (sem imagem externa).
 *
 * Os desenhos de HTML e CSS são escudos simplificados com o número, no espírito
 * dos logotipos oficiais, mas feitos aqui — não são os arquivos da W3C.
 * Trilha bloqueada mostra o cadeado no lugar do desenho.
 */
function iconeDaTrilha(?string $codigo, bool $bloqueada = false, string $classeExtra = ''): string
{
    $codigo = array_key_exists((string) $codigo, [
        'logica' => 1, 'html' => 1, 'css' => 1, 'js' => 1, 'loop' => 1,
        'funcao' => 1, 'desafios' => 1, 'projeto' => 1,
    ]) ? $codigo : 'padrao';

    $logo = logoDaTrilha($codigo);

    $desenho = $bloqueada ? icone('cadeado', 30) : match ($codigo) {
        'html', 'css', 'js' => '<img src="' . e($logo) . '" alt="" class="bloco-trilha-logo">',
        'funcao'   => '<span class="bloco-trilha-texto bloco-trilha-fx"><i>f</i>x</span>',
        'loop'     => '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 1-15.5 6.2M3 12a9 9 0 0 1 15.5-6.2"/><path d="M18.5 2.5v3.8h-3.8M5.5 21.5v-3.8h3.8"/></svg>',
        'projeto'  => '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22V4a1 1 0 0 1 1-1h12l-2 4 2 4H5"/></svg>',
        default    => '<span class="bloco-trilha-texto bloco-trilha-codigo">&lt;/&gt;</span>',
    };

    return '<span class="bloco-trilha bloco-trilha-' . $codigo . ($bloqueada ? ' bloco-trilha-bloqueada' : '')
         . ($classeExtra !== '' ? ' ' . $classeExtra : '') . '">' . $desenho . '</span>';
}


/**
 * Logo oficial da linguagem (HTML5, CSS3, JS) para as trilhas que têm uma.
 * As outras trilhas usam o desenho em SVG de iconeDaTrilha().
 */
function logoDaTrilha(?string $codigo): ?string
{
    return match ($codigo) {
        'html'  => asset('imagens/logo-html.png'),
        'css'   => asset('imagens/logo-css.png'),
        'js'    => asset('imagens/logo-js.png'),
        default => null,
    };
}


/**
 * Converte um link do YouTube no endereço de incorporação (embed), ou devolve
 * null se não for um vídeo do YouTube.
 *
 * O endereço vai para <iframe src>. Sem esta checagem, um "javascript:..."
 * gravado no campo rodaria na página do aluno. Por isso só passa o formato
 * conhecido, montado aqui a partir do ID do vídeo (11 caracteres).
 * Aceita: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, /shorts/ID.
 */
function urlVideoSegura(?string $url): ?string
{
    $url = trim((string) $url);
    $padrao = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})~';
    if (!preg_match($padrao, $url, $m)) {
        return null;
    }
    return 'https://www.youtube-nocookie.com/embed/' . $m[1];
}


/**
 * Caminho de um arquivo de assets/ com a data de modificação na URL.
 *
 *     asset('css/styles.css')  →  assets/css/styles.css?v=1789958320
 *
 * Sem isso o navegador reaproveita o CSS/JS antigo do cache depois de uma
 * atualização, e a página aparece "quebrada": HTML novo com estilo velho.
 * Como o número muda sempre que o arquivo muda, o navegador baixa de novo
 * só quando precisa.
 */
function asset(string $caminho): string
{
    $arquivo = __DIR__ . '/../assets/' . $caminho;
    $versao  = is_file($arquivo) ? filemtime($arquivo) : 0;

    return 'assets/' . $caminho . '?v=' . $versao;
}


/**
 * Selo hexagonal de conquista, na cor da raridade (como no wireframe).
 * Bloqueada fica cinza, com cadeado.
 */
function seloConquista(string $icone, string $raridade, bool $bloqueada): string
{
    $cores = [
        'bronze'   => ['#fb923c', '#9a3412'],
        'prata'    => ['#60a5fa', '#1e3a8a'],
        'especial' => ['#c084fc', '#4c1d95'],
        'ouro'     => ['#fcd34d', '#92400e'],
    ];
    [$c1, $c2] = $bloqueada ? ['#4b5568', '#1f2533'] : ($cores[$raridade] ?? $cores['bronze']);
    $mapa = ['trofeu' => 'ranking', 'mapa' => 'mapa', 'medalha' => 'medalha'];
    $desenho = $bloqueada ? 'cadeado' : ($mapa[$icone] ?? $icone);
    $id = 'g' . substr(md5($raridade . ($bloqueada ? 'b' : '')), 0, 6);

    return '<span class="selo-conquista' . ($bloqueada ? ' selo-conquista-bloqueada' : '') . '">'
         . '<svg viewBox="0 0 100 110" aria-hidden="true"><defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
         . '<stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
         . '<polygon points="50,3 95,29 95,81 50,107 5,81 5,29" fill="url(#' . $id . ')"/>'
         . '<polygon points="50,14 85,34 85,76 50,96 15,76 15,34" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="3"/>'
         . '</svg><span class="selo-conquista-icone">' . icone($desenho, 30) . '</span></span>';
}



/* -----------------------------------------------------------------------------
 * Datas por extenso, em português
 *
 * Sem depender da extensão intl nem de setlocale(): no XAMPP do Windows o
 * locale pt_BR muitas vezes não está instalado, e a data sairia em inglês.
 * -------------------------------------------------------------------------- */

/** Ex.: "23 de setembro de 2026". */
function dataPorExtenso(?string $quando = null): string
{
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
              'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = $quando !== null ? strtotime($quando) : time();

    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

/** Ex.: "Quinta-feira". */
function diaDaSemana(?string $quando = null): string
{
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
             'Quinta-feira', 'Sexta-feira', 'Sábado'];

    return $dias[(int) date('w', $quando !== null ? strtotime($quando) : time())];
}
