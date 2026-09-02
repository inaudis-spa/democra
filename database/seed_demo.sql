USE inaudis_vote;

INSERT INTO organizations (name,tax_id,slug,status)
VALUES ('Sindicato Demo INAUDIS','76.000.000-0','sindicato-demo','active');
SET @org_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (NULL,'Administrador INAUDIS','admin@inaudis.local','$2y$12$lPGjdpXrDFvgaJeS98/geuk8MwDeayRLCdhW3PQBwmND37E2JDCsO','admin','active');
SET @admin_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (@org_id,'Organizador Demo','organizador@inaudis.local','$2y$12$lPGjdpXrDFvgaJeS98/geuk8MwDeayRLCdhW3PQBwmND37E2JDCsO','organizer','active');

INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by)
VALUES (@org_id,'Elección Directorio Demo 2026','Proceso de demostración de punta a punta para INAUDIS Vote.','secret','open',DATE_SUB(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 7 DAY),0,@admin_id);
SET @election_id = LAST_INSERT_ID();

INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order)
VALUES (@election_id,'¿Quién debe asumir la presidencia del directorio?','single',1,1,1);
SET @question_id = LAST_INSERT_ID();

INSERT INTO options (question_id,label,sort_order) VALUES
(@question_id,'Lista A',1),(@question_id,'Lista B',2),(@question_id,'Blanco',3);

INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES
(@election_id,'11.111.111-1','Ana Pérez','ana.demo@inaudis.local','+56911111111',1,'Socios','eligible'),
(@election_id,'22.222.222-2','Carlos Soto','carlos.demo@inaudis.local','+56922222222',1,'Socios','eligible'),
(@election_id,'33.333.333-3','Marcela Díaz','marcela.demo@inaudis.local','+56933333333',1,'Socios','eligible');

-- Credenciales demo (solo ambiente local):
-- admin@inaudis.local / Demo2026!
-- organizador@inaudis.local / Demo2026!
-- Cambiar/eliminar estas credenciales antes de cualquier despliegue real.
