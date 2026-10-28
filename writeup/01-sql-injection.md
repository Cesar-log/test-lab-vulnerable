# 01 — SQL Injection

| | |
|---|---|
| **Severidade** | Crítica |
| **Onde** | `app/login.php` (bypass de login) e `app/busca.php` (vazamento de dados via UNION) |
| **Categoria** | OWASP Top 10 — A03:2021 Injection |

## Resumo

A aplicação monta os comandos SQL **colando o texto digitado pelo usuário direto dentro da query**. Como o banco recebe dado e comando na mesma string, um atacante consegue encerrar o campo com uma aspa (`'`) e escrever seu próprio SQL. Isso permite **entrar sem saber a senha** e **ler qualquer tabela do banco** (incluindo as senhas de todos os usuários).

---

## 1. Como achar

Sinal clássico: digitar uma aspa simples (`'`) num campo e a aplicação quebrar ou se comportar diferente.

Na busca, pesquisar por `'` gera um erro de SQL na tela:

```
Erro SQL: SQLSTATE[HY000]: near "...": syntax error
```

Um erro de sintaxe vindo do banco é a confissão de que **a entrada está virando parte do comando**. A partir daí, é injeção.

## 2. Como explorar

### 2a. Bypass de login (`login.php`)

O código monta:

```sql
SELECT * FROM usuarios WHERE usuario = '<ENTRADA>' AND senha = '<ENTRADA>'
```

Enviando no campo **usuário**:

```
admin' -- 
```

A query vira:

```sql
SELECT * FROM usuarios WHERE usuario = 'admin' -- ' AND senha = '...'
```

- A aspa fecha o campo `usuario` antes da hora.
- `--` é comentário em SQL: tudo depois é ignorado, **inclusive a checagem de senha**.

Resultado: autenticado como `admin` sem conhecer a senha.

### 2b. Vazamento de dados via UNION (`busca.php`)

A busca roda:

```sql
SELECT nome, preco, categoria FROM produtos WHERE nome LIKE '%<ENTRADA>%'
```

São **3 colunas**. Com `UNION SELECT` dá pra anexar o resultado de *outra* consulta na mesma lista, desde que o número de colunas bata. Pesquisando por:

```
' UNION SELECT usuario, senha, cargo FROM usuarios -- 
```

A query vira:

```sql
SELECT nome, preco, categoria FROM produtos WHERE nome LIKE '%'
UNION SELECT usuario, senha, cargo FROM usuarios -- %'
```

Resultado: a lista de "produtos" passa a exibir **usuário, senha e cargo de todo mundo**.

```
admin  | S3nh@F0rte!2026 | administrador
user   | batata123       | usuario
maria  | maria2024       | usuario
```

## 3. Impacto

- Login como qualquer usuário (incluindo administrador) sem credenciais.
- Leitura de todo o banco: senhas, e-mails, telefones, qualquer tabela.
- Dependendo do banco/permissões: escrita, apagar dados, às vezes execução de comando no sistema.

## 4. Causa raiz

Concatenar entrada do usuário dentro do SQL:

```php
// app/login.php — VULNERÁVEL
$sql = "SELECT * FROM usuarios WHERE usuario = '$usuario' AND senha = '$senha'";
$dono = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
```

O banco não tem como saber onde o "dado" termina e o "comando" começa — está tudo na mesma string de texto.

## 5. Correção — Prepared Statements (consultas parametrizadas)

A correção certa **não** é "filtrar aspas". É usar **consultas parametrizadas**: o comando vai com marcadores (`?`) e os dados vão **separados**, por outro canal. Assim o banco já recebe o comando pronto e trata os dados sempre como dado puro — nunca como comando. A aspa perde o poder.

```php
// app/login.php — CORRIGIDO
$sql = "SELECT * FROM usuarios WHERE usuario = ? AND senha = ?";
$stmt = $pdo->prepare($sql);      // manda o comando com os marcadores
$stmt->execute([$usuario, $senha]); // manda os dados por outro canal
$dono = $stmt->fetch(PDO::FETCH_ASSOC);
```

Com isso, enviar `admin' -- ` faz o banco procurar literalmente por um usuário **chamado** `admin' -- ` — que não existe. O ataque morre.

O mesmo vale pra busca:

```php
// app/busca.php — CORRIGIDO
$stmt = $pdo->prepare("SELECT nome, preco, categoria FROM produtos WHERE nome LIKE ?");
$stmt->execute(['%' . $q . '%']);  // o % entra no dado, não no comando
$linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Defesa em profundidade (camadas extras)

- **Menor privilégio:** a conta do banco usada pela aplicação não precisa poder apagar tabelas.
- **Senhas com hash:** nunca guardar senha em texto puro; usar `password_hash()` / `password_verify()`. Assim, mesmo que vaze, a senha não vem legível.
- **Mensagem de erro genérica:** não jogar o erro de SQL na tela do usuário (ajuda o atacante).

## 6. Validação da correção

Testado o payload `admin' -- ` contra a versão com prepared statement: o login é **negado** (nenhum usuário chamado `admin' -- ` existe). A falha deixa de existir sem precisar filtrar nenhum caractere.
