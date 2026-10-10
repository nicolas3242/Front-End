-- =====================================================================
-- DevFox — Schema do banco de dados (v13)
-- =====================================================================
-- Plataforma educacional gamificada de lógica de programação e JavaScript.
--
-- Use este arquivo para criar o banco DO ZERO.
-- Se já tem o banco com dados que não quer perder, rode as migrações em ordem:
-- migracao_v1_para_v2.sql, migracao_v2_para_v3.sql, migracao_v3_para_v4.sql,
-- migracao_v4_para_v5.sql, migracao_v5_para_v6.sql, migracao_v6_para_v7.sql,
-- migracao_v7_para_v8.sql, migracao_v8_para_v9.sql, migracao_v9_para_v10.sql,
-- migracao_v10_para_v11.sql, migracao_v11_para_v12.sql,
-- migracao_v12_para_v13.sql.
--
-- Compatível com MySQL 5.7+ e MariaDB 10.4+ (o que vem no XAMPP).
-- Documentação das decisões: database/README.md
-- =====================================================================

-- Declara que o conteúdo DESTE arquivo está em UTF-8.
-- Sem esta linha, um cliente configurado em latin1 (o padrão de várias
-- instalações de linha de comando) interpreta cada byte acentuado como um
-- caractere separado e grava tudo duplamente codificado: "Lógica" vira
-- "LÃ³gica" no site. O erro só aparece na tela, nunca na importação.
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS devfox
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE devfox;

-- Ordem de DROP respeita as dependências de chave estrangeira (filhos primeiro).
DROP TABLE IF EXISTS admin_registro;
DROP TABLE IF EXISTS tentativas_login;
DROP TABLE IF EXISTS mensagens_suporte;
DROP TABLE IF EXISTS redefinicoes_senha;
DROP TABLE IF EXISTS lembrar_tokens;
DROP TABLE IF EXISTS trilha_requisitos;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS dicas_vistas;
DROP TABLE IF EXISTS tentativas_prova;
DROP TABLE IF EXISTS conquistas_usuarios;
DROP TABLE IF EXISTS conquistas;
DROP TABLE IF EXISTS modulos_concluidos;
DROP TABLE IF EXISTS atividades_concluidas;
DROP TABLE IF EXISTS submissoes;
DROP TABLE IF EXISTS submissoes_alunos;   -- nome antigo, da v1
DROP TABLE IF EXISTS alternativas;
DROP TABLE IF EXISTS casos_teste;
DROP TABLE IF EXISTS atividades;
DROP TABLE IF EXISTS modulos;
DROP TABLE IF EXISTS trilhas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS itens_loja;         -- depois de usuarios, que aponta para ela


-- ---------------------------------------------------------------------
-- 1. usuarios
-- ---------------------------------------------------------------------
-- `nivel` NÃO existe como coluna: o nível é sempre derivado do XP em PHP
-- (uma única fonte da verdade). Guardar os dois leva a divergência.
--
-- `sequencia_dias` só funciona junto com `ultimo_dia_ativo`: para saber se
-- a ofensiva continua ou zera, é preciso comparar com a data do último acesso.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(100)  NOT NULL,
    email            VARCHAR(150)  NOT NULL,
    senha            VARCHAR(255)  NOT NULL,           -- hash de password_hash(), nunca texto puro
    tipo             ENUM('aluno','admin') NOT NULL DEFAULT 'aluno',
    ativo            TINYINT(1)    NOT NULL DEFAULT 1,  -- v10: 0 = conta desativada pelo admin (não entra, nada é apagado)
    xp               INT           NOT NULL DEFAULT 0,
    sequencia_dias   INT           NOT NULL DEFAULT 0,
    melhor_sequencia INT           NOT NULL DEFAULT 0,  -- v8: recorde de dias seguidos ("Melhor: 12 dias")
    ultimo_dia_ativo DATE          NULL,               -- NULL = nunca acessou depois do cadastro
    foto_avatar      VARCHAR(255)  NOT NULL DEFAULT 'padrao',  -- código do catálogo (includes/avatares.php)
    moldura_id       INT           NULL,               -- item da loja equipado (v5); NULL = nenhum
    titulo_id        INT           NULL,               -- idem; as FKs ficam no fim do schema
    criado_em        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_xp (xp)                            -- usado pelo ranking (ORDER BY xp DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. trilhas
-- ---------------------------------------------------------------------
-- Valores de ENUM em minúsculo e sem acento: o valor guardado é um código,
-- o rótulo bonito ("Intermediário") é responsabilidade do PHP na hora de exibir.
-- ---------------------------------------------------------------------
CREATE TABLE trilhas (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(100) NOT NULL,
    descricao         TEXT         NULL,
    nivel_dificuldade ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
    icone             VARCHAR(50)  NULL,        -- identificador do ícone no card (ex: 'js', 'logica')
    ordem             INT          NOT NULL DEFAULT 1,
    publicada         TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = rascunho do professor, invisível ao aluno
    linguagem         ENUM('javascript','html','python') NOT NULL DEFAULT 'javascript',  -- v6: qual executor corrige
    subtitulo         VARCHAR(80)  NULL,        -- v6: chamada curta do cartão de destaque ("Estrutura e Conteúdo")
    destaque          TINYINT(1)   NOT NULL DEFAULT 0,  -- v6: aparece no painel lateral da tela de Trilhas
    arquivada         TINYINT(1)   NOT NULL DEFAULT 0,  -- v10: sai da lista do admin e do aluno, progresso fica
    criado_em         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_trilhas_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. modulos  (as "aulas" dentro de uma trilha)
-- ---------------------------------------------------------------------
-- Um módulo é a explicação (teoria e/ou vídeo) seguida das atividades que
-- aplicam o conteúdo. Um módulo do tipo 'prova' não traz teoria: ele agrupa
-- as questões que avaliam os módulos anteriores e libera o avanço na trilha.
--
-- `ordem` é obrigatório: uma trilha é uma sequência progressiva, então sem
-- ordem não há como dizer qual aula vem antes nem calcular "Módulo 4 de 7".
-- Sempre consultar com `ORDER BY ordem, id` — o `id` desempata caso duas
-- aulas fiquem com a mesma ordem.
-- ---------------------------------------------------------------------
CREATE TABLE modulos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    trilha_id    INT          NOT NULL,
    titulo       VARCHAR(100) NOT NULL,
    tipo         ENUM('aula','prova') NOT NULL DEFAULT 'aula',
    nota_minima  TINYINT UNSIGNED NOT NULL DEFAULT 70,   -- % de acerto, só usado em 'prova'
    descricao    TEXT         NULL,
    url_video    VARCHAR(255) NULL,
    teoria_texto TEXT         NULL,
    ordem        INT          NOT NULL DEFAULT 1,
    criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_modulos_trilha FOREIGN KEY (trilha_id)
        REFERENCES trilhas(id) ON DELETE CASCADE,
    KEY idx_modulos_trilha (trilha_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. atividades  (exercícios e desafios de um módulo)
-- ---------------------------------------------------------------------
-- A resposta esperada NÃO fica aqui. Ela mora em `casos_teste` (exercícios de
-- código) ou em `alternativas` (quiz), porque um exercício sério precisa de
-- mais de um caso de teste — e comparar uma string só é o que permitia burlar
-- a correção na versão anterior.
-- ---------------------------------------------------------------------
CREATE TABLE atividades (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id       INT          NOT NULL,
    modulo_referencia_id INT     NULL,      -- só em questões de prova: que módulo ela avalia
    titulo          VARCHAR(100) NOT NULL,
    enunciado       TEXT         NOT NULL,
    tipo            ENUM('quiz','completar','corrigir_erro','codigo_livre') NOT NULL,
    dificuldade     ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil',
    codigo_inicial  TEXT         NULL,     -- código que já aparece no editor
    dica            TEXT         NULL,     -- botão "Usar dica"
    solucao_exemplo TEXT         NULL,     -- botão "Mostrar exemplo"
    xp_recompensa   INT          NOT NULL DEFAULT 150,
    ordem           INT          NOT NULL DEFAULT 1,
    criado_em       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_atividades_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos(id) ON DELETE CASCADE,
    CONSTRAINT fk_atividades_modulo_referencia FOREIGN KEY (modulo_referencia_id)
        REFERENCES modulos(id) ON DELETE SET NULL,
    KEY idx_atividades_modulo (modulo_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. casos_teste  (para atividades de código)
-- ---------------------------------------------------------------------
-- `visivel = 1` → o caso aparece na tela como "Saída esperada", para o aluno
--                 entender o que se pede.
-- `visivel = 0` → caso oculto. Existe justamente para que o aluno não consiga
--                 passar chumbando a resposta visível no código.
-- ---------------------------------------------------------------------
CREATE TABLE casos_teste (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    atividade_id   INT          NOT NULL,
    descricao      VARCHAR(150) NULL,
    entrada        TEXT         NULL,      -- NULL quando o exercício não recebe entrada
    saida_esperada TEXT         NOT NULL,
    visivel        TINYINT(1)   NOT NULL DEFAULT 1,
    ordem          INT          NOT NULL DEFAULT 1,

    CONSTRAINT fk_casos_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    KEY idx_casos_atividade (atividade_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. alternativas  (para atividades do tipo quiz)
-- ---------------------------------------------------------------------
CREATE TABLE alternativas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    atividade_id INT          NOT NULL,
    texto        VARCHAR(255) NOT NULL,
    correta      TINYINT(1)   NOT NULL DEFAULT 0,
    ordem        INT          NOT NULL DEFAULT 1,

    CONSTRAINT fk_alternativas_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    KEY idx_alternativas_atividade (atividade_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 7. submissoes  (histórico de TODAS as tentativas)
-- ---------------------------------------------------------------------
-- Guarda tudo que o aluno tentou, inclusive erros. Serve para o professor
-- acompanhar dificuldade e para a análise de desempenho do TCC.
-- Esta tabela NÃO concede XP — quem faz isso é `atividades_concluidas`.
-- ---------------------------------------------------------------------
CREATE TABLE submissoes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT        NOT NULL,
    atividade_id INT        NOT NULL,
    resposta     TEXT       NULL,
    correta      TINYINT(1) NOT NULL DEFAULT 0,
    enviado_em   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_submissoes_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissoes_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    KEY idx_submissoes_aluno (aluno_id, atividade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 8. atividades_concluidas  (o que o aluno JÁ concluiu, e o XP que ganhou)
-- ---------------------------------------------------------------------
-- ESTA É A TABELA QUE IMPEDE O FARM DE XP.
-- A chave única (aluno_id, atividade_id) garante que a mesma atividade só
-- pode ser concluída uma vez por aluno. No PHP, use:
--     INSERT IGNORE INTO atividades_concluidas (...) VALUES (...)
-- e só some XP no usuário se a linha realmente tiver sido inserida
-- (`$stmt->rowCount() === 1`). Sem isso, o aluno recarrega a página e ganha
-- XP de novo, infinitamente — que era o comportamento da versão anterior.
--
-- `xp_concedido` é copiado da atividade no momento da conclusão: se a
-- recompensa mudar depois, o histórico continua contando a verdade.
-- ---------------------------------------------------------------------
CREATE TABLE atividades_concluidas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id      INT       NOT NULL,
    atividade_id  INT       NOT NULL,
    xp_concedido  INT       NOT NULL DEFAULT 0,
    tentativas    INT       NOT NULL DEFAULT 1,
    concluido_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_concluidas_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_concluidas_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    UNIQUE KEY uq_concluidas_aluno_atividade (aluno_id, atividade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 9. modulos_concluidos  (aula marcada como concluída)
-- ---------------------------------------------------------------------
-- Necessário porque um módulo pode ser só teoria/vídeo, sem nenhuma atividade.
-- Sem esta tabela, esse módulo nunca contaria no progresso da trilha.
-- ---------------------------------------------------------------------
CREATE TABLE modulos_concluidos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT       NOT NULL,
    modulo_id    INT       NOT NULL,
    concluido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mod_concluidos_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_mod_concluidos_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_mod_concluidos (aluno_id, modulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 10. conquistas  (catálogo)
-- ---------------------------------------------------------------------
-- `codigo` é o identificador usado no PHP ('primeira_aula'), estável mesmo se
-- o título mudar. `raridade` define a cor do badge na tela de perfil.
--
-- v10 — o admin cria conquistas pela tela. A regra NÃO é código: é um TIPO de
-- um catálogo fechado (includes/conquistas.php → tiposDeRegra()) mais um valor
-- e, quando precisa, um alvo (trilha ou prova). 'fixa' = uma das 6 originais,
-- com a regra escrita no PHP pelo `codigo`.
-- ---------------------------------------------------------------------
CREATE TABLE conquistas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(50)  NOT NULL,
    titulo      VARCHAR(100) NOT NULL,
    descricao   VARCHAR(255) NULL,
    icone       VARCHAR(50)  NULL,
    raridade    ENUM('bronze','prata','ouro','especial') NOT NULL DEFAULT 'bronze',
    ativa       TINYINT(1)   NOT NULL DEFAULT 1,       -- v10: 0 = ninguém mais ganha; quem ganhou continua com ela
    regra_tipo  VARCHAR(20)  NOT NULL DEFAULT 'fixa',  -- v10: ver tiposDeRegra()
    regra_valor INT          NULL,                     -- v10: o N da regra (ex.: 10 atividades, 1000 XP)
    regra_alvo  INT          NULL,                     -- v10: id da trilha ou do módulo de prova, quando a regra pede

    UNIQUE KEY uq_conquistas_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 11. conquistas_usuarios  (quem desbloqueou o quê)
-- ---------------------------------------------------------------------
CREATE TABLE conquistas_usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT       NOT NULL,
    conquista_id    INT       NOT NULL,
    desbloqueada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_cu_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_conquista FOREIGN KEY (conquista_id)
        REFERENCES conquistas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_conquista_usuario (usuario_id, conquista_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 12. tentativas_prova  (histórico das avaliações de fim de nível)
-- ---------------------------------------------------------------------
-- Diferente de `atividades_concluidas`, aqui PODE haver várias linhas por
-- (aluno, módulo): é justamente o histórico de tentativas que mostra a
-- evolução do aluno, e é dele que sai a indicação do que revisar quando a
-- prova é reprovada.
-- ---------------------------------------------------------------------
CREATE TABLE tentativas_prova (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT              NOT NULL,
    modulo_id    INT              NOT NULL,
    acertos      INT              NOT NULL,
    total        INT              NOT NULL,
    percentual   TINYINT UNSIGNED NOT NULL,
    aprovado     TINYINT(1)       NOT NULL DEFAULT 0,
    realizada_em TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tentativas_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tentativas_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos(id) ON DELETE CASCADE,
    KEY idx_tentativas_aluno (aluno_id, modulo_id, realizada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 13. dicas_vistas  (quem pediu ajuda em qual atividade)
-- ---------------------------------------------------------------------
-- Existe por causa da conquista "Por Conta Própria" (acertar 5 desafios sem
-- usar dica): sem registrar quem pediu ajuda, não há como saber quem não pediu.
--
-- O registro é feito pelo SERVIDOR, em api/dica.php, no momento em que o texto
-- da dica é entregue — e não por um campo enviado junto com a resposta. A
-- diferença importa: um campo enviado pelo navegador seria editável pelo aluno
-- no DevTools, e a conquista viraria enfeite.
--
-- A chave única impede que abrir a mesma dica duas vezes vire duas linhas.
-- ---------------------------------------------------------------------
CREATE TABLE dicas_vistas (
    id           INT       AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT       NOT NULL,
    atividade_id INT       NOT NULL,
    vista_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_dicas_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_dicas_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    UNIQUE KEY uq_dicas_aluno_atividade (aluno_id, atividade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 14. itens_loja  (v5)
-- ---------------------------------------------------------------------
-- Catálogo da Loja. Só itens COSMÉTICOS (moldura do avatar, título exibido
-- sob o nome): nada que a loja venda pode dar vantagem no aprendizado, senão
-- o ranking passaria a medir quem gastou moeda, e não quem aprendeu.
--
-- `slug` é o código estável do item. Para molduras ele vira a classe CSS
-- `moldura-<slug>`; por isso só aceita [a-z0-9-] (conferido também no PHP).
--
-- Item que sai de venda é DESATIVADO (ativo = 0), nunca apagado: quem já
-- comprou continua dono. A FK de `compras` é RESTRICT justamente para
-- impedir a exclusão acidental.
-- ---------------------------------------------------------------------
CREATE TABLE itens_loja (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(40)   NOT NULL,
    nome       VARCHAR(60)   NOT NULL,
    descricao  VARCHAR(160)  NOT NULL,
    categoria  ENUM('moldura','titulo') NOT NULL,
    preco      INT UNSIGNED  NOT NULL,
    ordem      INT           NOT NULL DEFAULT 0,
    ativo      TINYINT(1)    NOT NULL DEFAULT 1,

    UNIQUE KEY uq_itens_loja_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 15. compras  (v5)
-- ---------------------------------------------------------------------
-- O SALDO DE MOEDAS NÃO É GUARDADO. Ele é derivado:
--
--     saldo = xp DIV 10  -  SUM(compras.preco_pago)
--
-- Mesmo princípio do nível: uma coluna `moedas` seria uma segunda fonte da
-- verdade, e bastaria um UPDATE esquecido para ela divergir do XP. Assim, a
-- única forma de ganhar moeda é ganhar XP — que já é protegido contra farm
-- em atividades_concluidas.
--
-- `preco_pago` guarda o preço NO MOMENTO da compra: se o admin mudar o preço
-- depois, o saldo de quem já comprou não muda.
--
-- A chave única impede comprar o mesmo item duas vezes (INSERT IGNORE +
-- rowCount, o mesmo padrão do XP e das conquistas).
-- ---------------------------------------------------------------------
CREATE TABLE compras (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT           NOT NULL,
    item_id      INT           NOT NULL,
    preco_pago   INT UNSIGNED  NOT NULL,
    comprado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_compras_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_compras_item FOREIGN KEY (item_id)
        REFERENCES itens_loja(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_compras_usuario_item (usuario_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Itens equipados. As FKs vêm aqui, e não no CREATE de usuarios, porque
-- itens_loja precisa existir antes. ON DELETE SET NULL: se um item sumir,
-- o aluno fica sem moldura em vez de o banco recusar a operação.
ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_moldura FOREIGN KEY (moldura_id)
        REFERENCES itens_loja(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_usuarios_titulo FOREIGN KEY (titulo_id)
        REFERENCES itens_loja(id) ON DELETE SET NULL;


-- ---------------------------------------------------------------------
-- 16. trilha_requisitos  (v6)
-- ---------------------------------------------------------------------
-- Uma trilha pode exigir que outras estejam CONCLUÍDAS antes de abrir (ex.: o
-- Projeto Final exige todas as outras). Tabela própria, e não uma coluna
-- "requisito_id" em trilhas, porque uma trilha pode ter vários requisitos.
--
-- Quem aplica a regra é estadoDosModulos() (includes/auth.php): trilha com
-- requisito pendente tem TODOS os módulos bloqueados — inclusive pela URL.
-- ---------------------------------------------------------------------
CREATE TABLE trilha_requisitos (
    trilha_id    INT NOT NULL,
    requisito_id INT NOT NULL,

    PRIMARY KEY (trilha_id, requisito_id),
    CONSTRAINT fk_req_trilha FOREIGN KEY (trilha_id)
        REFERENCES trilhas(id) ON DELETE CASCADE,
    CONSTRAINT fk_req_requisito FOREIGN KEY (requisito_id)
        REFERENCES trilhas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 17. lembrar_tokens  (v7) — "Lembrar-me" no login
-- ---------------------------------------------------------------------
-- Padrão seletor + validador: o cookie leva "seletor:validador"; o banco
-- guarda o seletor (para achar a linha) e só o HASH do validador. Quem ler o
-- banco não consegue montar um cookie válido, e a comparação do validador é
-- feita com hash_equals (tempo constante). Cada uso gera um token novo e apaga
-- o antigo; trocar a senha apaga todos os tokens do usuário.
-- ---------------------------------------------------------------------
CREATE TABLE lembrar_tokens (
    id              INT        AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT        NOT NULL,
    seletor         CHAR(24)   NOT NULL,
    validador_hash  CHAR(64)   NOT NULL,       -- sha256 do validador
    expira_em       DATETIME   NOT NULL,
    criado_em       TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lembrar_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_lembrar_seletor (seletor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 18. redefinicoes_senha  (v7) — "Esqueci minha senha"
-- ---------------------------------------------------------------------
-- Guarda só o HASH do token enviado por e-mail, com validade de 1 hora e uso
-- único. Pedir de novo invalida os pedidos anteriores do mesmo usuário.
-- ---------------------------------------------------------------------
CREATE TABLE redefinicoes_senha (
    id          INT        AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT        NOT NULL,
    token_hash  CHAR(64)   NOT NULL,
    expira_em   DATETIME   NOT NULL,
    usado_em    DATETIME   NULL,
    criado_em   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_redef_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_redef_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 19. mensagens_suporte  (v7) — "Precisa de ajuda? Fale com o suporte"
-- ---------------------------------------------------------------------
-- Formulário público (funciona sem login — quem esqueceu a senha também
-- precisa de ajuda). A equipe lê em Administração → Suporte.
-- ---------------------------------------------------------------------
CREATE TABLE mensagens_suporte (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT           NULL,            -- preenchido se estava logado
    nome        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL,
    assunto     ENUM('conteudo','tecnico','conta','outro') NOT NULL DEFAULT 'outro',
    mensagem    TEXT          NOT NULL,
    lida        TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_suporte_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_suporte_lida (lida, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 20. tentativas_login  (v9) — limite de tentativas de login
-- ---------------------------------------------------------------------
-- Uma linha por senha ERRADA. Com 5 erros no mesmo e-mail em 15 minutos, o
-- login desse e-mail fica bloqueado até a janela passar (força bruta deixa de
-- ser viável). Também há um teto por IP, mas alto: numa escola, uma sala
-- inteira sai pelo MESMO IP, e um teto baixo trancaria a turma toda.
-- Sem chave estrangeira de propósito: o e-mail digitado pode nem existir.
-- Linhas com mais de 1 dia são apagadas no próprio login (includes/conta.php).
-- ---------------------------------------------------------------------
CREATE TABLE tentativas_login (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150)  NOT NULL,
    ip         VARCHAR(45)   NOT NULL,          -- 45 cabe um IPv6
    criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_tentativas_email (email, criado_em),
    KEY idx_tentativas_ip    (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 21. admin_registro  (v10) — o que cada admin fez, e quando
-- ---------------------------------------------------------------------
-- Uma linha por ação que altera dados na Administração (publicar, apagar,
-- promover, desativar conta...). Responde "quem mudou isso?" — num sistema
-- com mais de um admin, sem isto não há como saber.
-- admin_id vira NULL se a conta do admin for excluída: o registro fica.
-- ---------------------------------------------------------------------
CREATE TABLE admin_registro (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT           NULL,
    acao       VARCHAR(60)   NOT NULL,           -- ex.: 'trilha.publicar', 'aluno.desativar'
    alvo       VARCHAR(160)  NULL,               -- texto legível: "Trilha: HTML"
    criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_registro_admin FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_registro_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- DADOS INICIAIS
-- =====================================================================
-- Gerado por tools/gerar_banco.py a partir de tools/conteudo.py.
-- Para mudar o conteúdo, edite conteudo.py e rode o gerador de novo —
-- editar este trecho na mão faz o próximo gerador apagar sua alteração.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Contas de teste
-- ---------------------------------------------------------------------
-- ⚠ SOMENTE PARA DESENVOLVIMENTO LOCAL. Troque as senhas (ou apague estas
--   contas) antes de qualquer publicação. As senhas em texto puro são:
--     admin@devfox.local  → devfox123
--     aluno@devfox.local  → aluno123
--
-- Contas de admin são criadas AQUI ou direto no banco, nunca pela tela de
-- cadastro: o cadastro público cria apenas alunos.
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Admin DevFox',   'admin@devfox.local', '$2y$12$JnEgiuwPMktGOYX2NztOZ./xu6gB/jjgGVr3E9UgL6xPlicFiNuOS', 'admin'),
('Aluno de Teste', 'aluno@devfox.local', '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno');


-- ---------------------------------------------------------------------
-- Trilhas
-- ---------------------------------------------------------------------
INSERT INTO trilhas (id, titulo, descricao, nivel_dificuldade, icone, ordem, publicada, linguagem, subtitulo, destaque) VALUES
(1, 'Lógica de Programação', 'Aprenda lógica com Python, do primeiro print às decisões com if, else e elif.', 'iniciante', 'logica', 1, 1, 'python', NULL, 0),
(2, 'HTML', 'Estrutura e conteúdo de páginas web com HTML.', 'iniciante', 'html', 2, 1, 'html', 'Estrutura e Conteúdo', 1),
(3, 'CSS', 'Estilize suas páginas e crie layouts modernos.', 'iniciante', 'css', 3, 1, 'html', 'Estilo e Apresentação', 1),
(4, 'JavaScript Básico', 'Aprenda JavaScript do básico aos laços de repetição, funções e métodos.', 'iniciante', 'js', 4, 1, 'javascript', NULL, 0),
(5, 'Desafios Práticos', 'Resolva desafios práticos e aplique seus conhecimentos.', 'avancado', 'desafios', 5, 1, 'javascript', NULL, 0),
(6, 'Projeto Final', 'Desenvolva um projeto completo aplicando tudo que aprendeu.', 'avancado', 'projeto', 6, 1, 'javascript', NULL, 0);

-- Trilhas que só abrem depois de outras concluídas
INSERT INTO trilha_requisitos (trilha_id, requisito_id) VALUES
(6, 1),
(6, 2),
(6, 3),
(6, 4),
(6, 5);


-- =====================================================================
-- TRILHA 1: Lógica de Programação
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(1, 1, 'Primeiros passos com Python',
    'Use print() para mostrar mensagens e resultados.',
    'Python é uma linguagem de programação com uma escrita simples. A função print() mostra uma mensagem ou valor na saída do programa. Textos ficam entre aspas; números podem ser escritos diretamente. Cada linha é executada em sequência, de cima para baixo.',
    1),
(2, 1, 'Variáveis e tipos de dados',
    'Guarde textos, números e valores verdadeiros ou falsos.',
    'Variáveis guardam valores e recebem nomes. Em Python, basta escrever nome = valor; não se usa let ou const. Textos são strings (str), números inteiros são int e números decimais são float. True e False são valores booleanos (bool). Use print(nome) para exibir o valor guardado.',
    2),
(3, 1, 'Operadores e comparações',
    'Faça contas e compare valores antes de tomar decisões.',
    'Python usa +, -, *, / e % para operações aritméticas. Comparações como ==, !=, >, <, >= e <= produzem True ou False. Atenção: = atribui um valor a uma variável; == compara dois valores.',
    3),
(4, 1, 'Decisões com if, else e elif',
    'Faça o programa escolher o que executar conforme uma condição.',
    'O if executa um bloco quando a condição é verdadeira. O else executa quando ela é falsa. O elif permite testar outra condição caso as anteriores não sejam verdadeiras. Em Python, os dois-pontos : iniciam o bloco e a indentação (espaços no início da linha) define quais instruções pertencem a ele.',
    4);

-- Módulo 1: Primeiros passos com Python
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(1, 1, 'Para que serve print()?',
    'O que o comando print("Olá") faz?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(1, 'Mostra Olá na saída do programa', 1, 1),
(1, 'Cria uma variável', 0, 2),
(1, 'Repete o programa', 0, 3),
(1, 'Fecha o programa', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(2, 1, 'Minha primeira mensagem',
    'Complete para exibir a mensagem DevFox.',
    'completar', 'facil',
    '___("DevFox")',
    'Use o comando que mostra valores na saída.',
    'print("DevFox")',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(2, 'Mensagem exibida', NULL, 'DevFox', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(3, 1, 'Duas mensagens',
    'Exiba a palavra Python e, na linha seguinte, a palavra DevFox.',
    'codigo_livre', 'facil',
    '# escreva seu código aqui',
    'Use um print() para cada linha.',
    'print("Python")\nprint("DevFox")',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(3, 'Duas linhas', NULL, 'Python\nDevFox', 1, 1);


-- Módulo 2: Variáveis e tipos de dados
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(4, 2, 'Criando uma variável',
    'Como guardar o número 10 na variável idade em Python?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(4, 'idade == 10', 0, 1),
(4, 'idade = 10', 1, 2),
(4, 'let idade = 10', 0, 3),
(4, 'const idade: 10', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(5, 2, 'Guardando uma idade',
    'Complete para guardar 18 em idade e exibir esse valor.',
    'completar', 'facil',
    'idade = ___\nprint(idade)',
    'O valor é um número, então não precisa de aspas.',
    'idade = 18\nprint(idade)',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(5, 'Idade exibida', NULL, '18', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(6, 2, 'Apresente-se',
    'Crie uma variável nome com seu nome (use Ana como exemplo) e exiba-a com print().',
    'codigo_livre', 'facil',
    'nome = "Ana"\n# exiba a variável',
    'Passe o nome da variável para print(), sem aspas.',
    'nome = "Ana"\nprint(nome)',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(6, 'Nome exibido', NULL, 'Ana', 1, 1);


-- Módulo 3: Operadores e comparações
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(7, 3, 'Comparando valores',
    'Qual operador verifica se dois valores são iguais?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(7, '=', 0, 1),
(7, '==', 1, 2),
(7, '!=', 0, 3),
(7, '>=', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(8, 3, 'Somando valores',
    'Complete para exibir a soma de 7 e 5.',
    'completar', 'facil',
    'print(7 ___ 5)',
    'Use o operador de adição.',
    'print(7 + 5)',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(8, 'Resultado da soma', NULL, '12', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(9, 3, 'Maior de idade?',
    'Crie idade = 18 e exiba o resultado de idade >= 18.',
    'codigo_livre', 'facil',
    'idade = 18\n# mostre a comparação',
    'Use print() com a comparação.',
    'idade = 18\nprint(idade >= 18)',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(9, 'Comparação verdadeira', NULL, 'True', 1, 1);


-- Módulo 4: Decisões com if, else e elif
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(10, 4, 'Quando usar else?',
    'Quando o bloco else é executado?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(10, 'Quando a condição do if é falsa', 1, 1),
(10, 'Sempre antes do if', 0, 2),
(10, 'Só quando há uma função', 0, 3),
(10, 'Quando o programa inicia', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(11, 4, 'Verificando a idade',
    'Complete para exibir Maior de idade quando idade for 18 ou mais; caso contrário, Menor de idade.',
    'completar', 'facil',
    'idade = 18\nif idade >= 18:\n    print("Maior de idade")\n___:\n    print("Menor de idade")',
    'A segunda alternativa usa a palavra-chave que acompanha if.',
    'idade = 18\nif idade >= 18:\n    print("Maior de idade")\nelse:\n    print("Menor de idade")',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(11, 'Maior de idade', NULL, 'Maior de idade', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(12, 4, 'Classificando uma nota',
    'Crie nota = 7. Se nota for 7 ou mais, exiba Aprovado; caso seja menor, exiba Reprovado.',
    'codigo_livre', 'facil',
    'nota = 7\n# escreva a condição',
    'Use if/else e mantenha o print indentado dentro de cada bloco.',
    'nota = 7\nif nota >= 7:\n    print("Aprovado")\nelse:\n    print("Reprovado")',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(12, 'Nota aprovada', NULL, 'Aprovado', 1, 1),
(12, 'Verificação adicional', NULL, 'Aprovado', 0, 2);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 1
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(5, 1, 'Prova: Fundamentos de Lógica', 'prova', 70,
    'Revise print, variáveis, operadores e condicionais em Python.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(13, 5, 1, 'Questão 1', 'Qual comando exibe um valor em Python?', 'quiz', 'facil', 50, 1),
(14, 5, 2, 'Questão 2', 'Como se atribui o valor 5 à variável pontos?', 'quiz', 'facil', 50, 2),
(15, 5, 3, 'Questão 3', 'Qual é o resultado de 7 % 2 em Python?', 'quiz', 'facil', 50, 3),
(16, 5, 4, 'Questão 4', 'Para que serve elif?', 'quiz', 'medio', 50, 4),
(17, 5, 4, 'Questão 5', 'O que define os blocos de código em Python?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(13, 'print()', 1, 1),
(13, 'console.log()', 0, 2),
(13, 'echo()', 0, 3),
(13, 'show()', 0, 4),
(14, 'pontos == 5', 0, 1),
(14, 'pontos = 5', 1, 2),
(14, 'let pontos = 5', 0, 3),
(14, 'const pontos = 5', 0, 4),
(15, '0', 0, 1),
(15, '1', 1, 2),
(15, '3', 0, 3),
(15, '3.5', 0, 4),
(16, 'Testar outra condição', 1, 1),
(16, 'Declarar uma variável', 0, 2),
(16, 'Exibir texto', 0, 3),
(16, 'Encerrar o programa', 0, 4),
(17, 'Chaves {}', 0, 1),
(17, 'Indentação', 1, 2),
(17, 'Ponto e vírgula', 0, 3),
(17, 'Parênteses', 0, 4);


-- =====================================================================
-- TRILHA 2: HTML
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(6, 2, 'Estrutura, Títulos e Parágrafos',
    'Como um documento HTML é organizado e como escrever títulos e textos.',
    'HTML é a linguagem que descreve o conteúdo de uma página. Ela é feita de tags: uma tag de abertura, como <p>, o conteúdo, e uma tag de fechamento com barra, como </p>. Juntas, elas formam um elemento: <p>Olá, mundo!</p> é um parágrafo.\n\nTodo documento completo começa com <!DOCTYPE html> e tem a tag <html>, que guarda o <head> (informações sobre a página, como o <title> que aparece na aba) e o <body> (tudo o que aparece na tela). Os títulos vão de <h1>, o mais importante, até <h6>, o menos importante, e os textos corridos ficam em <p>.\n\nO erro mais comum é escolher o título pelo tamanho da letra: usar <h3> porque "fica menor" ou <h1> em tudo para "ficar grande". O número indica a importância do título na estrutura da página. A aparência se ajusta depois, com CSS.',
    1),
(7, 2, 'Listas e Links',
    'Organizar itens em listas e ligar uma página a outra.',
    'Listas agrupam itens relacionados. A <ul> é a lista não ordenada, com marcadores, boa para itens em que a ordem não importa, como uma lista de compras. A <ol> é a lista ordenada, numerada automaticamente, para passos em sequência. Nas duas, cada item fica dentro de um <li>: <ul><li>Arroz</li><li>Feijão</li></ul>.\n\nLinks são criados com a tag <a>. O endereço de destino vai no atributo href, e o texto entre as tags é o que a pessoa clica: <a href="https://developer.mozilla.org">Documentação MDN</a>. O href pode ser um site completo ou outro arquivo do seu projeto, como "contato.html".\n\nDois erros aparecem muito no começo: colocar <li> solto, fora de uma <ul> ou <ol>, e esquecer o href, o que deixa o link sem destino. Um <a> sem href parece texto comum e não leva a lugar nenhum.',
    2),
(8, 2, 'Imagens e Atributos',
    'Colocar imagens na página e configurá-las com atributos.',
    'Atributos são informações extras que ficam dentro da tag de abertura, no formato nome="valor". Você já viu o href do link. A imagem usa outros: <img src="raposa.png" alt="Uma raposa laranja" width="200">.\n\nA tag <img> não tem fechamento. O src diz qual arquivo mostrar, o alt descreve a imagem em texto e o width define a largura em pixels. O alt não é enfeite: é ele que os leitores de tela leem para pessoas cegas, e é ele que aparece quando a imagem não carrega.\n\nO erro mais comum é um erro de digitação no nome do atributo, como escrever scr em vez de src. O navegador não reclama: ele só ignora o atributo que não conhece, e a imagem simplesmente não aparece.',
    3),
(9, 2, 'Tags Semânticas e Formulários',
    'Dar significado às partes da página e receber dados do usuário.',
    'Tags semânticas dizem o que cada parte da página é. <header> é o cabeçalho, <nav> guarda os links de navegação, <main> é o conteúdo principal e <footer> é o rodapé. Na tela elas parecem uma <div> qualquer, mas ajudam leitores de tela, buscadores e quem vai ler o seu código.\n\nFormulários recebem dados. O <input> é o campo, e o atributo type muda o tipo: "text" para texto, "email" para e-mail, "password" para senha. O <label> é o rótulo do campo, e se liga a ele pelo atributo for, que precisa ter o mesmo valor do id do input: <label for="email">E-mail</label> <input type="email" id="email">. Um <button> envia o formulário.\n\nO erro mais comum é o for do label não bater com o id do input. Nada quebra na tela, mas clicar no rótulo deixa de selecionar o campo e o leitor de tela não sabe qual rótulo é de qual campo.',
    4);

-- Módulo 6: Estrutura, Títulos e Parágrafos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(18, 6, 'O título principal',
    'Qual tag deve ser usada para o título mais importante de uma página?',
    'quiz', 'facil', '<title> aparece na aba do navegador, não no conteúdo da página.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(18, '<title>', 0, 1),
(18, '<h1>', 1, 2),
(18, '<h6>', 0, 3),
(18, '<head>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(19, 6, 'Minha primeira página',
    'Complete o código trocando os ___ pela tag de título principal, para que o texto "Minha primeira página" vire um título h1. O parágrafo "Olá, mundo!" deve continuar abaixo dele.',
    'completar', 'facil',
    '<___>Minha primeira página</___>\n<p>Olá, mundo!</p>',
    'O título mais importante da página usa a tag de número 1.',
    '<h1>Minha primeira página</h1>\n<p>Olá, mundo!</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(19, 'O título h1 existe com o texto certo', 'texto("h1")', 'Minha primeira página', 1, 1),
(19, 'Existe exatamente um h1', 'contar("h1")', '1', 1, 2),
(19, 'O parágrafo continua lá', 'texto("p")', 'Olá, mundo!', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(20, 6, 'Caderno de receitas',
    'Escreva um título h1 com o texto "Receitas da Vó" e, abaixo dele, dois parágrafos: o primeiro com "Bolo de cenoura" e o segundo com "Pudim de leite".',
    'codigo_livre', 'facil',
    '<!-- escreva seu HTML aqui -->\n',
    'Cada parágrafo tem a sua própria tag <p> ... </p>.',
    '<h1>Receitas da Vó</h1>\n<p>Bolo de cenoura</p>\n<p>Pudim de leite</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(20, 'O título h1 está correto', 'texto("h1")', 'Receitas da Vó', 1, 1),
(20, 'Existem dois parágrafos', 'contar("p")', '2', 1, 2),
(20, 'O primeiro parágrafo', 'texto("p:first-of-type")', 'Bolo de cenoura', 0, 3),
(20, 'O segundo parágrafo', 'texto("p:last-of-type")', 'Pudim de leite', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(21, 6, 'Conserte os títulos',
    'Nesta página, "Blog do Dev" é o título principal e deveria estar num h1, e "Últimas postagens" é um subtítulo em h2. Alguém usou a tag errada no título principal. Corrija, sem mudar os textos.',
    'corrigir_erro', 'medio',
    '<h3>Blog do Dev</h3>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
    'O número da tag indica a importância, não o tamanho da letra. Qual é o número do título principal?',
    '<h1>Blog do Dev</h1>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(21, 'O título principal está num h1', 'texto("h1")', 'Blog do Dev', 1, 1),
(21, 'O subtítulo continua em h2', 'texto("h2")', 'Últimas postagens', 1, 2),
(21, 'Não sobrou nenhum h3', 'contar("h3")', '0', 0, 3);


-- Módulo 7: Listas e Links
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(22, 7, 'Lista numerada',
    'Qual tag cria uma lista em que os itens aparecem numerados (1, 2, 3...)?',
    'quiz', 'facil', 'Pense em "ordered list", lista ordenada.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(22, '<ul>', 0, 1),
(22, '<ol>', 1, 2),
(22, '<li>', 0, 3),
(22, '<list>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(23, 7, 'Link para a documentação',
    'Complete o código trocando o ___ pelo atributo que define o destino do link, para que o link "Documentação MDN" leve a https://developer.mozilla.org.',
    'completar', 'facil',
    '<a ___="https://developer.mozilla.org">Documentação MDN</a>',
    'É o atributo de quatro letras que guarda o endereço do link.',
    '<a href="https://developer.mozilla.org">Documentação MDN</a>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(23, 'O link aponta para a MDN', 'atributo("a", "href")', 'https://developer.mozilla.org', 1, 1),
(23, 'O texto do link continua o mesmo', 'texto("a")', 'Documentação MDN', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(24, 7, 'Lista de compras',
    'Crie uma lista não ordenada (ul) com três itens, nesta ordem: "Arroz", "Feijão" e "Farofa". Não use lista numerada.',
    'codigo_livre', 'facil',
    '<!-- escreva sua lista aqui -->\n',
    'Comece com <ul> e coloque cada item dentro de um <li>.',
    '<ul>\n  <li>Arroz</li>\n  <li>Feijão</li>\n  <li>Farofa</li>\n</ul>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(24, 'A lista tem três itens', 'contar("ul > li")', '3', 1, 1),
(24, 'O primeiro item é Arroz', 'texto("ul > li")', 'Arroz', 1, 2),
(24, 'O último item é Farofa', 'texto("ul > li:last-child")', 'Farofa', 0, 3),
(24, 'Não há lista numerada', 'existe("ol")', 'não', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(25, 7, 'Passo a passo numerado',
    'Os passos para fazer café precisam aparecer numerados, porque a ordem importa. Corrija o tipo de lista, mantendo os três itens e seus textos.',
    'corrigir_erro', 'medio',
    '<h2>Como fazer café</h2>\n<ul>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n  <li>Despeje a água sobre o pó</li>\n</ul>',
    'Existe uma lista com marcadores e outra numerada. Qual das duas é para passos em sequência?',
    '<h2>Como fazer café</h2>\n<ol>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n  <li>Despeje a água sobre o pó</li>\n</ol>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(25, 'A lista numerada tem três passos', 'contar("ol > li")', '3', 1, 1),
(25, 'O primeiro passo está certo', 'texto("ol > li")', 'Ferva a água', 1, 2),
(25, 'Não sobrou lista com marcadores', 'existe("ul")', 'não', 0, 3);


-- Módulo 8: Imagens e Atributos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(26, 8, 'Para que serve o alt',
    'Qual é a função do atributo alt em uma imagem?',
    'quiz', 'facil', 'Pense em quem não consegue ver a imagem.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(26, 'Definir a largura da imagem', 0, 1),
(26, 'Descrever a imagem em texto, para leitores de tela e quando ela não carrega', 1, 2),
(26, 'Indicar o endereço do arquivo da imagem', 0, 3),
(26, 'Fazer a imagem virar um link', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(27, 8, 'Mostrando a raposa',
    'Complete o código trocando o ___ pelo atributo que indica o arquivo da imagem, para que ela mostre o arquivo "raposa.png". O texto alternativo "Uma raposa laranja" deve continuar.',
    'completar', 'facil',
    '<img ___="raposa.png" alt="Uma raposa laranja">',
    'É o atributo que diz de onde vem a imagem (source, em inglês).',
    '<img src="raposa.png" alt="Uma raposa laranja">',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(27, 'A imagem aponta para raposa.png', 'atributo("img", "src")', 'raposa.png', 1, 1),
(27, 'O texto alternativo continua', 'atributo("img", "alt")', 'Uma raposa laranja', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(28, 8, 'O logo da página',
    'Crie uma imagem com o arquivo "logo.png", o texto alternativo "Logo do DevFox" e a largura de 200 pixels (atributo width="200").',
    'codigo_livre', 'facil',
    '<!-- escreva sua imagem aqui -->\n',
    'Todos os atributos ficam dentro da mesma tag <img>, separados por espaço.',
    '<img src="logo.png" alt="Logo do DevFox" width="200">',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(28, 'Existe uma imagem', 'existe("img")', 'sim', 1, 1),
(28, 'O texto alternativo está certo', 'atributo("img", "alt")', 'Logo do DevFox', 1, 2),
(28, 'A largura é 200', 'atributo("img", "width")', '200', 0, 3),
(28, 'O arquivo é logo.png', 'atributo("img", "src")', 'logo.png', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(29, 8, 'A foto que não aparece',
    'A foto do gato deveria aparecer e, ao ser clicada, levar para "galeria.html". Mas a imagem não carrega. Encontre o erro e corrija, mantendo o link e o texto alternativo.',
    'corrigir_erro', 'medio',
    '<a href="galeria.html">\n  <img scr="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
    'Leia com atenção o nome de cada atributo da imagem, letra por letra.',
    '<a href="galeria.html">\n  <img src="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(29, 'A imagem aponta para gato.jpg', 'atributo("img", "src")', 'gato.jpg', 1, 1),
(29, 'A imagem continua dentro do link', 'existe("a > img")', 'sim', 0, 2),
(29, 'O link leva para a galeria', 'atributo("a", "href")', 'galeria.html', 0, 3);


-- Módulo 9: Tags Semânticas e Formulários
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(30, 9, 'O conteúdo principal',
    'Qual tag semântica deve envolver o conteúdo principal da página?',
    'quiz', 'facil', 'Procure a palavra que significa "principal" em inglês.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(30, '<header>', 0, 1),
(30, '<main>', 1, 2),
(30, '<footer>', 0, 3),
(30, '<nav>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(31, 9, 'Campo de e-mail',
    'Complete o código trocando o ___ pelo tipo de campo certo para e-mail. O label "E-mail" deve continuar ligado ao campo de id "email".',
    'completar', 'facil',
    '<label for="email">E-mail</label>\n<input type="___" id="email">',
    'O valor do type para e-mail é a própria palavra, em inglês e em minúsculas.',
    '<label for="email">E-mail</label>\n<input type="email" id="email">',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(31, 'O campo é do tipo email', 'atributo("input", "type")', 'email', 1, 1),
(31, 'O label aponta para o campo', 'atributo("label", "for")', 'email', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(32, 9, 'Esqueleto da loja',
    'Monte a estrutura de uma página com três partes semânticas, nesta ordem: um header com um h1 "Minha Loja"; um main com um parágrafo "Produtos em destaque"; e um footer com um parágrafo "Contato: loja@exemplo.com".',
    'codigo_livre', 'facil',
    '<!-- escreva a estrutura aqui -->\n',
    'Cada parte é uma tag (<header>, <main>, <footer>) com o seu conteúdo dentro.',
    '<header>\n  <h1>Minha Loja</h1>\n</header>\n<main>\n  <p>Produtos em destaque</p>\n</main>\n<footer>\n  <p>Contato: loja@exemplo.com</p>\n</footer>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(32, 'O título está dentro do header', 'texto("header h1")', 'Minha Loja', 1, 1),
(32, 'O parágrafo está dentro do main', 'texto("main p")', 'Produtos em destaque', 1, 2),
(32, 'O rodapé tem o contato', 'texto("footer p")', 'Contato: loja@exemplo.com', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(33, 9, 'O rótulo perdido',
    'O rótulo "Nome" deveria estar ligado ao campo de texto, mas o for do label e o id do input não batem. O label está certo: corrija o id do campo para "nome". Mantenha o botão "Enviar".',
    'corrigir_erro', 'medio',
    '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="usuario">\n  <button>Enviar</button>\n</form>',
    'O valor do for do label precisa ser exatamente igual ao id do input.',
    '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="nome">\n  <button>Enviar</button>\n</form>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(33, 'O campo tem o id nome', 'atributo("input", "id")', 'nome', 1, 1),
(33, 'Existe um campo com id nome', 'existe("input#nome")', 'sim', 0, 2),
(33, 'O botão continua lá', 'texto("button")', 'Enviar', 0, 3);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 2
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(10, 2, 'Prova: HTML', 'prova', 70,
    'Avaliação dos módulos de estrutura, listas e links, imagens e formulários. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(34, 10, 6, 'Questão 1', 'Em qual parte do documento fica o conteúdo que aparece na tela?', 'quiz', 'facil', 50, 1),
(35, 10, 7, 'Questão 2', 'Qual atributo define o destino de um link <a>?', 'quiz', 'facil', 50, 2),
(36, 10, 7, 'Questão 3', 'Qual tag representa cada item de uma lista <ul> ou <ol>?', 'quiz', 'facil', 50, 3),
(37, 10, 8, 'Questão 4', 'O que acontece com <img scr="foto.png"> (com src escrito errado)?', 'quiz', 'medio', 50, 4),
(38, 10, 9, 'Questão 5', 'Para ligar <label for="idade"> a um campo, o que o <input> precisa ter?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(34, '<head>', 0, 1),
(34, '<body>', 1, 2),
(34, '<title>', 0, 3),
(34, '<!DOCTYPE html>', 0, 4),
(35, 'src', 0, 1),
(35, 'href', 1, 2),
(35, 'link', 0, 3),
(35, 'alt', 0, 4),
(36, '<item>', 0, 1),
(36, '<li>', 1, 2),
(36, '<p>', 0, 3),
(36, '<list>', 0, 4),
(37, 'O navegador corrige sozinho e mostra a foto', 0, 1),
(37, 'A imagem não aparece, porque o atributo é ignorado', 1, 2),
(37, 'A página inteira para de funcionar', 0, 3),
(37, 'A foto aparece, mas sem texto alternativo', 0, 4),
(38, 'name="label"', 0, 1),
(38, 'id="idade"', 1, 2),
(38, 'type="idade"', 0, 3),
(38, 'for="idade"', 0, 4);


-- =====================================================================
-- TRILHA 3: CSS
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(11, 3, 'Seletores e Cores',
    'Escolher quais elementos estilizar e mudar suas cores.',
    'Uma regra CSS tem duas partes: o seletor, que diz QUAIS elementos serão estilizados, e o bloco entre chaves, com as declarações no formato propriedade: valor;. Em h1 { color: #ff6b00; }, o seletor é h1 e a declaração pinta o texto de laranja.\n\nOs três seletores básicos são: o nome da tag (p pega todos os parágrafos), a classe com ponto na frente (.destaque pega todo elemento com class="destaque") e o id com cerquilha (#topo pega o elemento com id="topo"). A propriedade color muda a cor do texto; background-color muda a cor de fundo. As cores podem ser escritas em hexadecimal (#ff6b00), por nome (white) ou em rgb(255, 107, 0).\n\nO erro mais comum de quem começa é trocar o ponto pela cerquilha: escrever .topo quando o HTML tem id="topo". O navegador não avisa nada, a regra simplesmente não encontra ninguém e a página continua sem estilo.',
    1),
(12, 3, 'Texto e Fontes',
    'Tamanho, peso, alinhamento e espaçamento do texto.',
    'Quatro propriedades resolvem a maior parte da tipografia: font-size define o tamanho (24px, 1.5rem), font-weight define o peso (normal ou bold, que equivale a 700), text-align alinha o texto dentro do elemento (left, center, right) e line-height controla a distância entre as linhas.\n\nSobre as unidades: px é um tamanho fixo na tela; rem é relativo ao tamanho de fonte da página, que por padrão é 16px. Então 1.5rem vale 24px e 2rem vale 32px. Usar rem facilita respeitar quem aumentou a fonte no navegador.\n\nO erro mais comum aqui é esquecer o ponto e vírgula no fim de uma declaração. Sem ele, o navegador junta aquela linha com a próxima, não entende o resultado e descarta as DUAS declarações — e o erro aparece numa propriedade que estava certa.',
    2),
(13, 3, 'Box Model',
    'Margem, preenchimento, borda e largura: toda caixa da página.',
    'Para o CSS, todo elemento é uma caixa com quatro camadas, de dentro para fora: o conteúdo (cujo tamanho é o width), o padding (espaço interno, entre o conteúdo e a borda), a border (a linha em volta) e a margin (espaço externo, que afasta a caixa das vizinhas).\n\npadding e margin aceitam um valor para os quatro lados (padding: 16px) ou lados separados (margin-top, margin-bottom...). A borda precisa de espessura, estilo e cor: border: 2px solid #ff6b00. Sem o estilo (solid, dashed...), a borda não aparece.\n\nO erro mais comum é esquecer a unidade: width: 300 não é 300px, é uma declaração inválida que o navegador ignora sem avisar. A única medida que dispensa unidade é o 0. Outro clássico é digitar o nome da propriedade errado, como pading ou widht.',
    3),
(14, 3, 'Flexbox',
    'Alinhar e distribuir elementos em linha ou em coluna.',
    'Flexbox é o jeito moderno de organizar elementos lado a lado ou um embaixo do outro. Ao colocar display: flex num elemento, ele vira um contêiner flex e os filhos DIRETOS dele passam a ser organizados em linha. flex-direction: column troca a linha por coluna, e gap define o espaço entre os filhos.\n\nDuas propriedades fazem o alinhamento: justify-content distribui os filhos no eixo principal (na horizontal, quando a direção é linha) com valores como center e space-between; align-items alinha no eixo cruzado (na vertical, quando a direção é linha), com valores como center.\n\nO erro mais comum é colocar display: flex nos próprios itens, e não no elemento que os envolve. As propriedades de flex vão no PAI: é ele quem organiza os filhos. Colocadas nos filhos, elas só organizariam o que estiver dentro de cada um.',
    4);

-- Módulo 11: Seletores e Cores
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(39, 11, 'Selecionando por classe',
    'Qual seletor pega o elemento <p class="destaque">?',
    'quiz', 'facil', 'Classe usa um símbolo, id usa outro. Qual é o da classe?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(39, '#destaque', 0, 1),
(39, '.destaque', 1, 2),
(39, 'destaque', 0, 3),
(39, 'p#destaque', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(40, 11, 'Pintando o título',
    'Complete o seletor para que APENAS o elemento com a classe titulo fique com a cor laranja #ff6b00. O parágrafo deve continuar com a cor padrão (preto).',
    'completar', 'facil',
    '<style>\n___ {\n  color: #ff6b00;\n}\n</style>\n\n<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>',
    'Para selecionar por classe, escreva o nome da classe com um ponto na frente.',
    '<style>\n.titulo {\n  color: #ff6b00;\n}\n</style>\n\n<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(40, 'O título fica laranja', 'estilo(".titulo", "color")', 'rgb(255, 107, 0)', 1, 1),
(40, 'O parágrafo continua preto', 'estilo("p", "color")', 'rgb(0, 0, 0)', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(41, 11, 'Caixa de aviso',
    'Escreva o CSS para: o elemento com id aviso ter fundo (background-color) #222222 e texto (color) branco (white); e os elementos com a classe nota terem o texto azul #0066cc.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div id="aviso">Sua sequência termina hoje!</div>\n<p class="nota">Faça uma atividade para não perder.</p>\n<p class="nota">Cada dia conta.</p>',
    'Use #aviso para o id e .nota para a classe. Dentro de cada bloco, uma declaração por linha, sempre terminando com ponto e vírgula.',
    '<style>\n#aviso {\n  background-color: #222222;\n  color: white;\n}\n\n.nota {\n  color: #0066cc;\n}\n</style>\n\n<div id="aviso">Sua sequência termina hoje!</div>\n<p class="nota">Faça uma atividade para não perder.</p>\n<p class="nota">Cada dia conta.</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(41, 'Fundo escuro no aviso', 'estilo("#aviso", "background-color")', 'rgb(34, 34, 34)', 1, 1),
(41, 'Texto branco no aviso', 'estilo("#aviso", "color")', 'rgb(255, 255, 255)', 1, 2),
(41, 'Nota em azul', 'estilo(".nota", "color")', 'rgb(0, 102, 204)', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(42, 11, 'O seletor errado',
    'O título deveria ficar laranja (#ff6b00) sobre fundo quase preto (#1a1a1a), mas continua sem estilo nenhum. Olhe o HTML e corrija o seletor.',
    'corrigir_erro', 'medio',
    '<style>\n.titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}\n</style>\n\n<h1 id="titulo">Bem-vindo ao DevFox</h1>',
    'No HTML, o título tem id, e não class. Qual símbolo seleciona um id?',
    '<style>\n#titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}\n</style>\n\n<h1 id="titulo">Bem-vindo ao DevFox</h1>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(42, 'O título fica laranja', 'estilo("#titulo", "color")', 'rgb(255, 107, 0)', 1, 1),
(42, 'O fundo fica escuro', 'estilo("h1", "background-color")', 'rgb(26, 26, 26)', 0, 2);


-- Módulo 12: Texto e Fontes
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(43, 12, 'Centralizando o texto',
    'Qual declaração centraliza o texto de um parágrafo?',
    'quiz', 'facil', 'A propriedade começa com "text" e trata do alinhamento.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(43, 'align: center;', 0, 1),
(43, 'text-align: center;', 1, 2),
(43, 'font-align: center;', 0, 3),
(43, 'text-position: middle;', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(44, 12, 'Título grande e centralizado',
    'Complete o CSS para que o h1 tenha tamanho de fonte 32px e o parágrafo fique centralizado.',
    'completar', 'facil',
    '<style>\nh1 {\n  font-size: ___;\n}\n\np {\n  text-align: ___;\n}\n</style>\n\n<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>',
    'O tamanho leva o número junto da unidade, sem espaço: 32px. O alinhamento centralizado é center.',
    '<style>\nh1 {\n  font-size: 32px;\n}\n\np {\n  text-align: center;\n}\n</style>\n\n<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(44, 'Título com 32px', 'estilo("h1", "font-size")', '32px', 1, 1),
(44, 'Parágrafo centralizado', 'estilo("p", "text-align")', 'center', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(45, 12, 'Frase de destaque',
    'Estilize os elementos com a classe destaque: texto em negrito (font-weight: bold), tamanho 24px (pode escrever 24px ou 1.5rem) e altura de linha (line-height) de 30px.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>',
    'Um único bloco .destaque { } com três declarações resolve. Lembre das unidades.',
    '<style>\n.destaque {\n  font-weight: bold;\n  font-size: 24px;\n  line-height: 30px;\n}\n</style>\n\n<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(45, 'Texto em negrito', 'estilo(".destaque", "font-weight")', '700', 1, 1),
(45, 'Tamanho de 24px', 'estilo(".destaque", "font-size")', '24px', 1, 2),
(45, 'Altura de linha', 'estilo(".destaque", "line-height")', '30px', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(46, 12, 'O ponto e vírgula sumido',
    'O parágrafo deveria ter fonte de 18px, ficar centralizado e com cor #333333, mas o tamanho e o alinhamento não funcionam. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    '<style>\np {\n  font-size: 18px\n  text-align: center;\n  color: #333333;\n}\n</style>\n\n<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>',
    'Olhe o fim de cada linha. Uma delas não termina como as outras.',
    '<style>\np {\n  font-size: 18px;\n  text-align: center;\n  color: #333333;\n}\n</style>\n\n<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(46, 'Fonte de 18px', 'estilo("p", "font-size")', '18px', 1, 1),
(46, 'Cor cinza escuro', 'estilo("p", "color")', 'rgb(51, 51, 51)', 1, 2),
(46, 'Texto centralizado', 'estilo("p", "text-align")', 'center', 0, 3);


-- Módulo 13: Box Model
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(47, 13, 'Espaço de dentro',
    'Qual propriedade cria espaço ENTRE o conteúdo e a borda de uma caixa?',
    'quiz', 'facil', 'Uma delas é o espaço de fora, que afasta as caixas vizinhas. Você quer a outra.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(47, 'margin', 0, 1),
(47, 'padding', 1, 2),
(47, 'border', 0, 3),
(47, 'width', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(48, 13, 'Respiro na caixa',
    'Complete o CSS para que a caixa tenha padding de 20px em todos os lados. A borda de 2px já está pronta.',
    'completar', 'facil',
    '<style>\n.caixa {\n  padding: ___;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="caixa">Conteúdo com espaço para respirar.</div>',
    'Um único valor no padding vale para os quatro lados. Não esqueça o px.',
    '<style>\n.caixa {\n  padding: 20px;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="caixa">Conteúdo com espaço para respirar.</div>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(48, 'Padding em cima', 'estilo(".caixa", "padding-top")', '20px', 1, 1),
(48, 'Borda de 2px', 'estilo(".caixa", "border-top-width")', '2px', 1, 2),
(48, 'Padding na esquerda', 'estilo(".caixa", "padding-left")', '20px', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(49, 13, 'Montando um cartão',
    'Estilize a classe cartao com: largura (width) de 300px, padding de 16px, borda de 1px sólida (solid) na cor #cccccc e margem de baixo (margin-bottom) de 24px.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>',
    'Tudo cabe num bloco .cartao { }. A borda pode ser escrita numa linha só: border: 1px solid #cccccc;',
    '<style>\n.cartao {\n  width: 300px;\n  padding: 16px;\n  border: 1px solid #cccccc;\n  margin-bottom: 24px;\n}\n</style>\n\n<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(49, 'Largura de 300px', 'estilo(".cartao", "width")', '300px', 1, 1),
(49, 'Padding de 16px', 'estilo(".cartao", "padding-left")', '16px', 1, 2),
(49, 'Borda sólida', 'estilo(".cartao", "border-top-style")', 'solid', 1, 3),
(49, 'Cor da borda', 'estilo(".cartao", "border-top-color")', 'rgb(204, 204, 204)', 0, 4),
(49, 'Margem de baixo', 'estilo(".cartao", "margin-bottom")', '24px', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(50, 13, 'O botão desregulado',
    'O botão deveria ter padding de 12px em todos os lados e largura de 200px, mas nenhuma das duas coisas funciona. Há dois erros: um no nome de uma propriedade e outro numa medida.',
    'corrigir_erro', 'medio',
    '<style>\n.botao {\n  pading: 12px;\n  width: 200;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="botao">Continuar</div>',
    'Confira a grafia de padding letra por letra. E lembre: toda medida diferente de zero precisa de unidade.',
    '<style>\n.botao {\n  padding: 12px;\n  width: 200px;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="botao">Continuar</div>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(50, 'Padding em cima', 'estilo(".botao", "padding-top")', '12px', 1, 1),
(50, 'Largura de 200px', 'estilo(".botao", "width")', '200px', 1, 2),
(50, 'Padding na direita', 'estilo(".botao", "padding-right")', '12px', 0, 3);


-- Módulo 14: Flexbox
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(51, 14, 'Onde vai o flex',
    'Você quer colocar três <div class="item"> lado a lado. Eles estão dentro de uma <div class="linha">. Onde deve ir o display: flex?',
    'quiz', 'facil', 'O flex vai no elemento que organiza os filhos.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(51, 'Em .item', 0, 1),
(51, 'Em .linha', 1, 2),
(51, 'No body', 0, 3),
(51, 'Em cada item, com um id', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(52, 14, 'Menu espalhado',
    'Complete o CSS para que o menu seja um contêiner flex e distribua os links com o espaço sobrando ENTRE eles (space-between).',
    'completar', 'facil',
    '<style>\n.menu {\n  display: ___;\n  justify-content: ___;\n}\n</style>\n\n<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>',
    'O primeiro espaço liga o flexbox. O segundo é o valor que joga o espaço para o meio dos itens.',
    '<style>\n.menu {\n  display: flex;\n  justify-content: space-between;\n}\n</style>\n\n<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(52, 'O menu é flex', 'estilo(".menu", "display")', 'flex', 1, 1),
(52, 'Espaço entre os links', 'estilo(".menu", "justify-content")', 'space-between', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(53, 14, 'Painel em coluna',
    'Faça a classe painel virar um contêiner flex em COLUNA (flex-direction: column), com os itens centralizados no eixo cruzado (align-items: center) e 12px de espaço entre eles (gap).',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>',
    'Quatro declarações no bloco .painel { }: display, flex-direction, align-items e gap.',
    '<style>\n.painel {\n  display: flex;\n  flex-direction: column;\n  align-items: center;\n  gap: 12px;\n}\n</style>\n\n<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(53, 'O painel é flex', 'estilo(".painel", "display")', 'flex', 1, 1),
(53, 'Direção em coluna', 'estilo(".painel", "flex-direction")', 'column', 1, 2),
(53, 'Itens centralizados', 'estilo(".painel", "align-items")', 'center', 0, 3),
(53, 'Espaço entre os itens', 'estilo(".painel", "row-gap")', '12px', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(54, 14, 'Flex no lugar errado',
    'Os três itens deveriam ficar lado a lado, centralizados na horizontal (justify-content: center) e na vertical (align-items: center) dentro da linha. O CSS foi colocado no elemento errado. Corrija, deixando essas três declarações na classe linha.',
    'corrigir_erro', 'medio',
    '<style>\n.linha {\n  height: 120px;\n}\n\n.item {\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}\n</style>\n\n<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>',
    'Quem organiza os filhos é o pai. As propriedades de flex precisam estar no elemento que envolve os itens.',
    '<style>\n.linha {\n  height: 120px;\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}\n</style>\n\n<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(54, 'A linha é flex', 'estilo(".linha", "display")', 'flex', 1, 1),
(54, 'Centralizado na horizontal', 'estilo(".linha", "justify-content")', 'center', 1, 2),
(54, 'Centralizado na vertical', 'estilo(".linha", "align-items")', 'center', 0, 3);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 3
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(15, 3, 'Prova: CSS', 'prova', 70,
    'Avaliação dos módulos de seletores, texto, box model e flexbox. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(55, 15, 11, 'Questão 1', 'O HTML tem <div id="topo">. Qual seletor pega esse elemento?', 'quiz', 'facil', 50, 1),
(56, 15, 12, 'Questão 2', 'Por que esquecer o ponto e vírgula em "font-size: 18px" pode quebrar a linha seguinte?', 'quiz', 'facil', 50, 2),
(57, 15, 13, 'Questão 3', 'Qual propriedade afasta uma caixa das caixas vizinhas, criando espaço do lado de FORA da borda?', 'quiz', 'facil', 50, 3),
(58, 15, 13, 'Questão 4', 'O que acontece com a declaração "width: 300;"?', 'quiz', 'medio', 50, 4),
(59, 15, 14, 'Questão 5', 'Num contêiner com display: flex em linha, qual propriedade distribui os itens na horizontal?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(55, '.topo', 0, 1),
(55, '#topo', 1, 2),
(55, 'topo', 0, 3),
(55, '*topo', 0, 4),
(56, 'Porque o navegador junta as duas linhas numa declaração inválida e descarta ambas', 1, 1),
(56, 'Porque o navegador apaga a regra inteira e todas as outras da página', 0, 2),
(56, 'Porque o ponto e vírgula é o que define o tamanho da fonte', 0, 3),
(56, 'Não quebra: o ponto e vírgula é opcional em qualquer linha', 0, 4),
(57, 'padding', 0, 1),
(57, 'margin', 1, 2),
(57, 'width', 0, 3),
(57, 'gap-border', 0, 4),
(58, 'A largura vira 300px automaticamente', 0, 1),
(58, 'A largura vira 300% da página', 0, 2),
(58, 'Ela é inválida e o navegador a ignora', 1, 3),
(58, 'O navegador mostra uma mensagem de erro na página', 0, 4),
(59, 'align-items', 0, 1),
(59, 'justify-content', 1, 2),
(59, 'text-align', 0, 3),
(59, 'flex-direction', 0, 4);


-- =====================================================================
-- TRILHA 4: JavaScript Básico
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(16, 4, 'Console, variáveis e valores',
    'Comece exibindo mensagens e guardando informações.',
    'O console.log() exibe valores no console do navegador. Use aspas para textos, como console.log("Olá!"). Variáveis guardam valores: let permite reatribuir um valor e const declara uma variável que não pode ser reatribuída. Os tipos básicos incluem number, string e boolean. Primeiro pratique declarar, atribuir e exibir valores antes de aprender funções.',
    1),
(17, 4, 'Operadores e expressões',
    'Faça cálculos simples com variáveis e exiba o resultado.',
    'Use + para somar, - para subtrair, * para multiplicar e / para dividir. O operador % retorna o resto da divisão. As expressões podem usar variáveis: let total = preco * quantidade. Exiba o resultado com console.log(total).',
    2),
(18, 4, 'Decisões com if e else',
    'Execute instruções diferentes conforme uma condição.',
    'O if executa um bloco quando a condição é verdadeira; else executa quando ela é falsa. Use === para comparar valores e >= para verificar se um valor é maior ou igual a outro. As chaves delimitam os blocos e console.log() exibe a mensagem escolhida.',
    3),
(19, 4, 'Primeiras funções',
    'Depois das variáveis, operadores e condicionais, aprenda a reutilizar código.',
    'Uma função agrupa instruções para poderem ser reutilizadas. Ela é declarada com function, pode receber parâmetros e pode devolver um resultado com return. Use funções depois de entender variáveis, expressões e if/else.',
    9),
(20, 4, 'O laço while',
    'Repetir enquanto uma condição continuar verdadeira.',
    'O while repete um bloco de código enquanto a condição dele for verdadeira. A condição é testada ANTES de cada volta: se já começar falsa, o bloco não roda nenhuma vez.\n\nTodo while precisa de três coisas para funcionar: uma variável de controle iniciada antes do laço, uma condição que dependa dela, e uma linha DENTRO do laço que mude essa variável.\n\nEsquecer a terceira é o erro clássico: a condição nunca vira falsa e o laço roda para sempre. No DevFox o código é interrompido depois de 3 segundos, mas num programa de verdade isso trava a página inteira.\n\nUse while quando você não sabe de antemão quantas voltas serão necessárias.',
    4),
(21, 4, 'O laço for',
    'Repetir um número conhecido de vezes.',
    'O for junta as três partes do laço numa linha só: for (inicialização; condição; incremento).\n\nEm for (let i = 1; i <= 5; i++), o i começa em 1, o laço continua enquanto i for menor ou igual a 5, e a cada volta o i aumenta em 1. O i++ é uma forma curta de escrever i = i + 1.\n\nComo as três partes ficam juntas e visíveis, é muito mais difícil esquecer o incremento — por isso o for é preferido quando você JÁ SABE quantas voltas quer.\n\nO erro mais comum aqui é de limite: trocar < por <= muda o número de voltas em um. Esse engano tem até nome próprio na área: erro de um a mais, ou off-by-one.',
    5),
(22, 4, 'Percorrendo arrays',
    'Usar laços para visitar cada item de uma lista.',
    'Juntar laço e array é o que torna as duas coisas realmente úteis. O padrão é sempre o mesmo: for (let i = 0; i < lista.length; i++), e dentro do laço você usa lista[i] para chegar ao item da vez.\n\nPreste atenção nos dois limites. O i começa em 0, porque é ali que o array começa. E a condição usa < lista.length, sem o igual: numa lista de 3 itens, os índices válidos são 0, 1 e 2 — o índice 3 não existe.\n\nEsse padrão resolve uma família inteira de problemas: somar todos os itens, contar quantos atendem a uma regra, achar o maior. Muda só o que acontece dentro.',
    6),
(23, 4, 'Laços aninhados',
    'Um laço dentro do outro, para percorrer duas dimensões.',
    'Um laço aninhado é um for dentro de outro for. A cada UMA volta do laço de fora, o laço de dentro roda INTEIRO. Por isso o total de execuções é a multiplicação dos dois: 3 voltas por fora e 4 por dentro dão 12 execuções.\n\nÉ o que se usa para percorrer uma matriz — um array cujos itens são outros arrays, como [[1, 2], [3, 4]]. O laço de fora escolhe a linha, o de dentro percorre as colunas daquela linha: matriz[i][j].\n\nDuas armadilhas. A primeira é usar o mesmo nome de variável nos dois laços, ou escrever matriz[i][i] em vez de matriz[i][j] — o código roda e entrega resultado errado, sem avisar. A segunda é o custo: laços aninhados crescem rápido, e com listas grandes isso pesa de verdade.',
    7),
(24, 4, 'Parâmetros e Escopo',
    'Valores padrão, onde cada variável existe e funções que usam outras funções.',
    'Um parâmetro pode ter um valor padrão, escrito com = na própria declaração: function saudar(nome = "visitante"). Se quem chama a função não mandar esse argumento, o parâmetro assume o valor padrão em vez de ficar undefined.\n\nEscopo é a região do código onde uma variável existe. Uma variável criada com let ou const dentro de chaves { } só vive ali dentro: fora do bloco ela simplesmente não existe. Por isso funções diferentes podem ter variáveis com o mesmo nome sem uma atrapalhar a outra.\n\nO erro mais comum é declarar a variável dentro de um if e tentar usá-la depois dele, o que gera "ReferenceError: ... is not defined". A solução é declarar a variável antes do bloco e só atribuir o valor lá dentro. Outra boa prática é dividir o trabalho: uma função pequena pode chamar outra, e cada uma faz uma coisa só.',
    10),
(25, 4, 'Arrow Functions e Callbacks',
    'Funções como valores: guardar em variáveis e passar como argumento.',
    'Arrow function é um jeito mais curto de escrever uma função: const dobro = (n) => n * 2; faz o mesmo que uma function que devolve n * 2. Quando o corpo é uma única expressão, sem chaves, o resultado é devolvido automaticamente, sem precisar escrever return.\n\nEm JavaScript, funções são valores como números e textos: podem ser guardadas em variáveis e enviadas para outras funções. Uma função recebida como argumento se chama callback. Quem recebe decide quando chamá-la, escrevendo fn(valor).\n\nO erro mais comum é colocar chaves na arrow function e esquecer o return: n => { n * 2 } devolve undefined, porque com chaves o retorno deixa de ser automático. Outro erro frequente é passar o resultado em vez da função: executar(dobro(3)) envia o número 6, e não a função dobro.',
    11),
(26, 4, 'Métodos de Array',
    'map, filter, reduce, find, some, every e includes na prática.',
    'Arrays têm métodos prontos que recebem um callback. map cria um array novo transformando cada item; filter cria um array novo só com os itens que passam num teste; find devolve o primeiro item que passa no teste (ou undefined); some responde se ALGUM item passa e every se TODOS passam; includes diz se um valor está no array.\n\nreduce junta todos os itens em um único valor. Ele recebe um callback com o acumulado e o item atual, e um valor inicial: numeros.reduce((soma, n) => soma + n, 0) soma tudo começando do zero. Esses métodos podem ser encadeados: numeros.filter(n => n > 0).map(n => n * 2).\n\nO erro mais comum é esquecer o valor inicial do reduce. Com um array vazio, reduce sem valor inicial quebra com "Reduce of empty array with no initial value". Outro engano frequente é usar filter quando se quer um único item: filter sempre devolve um array, mesmo que tenha só um elemento; para um item só, use find.',
    12),
(27, 4, 'Objetos e Métodos',
    'Propriedades, métodos, a palavra this e Object.keys.',
    'Um objeto agrupa informações relacionadas em pares chave: valor, como { nome: "Ana", idade: 20 }. Cada valor é acessado com ponto (pessoa.nome) ou com colchetes (pessoa["nome"]). Quando o valor de uma propriedade é uma função, ela se chama método: jogador.ganhar(10).\n\nDentro de um método, a palavra this representa o próprio objeto que chamou o método. Assim, this.pontos lê ou altera a propriedade pontos desse objeto. Para percorrer um objeto, Object.keys(obj) devolve um array com os nomes das chaves e Object.values(obj) um array com os valores.\n\nO erro mais comum é escrever um método como arrow function: ganhar: (qtd) => { ... }. Arrow functions não têm this próprio, então this.pontos não aponta para o objeto e o resultado vira NaN ou um erro. Para métodos que usam this, use a forma ganhar(qtd) { ... } ou ganhar: function (qtd) { ... }.',
    12);

-- Módulo 16: Console, variáveis e valores
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(60, 16, 'Exibindo uma mensagem',
    'O que faz console.log("Olá")?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(60, 'Exibe Olá no console', 1, 1),
(60, 'Cria uma página HTML', 0, 2),
(60, 'Declara uma função', 0, 3),
(60, 'Guarda um arquivo', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(61, 16, 'Sua primeira variável',
    'Complete para guardar o texto DevFox em nome e exibi-lo.',
    'completar', 'facil',
    'let nome = ___;\nconsole.log(nome);',
    'Textos precisam ficar entre aspas.',
    'let nome = "DevFox";\nconsole.log(nome);',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(61, 'Nome exibido', NULL, 'DevFox', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(62, 16, 'Um valor constante',
    'Use const para guardar o número 10 em pontos e exibi-lo.',
    'completar', 'facil',
    '___ pontos = 10;\nconsole.log(pontos);',
    'Use a palavra-chave para uma variável que não será reatribuída.',
    'const pontos = 10;\nconsole.log(pontos);',
    100, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(62, 'Pontos exibidos', NULL, '10', 0, 1);


-- Módulo 17: Operadores e expressões
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(63, 17, 'Operador de multiplicação',
    'Qual operador multiplica dois números em JavaScript?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(63, '+', 0, 1),
(63, '*', 1, 2),
(63, '%', 0, 3),
(63, '=', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(64, 17, 'Somando dois valores',
    'Complete para exibir a soma de 7 e 5.',
    'completar', 'facil',
    'console.log(7 ___ 5);',
    'Use o operador de adição.',
    'console.log(7 + 5);',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(64, 'Resultado da soma', NULL, '12', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(65, 17, 'Calculando um total',
    'Crie preco = 10 e quantidade = 3 usando let e exiba o resultado da multiplicação.',
    'codigo_livre', 'facil',
    'let preco = 10;\nlet quantidade = 3;\n// exiba o total',
    'Multiplique as variáveis dentro de console.log().',
    'let preco = 10;\nlet quantidade = 3;\nconsole.log(preco * quantidade);',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(65, 'Total calculado', NULL, '30', 0, 1);


-- Módulo 18: Decisões com if e else
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(66, 18, 'Condição verdadeira',
    'Qual bloco roda quando a condição do if é falsa?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(66, 'O bloco else', 1, 1),
(66, 'O bloco if', 0, 2),
(66, 'Uma função obrigatoriamente', 0, 3),
(66, 'Nenhum código pode rodar', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(67, 18, 'Verificando a idade',
    'Complete para exibir Maior de idade quando idade for 18 ou mais.',
    'completar', 'facil',
    'let idade = 18;\nif (idade ___ 18) {\n  console.log("Maior de idade");\n} else {\n  console.log("Menor de idade");\n}',
    'Use o operador que inclui o valor 18.',
    'let idade = 18;\nif (idade >= 18) {\n  console.log("Maior de idade");\n} else {\n  console.log("Menor de idade");\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(67, 'Mensagem correta', NULL, 'Maior de idade', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(68, 18, 'Aprovado ou reprovado',
    'Crie nota = 7. Use if/else para exibir Aprovado se a nota for 7 ou mais; caso contrário, Reprovado.',
    'codigo_livre', 'facil',
    'let nota = 7;\n// escreva a condição',
    'Compare nota com 7 e use console.log() nos dois blocos.',
    'let nota = 7;\nif (nota >= 7) {\n  console.log("Aprovado");\n} else {\n  console.log("Reprovado");\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(68, 'Nota aprovada', NULL, 'Aprovado', 0, 1);


-- Módulo 19: Primeiras funções
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(69, 19, 'O que é uma função?',
    'Para que serve uma função?',
    'quiz', 'facil', NULL, 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(69, 'Agrupar código reutilizável', 1, 1),
(69, 'Exibir sempre uma mensagem', 0, 2),
(69, 'Declarar apenas números', 0, 3),
(69, 'Substituir todas as variáveis', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(70, 19, 'Dobro de um número',
    'Complete para a função dobro devolver o número multiplicado por 2.',
    'completar', 'facil',
    'function dobro(n) {\n  return n ___ 2;\n}',
    'Use o operador de multiplicação.',
    'function dobro(n) {\n  return n * 2;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(70, 'Dobro de 5', 'dobro(5)', '10', 1, 1);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(71, 19, 'Somando com função',
    'Escreva somar(a, b) para devolver a soma dos dois valores.',
    'codigo_livre', 'facil',
    'function somar(a, b) {\n  // escreva seu código\n}',
    'Use return a + b.',
    'function somar(a, b) {\n  return a + b;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(71, 'Soma de positivos', 'somar(2, 3)', '5', 0, 1);


-- Módulo 20: O laço while
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(72, 20, 'Quando o laço não para',
    'O que acontece se a condição de um while nunca se tornar falsa?',
    'quiz', 'facil', 'Pense no que falta: algo dentro do laço precisa mudar a condição.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(72, 'O laço roda para sempre e trava o programa', 1, 1),
(72, 'O laço roda uma vez e para sozinho', 0, 2),
(72, 'O JavaScript corrige a condição automaticamente', 0, 3),
(72, 'O laço nunca chega a rodar', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(73, 20, 'Somando até n',
    'Use while para somar os números de 1 até 5 e exiba o total com console.log(). Não use funções.',
    'codigo_livre', 'medio',
    'let i = 1;\nlet total = 0;\n// complete o while\nconsole.log(total);',
    'Enquanto i for menor ou igual a 5, some i ao total e aumente i em 1.',
    'let i = 1;\nlet total = 0;\nwhile (i <= 5) {\n  total = total + i;\n  i = i + 1;\n}\nconsole.log(total);',
    150, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(73, 'Soma de 1 a 5', NULL, '15', 1, 1),
(73, 'Verificação oculta', NULL, '15', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(74, 20, 'A condição do laço',
    'Complete a condição para contar de 1 até 3 usando while. Exiba o total com console.log(). Não use funções.',
    'completar', 'facil',
    'let i = 1;\nlet total = 0;\nwhile (___) {\n  total = total + 1;\n  i = i + 1;\n}\nconsole.log(total);',
    'O laço continua enquanto i for menor ou igual a 3.',
    'let i = 1;\nlet total = 0;\nwhile (i <= 3) {\n  total = total + 1;\n  i = i + 1;\n}\nconsole.log(total);',
    100, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(74, 'Contagem', NULL, '3', 1, 1),
(74, 'Verificação oculta', NULL, '3', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(75, 20, 'O laço que nunca termina',
    'O while abaixo não termina. Corrija-o para somar de 4 até 1 e exiba o total com console.log(). Não use funções.',
    'corrigir_erro', 'dificil',
    'let n = 4;\nlet total = 0;\nwhile (n > 0) {\n  total = total + n;\n  // falta atualizar n\n}\nconsole.log(total);',
    'A cada volta, diminua n em 1.',
    'let n = 4;\nlet total = 0;\nwhile (n > 0) {\n  total = total + n;\n  n = n - 1;\n}\nconsole.log(total);',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(75, 'Soma regressiva', NULL, '10', 1, 1),
(75, 'Verificação oculta', NULL, '10', 0, 2);


-- Módulo 21: O laço for
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(76, 21, 'As partes do for',
    'Quais são as três partes que ficam entre os parênteses de um for?',
    'quiz', 'facil', 'São as mesmas três coisas que um while precisa, só que escritas juntas.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(76, 'Inicialização, condição e incremento', 1, 1),
(76, 'Início, meio e fim', 0, 2),
(76, 'Variável, função e retorno', 0, 3),
(76, 'Condição, bloco e return', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(77, 21, 'Fatorial',
    'Use for para calcular 5! (5 × 4 × 3 × 2 × 1) e exiba o resultado com console.log(). Não use funções.',
    'codigo_livre', 'medio',
    'let total = 1;\n// escreva o for aqui\nconsole.log(total);',
    'Comece total em 1 e multiplique pelos números de 2 até 5.',
    'let total = 1;\nfor (let i = 2; i <= 5; i++) {\n  total = total * i;\n}\nconsole.log(total);',
    150, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(77, 'Fatorial de 5', NULL, '120', 1, 1),
(77, 'Verificação oculta', NULL, '120', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(78, 21, 'Somando só os pares',
    'Complete a condição para somar os números pares de 1 até 10 e exiba o total com console.log(). Não use funções.',
    'completar', 'medio',
    'let total = 0;\nfor (let i = 1; i <= 10; i++) {\n  if (___) {\n    total = total + i;\n  }\n}\nconsole.log(total);',
    'Use o resto da divisão por 2 para identificar os pares.',
    'let total = 0;\nfor (let i = 1; i <= 10; i++) {\n  if (i % 2 === 0) {\n    total = total + i;\n  }\n}\nconsole.log(total);',
    100, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(78, 'Soma dos pares', NULL, '30', 1, 1),
(78, 'Verificação oculta', NULL, '30', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(79, 21, 'Falta uma volta',
    'O for deveria somar de 1 até 5, mas deixa o último número de fora. Corrija e exiba o total com console.log(). Não use funções.',
    'corrigir_erro', 'medio',
    'let total = 0;\nfor (let i = 1; i < 5; i++) {\n  total = total + i;\n}\nconsole.log(total);',
    'Ajuste o limite para incluir o próprio 5.',
    'let total = 0;\nfor (let i = 1; i <= 5; i++) {\n  total = total + i;\n}\nconsole.log(total);',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(79, 'Soma de 1 a 5', NULL, '15', 1, 1),
(79, 'Verificação oculta', NULL, '15', 0, 2);


-- Módulo 22: Percorrendo arrays
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(80, 22, 'Até onde vai o laço',
    'Numa lista com 3 itens, até qual índice o laço deve ir?',
    'quiz', 'facil', 'O índice do último item é sempre length - 1.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(80, 'Até 3', 0, 1),
(80, 'Até 2', 1, 2),
(80, 'Até 4', 0, 3),
(80, 'Até 1', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(81, 22, 'Somando a lista',
    'Percorra o array [1, 2, 3] com for, some seus itens e exiba o total com console.log(). Não use funções.',
    'codigo_livre', 'medio',
    'let lista = [1, 2, 3];\nlet total = 0;\n// percorra o array\nconsole.log(total);',
    'Use i começando em 0 e continue enquanto i < lista.length. Some lista[i].',
    'let lista = [1, 2, 3];\nlet total = 0;\nfor (let i = 0; i < lista.length; i++) {\n  total = total + lista[i];\n}\nconsole.log(total);',
    150, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(81, 'Soma da lista', NULL, '6', 1, 1),
(81, 'Verificação oculta', NULL, '6', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(82, 22, 'O maior da lista',
    'Complete a condição para encontrar o maior número de [3, 9, 4] e exiba-o com console.log(). Não use funções.',
    'completar', 'medio',
    'let lista = [3, 9, 4];\nlet maiorValor = lista[0];\nfor (let i = 1; i < lista.length; i++) {\n  if (___) {\n    maiorValor = lista[i];\n  }\n}\nconsole.log(maiorValor);',
    'Compare o item atual com maiorValor.',
    'let lista = [3, 9, 4];\nlet maiorValor = lista[0];\nfor (let i = 1; i < lista.length; i++) {\n  if (lista[i] > maiorValor) {\n    maiorValor = lista[i];\n  }\n}\nconsole.log(maiorValor);',
    100, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(82, 'Maior número', NULL, '9', 1, 1),
(82, 'Verificação oculta', NULL, '9', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(83, 22, 'Contando os pares',
    'Conte quantos números pares existem em [1, 2, 3, 4] e exiba a quantidade com console.log(). Não use funções.',
    'codigo_livre', 'medio',
    'let lista = [1, 2, 3, 4];\nlet total = 0;\n// percorra e conte os pares\nconsole.log(total);',
    'Percorra a lista com for e use if com % 2 === 0.',
    'let lista = [1, 2, 3, 4];\nlet total = 0;\nfor (let i = 0; i < lista.length; i++) {\n  if (lista[i] % 2 === 0) {\n    total = total + 1;\n  }\n}\nconsole.log(total);',
    150, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(83, 'Contagem de pares', NULL, '2', 1, 1),
(83, 'Verificação oculta', NULL, '2', 0, 2);


-- Módulo 23: Laços aninhados
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(84, 23, 'Quantas voltas no total',
    'Um laço de 3 voltas contém outro de 4 voltas. Quantas vezes o bloco de dentro executa?',
    'quiz', 'facil', 'O laço de dentro roda inteiro a cada volta do de fora.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(84, '7', 0, 1),
(84, '12', 1, 2),
(84, '4', 0, 3),
(84, '3', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(85, 23, 'Contando os passos',
    'Use dois for aninhados: 3 voltas no laço externo e 4 no interno. Conte as execuções e exiba o total com console.log(). Não use funções.',
    'codigo_livre', 'medio',
    'let total = 0;\n// escreva os dois laços\nconsole.log(total);',
    'O laço interno deve executar 4 vezes para cada uma das 3 voltas externas.',
    'let total = 0;\nfor (let i = 0; i < 3; i++) {\n  for (let j = 0; j < 4; j++) {\n    total = total + 1;\n  }\n}\nconsole.log(total);',
    150, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(85, 'Contagem de passos', NULL, '12', 1, 1),
(85, 'Verificação oculta', NULL, '12', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(86, 23, 'Somando a matriz',
    'Complete o limite do laço interno para somar todos os números de [[1, 2], [3, 4]] e exiba o total com console.log(). Não use funções.',
    'completar', 'dificil',
    'let matriz = [[1, 2], [3, 4]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < ___; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
    'O limite é o tamanho da linha atual: matriz[i].length.',
    'let matriz = [[1, 2], [3, 4]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
    100, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(86, 'Soma da matriz', NULL, '10', 1, 1),
(86, 'Verificação oculta', NULL, '10', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(87, 23, 'O índice trocado',
    'O código deve somar todos os valores da matriz [[1, 2], [3, 5]], mas usa o índice errado. Corrija e exiba o total com console.log(). Não use funções.',
    'corrigir_erro', 'dificil',
    'let matriz = [[1, 2], [3, 5]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][i];\n  }\n}\nconsole.log(total);',
    'Dentro do laço interno, use j para selecionar a coluna atual.',
    'let matriz = [[1, 2], [3, 5]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(87, 'Soma correta', NULL, '11', 1, 1),
(87, 'Verificação oculta', NULL, '11', 0, 2);


-- Módulo 24: Parâmetros e Escopo
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(88, 24, 'Valor padrão',
    'Dado function saudar(nome = "visitante") { return "Oi, " + nome; }, o que saudar() devolve?',
    'quiz', 'facil', 'O valor depois do = só é usado quando o argumento não é enviado.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(88, '"Oi, undefined"', 0, 1),
(88, '"Oi, visitante"', 1, 2),
(88, '"Oi, "', 0, 3),
(88, 'Um erro, porque faltou o argumento', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(89, 24, 'Desconto padrão',
    'A função calcularPreco(valor, desconto) devolve o valor com o desconto (em %) aplicado. Complete a declaração para que o desconto seja 10 quando ele não for informado. Exemplo: calcularPreco(100) devolve 90 e calcularPreco(200, 50) devolve 100.',
    'completar', 'facil',
    'function calcularPreco(valor, desconto = ___) {\n  return valor - valor * desconto / 100;\n}',
    'O valor padrão é escrito logo depois do = na lista de parâmetros.',
    'function calcularPreco(valor, desconto = 10) {\n  return valor - valor * desconto / 100;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(89, 'Sem informar o desconto', 'calcularPreco(100)', '90', 1, 1),
(89, 'Com desconto informado', 'calcularPreco(200, 50)', '100', 1, 2),
(89, 'Outro valor sem desconto', 'calcularPreco(80)', '72', 0, 3),
(89, 'Desconto zero informado', 'calcularPreco(80, 0)', '80', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(90, 24, 'Uma função chamando outra',
    'Escreva duas funções. quadrado(n) devolve n multiplicado por ele mesmo. somaDosQuadrados(a, b) devolve quadrado(a) + quadrado(b), usando a primeira função. Exemplo: somaDosQuadrados(3, 4) devolve 25.',
    'codigo_livre', 'facil',
    'function quadrado(n) {\n  // escreva seu código aqui\n}\n\nfunction somaDosQuadrados(a, b) {\n  // use a função quadrado aqui\n}',
    'Dentro de somaDosQuadrados, chame quadrado(a) e quadrado(b) e some os resultados.',
    'function quadrado(n) {\n  return n * n;\n}\n\nfunction somaDosQuadrados(a, b) {\n  return quadrado(a) + quadrado(b);\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(90, 'Quadrado de 5', 'quadrado(5)', '25', 1, 1),
(90, 'Soma dos quadrados de 3 e 4', 'somaDosQuadrados(3, 4)', '25', 1, 2),
(90, 'Soma com número negativo', 'somaDosQuadrados(-1, 2)', '5', 0, 3),
(90, 'Soma com zero', 'somaDosQuadrados(0, 6)', '36', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(91, 24, 'A variável que sumiu',
    'A função classificarNota(nota) deveria devolver "aprovado" para nota 7 ou mais e "reprovado" para as demais, mas dá erro ao rodar. Corrija o escopo da variável situacao.',
    'corrigir_erro', 'medio',
    'function classificarNota(nota) {\n  if (nota >= 7) {\n    let situacao = "aprovado";\n  } else {\n    let situacao = "reprovado";\n  }\n  return situacao;\n}',
    'O erro é "situacao is not defined". Declare a variável ANTES do if e, dentro dos blocos, apenas atribua o valor.',
    'function classificarNota(nota) {\n  let situacao;\n  if (nota >= 7) {\n    situacao = "aprovado";\n  } else {\n    situacao = "reprovado";\n  }\n  return situacao;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(91, 'Nota alta', 'classificarNota(9)', 'aprovado', 1, 1),
(91, 'Nota baixa', 'classificarNota(4)', 'reprovado', 1, 2),
(91, 'Exatamente 7', 'classificarNota(7)', 'aprovado', 0, 3),
(91, 'Quase lá', 'classificarNota(6.9)', 'reprovado', 0, 4);


-- Módulo 25: Arrow Functions e Callbacks
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(92, 25, 'Lendo uma arrow function',
    'Dado const dobro = n => n * 2;, qual é o valor de dobro(4)?',
    'quiz', 'facil', 'Sem chaves, a expressão depois da seta é devolvida automaticamente.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(92, '4', 0, 1),
(92, '8', 1, 2),
(92, 'undefined', 0, 3),
(92, '"n * 2"', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(93, 25, 'Total da compra',
    'Complete a arrow function calcularTotal(preco, quantidade) para que ela devolva o preço multiplicado pela quantidade. Exemplo: calcularTotal(10, 3) devolve 30.',
    'completar', 'facil',
    'const calcularTotal = (preco, quantidade) => ___;',
    'Depois da seta, escreva só a expressão que deve ser devolvida.',
    'const calcularTotal = (preco, quantidade) => preco * quantidade;',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(93, 'Três unidades', 'calcularTotal(10, 3)', '30', 1, 1),
(93, 'Preço quebrado', 'calcularTotal(2.5, 4)', '10', 1, 2),
(93, 'Quantidade zero', 'calcularTotal(7, 0)', '0', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(94, 25, 'Aplicar duas vezes',
    'Escreva uma função aplicarDuasVezes(fn, valor) que chame a função fn sobre valor e, depois, chame fn de novo sobre o resultado, devolvendo o valor final. Exemplo: aplicarDuasVezes(n => n + 3, 1) devolve 7.',
    'codigo_livre', 'facil',
    'function aplicarDuasVezes(fn, valor) {\n  // escreva seu código aqui\n}',
    'Guarde fn(valor) em uma variável e devolva fn aplicada a essa variável. Ou, direto: fn(fn(valor)).',
    'function aplicarDuasVezes(fn, valor) {\n  return fn(fn(valor));\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(94, 'Somando 3 duas vezes', 'aplicarDuasVezes(n => n + 3, 1)', '7', 1, 1),
(94, 'Dobrando duas vezes', 'aplicarDuasVezes(n => n * 2, 5)', '20', 1, 2),
(94, 'Funciona com textos', 'aplicarDuasVezes(t => t + "!", "oi")', 'oi!!', 0, 3),
(94, 'Subtraindo duas vezes', 'aplicarDuasVezes(n => n - 10, 0)', '-20', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(95, 25, 'A metade que sumiu',
    'A arrow function metade(n) deveria devolver a metade do número, e a função executar(fn, x) apenas chama o callback. Mas metade está devolvendo undefined. Corrija a arrow function.',
    'corrigir_erro', 'medio',
    'const metade = n => { n / 2 };\n\nfunction executar(fn, x) {\n  return fn(x);\n}',
    'Com chaves, a arrow function precisa de return. Remova as chaves ou escreva return.',
    'const metade = n => n / 2;\n\nfunction executar(fn, x) {\n  return fn(x);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(95, 'Metade de 8', 'metade(8)', '4', 1, 1),
(95, 'Usada como callback', 'executar(metade, 10)', '5', 1, 2),
(95, 'Metade de número ímpar', 'metade(3)', '1.5', 0, 3),
(95, 'Callback com negativo', 'executar(metade, -6)', '-3', 0, 4);


-- Módulo 26: Métodos de Array
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(96, 26, 'Escolhendo o método',
    'Qual método devolve um NOVO array contendo apenas os itens que passam em um teste?',
    'quiz', 'facil', 'O nome do método em inglês quer dizer "filtrar".', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(96, 'map', 0, 1),
(96, 'filter', 1, 2),
(96, 'find', 0, 3),
(96, 'includes', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(97, 26, 'Dobrando com map',
    'Complete a função dobrarTodos(numeros) para que ela devolva um novo array com cada número multiplicado por 2. Exemplo: dobrarTodos([1, 2, 3]) devolve [2,4,6].',
    'completar', 'facil',
    'function dobrarTodos(numeros) {\n  return numeros.___(n => n * 2);\n}',
    'Você quer transformar cada item, mantendo a mesma quantidade de itens.',
    'function dobrarTodos(numeros) {\n  return numeros.map(n => n * 2);\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(97, 'Três números', 'dobrarTodos([1, 2, 3])', '[2,4,6]', 1, 1),
(97, 'Com negativo', 'dobrarTodos([-5, 10])', '[-10,20]', 1, 2),
(97, 'Array vazio', 'dobrarTodos([])', '[]', 0, 3),
(97, 'Um número só', 'dobrarTodos([0.5])', '[1]', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(98, 26, 'Soma dos pares',
    'Escreva uma função somarPares(numeros) que devolva a soma apenas dos números pares do array. Se não houver pares, devolva 0. Exemplo: somarPares([1, 2, 3, 4]) devolve 6. Tente usar filter e reduce.',
    'codigo_livre', 'facil',
    'function somarPares(numeros) {\n  // escreva seu código aqui\n}',
    'Primeiro filtre com n % 2 === 0; depois some com reduce((soma, n) => soma + n, 0).',
    'function somarPares(numeros) {\n  return numeros.filter(n => n % 2 === 0).reduce((soma, n) => soma + n, 0);\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(98, 'Mistura de pares e ímpares', 'somarPares([1, 2, 3, 4])', '6', 1, 1),
(98, 'Sem nenhum par', 'somarPares([7, 9])', '0', 1, 2),
(98, 'Com negativo', 'somarPares([10, -2, 5])', '8', 0, 3),
(98, 'Array vazio', 'somarPares([])', '0', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(99, 26, 'Carrinho vazio',
    'A função totalCarrinho(precos) deveria devolver a soma dos preços, e 0 quando o carrinho estiver vazio. Com o carrinho vazio, porém, ela quebra. Corrija o reduce.',
    'corrigir_erro', 'medio',
    'function totalCarrinho(precos) {\n  return precos.reduce((soma, preco) => soma + preco);\n}',
    'O erro é "Reduce of empty array with no initial value". Passe o valor inicial 0 como segundo argumento do reduce.',
    'function totalCarrinho(precos) {\n  return precos.reduce((soma, preco) => soma + preco, 0);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(99, 'Três produtos', 'totalCarrinho([10, 20, 5])', '35', 1, 1),
(99, 'Carrinho vazio', 'totalCarrinho([])', '0', 1, 2),
(99, 'Um produto só', 'totalCarrinho([7.5])', '7.5', 0, 3);


-- Módulo 27: Objetos e Métodos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(100, 27, 'Quem é o this?',
    'Dentro de um método escrito como depositar(valor) { this.saldo += valor; }, a quem this se refere?',
    'quiz', 'facil', 'Em conta.depositar(50), quem está antes do ponto?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(100, 'Ao próprio objeto que chamou o método', 1, 1),
(100, 'Ao parâmetro valor', 0, 2),
(100, 'À última variável criada no programa', 0, 3),
(100, 'A nada: this sempre vale undefined', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(101, 27, 'Conta bancária',
    'A função criarConta(saldoInicial) devolve um objeto com a propriedade saldo e o método depositar(valor), que soma o valor ao saldo e devolve o novo saldo. Complete a linha que altera o saldo do próprio objeto. Exemplo: criarConta(100).depositar(50) devolve 150.',
    'completar', 'facil',
    'function criarConta(saldoInicial) {\n  return {\n    saldo: saldoInicial,\n    depositar(valor) {\n      ___ += valor;\n      return this.saldo;\n    }\n  };\n}',
    'Para falar da propriedade saldo do próprio objeto, use this seguido de ponto.',
    'function criarConta(saldoInicial) {\n  return {\n    saldo: saldoInicial,\n    depositar(valor) {\n      this.saldo += valor;\n      return this.saldo;\n    }\n  };\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(101, 'Depósito simples', 'criarConta(100).depositar(50)', '150', 1, 1),
(101, 'Conta começando do zero', 'criarConta(0).depositar(20)', '20', 0, 2),
(101, 'Dois depósitos seguidos', '(() => { const c = criarConta(10); c.depositar(5); return c.depositar(5); })()', '20', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(102, 27, 'Listando as chaves',
    'Escreva uma função listarChaves(obj) que devolva um texto com os nomes das chaves do objeto separados por vírgula e espaço. Exemplo: listarChaves({ nome: "Ana", idade: 20 }) devolve "nome, idade".',
    'codigo_livre', 'facil',
    'function listarChaves(obj) {\n  // escreva seu código aqui\n}',
    'Object.keys(obj) devolve um array com as chaves; o método join(", ") junta os itens de um array em um texto.',
    'function listarChaves(obj) {\n  return Object.keys(obj).join(", ");\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(102, 'Duas chaves', 'listarChaves({ nome: "Ana", idade: 20 })', 'nome, idade', 1, 1),
(102, 'Uma chave só', 'listarChaves({ cor: "laranja" })', 'cor', 1, 2),
(102, 'Três chaves', 'listarChaves({ a: 1, b: 2, c: 3 })', 'a, b, c', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(103, 27, 'Pontos que não somam',
    'A função criarJogador(nome) devolve um objeto com nome, pontos (começando em 0) e o método ganhar(qtd), que soma qtd aos pontos e devolve o total. Mas ganhar não altera os pontos do jogador. Corrija o método.',
    'corrigir_erro', 'medio',
    'function criarJogador(nome) {\n  return {\n    nome: nome,\n    pontos: 0,\n    ganhar: (qtd) => {\n      this.pontos += qtd;\n      return this.pontos;\n    }\n  };\n}',
    'Arrow functions não têm this próprio. Troque por ganhar(qtd) { ... } para que this seja o objeto do jogador.',
    'function criarJogador(nome) {\n  return {\n    nome: nome,\n    pontos: 0,\n    ganhar(qtd) {\n      this.pontos += qtd;\n      return this.pontos;\n    }\n  };\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(103, 'Primeira pontuação', 'criarJogador("Fox").ganhar(10)', '10', 1, 1),
(103, 'Pontos acumulando', '(() => { const j = criarJogador("Ana"); j.ganhar(5); return j.ganhar(3); })()', '8', 0, 2);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 4
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(28, 4, 'Prova: JavaScript', 'prova', 70,
    'Avaliação de fundamentos, estruturas de repetição, funções e métodos. Acerte 70% para avançar.',
    13);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(104, 28, 16, 'Questão 1', 'Onde aparece o resultado de um console.log()?', 'quiz', 'facil', 50, 1),
(105, 28, 19, 'Questão 2', 'O que uma função sem return devolve?', 'quiz', 'facil', 50, 2),
(106, 28, 17, 'Questão 3', 'Qual é o resultado de 7 % 2?', 'quiz', 'facil', 50, 3),
(107, 28, 18, 'Questão 4', 'Qual bloco executa quando if é falso?', 'quiz', 'medio', 50, 4),
(108, 28, 19, 'Questão 5', 'Qual palavra devolve um resultado de uma função?', 'quiz', 'medio', 50, 5),
(109, 28, 20, 'Questão 1', 'O que falta num while que roda para sempre?', 'quiz', 'medio', 50, 6),
(110, 28, 21, 'Questão 2', 'Quantas voltas dá o laço for (let i = 1; i <= 5; i++)?', 'quiz', 'medio', 50, 7),
(111, 28, 22, 'Questão 3', 'Qual é a condição correta para percorrer um array inteiro?', 'quiz', 'medio', 50, 8),
(112, 28, 22, 'Questão 4', 'Em que valor o índice deve começar ao percorrer um array?', 'quiz', 'medio', 50, 9),
(113, 28, 23, 'Questão 5', 'Numa matriz, o que matriz[i][j] representa?', 'quiz', 'medio', 50, 10),
(114, 28, 24, 'Questão 1', 'Uma variável foi declarada com let dentro do bloco de um if. O que acontece ao usá-la depois que o if termina?', 'quiz', 'medio', 50, 11),
(115, 28, 25, 'Questão 2', 'O que a arrow function const f = n => { n + 1 }; devolve ao chamar f(5)?', 'quiz', 'medio', 50, 12),
(116, 28, 26, 'Questão 3', 'Qual é o resultado de [3, 8, 12].find(n => n > 5)?', 'quiz', 'medio', 50, 13),
(117, 28, 26, 'Questão 4', 'Qual método responde true se PELO MENOS UM item do array passa no teste?', 'quiz', 'medio', 50, 14),
(118, 28, 27, 'Questão 5', 'O que Object.keys({ nome: "Fox", nivel: 3 }) devolve?', 'quiz', 'medio', 50, 15);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(104, 'Na própria página, como texto', 0, 1),
(104, 'No console do navegador', 1, 2),
(104, 'Num arquivo salvo no computador', 0, 3),
(104, 'Em lugar nenhum', 0, 4),
(105, 'undefined', 1, 1),
(105, '0', 0, 2),
(105, 'null', 0, 3),
(105, 'Um erro', 0, 4),
(106, '0', 0, 1),
(106, '1', 1, 2),
(106, '2', 0, 3),
(106, '3.5', 0, 4),
(107, 'if', 0, 1),
(107, 'else', 1, 2),
(107, 'function', 0, 3),
(107, 'return', 0, 4),
(108, 'console', 0, 1),
(108, 'return', 1, 2),
(108, 'const', 0, 3),
(108, 'if', 0, 4),
(109, 'Uma linha dentro do laço que mude a variável da condição', 1, 1),
(109, 'Um return antes do laço', 0, 2),
(109, 'Mais uma condição no if', 0, 3),
(109, 'Um ponto e vírgula no fim', 0, 4),
(110, '4', 0, 1),
(110, '5', 1, 2),
(110, '6', 0, 3),
(110, '1', 0, 4),
(111, 'i <= lista.length', 0, 1),
(111, 'i < lista.length', 1, 2),
(111, 'i < lista.length - 2', 0, 3),
(111, 'i > 0', 0, 4),
(112, '0', 1, 1),
(112, '1', 0, 2),
(112, '-1', 0, 3),
(112, 'lista.length', 0, 4),
(113, 'O item da linha i e coluna j', 1, 1),
(113, 'A soma da linha i com a coluna j', 0, 2),
(113, 'O tamanho da linha i', 0, 3),
(113, 'A última linha da matriz', 0, 4),
(114, 'Ela continua valendo normalmente', 0, 1),
(114, 'Ocorre um ReferenceError, porque ela só existe dentro do bloco', 1, 2),
(114, 'Ela passa a valer 0', 0, 3),
(114, 'Ela vira uma variável global', 0, 4),
(115, '6', 0, 1),
(115, '5', 0, 2),
(115, 'undefined', 1, 3),
(115, 'Um erro de sintaxe', 0, 4),
(116, '[8, 12]', 0, 1),
(116, '8', 1, 2),
(116, 'true', 0, 3),
(116, '12', 0, 4),
(117, 'every', 0, 1),
(117, 'map', 0, 2),
(117, 'some', 1, 3),
(117, 'reduce', 0, 4),
(118, '["Fox", 3]', 0, 1),
(118, '["nome", "nivel"]', 1, 2),
(118, '2', 0, 3),
(118, '{ nome: "Fox" }', 0, 4);


-- =====================================================================
-- TRILHA 5: Desafios Práticos
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(29, 5, 'Desafios com Texto',
    'Estratégias para analisar e transformar strings.',
    'Antes de escrever qualquer linha, resolva o problema com um exemplo na mão. Pegue uma frase curta, faça o processo no papel e anote cada passo. Esses passos viram o algoritmo: "percorrer cada letra", "guardar numa lista", "juntar no final".\n\nMuitos desafios com texto ficam fáceis depois de uma NORMALIZAÇÃO: deixar tudo em minúsculas com toLowerCase() e remover o que não interessa, como espaços. Assim "Ana" e "ana" passam a ser a mesma coisa, e a comparação deixa de depender de detalhes.\n\nOutra estratégia poderosa é quebrar o texto em partes: split("") separa letras e split(" ") separa palavras. Com um array em mãos, você pode inverter, filtrar ou contar, e depois usar join para montar o texto de volta. Sempre teste o caso da string vazia.',
    13),
(30, 5, 'Desafios com Números',
    'Primos, dígitos, sequências e divisores.',
    'Em desafios numéricos, a estratégia é transformar a definição matemática em passos. "Primo é o número maior que 1 que só é divisível por 1 e por ele mesmo" vira: se for menor que 2, não é primo; senão, procure um divisor entre 2 e o próprio número.\n\nCom os operadores % e Math.floor você desmonta um número: n % 10 devolve o último dígito e Math.floor(n / 10) remove esse dígito. Repetindo isso num while até o número chegar a 0, você visita cada dígito. Sequências como Fibonacci seguem a mesma ideia: guarde os dois valores anteriores e calcule o próximo a cada volta.\n\nPense também em eficiência e nos limites. Para testar se n é primo, basta procurar divisores até a raiz quadrada de n, porque todo divisor maior tem um par menor. E sempre teste os casos de borda: 0, 1, 2 e números negativos.',
    2),
(31, 5, 'Desafios com Listas',
    'Duplicados, posições e comparações entre arrays.',
    'Um padrão muito comum com listas é construir um resultado novo: crie um array vazio, percorra a lista original e decida, item a item, o que entra. É assim que se removem duplicados (só entra quem ainda não está no resultado) e que se calcula a intersecção de duas listas (só entra quem também aparece na outra).\n\nQuando o problema pede "o maior", "o segundo maior" ou "o menor", mantenha variáveis que guardam os melhores valores vistos até agora e atualize-as a cada item. Pense no que acontece com valores repetidos, listas com um único item e listas vazias: o enunciado deve dizer o que devolver nesses casos.\n\nPara problemas de posição, como rotacionar, use o operador %: (i + k) % tamanho faz o índice "dar a volta" no fim do array. E k % tamanho resolve rotações maiores que a própria lista, já que rotacionar 5 vezes uma lista de 5 itens é o mesmo que não mexer.',
    3),
(32, 5, 'Desafios com Dados',
    'Contar, somar, agrupar e ordenar listas de objetos.',
    'Programas reais trabalham com listas de objetos: produtos de um carrinho, jogadores de um ranking, pedidos de uma loja. A estratégia é sempre a mesma: descubra qual campo de cada objeto importa e o que deve acontecer com ele (somar, contar, comparar).\n\nUm objeto vazio funciona como um contador de muitas coisas ao mesmo tempo. Para cada item, use-o como chave: contagem[item] = (contagem[item] || 0) + 1. O "|| 0" resolve a primeira aparição, quando a chave ainda não existe. A mesma ideia serve para agrupar: em vez de somar 1, você empurra o item num array daquela chave.\n\nPara ordenar objetos, use sort com uma função de comparação: (a, b) => a.pontos - b.pontos deixa em ordem crescente e b.pontos - a.pontos em ordem decrescente. Lembre que sort altera o array original; se não quiser isso, ordene uma cópia feita com [...lista].',
    4);

-- Módulo 29: Desafios com Texto
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(119, 29, 'Normalizando antes de comparar',
    'Você quer saber se "Arara" é um palíndromo (lê-se igual de trás para frente). Qual é o primeiro passo mais seguro?',
    'quiz', 'facil', '"A" e "a" são caracteres diferentes para o JavaScript.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(119, 'Comparar o texto original com ele mesmo', 0, 1),
(119, 'Converter tudo para minúsculas antes de inverter e comparar', 1, 2),
(119, 'Contar quantas letras a palavra tem', 0, 3),
(119, 'Trocar as vogais por números', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(120, 29, 'Contando vogais',
    'Complete a função contarVogais(texto) para que ela devolva quantas vogais (a, e, i, o, u) o texto tem. Letras maiúsculas também contam. Texto vazio deve devolver 0. Considere apenas vogais sem acento.',
    'completar', 'facil',
    'function contarVogais(texto) {\n  const vogais = "aeiou";\n  let total = 0;\n  for (const letra of texto.toLowerCase()) {\n    if (___) {\n      total++;\n    }\n  }\n  return total;\n}',
    'Verifique se a string vogais inclui a letra atual com o método includes.',
    'function contarVogais(texto) {\n  const vogais = "aeiou";\n  let total = 0;\n  for (const letra of texto.toLowerCase()) {\n    if (vogais.includes(letra)) {\n      total++;\n    }\n  }\n  return total;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(120, 'Palavra simples', 'contarVogais("programar")', '3', 1, 1),
(120, 'Com maiúsculas', 'contarVogais("DEVFOX")', '2', 1, 2),
(120, 'Texto vazio', 'contarVogais("")', '0', 0, 3),
(120, 'Sem nenhuma vogal', 'contarVogais("xyz")', '0', 0, 4),
(120, 'Frase com espaços', 'contarVogais("Oi Ana")', '4', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(121, 29, 'Palíndromo',
    'Escreva uma função ehPalindromo(texto) que devolva true se o texto for lido igual de frente para trás e de trás para frente, e false caso contrário. Ignore espaços e diferenças entre maiúsculas e minúsculas: "A sacada da casa" é palíndromo. Um texto vazio é considerado palíndromo (devolve true).',
    'codigo_livre', 'medio',
    'function ehPalindromo(texto) {\n  // escreva seu código aqui\n}',
    'Normalize primeiro: toLowerCase() e remova os espaços (split(" ").join("")). Depois inverta com split("").reverse().join("") e compare.',
    'function ehPalindromo(texto) {\n  const limpo = texto.toLowerCase().split(" ").join("");\n  const invertido = limpo.split("").reverse().join("");\n  return limpo === invertido;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(121, 'Palavra palíndromo', 'ehPalindromo("arara")', 'true', 1, 1),
(121, 'Palavra comum', 'ehPalindromo("devfox")', 'false', 1, 2),
(121, 'Com maiúscula', 'ehPalindromo("Ana")', 'true', 0, 3),
(121, 'Frase com espaços', 'ehPalindromo("A sacada da casa")', 'true', 0, 4),
(121, 'Texto vazio', 'ehPalindromo("")', 'true', 0, 5),
(121, 'Quase palíndromo', 'ehPalindromo("ab")', 'false', 0, 6);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(122, 29, 'Invertendo a ordem das palavras',
    'A função inverterPalavras(frase) deveria devolver as palavras da frase em ordem inversa, separadas por um espaço: inverterPalavras("eu gosto de codar") deve devolver "codar de gosto eu". Com uma só palavra, devolve a própria palavra; com texto vazio, devolve texto vazio. Mas a função está perdendo uma palavra. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    'function inverterPalavras(frase) {\n  const palavras = frase.split(" ");\n  const resultado = [];\n  for (let i = palavras.length - 1; i > 0; i--) {\n    resultado.push(palavras[i]);\n  }\n  return resultado.join(" ");\n}',
    'Qual é o índice da primeira palavra? Confira se o laço chega até ele.',
    'function inverterPalavras(frase) {\n  const palavras = frase.split(" ");\n  const resultado = [];\n  for (let i = palavras.length - 1; i >= 0; i--) {\n    resultado.push(palavras[i]);\n  }\n  return resultado.join(" ");\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(122, 'Frase de quatro palavras', 'inverterPalavras("eu gosto de codar")', 'codar de gosto eu', 1, 1),
(122, 'Duas palavras', 'inverterPalavras("olá mundo")', 'mundo olá', 1, 2),
(122, 'Uma palavra só', 'inverterPalavras("DevFox")', 'DevFox', 0, 3),
(122, 'Texto vazio', 'inverterPalavras("")', '', 0, 4);


-- Módulo 30: Desafios com Números
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(123, 30, 'Até onde procurar divisores',
    'Para verificar se um número n é primo, até que valor basta procurar divisores?',
    'quiz', 'facil', 'Se 36 = 4 x 9, o divisor 9 tem um par menor (4). Onde os pares se encontram?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(123, 'Até n - 1, obrigatoriamente', 0, 1),
(123, 'Até a raiz quadrada de n', 1, 2),
(123, 'Até 10', 0, 3),
(123, 'Até n * 2', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(124, 30, 'Soma dos dígitos',
    'Complete a função somaDigitos(n) para que ela devolva a soma dos dígitos de um número inteiro não negativo. Por exemplo, somaDigitos(123) devolve 6. Para 0, devolve 0.',
    'completar', 'facil',
    'function somaDigitos(n) {\n  let soma = 0;\n  while (n > 0) {\n    soma += n % 10;\n    n = ___;\n  }\n  return soma;\n}',
    'Depois de somar o último dígito, remova-o: divida por 10 e arredonde para baixo com Math.floor.',
    'function somaDigitos(n) {\n  let soma = 0;\n  while (n > 0) {\n    soma += n % 10;\n    n = Math.floor(n / 10);\n  }\n  return soma;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(124, 'Três dígitos', 'somaDigitos(123)', '6', 1, 1),
(124, 'Com zeros no meio', 'somaDigitos(1005)', '6', 1, 2),
(124, 'Zero', 'somaDigitos(0)', '0', 0, 3),
(124, 'Um dígito', 'somaDigitos(7)', '7', 0, 4),
(124, 'Número maior', 'somaDigitos(9876)', '30', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(125, 30, 'Número primo',
    'Escreva uma função ehPrimo(n) que devolva true se n for primo e false caso contrário. Números menores que 2 (incluindo 0, 1 e negativos) não são primos.',
    'codigo_livre', 'medio',
    'function ehPrimo(n) {\n  // escreva seu código aqui\n}',
    'Trate primeiro n < 2. Depois, use um for de 2 até i * i <= n: se n % i === 0, achou um divisor e não é primo.',
    'function ehPrimo(n) {\n  if (n < 2) {\n    return false;\n  }\n  for (let i = 2; i * i <= n; i++) {\n    if (n % i === 0) {\n      return false;\n    }\n  }\n  return true;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(125, 'Primo pequeno', 'ehPrimo(7)', 'true', 1, 1),
(125, 'Não primo', 'ehPrimo(8)', 'false', 1, 2),
(125, 'Um não é primo', 'ehPrimo(1)', 'false', 0, 3),
(125, 'Dois é primo', 'ehPrimo(2)', 'true', 0, 4),
(125, 'Negativo', 'ehPrimo(-5)', 'false', 0, 5),
(125, 'Produto de primos', 'ehPrimo(91)', 'false', 0, 6),
(125, 'Primo maior', 'ehPrimo(97)', 'true', 0, 7);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(126, 30, 'Sequência de Fibonacci',
    'A função fibonacci(n) deveria devolver um array com os n primeiros termos da sequência de Fibonacci, começando por 0 e 1: fibonacci(5) devolve [0, 1, 1, 2, 3]. Para n = 0, devolve um array vazio. Mas ela está devolvendo termos a mais. Corrija.',
    'corrigir_erro', 'medio',
    'function fibonacci(n) {\n  const termos = [];\n  let a = 0;\n  let b = 1;\n  for (let i = 0; i <= n; i++) {\n    termos.push(a);\n    const proximo = a + b;\n    a = b;\n    b = proximo;\n  }\n  return termos;\n}',
    'Conte quantas vezes o laço roda para n = 1. Deveria ser uma vez só.',
    'function fibonacci(n) {\n  const termos = [];\n  let a = 0;\n  let b = 1;\n  for (let i = 0; i < n; i++) {\n    termos.push(a);\n    const proximo = a + b;\n    a = b;\n    b = proximo;\n  }\n  return termos;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(126, 'Cinco termos', 'fibonacci(5)', '[0,1,1,2,3]', 1, 1),
(126, 'Oito termos', 'fibonacci(8)', '[0,1,1,2,3,5,8,13]', 1, 2),
(126, 'Um termo', 'fibonacci(1)', '[0]', 0, 3),
(126, 'Zero termos', 'fibonacci(0)', '[]', 0, 4);


-- Módulo 31: Desafios com Listas
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(127, 31, 'Dando a volta no array',
    'Numa lista de tamanho 4, qual índice é calculado por (3 + 2) % 4?',
    'quiz', 'facil', '5 dividido por 4 dá 1 e sobra 1.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(127, '5', 0, 1),
(127, '1', 1, 2),
(127, '3', 0, 3),
(127, '0', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(128, 31, 'Removendo duplicados',
    'Complete a função removerDuplicados(lista) para que ela devolva um novo array sem valores repetidos, mantendo a ordem da primeira aparição de cada um. removerDuplicados([3, 1, 3, 2, 1]) devolve [3, 1, 2]. Lista vazia devolve [].',
    'completar', 'facil',
    'function removerDuplicados(lista) {\n  const resultado = [];\n  for (const item of lista) {\n    if (___) {\n      resultado.push(item);\n    }\n  }\n  return resultado;\n}',
    'O item só entra se o resultado AINDA NÃO o incluir. Use ! com includes.',
    'function removerDuplicados(lista) {\n  const resultado = [];\n  for (const item of lista) {\n    if (!resultado.includes(item)) {\n      resultado.push(item);\n    }\n  }\n  return resultado;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(128, 'Números repetidos', 'removerDuplicados([3, 1, 3, 2, 1])', '[3,1,2]', 1, 1),
(128, 'Textos repetidos', 'removerDuplicados(["a", "b", "a"])', '["a","b"]', 1, 2),
(128, 'Lista vazia', 'removerDuplicados([])', '[]', 0, 3),
(128, 'Todos iguais', 'removerDuplicados([7, 7, 7])', '[7]', 0, 4),
(128, 'Sem repetição', 'removerDuplicados([5, 4, 3])', '[5,4,3]', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(129, 31, 'Segundo maior',
    'Escreva uma função segundoMaior(lista) que devolva o segundo maior valor DISTINTO de uma lista de números. Em [4, 9, 9, 2], o maior é 9 e o segundo maior é 4. Se a lista não tiver pelo menos dois valores distintos (vazia, com um item ou com todos iguais), devolva null. A lista pode ter números negativos.',
    'codigo_livre', 'dificil',
    'function segundoMaior(lista) {\n  // escreva seu código aqui\n}',
    'Guarde duas variáveis começando em -Infinity: maior e segundo. Se o item for maior que o maior, o antigo maior vira segundo. Se ficar entre os dois (e for diferente do maior), vira o segundo.',
    'function segundoMaior(lista) {\n  let maior = -Infinity;\n  let segundo = -Infinity;\n  for (const n of lista) {\n    if (n > maior) {\n      segundo = maior;\n      maior = n;\n    } else if (n < maior && n > segundo) {\n      segundo = n;\n    }\n  }\n  return segundo === -Infinity ? null : segundo;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(129, 'Lista comum', 'segundoMaior([5, 1, 9, 3])', '5', 1, 1),
(129, 'Maior repetido', 'segundoMaior([4, 9, 9, 2])', '4', 1, 2),
(129, 'Um item só', 'segundoMaior([7])', 'null', 0, 3),
(129, 'Lista vazia', 'segundoMaior([])', 'null', 0, 4),
(129, 'Todos iguais', 'segundoMaior([2, 2, 2])', 'null', 0, 5),
(129, 'Só negativos', 'segundoMaior([-1, -5, -3])', '-3', 0, 6);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(130, 31, 'Rotacionando um array',
    'A função rotacionar(lista, k) deveria devolver um novo array com os itens deslocados k posições para a direita; quem sai pelo fim volta pelo começo. rotacionar([1, 2, 3, 4, 5], 2) devolve [4, 5, 1, 2, 3]. k pode ser maior que o tamanho da lista, e lista vazia devolve []. Mas os itens estão indo parar fora do array. Corrija.',
    'corrigir_erro', 'dificil',
    'function rotacionar(lista, k) {\n  if (lista.length === 0) {\n    return [];\n  }\n  const passos = k % lista.length;\n  const resultado = [];\n  for (let i = 0; i < lista.length; i++) {\n    resultado[i + passos] = lista[i];\n  }\n  return resultado;\n}',
    'O novo índice precisa "dar a volta" quando passa do fim. Use % lista.length nele também.',
    'function rotacionar(lista, k) {\n  if (lista.length === 0) {\n    return [];\n  }\n  const passos = k % lista.length;\n  const resultado = [];\n  for (let i = 0; i < lista.length; i++) {\n    resultado[(i + passos) % lista.length] = lista[i];\n  }\n  return resultado;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(130, 'Duas posições', 'rotacionar([1, 2, 3, 4, 5], 2)', '[4,5,1,2,3]', 1, 1),
(130, 'Uma posição', 'rotacionar(["a", "b", "c"], 1)', '["c","a","b"]', 1, 2),
(130, 'k maior que a lista', 'rotacionar([1, 2, 3], 4)', '[3,1,2]', 0, 3),
(130, 'Lista vazia', 'rotacionar([], 3)', '[]', 0, 4),
(130, 'Volta completa', 'rotacionar([1, 2, 3], 3)', '[1,2,3]', 0, 5);


-- Módulo 32: Desafios com Dados
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(131, 32, 'Ordenando do maior para o menor',
    'Qual função de comparação ordena jogadores do MAIOR para o MENOR número de pontos com sort?',
    'quiz', 'facil', 'Se o resultado for positivo, b vem antes de a.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(131, '(a, b) => a.pontos - b.pontos', 0, 1),
(131, '(a, b) => b.pontos - a.pontos', 1, 2),
(131, '(a, b) => a.pontos > b.pontos', 0, 3),
(131, '(a, b) => a.nome - b.nome', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(132, 32, 'Contando frequência',
    'Complete a função contarFrequencia(lista) para que ela devolva um objeto em que cada chave é um item da lista e o valor é quantas vezes ele aparece. contarFrequencia(["uva", "pera", "uva"]) devolve { uva: 2, pera: 1 }. Lista vazia devolve {}.',
    'completar', 'facil',
    'function contarFrequencia(lista) {\n  const contagem = {};\n  for (const item of lista) {\n    contagem[item] = ___;\n  }\n  return contagem;\n}',
    'Some 1 ao valor atual da chave; se ela ainda não existir, comece de 0 usando ||.',
    'function contarFrequencia(lista) {\n  const contagem = {};\n  for (const item of lista) {\n    contagem[item] = (contagem[item] || 0) + 1;\n  }\n  return contagem;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(132, 'Frutas repetidas', 'contarFrequencia(["uva", "pera", "uva"])', '{"uva":2,"pera":1}', 1, 1),
(132, 'Todos diferentes', 'contarFrequencia(["a", "b", "c"])', '{"a":1,"b":1,"c":1}', 1, 2),
(132, 'Lista vazia', 'contarFrequencia([])', '{}', 0, 3),
(132, 'Um item repetido', 'contarFrequencia(["x", "x", "x"])', '{"x":3}', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(133, 32, 'Total do carrinho',
    'Escreva uma função totalCarrinho(itens) que receba uma lista de objetos { nome, preco, quantidade } e devolva o valor total da compra (preco x quantidade de cada item). Se o total for 100 ou mais, aplique 10% de desconto. Carrinho vazio devolve 0. Exemplo: [{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, quantidade: 1 }] soma 110 e, com desconto, devolve 99.',
    'codigo_livre', 'medio',
    'function totalCarrinho(itens) {\n  // escreva seu código aqui\n}',
    'Primeiro some preco * quantidade de todos os itens (reduce ou for). Só depois verifique se o total chegou a 100 e, se sim, multiplique por 0.9.',
    'function totalCarrinho(itens) {\n  let total = 0;\n  for (const item of itens) {\n    total += item.preco * item.quantidade;\n  }\n  if (total >= 100) {\n    total = total * 0.9;\n  }\n  return total;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(133, 'Sem desconto', 'totalCarrinho([{ nome: "lápis", preco: 30, quantidade: 1 }, { nome: "borracha", preco: 20, quantidade: 2 }])', '70', 1, 1),
(133, 'Com desconto', 'totalCarrinho([{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, quantidade: 1 }])', '99', 1, 2),
(133, 'Exatamente 100 tem desconto', 'totalCarrinho([{ nome: "mochila", preco: 25, quantidade: 4 }])', '90', 0, 3),
(133, 'Carrinho vazio', 'totalCarrinho([])', '0', 0, 4),
(133, 'Logo abaixo do limite', 'totalCarrinho([{ nome: "livro", preco: 99, quantidade: 1 }])', '99', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(134, 32, 'Ranking de jogadores',
    'A função ranking(jogadores) recebe uma lista de objetos { nome, pontos } e deveria devolver um array só com os nomes, do jogador com MAIS pontos para o com MENOS. Lista vazia devolve []. Mas o ranking está saindo de cabeça para baixo. Corrija.',
    'corrigir_erro', 'medio',
    'function ranking(jogadores) {\n  const ordenados = [...jogadores].sort((a, b) => a.pontos - b.pontos);\n  return ordenados.map((j) => j.nome);\n}',
    'Para ordem decrescente, a subtração na função de comparação precisa começar pelo b.',
    'function ranking(jogadores) {\n  const ordenados = [...jogadores].sort((a, b) => b.pontos - a.pontos);\n  return ordenados.map((j) => j.nome);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(134, 'Três jogadores', 'ranking([{ nome: "Ana", pontos: 50 }, { nome: "Bia", pontos: 80 }, { nome: "Caio", pontos: 30 }])', '["Bia","Ana","Caio"]', 1, 1),
(134, 'Dois jogadores', 'ranking([{ nome: "Leo", pontos: 10 }, { nome: "Mia", pontos: 90 }])', '["Mia","Leo"]', 1, 2),
(134, 'Lista vazia', 'ranking([])', '[]', 0, 3),
(134, 'Com pontuação zero', 'ranking([{ nome: "Zé", pontos: 0 }, { nome: "Rui", pontos: 15 }, { nome: "Lia", pontos: 40 }])', '["Lia","Rui","Zé"]', 0, 4);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 5
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(33, 5, 'Prova: Desafios Práticos', 'prova', 70,
    'Avaliação das estratégias para resolver desafios com texto, números, listas e dados. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(135, 33, 29, 'Questão 1', 'Qual expressão devolve o texto "devfox" invertido ("xofved")?', 'quiz', 'facil', 50, 1),
(136, 33, 30, 'Questão 2', 'Se n vale 472, qual é o valor de n % 10 e de Math.floor(n / 10)?', 'quiz', 'facil', 50, 2),
(137, 33, 30, 'Questão 3', 'O MDC (máximo divisor comum) de 12 e 18 é:', 'quiz', 'facil', 50, 3),
(138, 33, 31, 'Questão 4', 'Qual código devolve a intersecção de a = [1, 2, 3] e b = [2, 3, 4], ou seja, [2, 3]?', 'quiz', 'medio', 50, 4),
(139, 33, 32, 'Questão 5', 'Você quer agrupar produtos por categoria num objeto grupos. Para cada produto p, o que garante que o array da categoria exista antes do push?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(135, '"devfox".reverse()', 0, 1),
(135, '"devfox".split("").reverse().join("")', 1, 2),
(135, '"devfox".split(" ").join("")', 0, 3),
(135, '"devfox".toLowerCase()', 0, 4),
(136, '2 e 47', 1, 1),
(136, '47 e 2', 0, 2),
(136, '4 e 72', 0, 3),
(136, '2 e 47.2', 0, 4),
(137, '2', 0, 1),
(137, '3', 0, 2),
(137, '6', 1, 3),
(137, '36', 0, 4),
(138, 'a.filter((x) => b.includes(x))', 1, 1),
(138, 'a.filter((x) => !b.includes(x))', 0, 2),
(138, 'a.concat(b)', 0, 3),
(138, 'a.map((x) => b.includes(x))', 0, 4),
(139, 'grupos[p.categoria] = grupos[p.categoria] || [];', 1, 1),
(139, 'grupos.push(p.categoria);', 0, 2),
(139, 'grupos[p.categoria] = 0;', 0, 3),
(139, 'delete grupos[p.categoria];', 0, 4);


-- =====================================================================
-- TRILHA 6: Projeto Final
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(34, 6, 'Projeto: Gerenciador de Tarefas',
    'Construa, passo a passo, um pequeno gerenciador de tarefas com arrays, objetos e funções.',
    'Neste projeto você vai juntar tudo o que aprendeu para construir um programa completo: um gerenciador de tarefas, parecido com uma lista de afazeres. Ele guarda tarefas, marca as que foram feitas, mostra o que ainda falta e gera um resumo. Não é preciso nada novo: variáveis, condicionais, laços, funções, arrays e objetos bastam.\n\nO coração do projeto é o modelo de dados. Cada tarefa é um objeto com três propriedades, sempre nesta ordem: titulo (texto, como "Estudar"), prioridade (texto: "alta" ou "baixa") e concluida (boolean, que começa como false). A lista de tarefas é um array desses objetos, por exemplo: [{ titulo: "Estudar", prioridade: "alta", concluida: false }].\n\nO plano é dividir o problema em funções pequenas, cada uma com uma única responsabilidade: criarTarefa monta o objeto, adicionarTarefa coloca na lista, concluirTarefa marca como feita, pendentes lista o que falta, contarPorPrioridade agrupa as tarefas e resumo descreve a lista em uma frase. Cada passo já traz pronto o código dos passos anteriores; você escreve só a parte nova.',
    1);

-- Módulo 34: Projeto: Gerenciador de Tarefas
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(140, 34, 'O modelo de dados',
    'No gerenciador, qual destes valores representa corretamente UMA tarefa nova, que ainda não foi feita?',
    'quiz', 'facil', 'Cada tarefa é um objeto com titulo, prioridade e concluida, e concluida é um boolean.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(140, '{ titulo: "Estudar", prioridade: "alta", concluida: false }', 1, 1),
(140, '["Estudar", "alta", false]', 0, 2),
(140, '{ titulo: "Estudar", prioridade: "alta", concluida: "não" }', 0, 3),
(140, '"Estudar - alta - pendente"', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(141, 34, 'Passo 1: criar uma tarefa',
    'Complete a função criarTarefa(titulo, prioridade) para que ela devolva um objeto no formato { titulo, prioridade, concluida }, nesta ordem. Toda tarefa nova começa ainda não concluída. Por exemplo, criarTarefa("Estudar", "alta") deve devolver {"titulo":"Estudar","prioridade":"alta","concluida":false}.',
    'completar', 'facil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: ___ };\n}',
    'Uma tarefa nova ainda não foi feita. Qual valor boolean representa "não"?',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(141, 'Tarefa de prioridade alta', 'criarTarefa("Estudar", "alta")', '{"titulo":"Estudar","prioridade":"alta","concluida":false}', 1, 1),
(141, 'Tarefa de prioridade baixa', 'criarTarefa("Ler", "baixa")', '{"titulo":"Ler","prioridade":"baixa","concluida":false}', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(142, 34, 'Passo 2: adicionar à lista',
    'A função criarTarefa já está pronta. Escreva agora adicionarTarefa(lista, tarefa), que coloca a tarefa no FINAL do array lista (alterando o próprio array) e devolve a nova quantidade de tarefas da lista. Por exemplo, adicionarTarefa([], criarTarefa("Estudar", "alta")) devolve 1. Se a lista já tiver 2 tarefas, deve devolver 3.',
    'codigo_livre', 'facil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  // escreva seu código aqui\n}',
    'O método push coloca um item no final do array. Depois, a propriedade length diz quantos itens ele tem.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(142, 'Lista vazia', 'adicionarTarefa([], criarTarefa("Estudar", "alta"))', '1', 1, 1),
(142, 'A tarefa entra no final da lista', '(() => { const l = [criarTarefa("Estudar", "alta")]; adicionarTarefa(l, criarTarefa("Ler", "baixa")); return l.map(t => t.titulo); })()', '["Estudar","Ler"]', 1, 2),
(142, 'Lista com duas tarefas', 'adicionarTarefa([criarTarefa("A", "alta"), criarTarefa("B", "baixa")], criarTarefa("C", "alta"))', '3', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(143, 34, 'Passo 3: concluir uma tarefa',
    'Escreva concluirTarefa(lista, titulo). Ela procura na lista a tarefa com esse titulo; se encontrar, muda concluida para true e devolve true. Se nenhuma tarefa tiver esse titulo (inclusive quando a lista está vazia), não altera nada e devolve false.',
    'codigo_livre', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  // escreva seu código aqui\n}',
    'O método find devolve o primeiro objeto que satisfaz a condição, ou undefined se não achar nenhum. Como ele devolve o próprio objeto, alterar tarefa.concluida altera a tarefa dentro da lista.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}',
    150, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(143, 'Tarefa existente', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Estudar")', 'true', 1, 1),
(143, 'A tarefa fica marcada como concluída', '(() => { const l = [criarTarefa("Estudar", "alta"), criarTarefa("Ler", "baixa")]; concluirTarefa(l, "Ler"); return l.map(t => t.concluida); })()', '[false,true]', 1, 2),
(143, 'Título que não existe', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Correr")', 'false', 0, 3),
(143, 'Lista vazia', 'concluirTarefa([], "Estudar")', 'false', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(144, 34, 'Passo 4: o que ainda falta',
    'Escreva pendentes(lista), que devolve um array com os TÍTULOS das tarefas ainda não concluídas, na mesma ordem em que aparecem na lista. Se todas estiverem concluídas, ou se a lista estiver vazia, devolva um array vazio []. Por exemplo, para Estudar (pendente) e Ler (concluída), deve devolver ["Estudar"].',
    'codigo_livre', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  // escreva seu código aqui\n}',
    'Use filter para ficar só com as tarefas em que concluida é false e, depois, map para pegar só o titulo.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}',
    150, 5);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(144, 'Uma pendente e uma concluída', 'pendentes([criarTarefa("Estudar", "alta"), {titulo: "Ler", prioridade: "baixa", concluida: true}])', '["Estudar"]', 1, 1),
(144, 'Todas pendentes', 'pendentes([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "alta")])', '["A","B","C"]', 1, 2),
(144, 'Todas concluídas', 'pendentes([{titulo: "Ler", prioridade: "baixa", concluida: true}, {titulo: "Correr", prioridade: "alta", concluida: true}])', '[]', 0, 3),
(144, 'Lista vazia', 'pendentes([])', '[]', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(145, 34, 'Passo 5: conserte a contagem',
    'A função contarPorPrioridade(lista) deveria devolver um objeto dizendo quantas tarefas há de cada prioridade, com as chaves na ordem em que cada prioridade aparece pela primeira vez. Por exemplo, para as prioridades "alta", "baixa", "baixa" o resultado deve ser {"alta":1,"baixa":2}. Para uma lista vazia, {}. Mas ela está contando errado. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] = 1;\n  }\n  return contagem;\n}',
    'Teste com duas tarefas de mesma prioridade: o valor da chave deveria aumentar, mas é sobrescrito.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}',
    200, 6);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(145, 'Prioridades misturadas', 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "baixa")])', '{"alta":1,"baixa":2}', 1, 1),
(145, 'Três tarefas de prioridade alta', 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "alta"), criarTarefa("C", "alta")])', '{"alta":3}', 0, 2),
(145, 'Lista vazia', 'contarPorPrioridade([])', '{}', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(146, 34, 'Passo 6: o resumo da lista',
    'Escreva resumo(lista), que devolve uma frase no formato "<total> tarefas, <concluídas> concluídas, <pendentes> pendentes". Cada palavra fica no singular quando o número dela é exatamente 1 ("1 tarefa", "1 concluída", "1 pendente") e no plural em qualquer outro caso, inclusive 0. Exemplos: "3 tarefas, 1 concluída, 2 pendentes" e, para a lista vazia, "0 tarefas, 0 concluídas, 0 pendentes".',
    'codigo_livre', 'dificil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}\n\nfunction resumo(lista) {\n  // escreva seu código aqui\n}',
    'Conte as concluídas com filter(...).length; as pendentes são o total menos as concluídas. Para cada número, escolha a palavra com um if ou com o operador ternário (n === 1 ? "..." : "...").',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}\n\nfunction resumo(lista) {\n  const total = lista.length;\n  const feitas = lista.filter(t => t.concluida).length;\n  const faltam = total - feitas;\n  const tTarefa = total === 1 ? "tarefa" : "tarefas";\n  const tConcluida = feitas === 1 ? "concluída" : "concluídas";\n  const tPendente = faltam === 1 ? "pendente" : "pendentes";\n  return total + " " + tTarefa + ", " + feitas + " " + tConcluida + ", " + faltam + " " + tPendente;\n}',
    150, 7);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(146, 'Três tarefas, uma concluída', 'resumo([criarTarefa("A", "alta"), {titulo: "B", prioridade: "baixa", concluida: true}, criarTarefa("C", "baixa")])', '3 tarefas, 1 concluída, 2 pendentes', 1, 1),
(146, 'Duas tarefas concluídas', 'resumo([{titulo: "A", prioridade: "alta", concluida: true}, {titulo: "B", prioridade: "baixa", concluida: true}])', '2 tarefas, 2 concluídas, 0 pendentes', 1, 2),
(146, 'Lista vazia', 'resumo([])', '0 tarefas, 0 concluídas, 0 pendentes', 0, 3),
(146, 'Uma única tarefa pendente', 'resumo([criarTarefa("A", "alta")])', '1 tarefa, 0 concluídas, 1 pendente', 0, 4);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 6
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(35, 6, 'Avaliação do Projeto', 'prova', 70,
    'Questões sobre as decisões do projeto. Acerte 70% para concluir.',
    2);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(147, 35, 34, 'Questão 1', 'Por que a propriedade concluida de cada tarefa é um boolean (true/false) e não um texto?', 'quiz', 'facil', 50, 1),
(148, 35, 34, 'Questão 2', 'Qual método de array é o mais adequado para localizar UMA tarefa pelo título, como em concluirTarefa?', 'quiz', 'facil', 50, 2),
(149, 35, 34, 'Questão 3', 'Na função pendentes, por que usamos filter e depois map?', 'quiz', 'facil', 50, 3),
(150, 35, 34, 'Questão 4', 'Em contarPorPrioridade, o que acontece se a linha que atualiza a contagem usar "= 1" em vez de "+= 1"?', 'quiz', 'medio', 50, 4),
(151, 35, 34, 'Questão 5', 'Qual a principal vantagem de dividir o gerenciador em várias funções pequenas?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(147, 'Porque ela só tem dois estados possíveis e pode ser usada direto em condições e filtros', 1, 1),
(147, 'Porque objetos em JavaScript não aceitam propriedades de texto', 0, 2),
(147, 'Porque booleans ocupam mais espaço e deixam o programa mais seguro', 0, 3),
(147, 'Porque o método push só funciona com booleans', 0, 4),
(148, 'map', 0, 1),
(148, 'find', 1, 2),
(148, 'reduce', 0, 3),
(148, 'push', 0, 4),
(149, 'filter ordena as tarefas e map remove as concluídas', 0, 1),
(149, 'filter fica só com as tarefas não concluídas e map transforma cada uma no seu título', 1, 2),
(149, 'filter soma as tarefas e map conta quantas são', 0, 3),
(149, 'Os dois fazem a mesma coisa; usar ambos é só por estilo', 0, 4),
(150, 'O programa dá erro e para de rodar', 0, 1),
(150, 'Cada prioridade aparece com no máximo 1, mesmo tendo várias tarefas', 1, 2),
(150, 'A contagem passa a ser o dobro do valor correto', 0, 3),
(150, 'Nada muda, porque as duas formas são equivalentes', 0, 4),
(151, 'O programa fica mais rápido porque tem mais funções', 0, 1),
(151, 'Cada função tem uma responsabilidade clara e pode ser testada e corrigida separadamente', 1, 2),
(151, 'Funções pequenas dispensam o uso de arrays e objetos', 0, 3),
(151, 'Assim não é preciso usar return em nenhuma delas', 0, 4);


-- ---------------------------------------------------------------------
-- Catálogo de conquistas
-- ---------------------------------------------------------------------
-- A regra de cada conquista está em includes/conquistas.php, pelo código.
-- ---------------------------------------------------------------------
INSERT INTO conquistas (codigo, titulo, descricao, icone, raridade) VALUES
('primeira_aula', 'Código Iniciante', 'Complete sua primeira aula.', 'estrela', 'bronze'),
('primeiro_desafio', 'Primeiro Desafio', 'Conclua seu primeiro desafio de código.', 'trofeu', 'prata'),
('sequencia_7', 'Sequência de 7', 'Mantenha 7 dias seguidos de estudo.', 'fogo', 'especial'),
('explorador', 'Explorador', 'Inicie 3 trilhas diferentes.', 'mapa', 'ouro'),
('trilha_completa', 'Trilha Concluída', 'Conclua uma trilha inteira.', 'medalha', 'ouro'),
('sem_dica', 'Por Conta Própria', 'Acerte 5 desafios sem usar dica.', 'lampada', 'prata');


-- ---------------------------------------------------------------------
-- Catálogo da Loja (v5) — só itens cosméticos
-- ---------------------------------------------------------------------
INSERT INTO itens_loja (slug, nome, descricao, categoria, preco, ordem) VALUES
('brasa', 'Moldura Brasa', 'Anel laranja, a cor da raposa.', 'moldura', 60, 1),
('oceano', 'Moldura Oceano', 'Anel azul, calmo como um código sem bug.', 'moldura', 60, 2),
('floresta', 'Moldura Floresta', 'Anel verde, para quem vive em "testes passaram".', 'moldura', 80, 3),
('neon', 'Moldura Neon', 'Anel roxo que brilha na barra lateral.', 'moldura', 150, 4),
('ouro', 'Moldura Ouro', 'Anel dourado. Para quem chegou longe.', 'moldura', 250, 5),
('cacador-de-bugs', 'Caçador de Bugs', 'Para quem lê a mensagem de erro antes de pedir ajuda.', 'titulo', 80, 6),
('raposa-noturna', 'Raposa Noturna', 'Para quem resolve desafio depois da meia-noite.', 'titulo', 100, 7),
('mestre-do-loop', 'Mestre do Loop', 'Para quem já não tem medo de laço infinito.', 'titulo', 120, 8),
('lenda-do-console', 'Lenda do Console', 'Para quem depura tudo com console.log — e acerta.', 'titulo', 200, 9);


INSERT IGNORE INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(1000, 1, 'Identificando uma saída', 'Qual código mostra o número 25 na saída do Python?', 'quiz', 'facil', NULL, 100, 5),
(1001, 1, 'Texto entre aspas', 'Qual comando exibe a palavra Oi como texto?', 'quiz', 'facil', NULL, 100, 5),
(1002, 2, 'Tipo texto', 'Qual valor é do tipo string em Python?', 'quiz', 'facil', NULL, 100, 5),
(1003, 2, 'Atualizando uma variável', 'Depois de x = 4 e x = x + 3, qual é o valor de x?', 'quiz', 'facil', NULL, 100, 5),
(1004, 3, 'Resto da divisão', 'Qual é o resultado de 10 % 3 em Python?', 'quiz', 'facil', NULL, 100, 5),
(1005, 3, 'Comparação maior que', 'Qual expressão verifica se idade é maior que 17?', 'quiz', 'facil', NULL, 100, 5),
(1006, 4, 'Condição do if', 'Qual símbolo deve aparecer no final da linha if idade >= 18 em Python?', 'quiz', 'facil', NULL, 100, 5),
(1007, 4, 'Bloco indentado', 'Em Python, onde deve ficar o print dentro de um if?', 'quiz', 'facil', NULL, 100, 5),
(1019, 16, 'Tag de título', 'Qual tag representa o título principal de uma página HTML?', 'quiz', 'facil', NULL, 100, 5),
(1020, 17, 'Link HTML', 'Qual atributo de <a> define o endereço do link?', 'quiz', 'facil', NULL, 100, 5),
(1021, 18, 'Texto alternativo', 'Para que serve o atributo alt em uma imagem?', 'quiz', 'facil', NULL, 100, 5),
(1022, 19, 'Rótulo do campo', 'Qual atributo do label conecta o rótulo ao id de um input?', 'quiz', 'facil', NULL, 100, 5),
(1023, 21, 'Seletor de classe', 'Qual seletor CSS aplica estilo a class="cartao"?', 'quiz', 'facil', NULL, 100, 5),
(1024, 22, 'Tamanho da fonte', 'Qual propriedade CSS altera o tamanho do texto?', 'quiz', 'facil', NULL, 100, 5),
(1025, 23, 'Espaço externo', 'Qual propriedade cria espaço fora da borda de um elemento?', 'quiz', 'facil', NULL, 100, 5),
(1026, 24, 'Alinhamento flex', 'Qual propriedade distribui itens ao longo do eixo principal de um contêiner flex?', 'quiz', 'facil', NULL, 100, 5),
(1027, 26, 'Escopo de bloco', 'Uma variável let declarada dentro de um if pode ser usada fora das chaves desse if?', 'quiz', 'facil', NULL, 100, 5),
(1028, 27, 'Callback', 'O que é um callback?', 'quiz', 'facil', NULL, 100, 5),
(1029, 28, 'Método map', 'O que o método map normalmente devolve?', 'quiz', 'facil', NULL, 100, 5),
(1030, 29, 'Método de objeto', 'Como se chama uma função armazenada como propriedade de um objeto?', 'quiz', 'facil', NULL, 100, 5),
(1031, 31, 'Inverter texto', 'Qual sequência pode inverter os caracteres de uma string texto?', 'quiz', 'facil', NULL, 100, 5),
(1032, 32, 'Último dígito', 'Qual expressão obtém o último dígito de um número inteiro positivo n?', 'quiz', 'facil', NULL, 100, 5),
(1033, 33, 'Array sem duplicados', 'Qual estrutura pode ajudar a guardar valores únicos de um array?', 'quiz', 'facil', NULL, 100, 5),
(1034, 34, 'Ordenação numérica', 'Para ordenar números em ordem crescente com sort, qual comparação é adequada?', 'quiz', 'facil', NULL, 100, 5);

-- Questão complementar do módulo de decisões em JavaScript.
INSERT IGNORE INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(1035, 8, 'Operador lógico', 'Qual operador lógico exige que as duas condições sejam verdadeiras em JavaScript?', 'quiz', 'facil', NULL, 100, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1000, 'print(25)', 1, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1000 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1000, 'mostrar(25)', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1000 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1000, 'output = 25', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1000 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1000, 'echo 25', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1000 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1001, 'print(Oi)', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1001 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1001, 'print("Oi")', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1001 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1001, 'print = "Oi"', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1001 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1001, 'text("Oi")', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1001 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1002, '42', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1002 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1002, '3.5', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1002 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1002, '"DevFox"', 1, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1002 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1002, 'True', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1002 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1003, '3', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1003 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1003, '4', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1003 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1003, '7', 1, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1003 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1003, '43', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1003 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1004, '0', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1004 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1004, '1', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1004 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1004, '3', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1004 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1004, '3.33', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1004 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1005, 'idade = 17', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1005 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1005, 'idade > 17', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1005 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1005, 'idade + 17', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1005 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1005, 'idade != 17', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1005 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1006, ';', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1006 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1006, '{', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1006 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1006, ':', 1, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1006 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1006, '=>', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1006 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1007, 'Na mesma coluna do if', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1007 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1007, 'Com indentação dentro do bloco', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1007 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1007, 'Sempre no fim do arquivo', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1007 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1007, 'Entre chaves', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1007 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1019, '<head>', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1019 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1019, '<h1>', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1019 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1019, '<title>', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1019 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1019, '<p>', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1019 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1020, 'src', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1020 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1020, 'alt', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1020 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1020, 'href', 1, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1020 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1020, 'target', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1020 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1021, 'Mudar o tamanho', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1021 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1021, 'Descrever a imagem quando ela não aparece e apoiar acessibilidade', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1021 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1021, 'Criar um link automaticamente', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1021 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1021, 'Definir a cor da imagem', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1021 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1022, 'name', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1022 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1022, 'for', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1022 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1022, 'href', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1022 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1022, 'action', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1022 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1023, '#cartao', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1023 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1023, '.cartao', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1023 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1023, 'cartao()', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1023 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1023, '*cartao', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1023 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1024, 'font-style', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1024 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1024, 'text-align', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1024 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1024, 'font-size', 1, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1024 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1024, 'text-weight', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1024 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1025, 'padding', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1025 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1025, 'margin', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1025 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1025, 'border', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1025 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1025, 'outline', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1025 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1026, 'justify-content', 1, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1026 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1026, 'font-align', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1026 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1026, 'text-items', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1026 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1026, 'position', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1026 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1027, 'Sim, sempre', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1027 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1027, 'Não, ela fica limitada ao bloco', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1027 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1027, 'Só se for número', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1027 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1027, 'Somente com console.log', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1027 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1028, 'Uma função passada para outra função', 1, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1028 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1028, 'Um tipo de array', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1028 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1028, 'Uma variável global', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1028 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1028, 'Um laço que não termina', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1028 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1029, 'O primeiro item encontrado', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1029 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1029, 'Um novo array com os resultados da transformação', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1029 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1029, 'Um boolean', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1029 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1029, 'Sempre um número', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1029 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1030, 'Parâmetro', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1030 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1030, 'Método', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1030 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1030, 'Índice', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1030 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1030, 'Callback obrigatório', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1030 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1031, 'texto.reverse()', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1031 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1031, 'texto.split("").reverse().join("")', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1031 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1031, 'texto.sort()', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1031 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1031, 'texto.map()', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1031 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1032, 'Math.floor(n / 10)', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1032 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1032, 'n % 10', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1032 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1032, 'n * 10', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1032 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1032, 'n / 10', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1032 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1033, 'Set', 1, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1033 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1033, 'Math', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1033 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1033, 'String', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1033 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1033, 'Boolean', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1033 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1034, '(a, b) => b - a', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1034 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1034, '(a, b) => a - b', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1034 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1034, '(a, b) => a === b', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1034 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1034, '(a, b) => a + b', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1034 AND ordem = 4);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1035, '||', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1035 AND ordem = 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1035, '&&', 1, 2 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1035 AND ordem = 2);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1035, '!', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1035 AND ordem = 3);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) SELECT 1035, '=', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM alternativas WHERE atividade_id = 1035 AND ordem = 4);


-- Higienização da trilha JavaScript: remove apenas questões HTML/CSS
-- que foram inseridas incorretamente com os IDs 1019 a 1026.
DELETE FROM casos_teste WHERE atividade_id BETWEEN 1019 AND 1026;
DELETE FROM alternativas WHERE atividade_id BETWEEN 1019 AND 1026;
DELETE FROM atividades WHERE id BETWEEN 1019 AND 1026;
