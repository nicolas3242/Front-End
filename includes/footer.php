<?php
/**
 * includes/footer.php
 * Fecha as tags abertas por includes/header.php e carrega o que vale para
 * todas as páginas autenticadas.
 */

require_once __DIR__ . '/conquistas.php';

// Conquistas desbloqueadas num POST que redirecionou (prova, login) ficam
// guardadas na sessão até a próxima página — é aqui que elas saem de lá.
// A tela de desafio não passa por este caminho: lá a própria resposta da API
// traz as conquistas e o aviso aparece sem recarregar.
$conquistasNovas = consumirConquistasDaSessao();
?>
        </div>
    </main>
</div>

<?php if (!empty($conquistasNovas)): ?>
    <script type="application/json" id="dadosConquistas"><?= json_encode(
        array_map(static function (array $c): array {
            return [
                'titulo'    => $c['titulo'],
                'descricao' => $c['descricao'],
                'raridade'  => $c['raridade'],
            ];
        }, $conquistasNovas),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?></script>
<?php endif; ?>

<script src="<?= asset('js/conquistas.js') ?>"></script>
<script src="<?= asset('js/sino.js') ?>"></script>

</body>
</html>
