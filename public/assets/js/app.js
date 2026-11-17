"use strict";

document.querySelectorAll("form[data-confirmar]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        const mensagem = form.getAttribute("data-confirmar");
        if (mensagem && !window.confirm(mensagem)) {
            event.preventDefault();
            return;
        }

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((botao) => {
            botao.disabled = true;
        });
        form.setAttribute("aria-busy", "true");
    });
});

document.querySelectorAll('input[data-mascara="cnpj"]').forEach((campo) => {
    campo.addEventListener("input", () => {
        const numeros = campo.value.replace(/\D/g, "").slice(0, 14);
        campo.value = numeros
            .replace(/^(\d{2})(\d)/, "$1.$2")
            .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
            .replace(/\.(\d{3})(\d)/, ".$1/$2")
            .replace(/(\d{4})(\d)/, "$1-$2");
    });
});

document.querySelectorAll('input[data-mascara="valor"]').forEach((campo) => {
    campo.addEventListener("input", () => {
        const numeros = campo.value.replace(/\D/g, "").slice(-15);
        if (numeros === "") {
            campo.value = "";
            return;
        }

        const centavos = numeros.padStart(3, "0").slice(-2);
        const inteiros = numeros.padStart(3, "0").slice(0, -2);
        const formatados = inteiros.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        campo.value = `R$ ${formatados},${centavos}`;
    });
});

document.querySelectorAll("[data-preencher-login]").forEach((botao) => {
    botao.addEventListener("click", () => {
        const email = botao.getAttribute("data-email");
        const senha = botao.getAttribute("data-senha");
        const campoEmail = document.getElementById("email");
        const campoSenha = document.getElementById("senha");
        if (campoEmail && email) {
            campoEmail.value = email;
        }
        if (campoSenha && senha) {
            campoSenha.value = senha;
        }
    });
});

/* ==========================================================================
   Controle de Sidebar: Minimizar / Maximizar (Desktop e Mobile Drawer)
   ========================================================================== */
(function () {
    const botaoToggle = document.getElementById("botao-toggle-menu");
    const botaoFechar = document.getElementById("botao-fechar-menu-mobile");
    const backdrop = document.getElementById("menu-backdrop");

    function ehMobile() {
        return window.innerWidth <= 768;
    }

    // Carrega preferência persistida de colapso no desktop
    if (!ehMobile() && localStorage.getItem("tributo_sidebar_colapsada") === "1") {
        document.body.classList.add("sidebar-colapsada");
        if (botaoToggle) botaoToggle.setAttribute("aria-expanded", "false");
    }

    function alternarMenu() {
        if (ehMobile()) {
            const abrindo = !document.body.classList.contains("sidebar-aberta-mobile");
            document.body.classList.toggle("sidebar-aberta-mobile", abrindo);
            if (botaoToggle) botaoToggle.setAttribute("aria-expanded", abrindo ? "true" : "false");
        } else {
            const colapsado = document.body.classList.toggle("sidebar-colapsada");
            localStorage.setItem("tributo_sidebar_colapsada", colapsado ? "1" : "0");
            if (botaoToggle) botaoToggle.setAttribute("aria-expanded", colapsado ? "false" : "true");
        }
    }

    function fecharMobile() {
        document.body.classList.remove("sidebar-aberta-mobile");
        if (botaoToggle) botaoToggle.setAttribute("aria-expanded", "false");
    }

    if (botaoToggle) {
        botaoToggle.addEventListener("click", alternarMenu);
    }

    if (botaoFechar) {
        botaoFechar.addEventListener("click", fecharMobile);
    }

    if (backdrop) {
        backdrop.addEventListener("click", fecharMobile);
    }

    // Fecha o menu mobile ao navegar por um item
    document.querySelectorAll(".navegacao-principal a").forEach((link) => {
        link.addEventListener("click", () => {
            if (ehMobile()) {
                fecharMobile();
            }
        });
    });

    // Tecla Esc fecha menu no mobile
    window.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && document.body.classList.contains("sidebar-aberta-mobile")) {
            fecharMobile();
        }
    });
})();

/* ==========================================================================
   Filtros Dinâmicos por Cards no Portal do Empresário
   ========================================================================== */
(function () {
    const cards = document.querySelectorAll("[data-filtro-portal]");
    const barraFiltro = document.getElementById("barra-filtro-portal");
    const textoFiltro = document.getElementById("texto-filtro-ativo");
    const botaoLimpar = document.getElementById("botao-limpar-filtro");
    const secaoDocumentos = document.getElementById("secao-documentos-portal");
    const secaoGuias = document.getElementById("secao-guias-portal");
    const docsItems = document.querySelectorAll(".card-documento-item");
    const avisoNenhum = document.getElementById("aviso-nenhum-doc-filtro");

    if (!cards.length) return;

    let filtroAtual = null;

    function aplicarFiltro(tipo, rotulo) {
        if (filtroAtual === tipo) {
            removerFiltro();
            return;
        }

        filtroAtual = tipo;
        cards.forEach((c) => {
            c.classList.toggle("kpi-card-ativo", c.getAttribute("data-filtro-portal") === tipo);
        });

        if (barraFiltro) {
            barraFiltro.style.display = "flex";
            if (textoFiltro) {
                textoFiltro.innerHTML = `Mostrando apenas: <strong>${rotulo}</strong>`;
            }
        }

        if (tipo === "guias") {
            if (secaoDocumentos) secaoDocumentos.style.display = "none";
            if (secaoGuias) secaoGuias.style.display = "block";
            if (secaoGuias) secaoGuias.scrollIntoView({ behavior: "smooth", block: "start" });
        } else {
            if (secaoGuias) secaoGuias.style.display = "none";
            if (secaoDocumentos) secaoDocumentos.style.display = "block";

            let visiveis = 0;
            docsItems.forEach((doc) => {
                const grupo = doc.getAttribute("data-grupo-portal");
                const mostrar = (grupo === tipo);
                doc.style.display = mostrar ? "block" : "none";
                if (mostrar) visiveis++;
            });

            if (avisoNenhum) {
                avisoNenhum.style.display = (visiveis === 0) ? "block" : "none";
            }
            if (secaoDocumentos) {
                secaoDocumentos.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        }
    }

    function removerFiltro() {
        filtroAtual = null;
        cards.forEach((c) => c.classList.remove("kpi-card-ativo"));
        if (barraFiltro) barraFiltro.style.display = "none";
        if (secaoDocumentos) secaoDocumentos.style.display = "block";
        if (secaoGuias) secaoGuias.style.display = "block";
        docsItems.forEach((doc) => {
            doc.style.display = "block";
        });
        if (avisoNenhum) avisoNenhum.style.display = "none";
    }

    cards.forEach((card) => {
        card.addEventListener("click", () => {
            const tipo = card.getAttribute("data-filtro-portal");
            const rotuloElem = card.querySelector(".kpi-rotulo");
            const rotulo = rotuloElem ? rotuloElem.textContent.trim() : tipo;
            aplicarFiltro(tipo, rotulo);
        });

        card.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                card.click();
            }
        });
    });

    if (botaoLimpar) {
        botaoLimpar.addEventListener("click", removerFiltro);
    }
})();

/* ==========================================================================
   Filtros Dinâmicos no Painel do Contador (Semáforos)
   ========================================================================== */
(function () {
    const cardsSemaforo = document.querySelectorAll("[data-filtro-semaforo]");
    const barraFiltro = document.getElementById("barra-filtro-semaforo");
    const textoFiltro = document.getElementById("texto-filtro-semaforo");
    const botaoLimpar = document.getElementById("botao-limpar-filtro-semaforo");
    const linhasTabela = document.querySelectorAll(".linha-empresa-indice");
    const avisoSemEmpresa = document.getElementById("aviso-sem-empresa-filtro");

    if (!cardsSemaforo.length) return;

    let corAtiva = null;

    function aplicarFiltroSemaforo(cor, rotulo) {
        if (corAtiva === cor) {
            removerFiltroSemaforo();
            return;
        }

        corAtiva = cor;
        cardsSemaforo.forEach((c) => {
            c.classList.toggle("card-semaforo-ativo", c.getAttribute("data-filtro-semaforo") === cor);
        });

        if (barraFiltro) {
            barraFiltro.style.display = "flex";
            if (textoFiltro) {
                textoFiltro.innerHTML = `Situação: <strong>${rotulo}</strong>`;
            }
        }

        let visiveis = 0;
        linhasTabela.forEach((linha) => {
            const corLinha = linha.getAttribute("data-semaforo-linha");
            const mostrar = (corLinha === cor);
            linha.style.display = mostrar ? "" : "none";
            if (mostrar) visiveis++;
        });

        if (avisoSemEmpresa) {
            avisoSemEmpresa.style.display = (visiveis === 0) ? "block" : "none";
        }
    }

    function removerFiltroSemaforo() {
        corAtiva = null;
        cardsSemaforo.forEach((c) => c.classList.remove("card-semaforo-ativo"));
        if (barraFiltro) barraFiltro.style.display = "none";
        linhasTabela.forEach((linha) => {
            linha.style.display = "";
        });
        if (avisoSemEmpresa) avisoSemEmpresa.style.display = "none";
    }

    cardsSemaforo.forEach((card) => {
        card.addEventListener("click", () => {
            const cor = card.getAttribute("data-filtro-semaforo");
            const cabecalho = card.querySelector(".semaforo-cabecalho");
            const rotulo = cabecalho ? cabecalho.textContent.trim() : cor;
            aplicarFiltroSemaforo(cor, rotulo);
        });

        card.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                card.click();
            }
        });
    });

    if (botaoLimpar) {
        botaoLimpar.addEventListener("click", removerFiltroSemaforo);
    }
})();

/* ==========================================================================
   Feedback Visual no Input de Arquivos
   ========================================================================== */
(function () {
    document.querySelectorAll(".input-arquivo-oculto").forEach((input) => {
        input.addEventListener("change", () => {
            const container = input.closest(".box-upload-moderno");
            if (!container) return;

            const label = container.querySelector(".label-upload-customizado");
            if (!label) return;

            const textoInstrucao = label.querySelector(".texto-upload-instrucao");
            const nomeExibicao = label.querySelector(".nome-arquivo-escolhido");

            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                const tamanhoKb = Math.round(file.size / 1024);
                if (nomeExibicao) {
                    nomeExibicao.textContent = `✓ ${file.name} (${tamanhoKb} KB selecionado)`;
                    nomeExibicao.style.display = "block";
                }
                if (textoInstrucao) {
                    textoInstrucao.style.display = "none";
                }
            } else {
                if (nomeExibicao) {
                    nomeExibicao.style.display = "none";
                }
                if (textoInstrucao) {
                    textoInstrucao.style.display = "block";
                }
            }
        });
    });
})();
