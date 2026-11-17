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
