<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sistema de Leilão Eletrônico (SLE) da Receita Federal
    |--------------------------------------------------------------------------
    |
    | O portal (https://www25.receita.fazenda.gov.br/sle-sociedade) é uma SPA
    | Angular que busca os dados em uma API JSON pública sob "{base_url}/api".
    | O scraper consome essa mesma API em vez de interpretar o HTML.
    |
    */

    'base_url' => env('SLE_BASE_URL', 'https://www25.receita.fazenda.gov.br/sle-sociedade'),

    'timeout' => (int) env('SLE_TIMEOUT', 30),

    // Novas tentativas em caso de falha de conexão ou erro 5xx.
    'retries' => (int) env('SLE_RETRIES', 2),

    // Pausa entre requisições consecutivas, em milissegundos.
    'delay_ms' => (int) env('SLE_DELAY_MS', 300),

    'user_agent' => env('SLE_USER_AGENT', 'Mozilla/5.0 (compatible; receita-leilao/1.0)'),

];
