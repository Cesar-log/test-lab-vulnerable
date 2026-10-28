<?php
// index.php — pagina inicial: so um menu das areas vulneraveis do lab.
require __DIR__ . '/layout.php';
topo('Inicio');
?>
<div class="caixa">
    <h1>Lab Web Vulneravel</h1>
    <p>Aplicacao de treino com falhas <b>de proposito</b>. Cada area treina uma falha classica de pentest web.</p>
    <p class="aviso">Ambiente de estudo. Rode so na sua maquina (localhost). Nunca exponha na internet.</p>
    <ul>
        <li><a href="login.php">Login</a> &mdash; SQL Injection (entrar sem senha)</li>
        <li><a href="busca.php">Busca de produtos</a> &mdash; SQL Injection + XSS refletido</li>
        <li><a href="mural.php">Mural de recados</a> &mdash; XSS armazenado</li>
        <li><a href="perfil.php?id=2">Perfil</a> &mdash; IDOR (ver dados de outros usuarios)</li>
    </ul>
</div>
<?php rodape(); ?>
