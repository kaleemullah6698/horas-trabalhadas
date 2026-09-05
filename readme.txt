=== Horas Trabalhadas ===
Contributors: horastrabalhadas
Tags: horas trabalhadas, folha de ponto, cartao de ponto, horas extras, calculadora
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calculadora de horas trabalhadas e folha de ponto com intervalos, horas extras, dobro de horas, gorjetas, pagamento bruto e líquido, exportação CSV, impressão e link compartilhável.

== Description ==

O Horas Trabalhadas publica uma calculadora de jornada completa em qualquer página do seu site por meio de um shortcode. Ela calcula horas diárias e semanais, horas extras, dobro de horas, gorjetas, pagamento bruto e pagamento líquido após descontos — tudo no navegador, sem cadastro e sem armazenar dados de jornada no servidor.

**Recursos**

* Períodos prontos (esta semana, semana passada, duas semanas, mês) além de período personalizado e escolha do dia em que a semana começa
* Entrada de horário em 24 horas (padrão no Brasil) ou 12 horas, com tratamento automático de turnos que viram a noite
* Desconto de intervalo por dia
* Regras de horas extras: semanal (acima de 40h), limite diário personalizado e regras específicas de estados dos EUA (Califórnia, Alasca, Nevada) para quem precisa delas
* Multiplicador de horas extras configurável e limite opcional para dobro de horas
* Coluna de gorjetas, seletor de moeda, pagamento bruto e líquido (após impostos/descontos em %)
* Horas exibidas em HH:MM e em decimal
* Salve várias folhas/funcionários no próprio navegador
* Exporte CSV, imprima em PDF e copie um link compartilhável
* Tema claro/escuro e layout responsivo
* Os arquivos CSS e JS são carregados apenas nas páginas que usam o shortcode
* Os estilos têm escopo próprio, então o tema do site nunca é afetado

**Fuso horário**

Os períodos ("esta semana", "este mês") são resolvidos usando o fuso horário configurado em Configurações › Geral do WordPress, e não o fuso do dispositivo de quem acessa. Assim, um funcionário que abrir a folha de ponto viajando ou com o relógio do computador errado continua vendo a mesma semana que o empregador.

**Privacidade**

As folhas de ponto preenchidas ficam apenas no navegador de cada pessoa (localStorage). O plugin não cria tabelas no banco de dados, não registra jornada no servidor e não envia dados de jornada para lugar nenhum.

== Installation ==

1. No painel do WordPress, acesse **Plugins › Adicionar novo › Enviar plugin**.
2. Envie o arquivo `horas-trabalhadas.zip` e clique em **Instalar agora**.
3. Ative o plugin.
4. Insira o shortcode `[horas_trabalhadas]` na página desejada.
5. Opcional: em **Configurações › Horas Trabalhadas**, informe o repositório do GitHub para receber atualizações pela tela de Plugins.

== Frequently Asked Questions ==

= Qual shortcode devo usar? =

`[horas_trabalhadas]`. Os shortcodes das versões anteriores (`[work_hours_pro]` e `[work_hours_calculator]`) continuam funcionando nas páginas já publicadas, para que nada quebre na atualização.

= Posso colocar duas calculadoras na mesma página? =

Não. A calculadora usa identificadores fixos no HTML, então apenas a primeira ocorrência é exibida. Use um shortcode por página.

= Os dados dos funcionários ficam no servidor? =

Não. Tudo é calculado e armazenado no navegador de quem preenche.

= O plugin funciona sem licença ativa? =

Sim. A calculadora funciona integralmente sem licença. A licença controla apenas o recebimento de atualizações.

== Changelog ==

= 2.0.0 =
* Produto renomeado para **Horas Trabalhadas**; toda a interface administrativa passa a ser em português do Brasil.
* Os períodos passam a usar o fuso horário configurado no WordPress em vez do fuso do dispositivo do visitante.
* O dia de início da semana passa a seguir a configuração do WordPress.
* Entrada de horário em 24 horas passa a ser o padrão.
* Correção: as horas extras semanais (acima de 40h) reiniciavam apenas no início do período selecionado, e não a cada semana. Em períodos de duas semanas ou de um mês isso classificava horas normais como extras e inflava o pagamento bruto.
* Atualizações pela tela de Plugins do WordPress, a partir de Releases do GitHub, com suporte a atualizações automáticas.
* Nova tela **Configurações › Horas Trabalhadas** com licença, repositório e verificação de atualizações.
* Segurança: proteção contra fórmulas em CSV exportado, validação de dados vindos de links compartilhados, limitação de horas fora de faixa e validação estrita da origem dos pacotes de atualização.
* Remoção do `<h1>` interno, que competia com o título da própria página do WordPress.

= 1.2.0 =
* Versão anterior do produto.

== Upgrade Notice ==

= 2.0.0 =
Atualização recomendada. Corrige o cálculo de horas extras semanais em períodos maiores que uma semana e passa a usar o fuso horário do site. As folhas de ponto salvas no navegador são migradas automaticamente.
