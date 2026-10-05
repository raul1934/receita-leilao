{{--
    Galeria de fotos em tela cheia. Abre ao clicar em um elemento com
    data-galeria (lista JSON de {url, miniatura}) ou em um filho dele com
    data-indice. Sem JavaScript, os links abrem a foto ou o lote normalmente.
--}}
<div class="lightbox" id="galeria" hidden role="dialog" aria-modal="true" aria-label="Galeria de fotos">
    <button type="button" class="fechar" aria-label="Fechar galeria">&times;</button>
    <button type="button" class="anterior" aria-label="Foto anterior">&lsaquo;</button>
    <img alt="">
    <button type="button" class="proxima" aria-label="Próxima foto">&rsaquo;</button>
    <div class="legenda">
        <span class="contador"></span>
        <a class="original" target="_blank" rel="noopener">Abrir original</a>
    </div>
</div>

<script>
    (() => {
        const caixa = document.getElementById('galeria');
        const img = caixa.querySelector('img');
        const contador = caixa.querySelector('.contador');
        const original = caixa.querySelector('.original');
        const anterior = caixa.querySelector('.anterior');
        const proxima = caixa.querySelector('.proxima');
        let fotos = [];
        let atual = 0;
        let titulo = '';
        let origem = null;
        let toqueX = null;

        function mostrar(indice) {
            atual = (indice + fotos.length) % fotos.length;
            const foto = fotos[atual];

            // A miniatura aparece na hora; a foto grande substitui quando carregar.
            img.src = foto.miniatura || foto.url;
            img.alt = `${titulo} - foto ${atual + 1} de ${fotos.length}`;
            const grande = new Image();
            grande.onload = () => { if (fotos[atual] === foto) img.src = foto.url; };
            grande.src = foto.url;

            contador.textContent = `${titulo} · ${atual + 1} / ${fotos.length}`;
            original.href = foto.url;

            [atual - 1, atual + 1].forEach((i) => {
                const vizinha = fotos[(i + fotos.length) % fotos.length];
                if (vizinha) new Image().src = vizinha.url;
            });
        }

        function abrir(lista, indice, nome, elemento) {
            fotos = lista;
            titulo = nome || 'Foto';
            origem = elemento;
            const varias = fotos.length > 1;
            anterior.hidden = !varias;
            proxima.hidden = !varias;
            caixa.hidden = false;
            document.body.style.overflow = 'hidden';
            mostrar(indice);
            caixa.querySelector('.fechar').focus();
        }

        function fechar() {
            caixa.hidden = true;
            img.removeAttribute('src');
            document.body.style.overflow = '';
            origem?.focus();
        }

        document.addEventListener('click', (evento) => {
            const gatilho = evento.target.closest('[data-indice], [data-galeria]');
            const grupo = gatilho?.closest('[data-galeria]');
            if (!grupo || evento.ctrlKey || evento.metaKey || evento.shiftKey) return;

            let lista;
            try { lista = JSON.parse(grupo.dataset.galeria); } catch { return; }
            if (!Array.isArray(lista) || lista.length === 0) return;

            evento.preventDefault();
            abrir(lista, Number(gatilho.dataset.indice || 0), grupo.dataset.titulo, gatilho);
        });

        caixa.querySelector('.fechar').addEventListener('click', fechar);
        anterior.addEventListener('click', () => mostrar(atual - 1));
        proxima.addEventListener('click', () => mostrar(atual + 1));
        caixa.addEventListener('click', (evento) => { if (evento.target === caixa) fechar(); });

        document.addEventListener('keydown', (evento) => {
            if (caixa.hidden) return;
            if (evento.key === 'Escape') fechar();
            if (evento.key === 'ArrowLeft' && fotos.length > 1) mostrar(atual - 1);
            if (evento.key === 'ArrowRight' && fotos.length > 1) mostrar(atual + 1);
        });

        caixa.addEventListener('touchstart', (evento) => { toqueX = evento.touches[0].clientX; }, { passive: true });
        caixa.addEventListener('touchend', (evento) => {
            if (toqueX === null || fotos.length < 2) return;
            const distancia = evento.changedTouches[0].clientX - toqueX;
            toqueX = null;
            if (Math.abs(distancia) > 50) mostrar(atual + (distancia < 0 ? 1 : -1));
        });
    })();
</script>
