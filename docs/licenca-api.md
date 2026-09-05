# Arquitetura de licenciamento

Contrato que o servidor de licenças precisa implementar, e por que ele existe
separado do GitHub.

## Por que o GitHub não é um servidor de licenças

Um token do GitHub colocado dentro do plugin **não protege nada**. Todo arquivo
do plugin fica legível para qualquer pessoa que consiga instalá-lo, então o token
vaza no primeiro download. O que ele faz é dar a essa pessoa acesso ao
repositório — o oposto do objetivo.

Por isso:

- O plugin **nunca** contém token, chave de assinatura ou segredo de qualquer tipo.
- O repositório de código é público e serve para versionamento e distribuição.
- O controle comercial, quando existir, fica em um servidor que você controla.

```
Site WordPress (este plugin)
    │  HTTPS: chave de licença + URL do site
    ▼
Servidor de licenças  (LICENSE_API_URL)   ← aqui ficam TODOS os segredos
    │  valida chave, limite de ativações, validade
    ▼
Distribuição privada / URL de download temporária (opcional)
```

## Configuração

Em **Configurações › Horas Trabalhadas**, campo **URL da API de licenças**.
Somente endereços **HTTPS** são aceitos.

Ou fixe por ambiente:

```php
define( 'HORAS_TRABALHADAS_LICENSE_API_URL', 'https://licencas.suaempresa.com.br/api/v1' );
```

**Deixe em branco** para distribuir o plugin sem controle de licença. Nesse modo
o plugin funciona integralmente e as atualizações não são bloqueadas.

## Contrato da API

Uma única rota, `POST`, corpo em `application/x-www-form-urlencoded`.

### Campos enviados

| Campo | Descrição |
|---|---|
| `action` | `activate`, `deactivate` ou `check` |
| `license_key` | Chave informada pelo cliente (`[A-Za-z0-9-_]`, até 128 caracteres) |
| `site_url` | `home_url()` do site |
| `plugin` | Sempre `horas-trabalhadas` |
| `version` | Versão instalada |

### Resposta esperada

HTTP **200** com um objeto JSON:

```json
{
  "active": true,
  "expires": "2027-03-31",
  "message": "Licença ativa."
}
```

| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| `active` | booleano | sim | Única coisa que decide se a licença vale |
| `expires` | string `YYYY-MM-DD` | não | Vazio = sem validade definida |
| `message` | string | não | Exibido ao administrador quando `active` é `false` |

Qualquer outro código HTTP, ou um corpo que não seja um objeto JSON, é tratado
como **servidor indisponível** — não como licença inválida. Essa distinção é
importante: ela é o que impede uma falha sua de desligar o site do cliente.

### Recusando uma licença

```json
{
  "active": false,
  "message": "Esta chave já está em uso em outro site."
}
```

## Política de falha

| Situação | Comportamento |
|---|---|
| Nenhuma API configurada | Plugin completo, atualizações liberadas |
| Licença ativa | Plugin completo, atualizações liberadas |
| Servidor fora do ar | Licença mantida por **14 dias** de tolerância |
| Tolerância esgotada | Atualizações deixam de ser oferecidas |
| Licença inválida | Atualizações deixam de ser oferecidas |

**A calculadora nunca para de funcionar por causa da licença.** Uma folha de
ponto que some porque um servidor de licenças caiu é um problema muito maior que
uma cópia não licenciada. A licença controla apenas a entrega de atualizações.

A licença é revalidada no máximo uma vez por dia, em `admin_init`.

## Armazenamento local

Opção `horas_trabalhadas_license` (com `autoload` desativado):

```php
array(
    'key'         => 'ABCD-1234-...',  // normalizada, máx. 128 caracteres
    'status'      => 'active',          // active|invalid|unreachable|inactive
    'expires'     => '2027-03-31',
    'site'        => 'https://exemplo.com.br',
    'last_check'  => 1772000000,
    'last_error'  => '',
    'grace_until' => 1773209600,
)
```

A chave aparece mascarada na tela de configurações (primeiros e últimos quatro
caracteres), para não vazar em capturas de tela enviadas ao suporte.

## Distribuição privada (opcional)

Se um dia o repositório precisar ser privado, o servidor de licenças passa a
devolver também uma URL de download temporária, e o atualizador usa essa URL no
lugar do anexo do GitHub. Nesse cenário, adicione o host dessa URL a
`Updater::ALLOWED_PACKAGE_HOSTS` — a lista existe justamente para que uma origem
nova seja uma decisão explícita, e não algo que uma resposta possa injetar.
