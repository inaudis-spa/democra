-- Vincula identidad y papeleta exclusivamente en procesos de voto público.
-- Los votos secretos existentes y futuros conservan census_member_id en NULL.
ALTER TABLE ballots
    ADD COLUMN census_member_id BIGINT UNSIGNED NULL
        COMMENT 'Solo se informa en votaciones públicas/identificadas'
        AFTER election_id,
    ADD KEY idx_ballots_member (census_member_id),
    ADD CONSTRAINT fk_ballots_member
        FOREIGN KEY (census_member_id) REFERENCES census_members(id)
        ON DELETE SET NULL;
