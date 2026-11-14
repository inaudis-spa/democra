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

-- Votación 3: consulta extraordinaria en curso
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,ballot_locked_at,census_locked_at,created_by)
VALUES (@org_id,'Presupuesto extraordinario de áreas comunes','Consulta para aprobar mejoras de iluminación, accesos y jardines.','secret','open',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(NOW(),INTERVAL 5 DAY),0,NOW(),NOW(),@organizer_1_id);
SET @election_3_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_3_id,'¿Aprueba el presupuesto extraordinario de $18.500.000?','single',1,1,1);SET @question_3_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_3_id,'Apruebo',1),(@question_3_id,'Rechazo',2),(@question_3_id,'Me abstengo',3);SET @option_3_yes=LAST_INSERT_ID();SET @option_3_no=LAST_INSERT_ID()+1;
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status,has_voted) VALUES
(@election_3_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Torre A','eligible',1),(@election_3_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Torre A','eligible',1),(@election_3_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Torre B','eligible',0),(@election_3_id,'44.444.444-4','Rodrigo Silva','rodrigo.silva@inaudis.local','+56944444444',1,'Torre B','eligible',0),(@election_3_id,'55.555.555-5','Elena Rojas','elena.rojas@inaudis.local','+56955555555',1,'Torre C','eligible',0);
INSERT INTO ballots (election_id,ballot_token,weight) VALUES (@election_3_id,SHA2(CONCAT(UUID(),'h'),256),1);SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_3_id,@option_3_yes);
INSERT INTO ballots (election_id,ballot_token,weight) VALUES (@election_3_id,SHA2(CONCAT(UUID(),'i'),256),1);SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_3_id,@option_3_no);
INSERT INTO election_managers VALUES (@election_3_id,@organizer_1_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_3_id,@witness_1_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_3_id,@question_3_id,0,@organizer_1_id);

-- Votación 4: consulta de seguridad en curso
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,ballot_locked_at,census_locked_at,created_by)
VALUES (@org_id,'Plan de seguridad comunitaria','Define la implementación de cámaras y control de acceso nocturno.','secret','open',DATE_SUB(NOW(),INTERVAL 6 HOUR),DATE_ADD(NOW(),INTERVAL 3 DAY),0,NOW(),NOW(),@organizer_2_id);
SET @election_4_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_4_id,'¿Qué alternativa de seguridad prefiere?','single',1,1,1);SET @question_4_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_4_id,'Cámaras y conserjería nocturna',1),(@question_4_id,'Solo cámaras',2),(@question_4_id,'Mantener sistema actual',3);SET @option_4_a=LAST_INSERT_ID();
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status,has_voted) VALUES
(@election_4_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible',1),(@election_4_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible',0),(@election_4_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible',0),(@election_4_id,'44.444.444-4','Rodrigo Silva','rodrigo.silva@inaudis.local','+56944444444',1,'Propietarios','eligible',0);
INSERT INTO ballots (election_id,ballot_token,weight) VALUES (@election_4_id,SHA2(CONCAT(UUID(),'j'),256),1);SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_4_id,@option_4_a);
INSERT INTO election_managers VALUES (@election_4_id,@organizer_2_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_4_id,@witness_2_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_4_id,@question_4_id,0,@organizer_2_id);

-- Votación 5: programada
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by)
VALUES (@org_id,'Renovación del Comité de Administración','Elección de tres representantes para el período 2027–2029.','secret','scheduled',DATE_ADD(NOW(),INTERVAL 12 DAY),DATE_ADD(NOW(),INTERVAL 15 DAY),0,@organizer_1_id);
SET @election_5_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_5_id,'Seleccione hasta tres candidatos para el comité.','multiple',1,3,1);SET @question_5_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_5_id,'Paula Contreras',1),(@question_5_id,'Miguel Fuentes',2),(@question_5_id,'Teresa Valdés',3),(@question_5_id,'Jorge Araya',4),(@question_5_id,'Camila Núñez',5);
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES
(@election_5_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible'),(@election_5_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible'),(@election_5_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible'),(@election_5_id,'44.444.444-4','Rodrigo Silva','rodrigo.silva@inaudis.local','+56944444444',1,'Propietarios','eligible'),(@election_5_id,'55.555.555-5','Elena Rojas','elena.rojas@inaudis.local','+56955555555',1,'Propietarios','eligible');
INSERT INTO election_managers VALUES (@election_5_id,@organizer_1_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_5_id,@witness_1_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_5_id,NULL,0,@organizer_1_id);

-- Votación 6: borrador aún sin comenzar
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by)
VALUES (@org_id,'Reglamento de uso del quincho','Borrador para definir horarios, reservas y responsabilidades de uso.','secret','draft',DATE_ADD(NOW(),INTERVAL 25 DAY),DATE_ADD(NOW(),INTERVAL 28 DAY),0,@organizer_2_id);
SET @election_6_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_6_id,'¿Aprueba el nuevo reglamento de uso del quincho?','single',1,1,1);SET @question_6_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_6_id,'Sí, apruebo',1),(@question_6_id,'No, rechazo',2);
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES
(@election_6_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible'),(@election_6_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible'),(@election_6_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible');
INSERT INTO election_managers VALUES (@election_6_id,@organizer_2_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_6_id,@witness_2_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_6_id,NULL,0,@organizer_2_id);

-- Votación 7: terminada con votos y resultados
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,ballot_locked_at,census_locked_at,created_by)
VALUES (@org_id,'Aprobación del presupuesto anual 2026','Presupuesto operacional y plan de mantención anual.','secret','closed',DATE_SUB(NOW(),INTERVAL 40 DAY),DATE_SUB(NOW(),INTERVAL 37 DAY),0,DATE_SUB(NOW(),INTERVAL 40 DAY),DATE_SUB(NOW(),INTERVAL 40 DAY),@organizer_1_id);
SET @election_7_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_7_id,'¿Aprueba el presupuesto anual 2026?','single',1,1,1);SET @question_7_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_7_id,'Apruebo',1),(@question_7_id,'Rechazo',2),(@question_7_id,'Me abstengo',3);SET @option_7_yes=LAST_INSERT_ID();SET @option_7_no=LAST_INSERT_ID()+1;
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status,has_voted) VALUES
(@election_7_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible',1),(@election_7_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible',1),(@election_7_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible',1),(@election_7_id,'44.444.444-4','Rodrigo Silva','rodrigo.silva@inaudis.local','+56944444444',1,'Propietarios','eligible',1),(@election_7_id,'55.555.555-5','Elena Rojas','elena.rojas@inaudis.local','+56955555555',1,'Propietarios','eligible',0);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_7_id,SHA2(CONCAT(UUID(),'a'),256),1,DATE_SUB(NOW(),INTERVAL 38 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_7_id,@option_7_yes);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_7_id,SHA2(CONCAT(UUID(),'b'),256),1,DATE_SUB(NOW(),INTERVAL 38 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_7_id,@option_7_yes);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_7_id,SHA2(CONCAT(UUID(),'c'),256),1,DATE_SUB(NOW(),INTERVAL 38 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_7_id,@option_7_yes);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_7_id,SHA2(CONCAT(UUID(),'d'),256),1,DATE_SUB(NOW(),INTERVAL 38 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_7_id,@option_7_no);
INSERT INTO election_managers VALUES (@election_7_id,@organizer_1_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_7_id,@witness_1_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_7_id,@question_7_id,1,@organizer_1_id);INSERT INTO witness_checkpoints (election_id,user_id,stage,snapshot_json,sha256_hash) VALUES (@election_7_id,@witness_1_id,'results_attested',JSON_OBJECT('election_id',@election_7_id,'status','closed','voted',4),SHA2(CONCAT(@election_7_id,'results_attested'),256));

-- Votación 8: terminada con resultados
INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,ballot_locked_at,census_locked_at,created_by)
VALUES (@org_id,'Mantención de ascensores 2025','Selección de la propuesta de mantención para los ascensores.','secret','closed',DATE_SUB(NOW(),INTERVAL 90 DAY),DATE_SUB(NOW(),INTERVAL 87 DAY),0,DATE_SUB(NOW(),INTERVAL 90 DAY),DATE_SUB(NOW(),INTERVAL 90 DAY),@organizer_2_id);
SET @election_8_id=LAST_INSERT_ID();
INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (@election_8_id,'¿Qué propuesta de mantención prefiere?','single',1,1,1);SET @question_8_id=LAST_INSERT_ID();
INSERT INTO options (question_id,label,sort_order) VALUES (@question_8_id,'Propuesta Andes',1),(@question_8_id,'Propuesta Vertical',2),(@question_8_id,'Rechazar ambas propuestas',3);SET @option_8_a=LAST_INSERT_ID();SET @option_8_b=LAST_INSERT_ID()+1;
INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status,has_voted) VALUES
(@election_8_id,'11.111.111-1','Ana Pérez','ana.perez@inaudis.local','+56911111111',1,'Propietarios','eligible',1),(@election_8_id,'22.222.222-2','Carlos Soto','carlos.soto@inaudis.local','+56922222222',1,'Propietarios','eligible',1),(@election_8_id,'33.333.333-3','Marcela Díaz','marcela.diaz@inaudis.local','+56933333333',1,'Propietarios','eligible',1),(@election_8_id,'44.444.444-4','Rodrigo Silva','rodrigo.silva@inaudis.local','+56944444444',1,'Propietarios','eligible',0);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_8_id,SHA2(CONCAT(UUID(),'e'),256),1,DATE_SUB(NOW(),INTERVAL 88 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_8_id,@option_8_a);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_8_id,SHA2(CONCAT(UUID(),'f'),256),1,DATE_SUB(NOW(),INTERVAL 88 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_8_id,@option_8_b);
INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (@election_8_id,SHA2(CONCAT(UUID(),'g'),256),1,DATE_SUB(NOW(),INTERVAL 88 DAY));SET @ballot=LAST_INSERT_ID();INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (@ballot,@question_8_id,@option_8_a);
INSERT INTO election_managers VALUES (@election_8_id,@organizer_2_id,@admin_id,NOW());INSERT INTO election_witnesses VALUES (@election_8_id,@witness_2_id,@admin_id,NOW());INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (@election_8_id,@question_8_id,1,@organizer_2_id);INSERT INTO witness_checkpoints (election_id,user_id,stage,snapshot_json,sha256_hash) VALUES (@election_8_id,@witness_2_id,'results_attested',JSON_OBJECT('election_id',@election_8_id,'status','closed','voted',3),SHA2(CONCAT(@election_8_id,'results_attested'),256));

INSERT INTO audit_logs (user_id,event_type,entity_type,entity_id,metadata,ip_address) VALUES
(@admin_id,'demo.scenarios_loaded','organization',@org_id,JSON_OBJECT('open',4,'scheduled',1,'draft',1,'closed',2),'127.0.0.1');

-- Credenciales locales (todos usan Demo2026!):
-- admin@inaudis.local
-- encargada.directorio@inaudis.local
-- encargado.estatutos@inaudis.local
-- ministra.directorio@inaudis.local
-- ministro.estatutos@inaudis.local
