-- =====================================================================
-- DevFox — Migração do schema v2 para v3
-- =====================================================================
-- Use este arquivo se você JÁ TEM o banco na versão 2 e não quer perder os
-- dados. Se for criar do zero, use `banco.sql`, que já vem na v3.
--
-- ⚠ FAÇA BACKUP ANTES (phpMyAdmin → Exportar, ou mysqldump).
-- ⚠ RODE UMA VEZ SÓ. O script não é idempotente.
--
-- O que muda na v3:
--   1. O papel "professor" vira "admin". O DevFox é uma plataforma de
--      conteúdo (como Duolingo e Alura), não um sistema de sala de aula:
--      quem cadastra o conteúdo é a equipe do produto, não um professor que
--      se cadastra na plataforma.
--   2. Módulos passam a ter tipo ('aula' ou 'prova') e nota mínima.
--   3. Questões de prova apontam para o módulo que elas avaliam, para o
--      sistema conseguir dizer ao aluno reprovado O QUE revisar.
--   4. Nova tabela de tentativas de prova.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;


-- ---------------------------------------------------------------------
-- 1. usuarios: o papel "professor" passa a se chamar "admin"
-- ---------------------------------------------------------------------
-- A conversão passa por um ENUM intermediário que aceita os dois valores,
-- porque não dá para renomear um valor de ENUM com os dados já gravados.
-- ---------------------------------------------------------------------
ALTER TABLE usuarios MODIFY tipo ENUM('aluno','professor','admin') NOT NULL DEFAULT 'aluno';

UPDATE usuarios SET tipo = 'admin' WHERE tipo = 'professor';

ALTER TABLE usuarios MODIFY tipo ENUM('aluno','admin') NOT NULL DEFAULT 'aluno';


-- ---------------------------------------------------------------------
-- 2. modulos: tipo e nota mínima
-- ---------------------------------------------------------------------
-- Um módulo do tipo 'prova' agrupa as questões que avaliam os módulos
-- anteriores da trilha. `nota_minima` é o percentual de acerto necessário
-- para passar (só faz sentido em módulos do tipo 'prova').
-- ---------------------------------------------------------------------
ALTER TABLE modulos
    ADD COLUMN tipo        ENUM('aula','prova') NOT NULL DEFAULT 'aula' AFTER titulo,
    ADD COLUMN nota_minima TINYINT UNSIGNED NOT NULL DEFAULT 70 AFTER tipo;


-- ---------------------------------------------------------------------
-- 3. atividades: a qual módulo a questão se refere
-- ---------------------------------------------------------------------
-- Preenchido apenas nas questões de prova. É o que permite dizer ao aluno
-- reprovado exatamente quais módulos revisar, em vez de mandá-lo refazer
-- tudo — decisão registrada no AI_HANDOFF.md.
--
-- ON DELETE SET NULL: se o módulo avaliado for apagado, a questão continua
-- existindo, apenas sem a referência.
-- ---------------------------------------------------------------------
ALTER TABLE atividades
    ADD COLUMN modulo_referencia_id INT NULL AFTER modulo_id,
    ADD CONSTRAINT fk_atividades_modulo_referencia
        FOREIGN KEY (modulo_referencia_id) REFERENCES modulos(id) ON DELETE SET NULL;


-- ---------------------------------------------------------------------
-- 4. tentativas_prova: histórico das avaliações
-- ---------------------------------------------------------------------
-- Cada tentativa é registrada, aprovada ou não. Diferente de
-- `atividades_concluidas`, aqui PODE haver várias linhas por (aluno, módulo):
-- é justamente o histórico de tentativas que mostra a evolução do aluno.
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


-- =====================================================================
-- Conferência (rode depois da migração):
--
--   SELECT tipo, COUNT(*) FROM usuarios GROUP BY tipo;   -- nenhum 'professor'
--   SHOW COLUMNS FROM modulos LIKE 'tipo';
--   SHOW TABLES LIKE 'tentativas_prova';
--
-- Nenhuma prova é criada automaticamente: cadastre-as pelo painel de
-- Administração ou insira direto, com modulos.tipo = 'prova'.
-- =====================================================================
