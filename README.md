# Receita Leilão

Lê os editais e lotes do [Sistema de Leilão Eletrônico (SLE)](https://www25.receita.fazenda.gov.br/sle-sociedade/portal) da Receita Federal e salva tudo em um banco MySQL. Feito em Laravel 13 / PHP 8.5, rodando em Docker.

## Como os dados são extraídos

O portal do SLE é uma SPA em Angular: o HTML da página vem praticamente vazio e o navegador busca os dados em uma API JSON pública. Em vez de interpretar HTML ou usar um navegador headless, o sistema chama essa mesma API, o que dá menos trabalho e quebra menos quando o layout muda.

| Endpoint (`/sle-sociedade/api/...`)          | Conteúdo                                                   |
| -------------------------------------------- | ---------------------------------------------------------- |
| `edital/{unidade}/{numero}/{ano}`            | Dados do edital e lista resumida dos lotes                 |
| `lote/{unidade}/{numero}/{ano}/{lote}`       | Itens do lote (descrição, quantidade, recinto) e fotos     |
| `editais-disponiveis`                        | Editais listados no portal, agrupados por situação         |
| `edital/{unidade}/{numero}/{ano}/extrato-leilao` | PDF (em base64) com o valor de arrematação de cada lote, publicado quando a sessão termina |

Exemplo: a página `.../portal/edital/700100/12/2026` usa `.../api/edital/700100/12/2026`.

O valor de arremate não vem no JSON dos lotes: ele é lido do PDF "Extrato do Leilão", convertido em texto com o `pdftotext` (incluído na imagem Docker). Só o resultado e o valor de cada lote são gravados; o nome e o CPF/CNPJ do arrematante, que também constam no PDF, são ignorados.

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
| `scheduler` | Agendador do Laravel, que dispara a sincronização diária |
| `web`   | Nginx na porta 8080 (`APP_PORT`)                  |
| `db`    | MySQL 8.4, exposto na porta 3307 (`FORWARD_DB_PORT`) |

Em Linux, defina `HOST_UID` e `HOST_GID` no `.env` com o resultado de `id -u` e `id -g` antes do build, para que os arquivos criados pelo container fiquem com o seu usuário.

## Importando editais

### Pela interface web

Abra http://localhost:8080, cole a URL do edital (ou `700100/12/2026`) e clique em **Importar**. A importação vai para a fila e o container `queue` processa em segundo plano; atualize a página depois de alguns segundos. Editais grandes levam mais tempo, porque cada lote é uma requisição.

A lista de editais mostra primeiro os leilões com abertura dos lances ainda por vir (o mais próximo no topo) e depois os que já começaram; também dá para ordenar só pela data, crescente ou decrescente. O filtro de lances separa os editais **abertos para lances** (sessão ainda não encerrada, inclusive os que ainda vão abrir) dos **fechados** (sessão encerrada, homologados, cancelados), pela situação do edital.

A estrela ☆ ao lado de cada lote (na lista do edital e na página do lote) marca o lote como favorito sem recarregar a página. A tela **Favoritos** (menu do topo) reúne os lotes marcados de todos os editais, com os leilões mais próximos primeiro e o mesmo filtro de lances. Reimportar um edital não desmarca os favoritos.

A página **Mudanças recentes** (menu no topo) lista as mudanças de situação, valor mínimo e valor de avaliação detectadas nas importações, com o valor anterior, o novo e a variação, filtrando por período e tipo de mudança.

A página de cada edital lista os lotes com o depósito (recinto armazenador) de cada um, com filtros por tipo e por depósito, busca na descrição dos itens e ordenação por valor. A página do lote mostra itens, fotos e o histórico de situação e preço. Clicar em uma foto (na página do lote ou na miniatura da lista) abre a galeria em tela cheia: setas ou ← → do teclado para navegar, deslizar no celular, Esc para fechar.

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

Os editais são processados dos leilões mais próximos para os mais antigos. Editais finalizados (encerrado, homologado, cancelado, ata publicada) que já foram importados por completo e não mudaram de situação são ignorados; use `--todos` para reimportar tudo.

Os códigos de situação estão em [app/Enums/SituacaoEdital.php](app/Enums/SituacaoEdital.php). Para algumas situações (encerrados, homologados), o próprio portal só lista os editais mais recentes.

### Valores de arremate

Ao importar um edital cuja sessão já terminou, o sistema lê também o extrato do leilão e grava o resultado de cada lote (arrematado, não arrematado ou excluído) e o valor de arremate. Para completar editais importados antes disso, ou repetir a leitura:

```bash
# Todos os editais encerrados que ainda estão sem resultado
docker compose exec app php artisan leilao:resultados

# Editais específicos
docker compose exec app php artisan leilao:resultados 600100/3/2026
```

### Lances suspeitos

O extrato às vezes traz lances absurdos, como R$ 170 milhões num veículo de R$ 80 mil. Um arremate acima de 20 vezes o maior valor de referência do lote (mínimo ou avaliação) é marcado como suspeito: aparece com um aviso e não entra nos totais. O mínimo sozinho não serve de referência porque às vezes é simbólico (R$ 10 num lote avaliado em R$ 5.000). Para mudar o limite, ajuste `SLE_ARREMATE_SUSPEITO_MULTIPLO` no `.env` e reaplique a regra sem baixar nada:

```bash
docker compose exec app php artisan leilao:resultados --recalcular
```

### Sincronização automática

O container `scheduler` roda `leilao:sincronizar --fila` e `leilao:resultados` uma vez por dia a partir das 06:00 (horário de Brasília), e o `queue` processa as importações. Se o computador estiver desligado às 06:00, a sincronização do dia roda assim que ele ligar (o agendador confere a cada 5 minutos se ela já aconteceu). Rodar `leilao:sincronizar` completo manualmente também conta como a do dia. Para mudar o horário, ajuste `SLE_SINCRONIZACAO_HORARIO` no `.env` e reinicie o scheduler (`docker compose restart scheduler`). Para conferir o agendamento:

```bash
docker compose exec app php artisan schedule:list
```

Reimportar um edital é seguro: os registros são atualizados, sem duplicar.

Lotes baixados para reaproveitamento (situações 17 a 20, que o portal mostra como "Baixado") não têm itens nem fotos: a API recusa os detalhes deles (HTTP 422), então o sistema nem pede. Eles continuam na lista com tipo, valores e situação.

## Banco de dados

| Tabela         | Conteúdo                                                                                  |
| -------------- | ----------------------------------------------------------------------------------------- |
| `editais`      | Unidade/número/ano, situação, datas de propostas e lances, contato, publicação            |
| `lotes`        | Número, tipo, situação, valor mínimo, valor de avaliação, resultado e valor de arremate (em reais) |
| `lote_itens`   | Descrição, quantidade, unidade de medida, recinto armazenador                             |
| `lote_imagens` | URLs da foto e da miniatura (as imagens ficam no servidor da Receita, não são baixadas)   |
| `lote_historicos` | Situação, valor mínimo e valor de avaliação do lote a cada mudança, com a data (`registrado_em`) |

Um registro em `lote_historicos` é criado quando o lote aparece pela primeira vez e sempre que uma importação encontra situação ou valores diferentes; reimportar sem mudanças não gera registro. Por exemplo, para ver os lotes que mudaram de situação:

```sql
select lote_id, count(*) as registros from lote_historicos group by lote_id having count(*) > 1;
```

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
| `SLE_SINCRONIZACAO_HORARIO` | `06:00`                                       | Horário da sincronização diária                 |
| `SLE_ARREMATE_SUSPEITO_MULTIPLO` | `20`                                     | Arremate acima deste múltiplo do mínimo/avaliação é suspeito |

Depois de alterar o código, reinicie o worker e o agendador para eles carregarem a versão nova: `docker compose restart queue scheduler`. Se houver uma importação em andamento, espere ela terminar: reiniciar o worker interrompe o edital atual.
