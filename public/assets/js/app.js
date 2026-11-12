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
