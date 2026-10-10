-- =============================================================================
-- database/dados_demo.sql — alunos e progresso de DEMONSTRAÇÃO
-- -----------------------------------------------------------------------------
-- PARA QUE SERVE: encher o painel, os relatórios e o ranking para a
-- apresentação. Com um aluno só, o gráfico fica reto, a rosca fica de uma cor
-- e o funil fica vazio — o sistema funciona, mas não mostra o que sabe fazer.
--
-- ⚠ ISTO É DADO DE DEMONSTRAÇÃO, não uso real. Não apresente estes números
--   como se fossem alunos de verdade: são 6 contas criadas por este arquivo,
--   com respostas inseridas direto no banco. Se a banca perguntar de onde vêm
--   os dados, a resposta honesta é "são dados de teste que eu gerei".
--
-- COMO RODAR (uma vez, com o banco devfox já importado):
--   "C:\xampp\mysql\bin\mysql.exe" -u root devfox < "C:\xampp\htdocs\DevFox\database\dados_demo.sql"
--
-- COMO DESFAZER: a última linha deste arquivo (comentada) apaga tudo que ele
--   criou. Ou simplesmente reimporte o banco.sql, que recria o banco do zero.
--
-- Senha de todas as contas: aluno123
-- =============================================================================

-- 1. Alunos ---------------------------------------------------------------
-- O hash é o mesmo de `aluno@devfox.local` (bcrypt de "aluno123"): senha em
-- texto nunca entra no banco, nem em dado de teste.
INSERT INTO usuarios (nome, email, senha, tipo, xp, sequencia_dias, melhor_sequencia, ultimo_dia_ativo, criado_em) VALUES
 ('Ana Costa',    'ana@demo.devfox',   '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno', 1200, 3, 5, CURDATE(),                     NOW() - INTERVAL 21 DAY),
 ('Bruno Lima',   'bruno@demo.devfox', '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno', 2600, 7, 9, CURDATE(),                     NOW() - INTERVAL 17 DAY),
 ('Carla Souza',  'carla@demo.devfox', '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno',  300, 1, 2, CURDATE() - INTERVAL 2 DAY,    NOW() - INTERVAL 9 DAY),
 ('Diego Alves',  'diego@demo.devfox', '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno', 3200, 4, 6, CURDATE(),                     NOW() - INTERVAL 5 DAY),
 ('Eva Martins',  'eva@demo.devfox',   '$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno',  150, 1, 1, CURDATE() - INTERVAL 1 DAY,    NOW() - INTERVAL 3 DAY),
 ('Felipe Rocha', 'felipe@demo.devfox','$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno',   50, 1, 1, CURDATE(),                     NOW() - INTERVAL 1 DAY);

-- 2. Respostas enviadas ---------------------------------------------------
-- Espalhadas nos últimos 7 dias, com uma parte errada de propósito: sem erro
-- nenhum, o relatório "atividades mais difíceis" fica vazio e a taxa de acerto
-- dá 100%, o que não acontece na vida real.
INSERT INTO submissoes (aluno_id, atividade_id, resposta, correta, enviado_em)
SELECT u.id, a.id, 'resposta de demonstração',
       IF((u.id + a.id) % 5 = 0, 0, 1),
       NOW() - INTERVAL ((u.id + a.id) % 7) DAY
  FROM usuarios u
  JOIN atividades a ON a.id <= 12
 WHERE u.email LIKE '%@demo.devfox';

-- 3. Atividades concluídas ------------------------------------------------
-- Cada aluno conclui uma quantidade diferente, para o funil e o mapa de
-- progresso terem degraus em vez de uma coluna só.
INSERT IGNORE INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido, concluido_em)
SELECT u.id, a.id, a.xp_recompensa, NOW() - INTERVAL ((u.id + a.id) % 6) DAY
  FROM usuarios u
  JOIN atividades a ON a.id <= (3 + (u.id % 7))
 WHERE u.email LIKE '%@demo.devfox';

-- 4. Uma mensagem no Suporte ---------------------------------------------
-- Para o cartão de "não lidas" não ficar zerado na hora da demonstração.
INSERT INTO mensagens_suporte (usuario_id, nome, email, assunto, mensagem)
SELECT u.id, u.nome, u.email, 'conteudo',
       'O enunciado da atividade de operadores está confuso para mim.'
  FROM usuarios u WHERE u.email = 'carla@demo.devfox';

SELECT CONCAT('Pronto: ', COUNT(*), ' alunos de demonstração no banco.') AS resultado
  FROM usuarios WHERE email LIKE '%@demo.devfox';

-- =============================================================================
-- PARA APAGAR TUDO O QUE ESTE ARQUIVO CRIOU, rode a linha abaixo (sem o "--").
-- As respostas, conclusões e mensagens somem junto, porque as chaves
-- estrangeiras dessas tabelas são ON DELETE CASCADE.
--
-- DELETE FROM usuarios WHERE email LIKE '%@demo.devfox';
-- =============================================================================
