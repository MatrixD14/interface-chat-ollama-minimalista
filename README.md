# Chat IA no navegador — configuração e execução

Este projeto utiliza um arquivo PHP (`chat.php`) para disponibilizar uma
interface de chat no navegador. A interface se comunica com uma API
compatível com o formato OpenAI; neste exemplo, o serviço utilizado é o
**Ollama local**.

O projeto também possui um iniciador chamado `playChat`, usado para ligar
e desligar o Ollama e o servidor PHP pelo terminal.

---

## 1. Dependências

Antes de usar, verifique se os seguintes componentes estão instalados:

- **PHP** — executa o arquivo `chat.php` e inicia o servidor web integrado.
- **PHP cURL (`php-curl`)** — permite que o PHP envie as solicitações para a API da IA.
- **Ollama** — executa os modelos de IA localmente.
- **Um modelo baixado no Ollama** — por exemplo, `llama3.2:1b`.

No Termux, instale o PHP e a extensão cURL com:

```bash
pkg update
pkg install php php-curl
```

O Ollama deve estar instalado separadamente e disponível no terminal como
o comando `ollama`. Depois, baixe pelo menos um modelo. Exemplo:

```bash
ollama pull llama3.2:1b
```

Para conferir os modelos disponíveis:

```bash
ollama list
```

> O nome do modelo baixado precisa corresponder ao nome configurado em
> `$model` e/ou listado em `$allowedModels`.

---

## 2. Configuração do `chat.php`

As opções principais ficam no início do arquivo, dentro da seção
`// CONFIG`. **Esse bloco é livre para editar**: você pode trocar valores,
adicionar novas variáveis e apontar para outro endpoint/modelo sem quebrar
o restante do script — desde que mantenha os nomes das variáveis já usadas
pelo código.

> **Importante:** o que *não* pode ser alterado pela interface web é a
> configuração em si. O usuário logado no chat **não** muda senha, modelo,
> endpoint nem system prompt pela tela. Esses valores vêm sempre do PHP.
> Para alterar qualquer coisa, edite o `chat.php` e recarregue a página.

```php
// CONFIG
define('ACCESS_PASSWORD', 'local');
$apiKey  = 'ollama';
$baseUrl = 'http://127.0.0.1:11434/v1';
$model   = 'llama3.2:1b';
$allowedModels = ['llama3.2:1b'];
$systemPrompt = 'Be specific.';
$maxTokens = 1096;
```

### Regras para não quebrar ao editar

1. **Mantenha os nomes** das variáveis existentes — o restante do arquivo as referencia.
2. `$model` **precisa estar** em `$allowedModels`, senão a UI fica inconsistente.
3. `$baseUrl` deve terminar em `/v1` (o script concatena `/chat/completions`).
4. Se **adicionar uma variável nova**, use-a em algum lugar do código (ex.: dentro do `json_encode` do bloco SSE). Caso contrário, ela apenas fica ociosa — não causa erro.

---

### `ACCESS_PASSWORD` — senha de acesso

```php
define('ACCESS_PASSWORD', 'local');
```

É a senha solicitada na tela de login do chat. Troque `local` por uma
senha de sua preferência.

Exemplo:

```php
define('ACCESS_PASSWORD', 'minha-senha-123');
```

Use uma senha difícil de adivinhar se outras pessoas puderem acessar o
servidor. No script atual, a senha é enviada pelo parâmetro `pwd` da URL
durante o login. Evite reutilizar uma senha importante ou expor o endereço
de login publicamente.

---

### `$apiKey` — chave de autenticação da API

```php
$apiKey = 'ollama';
```

É a credencial enviada à API no cabeçalho de autenticação.

- **Ollama local:** em uma instalação local padrão, normalmente não é
  necessária uma chave real. O valor `ollama` funciona como preenchimento
  para compatibilidade com o formato da API.
- **Provedor externo ou serviço pago:** use a chave fornecida pelo
  provedor, conforme a documentação dele.

A chave, sozinha, não transforma o Ollama em uma IA avançada ou paga. Para
usar outro serviço, normalmente também será necessário alterar `$baseUrl`
e escolher um modelo aceito por esse provedor. Não compartilhe chaves
privadas.

---

### `$baseUrl` — endereço da API

```php
$baseUrl = 'http://127.0.0.1:11434/v1';
```

Indica onde o PHP deve encontrar o serviço de IA.

- `127.0.0.1` indica a própria máquina em que o PHP está sendo executado.
- `11434` é a porta padrão do Ollama.
- `/v1` é o caminho da API compatível com o formato utilizado pelo script.

Se usar outro servidor ou provedor, substitua pelo endereço de API
indicado por ele. O endereço precisa ser acessível a partir do ambiente
em que o PHP roda.

---

### `$model` — modelo inicial

```php
$model = 'llama3.2:1b';
```

Define qual modelo será selecionado quando o chat for aberto.

Exemplo:

```php
$model = 'llama3.2:3b';
```

O nome deve corresponder exatamente ao identificador disponibilizado pelo
serviço. No Ollama, confira os modelos instalados com:

```bash
ollama list
```

---

### `$allowedModels` — modelos disponíveis no menu

```php
$allowedModels = ['llama3.2:1b'];
```

É a lista de modelos que poderão aparecer no menu de seleção do site.
Adicione os modelos que deseja disponibilizar.

Exemplo:

```php
$allowedModels = [
    'llama3.2:1b',
    'llama3.2:3b',
    'qwen2.5:3b',
    'gemma2:2b'
];
```

Cada nome precisa corresponder a um modelo disponível no serviço. No
Ollama, isso normalmente significa que o modelo já foi baixado. O script
**não baixa modelos automaticamente**: ele apenas permite selecioná-los
no menu e envia o nome escolhido à API.

O valor de `$model` deve estar incluído em `$allowedModels`, para que o
modelo inicial também apareça na lista.

---

### `$systemPrompt` — comportamento da IA

```php
$systemPrompt = 'Be specific.';
```

É a instrução enviada à IA antes das mensagens da conversa. Ela orienta
o estilo e a forma das respostas.

Exemplo em português:

```php
$systemPrompt = 'Responda em português do Brasil. Seja claro, específico e direto. Explique os passos quando necessário e evite informações desnecessárias.';
```

Você pode adaptar essa instrução para pedir respostas resumidas,
didáticas, técnicas ou detalhadas. O resultado também depende do modelo
utilizado: modelos menores podem não seguir instruções complexas com a
mesma consistência de modelos maiores.

Para desativar, use string vazia:

```php
$systemPrompt = '';
```

---

### `$maxTokens` — limite de tokens por resposta

```php
$maxTokens = 1096;
```

Define o limite máximo de tokens que a IA pode gerar em uma resposta. Um
limite maior permite respostas mais longas, mas pode aumentar o tempo de
geração e o uso de recursos.

**Importante:** esse valor **não** determina sozinho o consumo de RAM. A
memória utilizada depende principalmente do tamanho e da quantização do
modelo, do contexto da conversa, do servidor e da quantidade de
solicitações simultâneas.

| Perfil     | `maxTokens` | Uso indicado                                        |
|------------|------------:|-----------------------------------------------------|
| Econômico  |      `1024` | Respostas curtas e equipamentos mais limitados.     |
| Equilibrado|      `2048` | Uso geral e respostas moderadas.                    |
| Detalhado  |      `4096` | Explicações maiores e códigos mais extensos.        |
| Longo      |      `8192` | Respostas extensas, se o modelo e o equipamento suportarem. |

#### Referência aproximada de RAM

As faixas abaixo são apenas pontos de partida para modelos pequenos ou
médios, quantizados e executados localmente. **Não são garantias**: um
modelo grande pode exigir muita memória mesmo com um limite baixo de
tokens.

| RAM disponível | Limite inicial sugerido                                       |
|---------------:|---------------------------------------------------------------|
|         4 GB   | 1024 tokens; prefira modelos bem pequenos.                    |
|      6–8 GB    | 2048 tokens; prefira modelos pequenos ou quantizados.         |
|     8–12 GB    | 4096 tokens, se houver memória suficiente para o modelo e o contexto. |
| 16 GB ou mais  | 8192 tokens pode ser viável, dependendo do modelo e do contexto. |

O valor padrão `1096` é conservador e serve como ponto de partida para
testar um modelo pequeno, como `llama3.2:1b`. Se houver lentidão,
travamentos ou falta de memória, reduza o limite e/ou utilize um modelo
menor.

---

## 3. Iniciador `playChat`

O arquivo `playChat` é um iniciador de terminal. Ele possui dois comandos:

- `start` — inicia o Ollama e o servidor PHP. Se detectar que já estão em
  execução, não tenta iniciar outra cópia.
- `stop` — encerra os processos iniciados pelo script.

### Dar permissão de execução

Na primeira utilização, entre na pasta onde está o arquivo e execute:

```bash
chmod +x playChat
```

Isso permite executar o iniciador diretamente, sem precisar chamar `bash`
antes do nome do arquivo.

### Iniciar o chat

```bash
./playChat start
```

Depois, abra no navegador:

```text
http://localhost:8001/chat.php
```

### Parar os serviços

```bash
./playChat stop
```

### Alterar a porta do servidor PHP com `PORT`

A porta do servidor PHP é definida no iniciador `playChat`, **não** no
bloco de configuração do `chat.php`.

**Observação sobre a versão atual:** a porta `8001` está escrita
diretamente no comando PHP e no endereço exibido. Para poder alterar a
porta por uma variável, adicione no início do arquivo:

```bash
PORT=8001
```

Depois, no comando que inicia o PHP, use a variável:

```bash
-S "localhost:$PORT"
```

E altere a mensagem que mostra o endereço para:

```bash
echo "init Chat http://localhost:$PORT/chat.php"
```

Assim, para mudar a porta, basta alterar somente o valor de `PORT`. Por
exemplo:

```bash
PORT=8080
```

Nesse caso, o endereço do chat será:

```text
http://localhost:8080/chat.php
```

Depois de alterar o iniciador, salve o arquivo. Se o servidor já estiver
rodando, pare-o e inicie novamente para aplicar a nova porta:

```bash
./playChat stop
./playChat start
```

A porta escolhida precisa estar livre. Se mudar a porta, lembre-se de
acessar o novo endereço no navegador.

---

## 4. Como funciona o menu de modelos

O menu no cabeçalho é montado a partir de `$allowedModels`. Quando alguém
escolhe um item:

1. O navegador atualiza o modelo selecionado.
2. Ao enviar uma mensagem, o navegador envia o nome escolhido ao PHP.
3. O PHP verifica se o nome está dentro de `$allowedModels`.
4. Se estiver autorizado, o PHP usa esse modelo na solicitação à API.

Essa verificação impede que o navegador escolha livremente um modelo que
não esteja na lista configurada no PHP.

---

## 5. O que dá e o que não dá para fazer sem editar o arquivo

| Ação                                                        | Seguro? | Observação |
|-------------------------------------------------------------|:-------:|------------|
| Trocar `ACCESS_PASSWORD`                                    | ✅      | Recarregue e logue com a nova senha. |
| Trocar `$apiKey`                                            | ✅      | Só é usada no header `Authorization`. |
| Trocar `$baseUrl`                                           | ✅      | Contanto que termine em `/v1` e o backend fale OpenAI-compat. |
| Trocar `$model`                                             | ✅      | Coloque o mesmo valor em `$allowedModels`. |
| Adicionar modelo em `$allowedModels`                        | ✅      | Aparece no seletor; precisa existir no Ollama (`ollama pull ...`). |
| Mudar `$systemPrompt`                                       | ✅      | `''` desativa o system prompt. |
| Mudar `$maxTokens`                                          | ✅      | Só afeta o tamanho da resposta. |
| Adicionar **nova variável** (ex.: `$temperature = 0.7;`)     | ✅      | Fica ociosa até você usá-la no `json_encode(...)` do SSE. |
| Renomear variável existente sem atualizar os usos           | ❌      | Quebra com `Undefined variable`. |
| Remover variável usada no código                            | ❌      | Mesmo problema. |
| Alterar senha/modelo/endpoint **pela interface web**        | ❌      | Não existe essa opção na UI — só via edição do PHP. |

---

## 6. Observações importantes

- O PHP precisa ter a extensão **cURL** habilitada.
- O Ollama precisa estar instalado e acessível pelo comando `ollama`.
- O serviço de IA precisa estar acessível pelo endereço definido em `$baseUrl`.
- Os modelos precisam estar disponíveis no Ollama antes de serem selecionados.
- O chat utiliza respostas em fluxo (*streaming*), mostrando o texto conforme a IA o gera.
- O histórico das conversas é salvo no `localStorage` do navegador. Ele fica naquele navegador/dispositivo e pode ser perdido se os dados do site forem apagados.
- A senha de acesso não substitui HTTPS nem outras medidas de segurança se o chat for disponibilizado fora de uma rede local confiável.
- A configuração do `chat.php` **não é exposta na interface**: o usuário final não consegue alterá-la pela tela, só editando o arquivo e recarregando.

---

## 7. Resumo das configurações

| Variável             | Função                                                        |
|----------------------|---------------------------------------------------------------|
| `ACCESS_PASSWORD`    | Senha para entrar no chat.                                    |
| `$apiKey`            | Chave ou credencial enviada à API.                            |
| `$baseUrl`           | Endereço do servidor de IA.                                   |
| `$model`             | Modelo selecionado inicialmente.                              |
| `$allowedModels`     | Lista de modelos disponíveis no menu.                         |
| `$systemPrompt`      | Instrução que orienta as respostas da IA.                     |
| `$maxTokens`         | Limite máximo de tokens gerados por resposta.                 |
| `PORT`               | Porta do servidor PHP no iniciador `playChat` (após configurar a variável nele). |
