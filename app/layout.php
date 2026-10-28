<?php
// layout.php — cabecalho/menu/rodape usados por todas as paginas.
// Evita repetir o mesmo HTML em cada arquivo.

function topo($titulo) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8">';
    echo '<title>Lab Vulneravel - ' . htmlspecialchars($titulo) . '</title>';
    echo '<style>
        body { font-family:sans-serif; background:#1a1a1a; color:#eee; margin:0; }
        nav { background:#111; padding:12px 20px; }
        nav a { color:#2d7; margin-right:16px; text-decoration:none; font-weight:bold; }
        nav a:hover { text-decoration:underline; }
        main { max-width:720px; margin:30px auto; padding:0 20px; }
        input, textarea { padding:8px; margin:6px 0; box-sizing:border-box; width:100%;
                          background:#262626; color:#eee; border:1px solid #444; border-radius:4px; }
        button { padding:10px 16px; background:#2d7; color:#111; border:0; font-weight:bold; cursor:pointer; border-radius:4px; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th,td { border:1px solid #333; padding:8px; text-align:left; }
        .caixa { background:#262626; padding:20px; border-radius:8px; }
        .erro { color:#f66; } .ok { color:#2d7; }
        .aviso { background:#3a2a00; border:1px solid #964; padding:8px 12px; border-radius:4px; font-size:13px; color:#fc9; }
    </style></head><body>';
    echo '<nav>
        <a href="index.php">Inicio</a>
        <a href="login.php">Login</a>
        <a href="busca.php">Busca</a>
        <a href="mural.php">Mural</a>
        <a href="perfil.php?id=2">Perfil</a>
    </nav><main>';
}

function rodape() {
    echo '</main></body></html>';
}
