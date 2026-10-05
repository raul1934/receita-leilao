# Receita Leilão

Lê os editais e lotes do [Sistema de Leilão Eletrônico (SLE)](https://www25.receita.fazenda.gov.br/sle-sociedade/portal) da Receita Federal e salva tudo em um banco MySQL. Feito em Laravel 13 / PHP 8.5, rodando em Docker.

## Como os dados são extraídos

O portal do SLE é uma SPA em Angular: o HTML da página vem praticamente vazio e o navegador busca os dados em uma API JSON pública. Em vez de interpretar HTML ou usar um navegador headless, o sistema chama essa mesma API, o que dá menos trabalho e quebra menos quando o layout muda.

| Endpoint (`/sle-sociedade/api/...`)          | Conteúdo                                                   |
| -------------------------------------------- | ---------------------------------------------------------- |
| `edital/{unidade}/{numero}/{ano}`            | Dados do edital e lista resumida dos lotes                 |
| `lote/{unidade}/{numero}/{ano}/{lote}`       | Itens do lote (descrição, quantidade, recinto) e fotos     |
| `editais-disponiveis`                        | Editais listados no portal, agrupados por situação         |

Exemplo: a página `.../portal/edital/700100/12/2026` usa `.../api/edital/700100/12/2026`.

Para não sobrecarregar o site da Receita, o cliente faz uma pausa entre as requisições (`SLE_DELAY_MS`, 300 ms por padrão) e tenta de novo em caso de erro de conexão ou HTTP 5xx.

## Subindo o ambiente

Requisito: Docker com Docker Compose.

```bash
docker compose up -d --build
```

Na primeira vez, o container `app` instala as dependências, cria o `.env` a partir do `.env.example`, gera a `APP_KEY` e roda as migrations. Depois disso, a aplicação fica em http://localhost:8080.

| Serviço | Função                                            |
| ------- | ------------------------------------------------- |
| `app`   | PHP-FPM com o Laravel                             |
| `queue` | Worker da fila, que roda as importações da web    |
| `web`   | Nginx na porta 8080 (`APP_PORT`)                  |
| `db`    | MySQL 8.4, exposto na porta 3307 (`FORWARD_DB_PORT`) |

Em Linux, defina `HOST_UID` e `HOST_GID` no `.env` com o resultado de `id -u` e `id -g` antes do build, para que os arquivos criados pelo container fiquem com o seu usuário.

## Importando editais

### Pela interface web

Abra http://localhost:8080, cole a URL do edital (ou `700100/12/2026`) e clique em **Importar**. A importação vai para a fila e o container `queue` processa em segundo plano; atualize a página depois de alguns segundos. Editais grandes levam mais tempo, porque cada lote é uma requisição.

A página de cada edital lista os lotes, com filtros por tipo, busca na descrição dos itens e ordenação por valor. A página do lote mostra itens e fotos.

### Pelo terminal

```bash
# Um ou mais editais (URL do portal, 700100/12/2026 ou 0700100/000012/2026)
docker compose exec app php artisan leilao:importar https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026

# Só a lista de lotes, sem itens e fotos (uma requisição por edital)
docker compose exec app php artisan leilao:importar 800100/7/2026 --sem-detalhes

# Enviar para a fila em vez de importar na hora
docker compose exec app php artisan leilao:importar 800100/7/2026 --fila
```

Para importar tudo o que aparece em "Editais disponíveis" no portal:

```bash
# Todos
docker compose exec app php artisan leilao:sincronizar --fila

# Só editais disponibilizados (2) ou abertos para proposta (3)
docker compose exec app php artisan leilao:sincronizar --situacao=2 --situacao=3
```

Os códigos de situação estão em [app/Enums/SituacaoEdital.php](app/Enums/SituacaoEdital.php). Para algumas situações (encerrados, homologados), o próprio portal só lista os editais mais recentes.

Reimportar um edital é seguro: os registros são atualizados, sem duplicar.

## Banco de dados

| Tabela         | Conteúdo                                                                                  |
| -------------- | ----------------------------------------------------------------------------------------- |
| `editais`      | Unidade/número/ano, situação, datas de propostas e lances, contato, publicação            |
| `lotes`        | Número, tipo, situação, valor mínimo e valor de avaliação (em reais)                      |
| `lote_itens`   | Descrição, quantidade, unidade de medida, recinto armazenador                             |
| `lote_imagens` | URLs da foto e da miniatura (as imagens ficam no servidor da Receita, não são baixadas)   |

`editais.dados` e `lotes.dados` guardam o JSON original da API, para campos que ainda não têm coluna própria.

Para acessar o MySQL de fora do Docker: `localhost:3307`, usuário `leilao`, senha `secret`, banco `leilao`.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes usam SQLite em memória e respostas reais da API salvas em [tests/Fixtures/sle](tests/Fixtures/sle), sem acessar a internet.

## Configuração

| Variável         | Padrão                                                   | Descrição                                       |
| ---------------- | -------------------------------------------------------- | ----------------------------------------------- |
| `SLE_BASE_URL`   | `https://www25.receita.fazenda.gov.br/sle-sociedade`     | Endereço do portal                              |
| `SLE_DELAY_MS`   | `300`                                                    | Pausa mínima entre requisições                  |
| `SLE_RETRIES`    | `2`                                                      | Novas tentativas em falha de conexão ou HTTP 5xx |
| `SLE_TIMEOUT`    | `30`                                                     | Timeout de cada requisição, em segundos         |

Depois de alterar o código, reinicie o worker para ele carregar a versão nova: `docker compose restart queue`.
