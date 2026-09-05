# Atualizações via GitHub Releases

Como o plugin recebe novas versões pela tela **Plugins** do WordPress.

## Visão geral

```
Código local
   ↓ git push + git tag vX.Y.Z
GitHub
   ↓ GitHub Actions (.github/workflows/release.yml)
Release com o anexo horas-trabalhadas.zip
   ↓ includes/class-updater.php lê a Release (API pública)
Transient update_plugins do WordPress
   ↓
Tela de Plugins: "Há uma nova versão disponível" → "Atualizar agora"
   (e o controle nativo "Ativar atualizações automáticas")
```

O plugin **não** implementa um sistema de atualização paralelo. Ele apenas
alimenta o mecanismo nativo do WordPress, o que preserva a tela de Plugins, a
tela de Atualizações, o contador de atualizações, a janela de detalhes e as
atualizações automáticas em segundo plano.

## Configuração

Em **Configurações › Horas Trabalhadas**, informe o repositório no formato
`proprietario/repositorio`.

Como alternativa, fixe o valor por ambiente no `wp-config.php` — nesse caso o
campo do painel fica bloqueado:

```php
define( 'HORAS_TRABALHADAS_GITHUB_REPOSITORY', 'minhaempresa/horas-trabalhadas' );
```

O repositório precisa ser **público**, porque a consulta é feita sem
credenciais. Veja `licenca-api.md` para distribuição privada.

## Requisitos da Release

O atualizador procura, nesta ordem:

1. Um anexo chamado exatamente **`horas-trabalhadas.zip`** (produzido pelo
   workflow de release). É o caminho preferido.
2. O `zipball_url` gerado automaticamente pelo GitHub, como alternativa.

O anexo é preferido porque o zipball do GitHub descompacta em uma pasta com nome
`proprietario-repositorio-a1b2c3d/`. Instalar isso criaria uma **segunda cópia**
do plugin em vez de atualizar a existente. O filtro `upgrader_source_selection`
corrige o nome da pasta como rede de segurança, mas o anexo já vem correto.

Releases marcadas como **rascunho** ou **pré-lançamento** são ignoradas.

## Validações de segurança

Tudo que vem do GitHub é tratado como entrada não confiável:

| Item | Validação |
|---|---|
| Repositório | Precisa casar com `^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$` antes de entrar na URL da API |
| Resposta HTTP | Precisa ser 200 e decodificar como objeto JSON |
| Tag | Precisa ser `X.Y.Z` (com `v` opcional); nada além de dígitos e pontos |
| URL do pacote | Precisa ser HTTPS **e** estar em uma lista fixa de hosts do GitHub |
| Notas da release | Escapadas com `esc_html()` antes de aparecerem no painel |

Nenhuma URL arbitrária pode virar origem de atualização. Nenhuma credencial é
armazenada ou enviada.

## Cache

O resultado da consulta fica em um *site transient* por 12 horas; uma falha fica
em cache por 1 hora, para que uma indisponibilidade do GitHub ou um limite de
requisições não gere consultas a cada carregamento de página.

O botão **Verificar atualizações agora** limpa o cache imediatamente.

## Publicando uma nova versão

```bash
# 1. Faça as alterações e atualize a versão em DOIS lugares:
#    - horas-trabalhadas.php  (cabeçalho Version e a constante)
#    - readme.txt             (Stable tag)

# 2. Valide localmente (o mesmo que a CI roda)
npm test

# 3. Reconstrua os arquivos .min se você alterou CSS ou JS
npm run build

# 4. Commit e push
git add -A
git commit -m "Versão 2.0.1"
git push origin main

# 5. Marque e publique
git tag v2.0.1
git push origin v2.0.1
```

O push da tag dispara o workflow de release, que valida, monta o pacote e
publica a Release com o anexo `horas-trabalhadas.zip`.

Se a tag não corresponder à versão dentro do plugin, o workflow **falha de
propósito** — isso evita publicar uma Release que o WordPress nunca ofereceria.

## Verificando no WordPress

O WordPress consulta atualizações a cada 12 horas. Para forçar imediatamente:

- **Painel › Atualizações › Verificar novamente**, ou
- **Configurações › Horas Trabalhadas › Verificar atualizações agora**.
