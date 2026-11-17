# Roteiro de Demonstração e Apresentação (15 Minutos)

**Projeto:** -Tributo! — Plataforma Web de Governança Fiscal e Portal do Cliente  
**Banca:** Avaliação Acadêmica de Programação Web II (CTT – UFPI)  
**Tempo Total:** 15 minutos cronometrados

---

## Estrutura do Tempo

| Bloco | Duração | Foco Principal |
|---|:---:|---|
| **1. Introdução e Problema** | 2 min | Dor dos escritórios, perda de prazos e desorganização do WhatsApp |
| **2. Demonstração: Visão do Contador** | 5 min | Painel gerencial, regularidade, gestão de guias e criação de solicitação |
| **3. Demonstração: Visão do Empresário** | 3 min | Acesso mobile/portal, visualização de guias e upload de documento |
| **4. Demonstração da Inovação & Conferência** | 2 min | Contador conferindo e aceitando o arquivo; avaliação na plataforma |
| **5. Arquitetura, Segurança e Qualidade** | 2 min | MVC puro, testes automatizados (731 testes) e isolamento multi-tenant |
| **6. Resultados do Teste com Usuário & Roadmap** | 1 min | Feedbacks reais, melhorias implementadas e encerramento |

---

## Roteiro Detalhado Passo a Passo

### 1. Introdução e Problema (00:00 – 02:00)
- **Fala:** *"Boa noite, professores e colegas. Nosso projeto é o **-Tributo!**, uma plataforma web desenvolvida para resolver uma das maiores dores do mercado contábil: a perda de prazos de guias fiscais e a perda de documentos que clientes enviam desordenadamente pelo WhatsApp."*
- **Contexto:**
  - Micro e pequenas empresas sofrem multas automáticas ao esquecer o DAS do Simples Nacional ou declarações acessórias.
  - O escritório gasta horas ligando ou cobrando clientes por extratos e notas fiscais.
- **Proposta de Valor:** Centralizar o escritório, calcular o Índice de Regularidade e oferecer um portal simples e direto para o cliente enviar documentos em segundos pelo celular.

---

### 2. Demonstração: Visão do Contador (02:00 – 07:00)
1. **Login como Contador:**
   - Acessar `http://localhost:8000/login` (ou pelo IP local no celular).
   - Entrar com `contador@teste.com` e senha `Senha@123`.
2. **Painel do Escritório (`/painel`):**
   - Apontar os cartões principais: Guias que vencem hoje, nos próximos 7 dias e já atrasadas.
   - Apontar o cartão de **Documentos e Solicitações**: *"Aqui o contador já vê quantos documentos os clientes enviaram e aguardam conferência."*
   - Mostrar a tabela dos **5 piores índices de regularidade**:
     - Explicar o cálculo de 0 a 100 e o semáforo (Verde, Amarelo, Vermelho).
     - Destacar a **Recomendação de Ação Imediata** (ex.: *Regularizar 1 obrigação com atraso crítico*).
3. **Módulo de Obrigações (`/obrigacoes`):**
   - Mostrar os filtros avançados por empresa, tipo, competência e status.
   - Apontar o badge vermelho de **Atraso Crítico (> 30 dias)**.
   - Clicar em uma obrigação, registrar o número de protocolo e marcar como `ENTREGUE`.
   - Mostrar que o sistema registrou automaticamente a data, hora e responsável no histórico.
4. **Criação da Solicitação de Documento:**
   - Ir no menu **Documentos** (`/solicitacoes`) e clicar em **+ Nova Solicitação**.
   - Selecionar a empresa: `Padaria Alvorada Ltda`.
   - Título: `Extrato Bancário de Outubro`.
   - Prazo: selecionar data para daqui a 3 dias.
   - Instrução: *"Favor anexar o extrato completo em PDF ou OFX."*
   - Clicar em **Criar Solicitação**.

---

### 3. Demonstração: Visão do Empresário no Portal (07:00 – 10:00)
1. **Acesso do Cliente (Abrir em aba anônima ou navegador mobile):**
   - Acessar `http://localhost:8000/login`.
   - Fazer login como `empresario@teste.com` com senha `Senha@123`.
2. **Interface Mobile-First do Portal (`/portal`):**
   - Destacar o layout limpo e sem jargões contábeis.
   - Banner superior em destaque: *"⚠️ Atenção: Você tem documentos pendentes de envio."*
   - Seção **Documentos Solicitados**:
     - Visualizar a solicitação recém-criada pelo contador (*Extrato Bancário de Outubro*).
   - Seção **Próximas Guias da Empresa**:
     - O empresário vê com clareza quando vence seu DAS ou FGTS.
3. **Envio do Arquivo:**
   - No campo da solicitação, clicar em escolher arquivo e selecionar um PDF ou imagem de exemplo.
   - Clicar no botão **📤 Enviar Documento**.
   - Mensagem de sucesso instantânea: *"Documento enviado com sucesso! Aguarde a conferência do escritório."*
   - O status muda automaticamente para *Em conferência pelo escritório*.

---

### 4. Demonstração da Inovação & Conferência (10:00 – 12:00)
1. **Contador Confere o Documento Recebido:**
   - Voltar à tela do contador (`/solicitacoes`).
   - Filtrar ou ver a solicitação atualizada com o arquivo anexado e status *Aguardando Conferência*.
   - Clicar no botão **📥 Baixar Arquivo** para conferir a integridade do documento.
   - Clicar no botão verde **✓ Aceitar** para validar o documento recebido.
2. **Recusa com Justificativa (caso estivesse errado):**
   - Explicar rapidamente que se o documento estivesse ilegível, o contador usaria o botão **✕ Recusar** com justificativa, e o empresário veria o motivo exato no portal dele.
3. **Registro de Avaliação da Plataforma:**
   - Clicar em **Avaliações** (`/avaliacao`).
   - Atribuir 5 estrelas, facilidade 5 e comentário de satisfação.
   - Mostrar a tela de `/avaliacoes` com a média consolidada (ex.: 4.8 ⭐) e comentários dos clientes.

---

### 5. Arquitetura, Segurança e Qualidade (12:00 – 14:00)
- **Stack Tecnológica:** PHP 8.2 puro, sem nenhum framework externo (Laravel, Symfony, etc.), banco MariaDB com PDO e HTML5/CSS3 responsivo artesanal.
- **Isolamento Multi-tenant Comprovado:**
  - Toda consulta filtra por `escritorio_id`.
  - Tentar acessar IDs de outro escritório na URL responde com **HTTP 404**, evitando ataques IDOR.
- **Upload Seguro:**
  - Whitelist restrita (PDF, XML, JPG, PNG).
  - Validação do MIME real no servidor com `finfo` (não confia na extensão).
  - Limite de 5 MB por arquivo.
  - Gravação **fora da pasta pública** em `storage/uploads/` com nome aleatório de 32 caracteres hexadecimais.
- **Testes Automatizados:**
  - Demonstrar no terminal a execução de `composer test`: **731 testes automatizados (unitários, integração e E2E) passando com 100% de sucesso**.

---

### 6. Resultados do Teste com Usuário & Próximos Passos (14:00 – 15:00)
- **Validação com Usuário Real:**
  - Teste conduzido com contador atuante com mais de 14 anos de profissão e 55 clientes na carteira.
  - Taxa de sucesso de 100% nas tarefas e nota média de 4.9 em usabilidade.
  - Gerou diretamente duas melhorias no produto: o **alerta de atraso crítico (> 30 dias)** e o **diagnóstico rápido com recomendação de ação no painel**.
- **Próximos Passos (Roadmap):**
  - Módulo de BPO Financeiro (contas a pagar/receber).
  - Integrações automáticas via API com prefeituras e SEFAZ.
- **Encerramento:** *"Muito obrigado. Estamos à disposição da banca para perguntas e considerações."*
