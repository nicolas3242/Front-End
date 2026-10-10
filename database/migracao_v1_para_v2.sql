-- =====================================================================
-- DevFox — Migração do banco v1 para v2
-- =====================================================================
-- Use este arquivo SE VOCÊ JÁ TEM o banco `devfox` antigo com dados que
-- não quer perder (usuários cadastrados, trilhas criadas, submissões).
-- Se for criar o banco do zero, use `banco.sql` — é mais simples.
--
-- ⚠ FAÇA BACKUP ANTES. No phpMyAdmin: aba "Exportar" → Executar.
--   Ou pelo terminal:
--       mysqldump -u root devfox > backup_devfox.sql
--
-- ⚠ ESTE SCRIPT NÃO É IDEMPOTENTE: rode UMA VEZ SÓ. Rodar duas vezes dá
--   erro (as colunas já vão existir). Se algo falhar no meio, restaure o
--   backup e comece de novo, em vez de tentar continuar do ponto do erro.
--
-- O que esta migração faz, em uma frase: separa "tentativas" de "conclusões"
-- para acabar com o farm de XP, dá ordem às aulas, e cria as tabelas de
-- casos de teste, alternativas de quiz e conquistas.
-- =====================================================================

-- Declara que o conteúdo deste arquivo está em UTF-8. Sem isso, um cliente
-- configurado em latin1 grava os acentos duplamente codificados ("LÃ³gica").
SET NAMES utf8mb4;

USE devfox;

SET FOREIGN_KEY_CHECKS = 0;


-- ---------------------------------------------------------------------
-- 1. usuarios
-- ---------------------------------------------------------------------
-- `ultimo_dia_ativo` é o que faltava para a ofensiva funcionar de verdade.
-- `nivel` é removido: passa a ser calculado a partir do XP no PHP, para não
-- existirem duas fontes da mesma verdade.
-- ---------------------------------------------------------------------
ALTER TABLE usuarios
    MODIFY email          VARCHAR(150) NOT NULL,
    MODIFY tipo           ENUM('aluno','professor') NOT NULL DEFAULT 'aluno',
    MODIFY xp             INT NOT NULL DEFAULT 0,
    MODIFY sequencia_dias INT NOT NULL DEFAULT 0,
    MODIFY foto_avatar    VARCHAR(255) NOT NULL DEFAULT 'default_fox.png',
    MODIFY criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN ultimo_dia_ativo DATE NULL AFTER sequencia_dias,
    ADD COLUMN atualizado_em TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE usuarios DROP COLUMN nivel;
ALTER TABLE usuarios ADD KEY idx_usuarios_xp (xp);


-- ---------------------------------------------------------------------
-- 2. trilhas
-- ---------------------------------------------------------------------
-- Os valores do ENUM passam a ser códigos em minúsculo e sem acento.
--
-- A conversão passa por VARCHAR no meio do caminho, e não por um ENUM que
-- aceite os dois formatos ao mesmo tempo: a collation utf8mb4_unicode_ci é
-- case-insensitive, então o MySQL considera 'Iniciante' e 'iniciante' valores
-- DUPLICADOS dentro do mesmo ENUM e recusa a alteração com o erro 1291.
-- ---------------------------------------------------------------------
ALTER TABLE trilhas
    ADD COLUMN icone     VARCHAR(50) NULL AFTER nivel_dificuldade,
    ADD COLUMN publicada TINYINT(1)  NOT NULL DEFAULT 1 AFTER ordem,
    ADD COLUMN criado_em TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY ordem INT NOT NULL DEFAULT 1;

ALTER TABLE trilhas MODIFY nivel_dificuldade VARCHAR(20) NOT NULL DEFAULT 'iniciante';

-- A comparação por prefixo evita depender de acento e de maiúscula/minúscula.
UPDATE trilhas SET nivel_dificuldade = CASE
    WHEN nivel_dificuldade LIKE 'Interm%' THEN 'intermediario'
    WHEN nivel_dificuldade LIKE 'Avan%'   THEN 'avancado'
    ELSE 'iniciante'
END;

ALTER TABLE trilhas MODIFY nivel_dificuldade
    ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante';

ALTER TABLE trilhas ADD KEY idx_trilhas_ordem (ordem);


-- ---------------------------------------------------------------------
-- 3. modulos
-- ---------------------------------------------------------------------
-- Sem `ordem` não dá para saber qual aula vem antes de qual, nem calcular
-- "Módulo 4 de 7". A ordem inicial usa o id, que preserva a ordem de criação;
-- o professor pode reordenar depois.
-- ---------------------------------------------------------------------
ALTER TABLE modulos
    ADD COLUMN descricao TEXT      NULL AFTER titulo,
    ADD COLUMN ordem     INT       NOT NULL DEFAULT 1,
    ADD COLUMN criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY trilha_id INT NOT NULL;

UPDATE modulos SET ordem = id;

ALTER TABLE modulos ADD KEY idx_modulos_trilha (trilha_id, ordem);


-- ---------------------------------------------------------------------
-- 4. atividades — novas colunas
-- ---------------------------------------------------------------------
ALTER TABLE atividades
    ADD COLUMN dificuldade     ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil' AFTER tipo,
    ADD COLUMN dica            TEXT      NULL AFTER codigo_inicial,
    ADD COLUMN solucao_exemplo TEXT      NULL AFTER dica,
    ADD COLUMN ordem           INT       NOT NULL DEFAULT 1,
    ADD COLUMN criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY modulo_id     INT NOT NULL,
    MODIFY xp_recompensa INT NOT NULL DEFAULT 150;

UPDATE atividades SET ordem = id;

ALTER TABLE atividades ADD KEY idx_atividades_modulo (modulo_id, ordem);


-- ---------------------------------------------------------------------
-- 5. Tabelas novas
-- ---------------------------------------------------------------------
CREATE TABLE casos_teste (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    atividade_id   INT          NOT NULL,
    descricao      VARCHAR(150) NULL,
    entrada        TEXT         NULL,
    saida_esperada TEXT         NOT NULL,
    visivel        TINYINT(1)   NOT NULL DEFAULT 1,
    ordem          INT          NOT NULL DEFAULT 1,
    CONSTRAINT fk_casos_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    KEY idx_casos_atividade (atividade_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Esta é a tabela que impede o farm de XP (ver comentário completo em banco.sql).
CREATE TABLE atividades_concluidas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT       NOT NULL,
    atividade_id INT       NOT NULL,
    xp_concedido INT       NOT NULL DEFAULT 0,
    tentativas   INT       NOT NULL DEFAULT 1,
    concluido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_concluidas_aluno FOREIGN KEY (aluno_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_concluidas_atividade FOREIGN KEY (atividade_id)
        REFERENCES atividades(id) ON DELETE CASCADE,
    UNIQUE KEY uq_concluidas_aluno_atividade (aluno_id, atividade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE conquistas (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    codigo    VARCHAR(50)  NOT NULL,
    titulo    VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    icone     VARCHAR(50)  NULL,
    raridade  ENUM('bronze','prata','ouro','especial') NOT NULL DEFAULT 'bronze',
    UNIQUE KEY uq_conquistas_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- 6. Migrar `saida_esperada` das atividades para `casos_teste`
-- ---------------------------------------------------------------------
-- Cada atividade que tinha uma resposta esperada vira um caso de teste
-- visível. Depois disso a coluna antiga é removida — a resposta esperada
-- passa a viver só em `casos_teste` (ou em `alternativas`, no caso de quiz).
-- ---------------------------------------------------------------------
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem)
SELECT id, 'Caso migrado da versão anterior', NULL, saida_esperada, 1, 1
FROM atividades
WHERE saida_esperada IS NOT NULL AND saida_esperada <> '';

ALTER TABLE atividades DROP COLUMN saida_esperada;


-- ---------------------------------------------------------------------
-- 7. submissoes_alunos → submissoes
-- ---------------------------------------------------------------------
-- A tabela passa a guardar apenas o histórico de tentativas. Ela não concede
-- mais XP: quem controla isso é `atividades_concluidas`.
-- ---------------------------------------------------------------------
RENAME TABLE submissoes_alunos TO submissoes;

ALTER TABLE submissoes
    CHANGE codigo_enviado resposta TEXT NULL,
    ADD COLUMN correta TINYINT(1) NOT NULL DEFAULT 0 AFTER resposta,
    MODIFY aluno_id     INT NOT NULL,
    MODIFY atividade_id INT NOT NULL,
    MODIFY enviado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE submissoes SET correta = 1 WHERE status = 'correto';

ALTER TABLE submissoes DROP COLUMN status;
ALTER TABLE submissoes ADD KEY idx_submissoes_aluno (aluno_id, atividade_id);


-- ---------------------------------------------------------------------
-- 8. Reconstruir as conclusões a partir do histórico
-- ---------------------------------------------------------------------
-- Para cada par (aluno, atividade) que já tem ao menos um acerto, cria UMA
-- conclusão, com a data do primeiro acerto e a contagem de tentativas.
-- Várias submissões corretas da mesma atividade viram uma única conclusão —
-- é exatamente aqui que o XP farmado deixa de ser contado.
-- ---------------------------------------------------------------------
INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido, tentativas, concluido_em)
SELECT s.aluno_id,
       s.atividade_id,
       a.xp_recompensa,
       COUNT(*),
       MIN(s.enviado_em)
FROM submissoes s
JOIN atividades a ON a.id = s.atividade_id
WHERE s.correta = 1
GROUP BY s.aluno_id, s.atividade_id;


-- ---------------------------------------------------------------------
-- 9. Recalcular o XP de todos os alunos
-- ---------------------------------------------------------------------
-- ⚠ Este passo APAGA o XP inflado por reenvio da mesma atividade (o farm que
--   existia na v1) e recoloca cada aluno no XP que ele realmente conquistou.
--   Se por algum motivo você quiser preservar o XP atual como está, comente
--   este UPDATE — mas aí o ranking continua contando XP que não foi merecido.
-- ---------------------------------------------------------------------
UPDATE usuarios u
SET u.xp = COALESCE((
        SELECT SUM(ac.xp_concedido)
        FROM atividades_concluidas ac
        WHERE ac.aluno_id = u.id
    ), 0);


-- ---------------------------------------------------------------------
-- 10. Catálogo de conquistas
-- ---------------------------------------------------------------------
INSERT IGNORE INTO conquistas (codigo, titulo, descricao, icone, raridade) VALUES
('primeira_aula',    'Código Iniciante',  'Complete sua primeira aula.',             'estrela', 'bronze'),
('primeiro_desafio', 'Primeiro Desafio',  'Conclua seu primeiro desafio de código.', 'trofeu',  'prata'),
('sequencia_7',      'Sequência de 7',    'Mantenha 7 dias seguidos de estudo.',     'fogo',    'especial'),
('explorador',       'Explorador',        'Inicie 3 trilhas diferentes.',            'mapa',    'ouro'),
('trilha_completa',  'Trilha Concluída',  'Conclua uma trilha inteira.',             'medalha', 'ouro'),
('sem_dica',         'Por Conta Própria', 'Acerte 5 desafios sem usar dica.',        'lampada', 'prata');


SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Conferência rápida (rode depois da migração):
--
--   SHOW TABLES;                       -- devem aparecer 11 tabelas
--   SELECT id, nome, xp, sequencia_dias, ultimo_dia_ativo FROM usuarios;
--   SELECT * FROM atividades_concluidas;
--
-- Se `atividades_concluidas` tiver menos linhas do que `submissoes` com
-- correta = 1, está certo: é o farm de XP que foi eliminado.
-- =====================================================================
