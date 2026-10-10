-- =====================================================================
-- DevFox — Migração do schema v8 para v9
-- =====================================================================
-- Use se você JÁ TEM o banco na v8 e não quer perder dados. Para criar do
-- zero, use `banco.sql`, que já vem na v9.  ⚠ Backup antes. ⚠ Rode uma vez.
--
-- O que muda na v9 (segurança do login):
--   - tabela tentativas_login: uma linha por senha errada, para bloquear o
--     e-mail depois de 5 erros em 15 minutos (limite de força bruta).
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;

CREATE TABLE IF NOT EXISTS tentativas_login (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150)  NOT NULL,
    ip         VARCHAR(45)   NOT NULL,
    criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_tentativas_email (email, criado_em),
    KEY idx_tentativas_ip    (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
