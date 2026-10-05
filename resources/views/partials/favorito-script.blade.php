<script>
    (() => {
        document.addEventListener('submit', async (evento) => {
            const form = evento.target.closest('form[data-favorito]');
            if (!form) return;

            evento.preventDefault();
            const botao = form.querySelector('button');
            botao.disabled = true;

            try {
                const resposta = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resposta.ok) throw new Error(`HTTP ${resposta.status}`);
                const { favorito, total } = await resposta.json();

                botao.textContent = favorito ? '★' : '☆';
                botao.classList.toggle('ativa', favorito);
                botao.setAttribute('aria-pressed', String(favorito));
                botao.title = favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
                document.querySelectorAll('[data-total-favoritos]').forEach((el) => { el.textContent = total; });
            } catch {
                // Sessão expirada ou erro: envia o formulário do jeito normal.
                form.submit();
            } finally {
                botao.disabled = false;
            }
        });
    })();
</script>
