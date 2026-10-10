-- =====================================================================
-- DevFox — Migração do schema v9 para v10
-- =====================================================================
-- Use se você JÁ TEM o banco na v9 e não quer perder dados. Para criar do
-- zero, use `banco.sql`, que já vem na v10.  ⚠ Backup antes. ⚠ Rode uma vez.
--
-- O que muda na v10 (área do administrador):
--   - usuarios.ativo: conta desativada não entra (nada é apagado);
--   - trilhas.arquivada: trilha arquivada some das listas, o progresso fica;
--   - conquistas.ativa / regra_tipo / regra_valor / regra_alvo: o admin cria
--     conquistas escolhendo um tipo de regra de um catálogo fechado;
--   - tabela admin_registro: o que cada admin fez, e quando.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;

ALTER TABLE usuarios
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER tipo;

ALTER TABLE trilhas
    ADD COLUMN arquivada TINYINT(1) NOT NULL DEFAULT 0 AFTER destaque;

ALTER TABLE conquistas
    ADD COLUMN ativa       TINYINT(1)  NOT NULL DEFAULT 1      AFTER raridade,
    ADD COLUMN regra_tipo  VARCHAR(20) NOT NULL DEFAULT 'fixa' AFTER ativa,
    ADD COLUMN regra_valor INT         NULL                    AFTER regra_tipo,
    ADD COLUMN regra_alvo  INT         NULL                    AFTER regra_valor;

CREATE TABLE IF NOT EXISTS admin_registro (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT           NULL,
    acao       VARCHAR(60)   NOT NULL,
    alvo       VARCHAR(160)  NULL,
    criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_registro_admin FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_registro_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
