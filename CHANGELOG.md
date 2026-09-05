# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento semântico: MAJOR.MINOR.PATCH.

## [2.0.0]

Versão maior: o produto foi renomeado e a pasta do plugin mudou, portanto a
atualização a partir da versão anterior é uma instalação nova (veja
`docs/atualizacoes.md`). Nenhum dado é perdido — as folhas de ponto ficam no
navegador e são migradas automaticamente.

### Alterado
- Produto renomeado para **Horas Trabalhadas**. Toda a interface administrativa
  passa a ser em português do Brasil.
- Arquitetura reescrita em classes com namespace `HorasTrabalhadas` e autoloader.
- Os períodos passam a ser resolvidos no fuso horário configurado no WordPress,
  e não no fuso do dispositivo de quem acessa.
- O dia de início da semana passa a seguir a configuração do WordPress.
- Entrada de horário em 24 horas passa a ser o padrão.
- Removido o `<h1>` interno, que competia com o título da página do WordPress.

### Corrigido
- **Horas extras semanais**: o limite de 40 horas reiniciava apenas no início do
  período selecionado, e não a cada semana. Em períodos de duas semanas ou de um
  mês, horas normais eram classificadas como extras e o pagamento bruto ficava
  inflado.
- Horas e minutos fora de faixa passam a ser limitados, em vez de gerar um total
  plausível porém errado.
- Declarações duplicadas de `--text-secondary` no CSS.

### Adicionado
- Atualizações pela tela de Plugins do WordPress a partir de Releases do GitHub,
  com suporte a atualizações automáticas nativas.
- Tela **Configurações › Horas Trabalhadas**: licença, repositório e verificação
  de atualizações.
- Arquitetura de licenciamento com servidor próprio, período de tolerância e
  falha graciosa.
- Sistema de migrações versionado e idempotente.
- Workflows de CI e de release, e verificações automáticas de versão, marca,
  estrutura e ausência de credenciais.

### Segurança
- Proteção contra fórmulas em CSV exportado.
- Validação de dados vindos de links compartilhados (`#d=`) e do armazenamento local.
- Sanitização do nome de arquivo gerado na exportação.
- Validação estrita da origem dos pacotes de atualização (HTTPS + lista fixa de hosts).
- Verificações de capacidade e nonce em todas as ações administrativas.
- `uninstall.php` passa a usar `get_sites()` em vez de consulta SQL direta.

## [1.2.0]

Versão anterior do produto.
