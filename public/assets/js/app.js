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
