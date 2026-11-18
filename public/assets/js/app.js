"use strict";

// Melhorias progressivas da interface: o menu e todos os links funcionam sem este arquivo.
document.documentElement.classList.add("js");

/**
 * Marca (aria-current="page") o link cujo caminho é o da página atual ou o prefixo mais longo dele.
 * Ex.: /empresas/12/editar destaca "Empresas". Em empate, o caminho mais específico vence.
 */
function marcarPaginaAtual(links) {
    const atual = window.location.pathname.replace(/\/+$/, "") || "/";
    let melhor = null;
    let tamanhoMelhor = -1;

    links.forEach((link) => {
        const caminho = new URL(link.href, window.location.href).pathname.replace(/\/+$/, "") || "/";
        const casa = atual === caminho || (caminho !== "/" && atual.startsWith(caminho + "/"));
        if (casa && caminho.length > tamanhoMelhor) {
            melhor = link;
            tamanhoMelhor = caminho.length;
        }
    });

    if (melhor !== null) {
        melhor.setAttribute("aria-current", "page");
    }
}

marcarPaginaAtual(document.querySelectorAll(".navegacao-principal a[href]"));

// Abas da empresa: só uma pode ser a atual (mesmo caminho exato).
document.querySelectorAll(".abas-empresa").forEach((abas) => {
    const atual = window.location.pathname.replace(/\/+$/, "");
    abas.querySelectorAll("a[href]").forEach((aba) => {
        if (new URL(aba.href, window.location.href).pathname.replace(/\/+$/, "") === atual) {
            aba.setAttribute("aria-current", "page");
        }
    });
});

// Menu em telas largas: o botão "Recolher menu" reduz a barra a ícones e lembra a escolha.
// Em telas pequenas o menu é o offcanvas do Bootstrap (abre pelo botão Menu, fecha por X, Esc ou clique fora).
const estrutura = document.querySelector(".estrutura-aplicacao");
const botaoRecolher = document.querySelector("[data-barra-recolher]");
if (estrutura !== null && botaoRecolher !== null) {
    const chave = "tributo.barra-recolhida";
    const guardar = (valor) => {
        try {
            window.localStorage.setItem(chave, valor ? "1" : "0");
        } catch (erro) {
            // Navegação privada ou armazenamento bloqueado: a escolha só vale nesta página.
        }
    };
    const lembrada = () => {
        try {
            return window.localStorage.getItem(chave) === "1";
        } catch (erro) {
            return false;
        }
    };
    const definirBarra = (recolhida) => {
        estrutura.classList.toggle("barra-recolhida", recolhida);
        botaoRecolher.setAttribute("aria-expanded", recolhida ? "false" : "true");
        botaoRecolher.setAttribute("title", recolhida ? "Expandir menu" : "Recolher menu");
        botaoRecolher.querySelector(".barra-recolher-rotulo").textContent = recolhida ? "Expandir menu" : "Recolher menu";
    };

    definirBarra(lembrada());
    botaoRecolher.addEventListener("click", () => {
        const recolher = botaoRecolher.getAttribute("aria-expanded") === "true";
        definirBarra(recolher);
        guardar(recolher);
    });
}

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
                    nomeExibicao.style.display = "inline-block";
                }
                if (textoInstrucao) {
                    textoInstrucao.style.display = "none";
                }
            } else {
                if (nomeExibicao) {
                    nomeExibicao.textContent = "";
                    nomeExibicao.style.display = "none";
                }
                if (textoInstrucao) {
                    textoInstrucao.style.display = "block";
                }
            }
        });
    });
})();
