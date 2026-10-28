# 03 — IDOR (Insecure Direct Object Reference)

| | |
|---|---|
| **Severidade** | Alta |
| **Onde** | `app/perfil.php` |
| **Categoria** | OWASP Top 10 — A01:2021 Broken Access Control |

## Resumo

A página mostra o perfil de um usuário a partir do número na URL (`?id=2`). O problema: ela **confia no id sem verificar se quem está pedindo tem direito de ver aquele registro**. Trocando o número (`?id=1`, `?id=2`, `?id=3`...) qualquer pessoa lê os dados de qualquer usuário.

Diferente de SQLi e XSS, aqui **não há dado virando código**. A entrada é um número legítimo. A falha é de **controle de acesso**: falta a pergunta *"esse usuário pode ver isso?"*.

---

## 1. Como achar

Procurar por **identificadores previsíveis na URL ou no corpo da requisição**: `?id=`, `?user=`, `?conta=`, `/pedido/1037`. O teste é direto: **trocar o número** e ver se vem dado de outra pessoa.

```
perfil.php?id=2   → meu perfil
perfil.php?id=1   → perfil do admin   ← não deveria conseguir
```

Se o id=1 retorna dados que não são seus, é IDOR.

## 2. Como explorar

A página roda:

```php
$id = $_GET['id'] ?? '1';
$sql = "SELECT id, usuario, cargo, email, telefone, senha FROM usuarios WHERE id = $id";
```

Não há **nenhuma** checagem de quem está pedindo. Basta iterar os ids:

```
perfil.php?id=1   → admin  | admin@loja.local | S3nh@F0rte!2026
perfil.php?id=2   → user   | user@loja.local  | batata123
perfil.php?id=3   → maria  | maria@loja.local | maria2024
```

Como os ids são sequenciais (1, 2, 3...), dá pra **enumerar a base inteira** com um laço simples — exatamente o que o scanner da parte 2 do projeto vai automatizar.

> Observação: concatenar `$id` no SQL também abre SQL Injection aqui. Mas a lição principal deste writeup é o **controle de acesso** — IDOR existe mesmo quando o SQL está 100% parametrizado.

## 3. Impacto

- Vazamento de dados pessoais de todos os usuários (LGPD): e-mail, telefone, senha.
- Em outros contextos: ler pedidos, notas fiscais, mensagens, prontuários de terceiros.
- Se a mesma falha existir em ações de **escrita** (ex.: `editar.php?id=`), permite **alterar/apagar** registros de outros.

## 4. Causa raiz

A aplicação usa o identificador que o **cliente** mandou como se fosse confiável, sem cruzar com **quem está logado**:

```php
// VULNERÁVEL: usa o id da URL direto, sem checar dono
$id = $_GET['id'];
// ...busca e mostra o registro desse id, seja de quem for
```

Falta a regra de autorização: *o registro pedido pertence a quem está pedindo (ou quem pede é admin)?*

## 5. Correção — verificar autorização no servidor

A correção **não** é esconder o id nem embaralhar o número (isso é "segurança por obscuridade" e não resolve). É **checar permissão no servidor, a cada requisição**.

### Opção A — usar a identidade da sessão, ignorar o id do cliente

Na maioria dos casos "ver meu perfil" nem precisa de id na URL: usa quem está logado.

```php
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Faça login.'); }

$stmt = $pdo->prepare("SELECT ... FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);   // o id vem da SESSÃO, não da URL
```

### Opção B — quando o id na URL é necessário, checar o dono

```php
session_start();
$id = (int) ($_GET['id'] ?? 0);

$ehDono  = ($id === (int) $_SESSION['user_id']);
$ehAdmin = ($_SESSION['cargo'] ?? '') === 'administrador';

if (!$ehDono && !$ehAdmin) {
    http_response_code(403);   // proibido
    exit('Você não tem acesso a este perfil.');
}
// só aqui busca e mostra
```

### Outras camadas

- **Nunca** retornar campos sensíveis que a tela não precisa (a senha nem deveria estar no SELECT; e deveria ser hash).
- Identificadores **não sequenciais** (UUID) dificultam a enumeração — mas são **complemento**, nunca substituem a checagem de permissão.
- Aplicar a mesma verificação em **toda** rota que recebe id (ler, editar, apagar), não só nesta.

## 6. Princípio geral

> O cliente pode pedir qualquer coisa. Quem decide o que ele **pode** receber é o servidor — e essa decisão tem que ser tomada **a cada requisição**, do lado de cá. Nunca confie em id, preço, cargo ou permissão que venha do navegador.
