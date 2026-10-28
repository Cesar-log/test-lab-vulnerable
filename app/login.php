<?php
// login.php — tela de login. Tem SQL Injection DE PROPOSITO.
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$erro = null;
$logado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha   = $_POST['senha']   ?? '';
    $pdo = conectar();

    // ---- AQUI MORA A FALHA ----
    // O que o usuario digita entra DIRETO no comando SQL (concatenacao de texto).
    // Com uma aspa ele "sai" do campo e passa a mandar comando pro banco.
    $sql = "SELECT * FROM usuarios WHERE usuario = '$usuario' AND senha = '$senha'";
    $dono = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    if ($dono) { $logado = $dono; }
    else       { $erro = 'Usuario ou senha invalidos.'; }
}

topo('Login');
?>
<div class="caixa">
    <h2>Entrar</h2>
    <?php if ($logado): ?>
        <p class="ok">Bem-vindo, <b><?= htmlspecialchars($logado['usuario']) ?></b>! Cargo: <?= htmlspecialchars($logado['cargo']) ?></p>
    <?php else: ?>
        <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <form method="post">
            <input name="usuario" placeholder="usuario" autofocus>
            <input name="senha" type="password" placeholder="senha">
            <button>Entrar</button>
        </form>
    <?php endif; ?>
</div>
<?php rodape(); ?>
