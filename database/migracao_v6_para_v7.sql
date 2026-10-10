-- =====================================================================
-- DevFox — Migração do schema v6 para v7
-- =====================================================================
-- Use se você JÁ TEM o banco na v6 e não quer perder dados. Para criar do
-- zero, use `banco.sql`, que já vem na v7.
-- ⚠ FAÇA BACKUP ANTES. ⚠ RODE UMA VEZ SÓ.
--
-- O que muda na v7 (só tabelas novas; nada existente é alterado):
--   - lembrar_tokens      "Lembrar-me" no login
--   - redefinicoes_senha  "Esqueci minha senha"
--   - mensagens_suporte   "Fale com o suporte"
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;

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


-- Conferência:  SHOW TABLES LIKE '%suporte%';   -- 1 linha
