-- =====================================================================
-- DevFox — Migração do schema v7 para v8
-- =====================================================================
-- Use se você JÁ TEM o banco na v7 e não quer perder dados. Para criar do
-- zero, use `banco.sql`, que já vem na v8.  ⚠ Backup antes. ⚠ Rode uma vez.
--
-- O que muda na v8 (tela de Perfil):
--   - usuarios.melhor_sequencia: o recorde de dias seguidos ("Melhor: 12 dias").
--     Começa igual à sequência atual — o histórico antigo não foi guardado.
--   - usuarios.foto_avatar passa a guardar o CÓDIGO do avatar escolhido
--     ('padrao', 'retrato'...), conferido contra o catálogo em PHP.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;

ALTER TABLE usuarios
    ADD COLUMN melhor_sequencia INT NOT NULL DEFAULT 0 AFTER sequencia_dias,
    MODIFY foto_avatar VARCHAR(255) NOT NULL DEFAULT 'padrao';

UPDATE usuarios SET melhor_sequencia = sequencia_dias;
UPDATE usuarios SET foto_avatar = 'padrao' WHERE foto_avatar = 'default_fox.png';
