# SnakeCode

[English](README.en.md)

Jogo da cobrinha no terminal disfarçado de IDE. Quem olha vê um editor com abas, syntax
highlighting, breadcrumb e statusbar. Você está jogando Snake.

```bash
snakecode                           # fundo = código do próprio jogo
snakecode /var/www/html/meu-projeto # fundo = código do SEU projeto
```

## Instalação

Os pacotes de distribuição são gerados em `dist/` pelo script abaixo. Esse diretório fica fora do repositório.

| Sistema | Pacote | Como instalar |
|---|---|---|
| **Windows 10/11 (x64)** | `snakecode-1.0.0-windows-x64.zip` | Extraia e dê dois cliques em `install.cmd`. **Não precisa de PHP**: o pacote traz o PHP 8.3 oficial portátil. |
| **Linux / macOS / WSL** | `snakecode-1.0.0-linux.tar.gz` | `tar xzf ...` e `sh install.sh` (instala em `~/.local`, sem sudo). Requer PHP >= 8.3 com `mbstring`. |
| Qualquer SO com PHP 8.3+ | `snakecode.phar` | `php snakecode.phar [pasta]` (no Windows, o PHP precisa de `extension=ffi` e `ffi.enable=true`). |

No Windows o jogo roda nativo no **Windows Terminal**, no PowerShell ou no cmd, e o instalador cria o comando
`snakecode` no PATH e um atalho no Menu Iniciar. Git Bash/mintty não é suportado. Pelo código-fonte, sem instalar:
`php8.3 bin/snakecode` no Linux ou `php bin\snakecode` no Windows.

### Gerar os pacotes

```bash
php8.3 -d phar.readonly=0 build/package.php               # phar + Linux + Windows
php8.3 -d phar.readonly=0 build/package.php --no-windows  # sem baixar o PHP para Windows
```

O pacote Windows usa o build oficial `nts-vs16-x64` de windows.php.net. O **SHA-256 é conferido** com o publicado,
e só entram o `php.exe`, as extensões necessárias e as DLLs que eles realmente importam.

## Usando o seu projeto como fundo

Ao informar a pasta de um projeto, o editor passa a mostrar os arquivos dele, com caminhos, abas e
breadcrumb relativos à raiz do projeto e a linguagem de cada arquivo na statusbar.

- **Só código escrito para o projeto:** em repositórios git são usados os arquivos versionados e os
  novos não ignorados (`git ls-files`, que respeita o `.gitignore`). Fora do git, a varredura pula
  `vendor/`, `node_modules/`, `storage/`, `cache/`, `dist/`, `build/`, `coverage/`, pastas ocultas e similares.
- **Só arquivos de código:** PHP, Blade, JS/TS, Vue, Svelte, HTML, CSS/SCSS, Python, Ruby, Go, Rust,
  Java, Kotlin, C#, C/C++, Swift, SQL e Shell. Mídia, documentos (`.md`, `.pdf`...), lock files,
  JSON/YAML, minificados (`*.min.js`), bundles e arquivos acima de 128 KB ficam de fora.
- **Nada de segredos na tela:** `.env*` e arquivos com nomes como `secrets.php` ou `credentials.js` nunca são exibidos.
- **40 arquivos**, os **editados mais recentemente** primeiro. Use `--files=N` para mudar a quantidade.
- Os arquivos só são lidos quando a aba abre, então projetos grandes não pesam na inicialização.

## Como ler a tela

| Na IDE | No jogo |
|---|---|
| Cursor em bloco | cabeça da cobra |
| Fundo de seleção atrás do cursor | corpo |
| Sublinhado ondulado vermelho (linter) | comida |
| `●` no gutter e `Ln X, Col Y` na statusbar | linha e coluna exatas da comida |
| `■ Undefined variable $food at col N` | dica inline na linha da comida |
| `▸ ⋯ N lines` (região dobrada) | obstáculo (a partir do nível 3) |
| Branch `feature/level-N` | nível atual |
| `⟳ mortes↓ pontos↑` e `✓ comidas/meta` | placar |
| Nova aba | **cada curva abre outro arquivo do workspace** |
| Painel TERMINAL com `PHP Fatal error` | game over, com o stack trace real da colisão |

## Menu inicial

O jogo abre numa aba **Welcome**, como a página inicial de uma IDE:

- **Iniciar:** `Iniciar`, a discrição (`◀ ▶` troca o perfil) e `Sair`. Quando o menu é aberto no meio do jogo com `m`,
  também aparecem `Continuar partida` e `Nova partida`.
- **Atalhos** e **Como funciona:** a legenda com amostras reais de cada elemento, desenhadas com o perfil de discrição
  escolhido (pré-visualização ao vivo).
- A tecla de pânico também funciona no menu. Use `--no-menu` para ir direto ao jogo.

## Controles

| Tecla | Ação |
|---|---|
| ↑ / ↓ e Enter (no menu) | escolher uma opção; ← / → trocam a discrição |
| `m` | abre o menu (pausa o jogo) |
| Setas / WASD / hjkl | mover (a primeira tecla também inicia) |
| `p` ou espaço | pausa ("Paused on breakpoint") |
| `v` | alterna a discrição: `subtle`, `medium` e `easy` |
| `Esc` ou `` ` `` | **pânico**: esconde tudo e deixa só código na tela; repita para voltar (pausado) |
| `Enter` | reinicia após o game over |
| `q` / Ctrl+C | sai e restaura o terminal |

## Opções

```
PASTA_DO_PROJETO               projeto usado como fundo (padrão: o código do próprio jogo)
--files=N                      quantidade de arquivos, 1 a 500 (padrão: 40)
--stealth=subtle|medium|easy   perfil de discrição (o último usado fica salvo)
--no-menu                      pula o menu inicial
--seed=N                       sorteio determinístico
--render-once                  imprime um frame e sai (sem TTY)
```

As opções podem vir antes ou depois da pasta, sempre no formato `--nome=valor`.

O recorde e o perfil ficam em `~/.local/state/snakecode/state.json` (diretório `0700` e arquivo `0600`,
com escrita atômica). Se o processo for morto com `kill -9` e o terminal ficar desconfigurado, rode `reset`.

## Testes

```bash
php8.3 /usr/local/bin/composer install
php8.3 vendor/bin/phpunit
```
