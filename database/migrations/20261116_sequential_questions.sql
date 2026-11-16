-- QUORUM: modalidad secuencial. Ejecutar una sola vez, después de respaldo.
-- La modalidad tradicional continúa siendo el valor predeterminado.
ALTER TABLE elections
    ADD COLUMN question_flow ENUM('traditional','sequential') NOT NULL DEFAULT 'traditional';

ALTER TABLE questions
    ADD COLUMN voting_status ENUM('pending','open','closed') NOT NULL DEFAULT 'pending',
    ADD COLUMN opened_at DATETIME NULL,
    ADD COLUMN closed_at DATETIME NULL;

-- Registro de participación separado de las papeletas y alternativas votadas.
-- La clave única impide votar dos veces la misma pregunta.
CREATE TABLE question_participations (
    question_id BIGINT UNSIGNED NOT NULL,
    census_member_id BIGINT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (question_id, census_member_id),
    CONSTRAINT fk_qp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_qp_member FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
