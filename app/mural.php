<?php
// mural.php — mural de recados. Tem XSS ARMAZENADO de proposito:
// o texto do recado e salvo no banco e depois impresso na tela SEM tratamento.
// Diferenca pro refletido: aqui o <script> fica GUARDADO e roda pra todo mundo que abrir a pagina.
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = conectar();

// Enviar um recado novo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $autor = $_POST['autor'] ?? 'anonimo';
    $texto = $_POST['texto'] ?? '';
    // Aqui o insert ate usa prepared statement (sem SQLi)...
    $stmt = $pdo->prepare("INSERT INTO comentarios (autor, texto) VALUES (?, ?)");
    $stmt->execute([$autor, $texto]);
    header('Location: mural.php'); // recarrega pra nao reenviar no F5
    exit;
}

$recados = $pdo->query("SELECT autor, texto, criado_em FROM comentarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

topo('Mural');
?>
<div class="caixa">
    <h2>Mural de recados</h2>
    <form method="post">
        <input name="autor" placeholder="seu nome">
        <textarea name="texto" placeholder="deixe um recado"></textarea>
        <button>Publicar</button>
    </form>

    <hr>
    <?php foreach ($recados as $r): ?>
        <p>
            <b><?= htmlspecialchars($r['autor']) ?></b>
            <small>(<?= htmlspecialchars($r['criado_em']) ?>)</small><br>
            <!-- FALHA: o texto do recado e impresso SEM htmlspecialchars -> XSS armazenado -->
            <?= $r['texto'] ?>
        </p>
    <?php endforeach; ?>
</div>
<?php rodape(); ?>
