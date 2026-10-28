<?php
// db.php — cria e abre o banco de dados do lab (SQLite, num arquivo so).
// SQLite nao precisa de servidor: o banco inteiro e o arquivo lab.db aqui do lado.

function conectar() {
    $caminho = __DIR__ . '/lab.db';
    $primeira_vez = !file_exists($caminho);

    $pdo = new PDO('sqlite:' . $caminho);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($primeira_vez) {
        criar_dados($pdo);
    }
    return $pdo;
}

// Na primeira execucao: cria as tabelas e poe dados de exemplo.
function criar_dados($pdo) {

    // --- usuarios (dados "sensiveis" de proposito, pro exercicio de IDOR) ---
    $pdo->exec("CREATE TABLE usuarios (
        id       INTEGER PRIMARY KEY,
        usuario  TEXT,
        senha    TEXT,
        cargo    TEXT,
        email    TEXT,
        telefone TEXT
    )");
    $pdo->exec("INSERT INTO usuarios (usuario, senha, cargo, email, telefone) VALUES
        ('admin', 'S3nh@F0rte!2026', 'administrador', 'admin@loja.local', '11-99999-0001'),
        ('user',  'batata123',       'usuario',       'user@loja.local', '11-98888-0002'),
        ('maria', 'maria2024',       'usuario',       'maria@loja.local', '11-97777-0003')
    ");

    // --- produtos (alvo da busca: SQLi + XSS refletido) ---
    $pdo->exec("CREATE TABLE produtos (
        id        INTEGER PRIMARY KEY,
        nome      TEXT,
        preco     REAL,
        categoria TEXT
    )");
    $pdo->exec("INSERT INTO produtos (nome, preco, categoria) VALUES
        ('Teclado Mecanico',  250.00, 'perifericos'),
        ('Mouse Gamer',       120.00, 'perifericos'),
        ('Monitor 24 pol',    900.00, 'telas'),
        ('Headset',           180.00, 'audio'),
        ('Webcam HD',         150.00, 'video')
    ");

    // --- comentarios (alvo do mural: XSS armazenado) ---
    $pdo->exec("CREATE TABLE comentarios (
        id        INTEGER PRIMARY KEY,
        autor     TEXT,
        texto     TEXT,
        criado_em TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("INSERT INTO comentarios (autor, texto) VALUES
        ('maria', 'Produto chegou rapido, recomendo!'),
        ('user', 'Teclado otimo pra programar.')
    ");
}
