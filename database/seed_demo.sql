USE inaudis_vote;

-- Cliente de demostración
INSERT INTO organizations (name,tax_id,slug,status)
VALUES ('Comunidad Los Alerces','76.123.456-7','comunidad-los-alerces','active');
SET @org_id = LAST_INSERT_ID();

-- Todos los accesos usan temporalmente Demo2026!
SET @password_hash = '$2y$12$lPGjdpXrDFvgaJeS98/geuk8MwDeayRLCdhW3PQBwmND37E2JDCsO';

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (NULL,'Administrador INAUDIS','admin@inaudis.local',@password_hash,'admin','active');
SET @admin_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (@org_id,'Encargada Elección Directorio','encargada.directorio@inaudis.local',@password_hash,'organizer','active');
SET @organizer_1_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (@org_id,'Encargado Reforma Estatutos','encargado.estatutos@inaudis.local',@password_hash,'organizer','active');
SET @organizer_2_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (@org_id,'Ministra de Fe Directorio','ministra.directorio@inaudis.local',@password_hash,'witness','active');
SET @witness_1_id = LAST_INSERT_ID();

INSERT INTO users (organization_id,name,email,password_hash,role,status)
VALUES (@org_id,'Ministro de Fe Estatutos','ministro.estatutos@inaudis.local',@password_hash,'witness','active');
SET @witness_2_id = LAST_INSERT_ID();

-- Votación 1: elección de directorio
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by)
VALUES (@org_id,'Elección de Directorio 2026','Elección de la nueva directiva de la Comunidad Los Alerces.','secret','open',DATE_SUB(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 7 DAY),0,@organizer_1_id);
SET @election_1_id = LAST_INSERT_ID();

INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order)
VALUES (@election_1_id,'¿Qué lista debe asumir la dirección de la comunidad?','single',1,1,1);
SET @question_1_id = LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES
(@question_1_id,'Lista Renovación',1),
(@question_1_id,'Lista Comunidad',2),
(@question_1_id,'Voto en blanco',3);

INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES
(@election_1_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible'),
(@election_1_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible'),
(@election_1_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible');

INSERT INTO election_witnesses (election_id,user_id,assigned_by)
VALUES (@election_1_id,@witness_1_id,@admin_id);
INSERT INTO election_managers (election_id,user_id,assigned_by)
VALUES (@election_1_id,@organizer_1_id,@admin_id);
INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by)
VALUES (@election_1_id,@question_1_id,0,@organizer_1_id);

-- Votación 2: reforma de estatutos
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by)
VALUES (@org_id,'Reforma de Estatutos 2026','Consulta para aprobar o rechazar la reforma de estatutos de la comunidad.','secret','open',DATE_SUB(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 10 DAY),0,@organizer_2_id);
SET @election_2_id = LAST_INSERT_ID();

INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order)
VALUES (@election_2_id,'¿Aprueba la propuesta de reforma de estatutos?','single',1,1,1);
SET @question_2_id = LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES
(@question_2_id,'Apruebo',1),
(@question_2_id,'Rechazo',2),
(@question_2_id,'Voto en blanco',3);

INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES
(@election_2_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible'),
(@election_2_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible'),
(@election_2_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible');

INSERT INTO election_witnesses (election_id,user_id,assigned_by)
VALUES (@election_2_id,@witness_2_id,@admin_id);
INSERT INTO election_managers (election_id,user_id,assigned_by)
VALUES (@election_2_id,@organizer_2_id,@admin_id);
INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by)
VALUES (@election_2_id,@question_2_id,0,@organizer_2_id);

-- Credenciales locales (todos usan Demo2026!):
-- admin@inaudis.local
-- encargada.directorio@inaudis.local
-- encargado.estatutos@inaudis.local
-- ministra.directorio@inaudis.local
-- ministro.estatutos@inaudis.local
