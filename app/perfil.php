<?php
// perfil.php — mostra o perfil de um usuario pelo ?id= da URL.
// Tem IDOR de proposito: nao checa se voce TEM DIREITO de ver aquele id.
// Trocando o numero na URL (?id=1, ?id=2, ?id=3...) voce le os dados de qualquer um.
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$id = $_GET['id'] ?? '1';
$pdo = conectar();

// ---- FALHA: pega o id da URL e confia nele sem verificar quem esta pedindo ----
// (de quebra, concatenar o id tambem abre SQLi; a licao principal aqui e o IDOR)
$sql = "SELECT id, usuario, cargo, email, telefone, senha FROM usuarios WHERE id = $id";
try {
    $u = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $u = null;
}

topo('Perfil');
?>
<div class="caixa">
    <h2>Perfil do usuario</h2>
    <p class="aviso">Experimento: troque o <b>?id=</b> na barra de enderecos (1, 2, 3...) e veja o que aparece.</p>
    <?php if ($u): ?>
        <table>
            <tr><th>Campo</th><th>Valor</th></tr>
            <tr><td>ID</td><td><?= htmlspecialchars($u['id']) ?></td></tr>
            <tr><td>Usuario</td><td><?= htmlspecialchars($u['usuario']) ?></td></tr>
            <tr><td>Cargo</td><td><?= htmlspecialchars($u['cargo']) ?></td></tr>
            <tr><td>E-mail</td><td><?= htmlspecialchars($u['email']) ?></td></tr>
            <tr><td>Telefone</td><td><?= htmlspecialchars($u['telefone']) ?></td></tr>
            <tr><td>Senha</td><td><?= htmlspecialchars($u['senha']) ?></td></tr>
        </table>
    <?php else: ?>
        <p class="erro">Usuario nao encontrado.</p>
    <?php endif; ?>
</div>
<?php rodape(); ?>
