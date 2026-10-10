-- =====================================================================
-- DevFox — Migração do schema v4 para v5
-- =====================================================================
-- Use este arquivo se você JÁ TEM o banco na versão 4 e não quer perder os
-- dados. Se for criar do zero, use `banco.sql`, que já vem na v5.
--
-- ⚠ FAÇA BACKUP ANTES (phpMyAdmin → Exportar, ou mysqldump).
-- ⚠ RODE UMA VEZ SÓ. O script não é idempotente.
--
-- O que muda na v5: a Loja.
--   - itens_loja   catálogo de itens cosméticos (moldura do avatar, título)
--   - compras      quem comprou o quê, e por quanto
--   - usuarios     ganha moldura_id e titulo_id (o item equipado)
--
-- Não existe coluna de moedas: o saldo é derivado do XP menos o que já foi
-- gasto (ver includes/loja.php). Por isso esta migração não precisa
-- "calcular moedas iniciais" — quem já tem XP já tem saldo.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;


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


ALTER TABLE usuarios
    ADD COLUMN moldura_id INT NULL AFTER foto_avatar,
    ADD COLUMN titulo_id  INT NULL AFTER moldura_id,
    ADD CONSTRAINT fk_usuarios_moldura FOREIGN KEY (moldura_id)
        REFERENCES itens_loja(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_usuarios_titulo FOREIGN KEY (titulo_id)
        REFERENCES itens_loja(id) ON DELETE SET NULL;


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


-- =====================================================================
-- Conferência (rode depois da migração):
--
--   SELECT COUNT(*) FROM itens_loja;                  -- 9 itens
--   SHOW COLUMNS FROM usuarios LIKE '%_id';           -- moldura_id e titulo_id
-- =====================================================================
