<?php
// busca.php — busca de produtos. Tem DUAS falhas de proposito:
//   1) SQL Injection: o termo entra direto no SQL (da pra usar UNION e vazar outras tabelas).
//   2) XSS refletido: o termo e devolvido na tela sem tratamento (da pra injetar <script>).
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$q = $_GET['q'] ?? '';
$linhas = [];
$erro_sql = null;

if ($q !== '') {
    $pdo = conectar();
    // ---- FALHA 1: concatenacao direta no SQL ----
    $sql = "SELECT nome, preco, categoria FROM produtos WHERE nome LIKE '%$q%'";
    try {
        $linhas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erro_sql = $e->getMessage();
    }
}

topo('Busca');
?>
<div class="caixa">
    <h2>Buscar produtos</h2>
    <form method="get">
        <input name="q" placeholder="ex: mouse" value="<?= htmlspecialchars($q) ?>" autofocus>
        <button>Buscar</button>
    </form>

    <?php if ($q !== ''): ?>
        <!-- FALHA 2: o termo e impresso SEM htmlspecialchars -> XSS refletido -->
        <p>Resultados para: <b><?= $q ?></b></p>

        <?php if ($erro_sql): ?>
            <p class="erro">Erro SQL: <?= htmlspecialchars($erro_sql) ?></p>
        <?php elseif ($linhas): ?>
            <table>
                <tr><th>Nome</th><th>Preco</th><th>Categoria</th></tr>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['nome']) ?></td>
                        <td><?= htmlspecialchars($l['preco']) ?></td>
                        <td><?= htmlspecialchars($l['categoria']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>Nenhum produto encontrado.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php rodape(); ?>
