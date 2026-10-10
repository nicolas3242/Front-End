-- =====================================================================
-- DevFox — Migração do schema v3 para v4
-- =====================================================================
-- Use este arquivo se você JÁ TEM o banco na versão 3 e não quer perder os
-- dados. Se for criar do zero, use `banco.sql`, que já vem na v4.
--
-- ⚠ FAÇA BACKUP ANTES (phpMyAdmin → Exportar, ou mysqldump).
-- ⚠ RODE UMA VEZ SÓ. O script não é idempotente.
--
-- O que muda na v4: uma tabela só, `dicas_vistas`.
--
-- Ela existe por causa da conquista "Por Conta Própria" — acertar 5 desafios
-- sem usar dica. Sem registrar quem pediu ajuda, não há como saber quem não
-- pediu.
--
-- O registro é feito pelo SERVIDOR (api/dica.php), no momento em que o texto
-- da dica é entregue ao navegador, e não por um campo enviado junto com a
-- resposta do aluno. A diferença importa: um campo vindo do navegador seria
-- editável no DevTools, e a conquista viraria enfeite. É a mesma regra que
-- vale para o XP em todo o resto do sistema.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;


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


-- =====================================================================
-- Conferência (rode depois da migração):
--
--   SHOW TABLES LIKE 'dicas_vistas';          -- deve retornar 1 linha
--   SELECT COUNT(*) FROM conquistas;          -- 6 conquistas no catálogo
--
-- Alunos que já concluíram atividades ANTES desta migração contam como "não
-- usou dica" nelas, porque não há registro em contrário. É o comportamento
-- desejado: ninguém perde conquista por causa de uma mudança de schema.
-- =====================================================================
