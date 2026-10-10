<?php
/**
 * includes/avatares.php — catálogo de avatares e o <img> do avatar.
 *
 * O aluno escolhe entre um catálogo FIXO (não há upload de imagem): upload
 * exigiria validar tipo real do arquivo, tamanho, redimensionar e servir com
 * segurança — risco e trabalho sem ganho para o TCC. O código escolhido fica em
 * usuarios.foto_avatar, e aqui ele é conferido contra a lista antes de virar
 * caminho de arquivo, para ninguém gravar "../../algo" no banco e ver na tela.
 */

require_once __DIR__ . '/ui.php';

/** Código → [arquivo, fundo (classe CSS extra), rótulo]. */
function avataresDisponiveis(): array
{
    // Os SVG (assets/imagens/avatares/raposa-*.svg) seguem o mesmo desenho,
    // mudando espécie e cor. Substituíram 4 cópias da logo recolorida, que
    // pareciam improvisadas. Código que saiu do catálogo cai em 'padrao'.
    return [
        'padrao'   => ['imagens/logo-marca.png',                  '',            'Raposa DevFox'],
        'retrato'  => ['imagens/avatares/raposa-retrato.jpg',      'avatar-foto', 'Raposa da montanha'],
        'vermelha' => ['imagens/avatares/raposa-vermelha.svg',     'avatar-foto', 'Raposa vermelha'],
        'artica'   => ['imagens/avatares/raposa-artica.svg',       'avatar-foto', 'Raposa do Ártico'],
        'feneco'   => ['imagens/avatares/raposa-feneco.svg',       'avatar-foto', 'Feneco do deserto'],
        'prateada' => ['imagens/avatares/raposa-prateada.svg',     'avatar-foto', 'Raposa prateada'],
        'dev'      => ['imagens/avatares/raposa-dev.svg',          'avatar-foto', 'Raposa dev'],
        'noturna'  => ['imagens/avatares/raposa-noturna.svg',      'avatar-foto', 'Raposa noturna'],
    ];
}

/** Normaliza o que veio do banco (inclui o antigo 'default_fox.png'). */
function codigoAvatar(?string $codigo): string
{
    return array_key_exists((string) $codigo, avataresDisponiveis()) ? (string) $codigo : 'padrao';
}

/**
 * <img> do avatar, já com a moldura da Loja (classes de classeMoldura()).
 *
 * @param string $classes  classes de tamanho, ex.: 'avatar-grande'
 * @param string $moldura  resultado de classeMoldura($slug) — começa com espaço
 */
function imgAvatar(?string $codigo, string $classes = '', string $moldura = ''): string
{
    [$arquivo, $fundo, $rotulo] = avataresDisponiveis()[codigoAvatar($codigo)];
    $classe = trim('avatar ' . $classes . ' ' . $fundo) . $moldura;

    return '<img src="assets/' . $arquivo . '" alt="" class="' . e($classe) . '" title="' . e($rotulo) . '">';
}
