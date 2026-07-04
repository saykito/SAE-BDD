-- Dump complet cimetiere - schema public
-- Durées valides : 5 ans, 10 ans, 20 ans, 30 ans, 50 ans, Perpetuite

CREATE TABLE IF NOT EXISTS utilisateur_site (
    id_utilisateur SERIAL PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    role VARCHAR(20) NOT NULL,
    idconcession INTEGER,
    CONSTRAINT chk_role CHECK (role IN ('admin', 'gestionnaire', 'client'))
);

CREATE TABLE IF NOT EXISTS allee (
    idallee SERIAL PRIMARY KEY,
    nomallee VARCHAR(80)
);

CREATE TABLE IF NOT EXISTS emplacement (
    idemplacement SERIAL PRIMARY KEY,
    numero INTEGER NOT NULL,
    capacite INTEGER NOT NULL DEFAULT 2,
    orientation VARCHAR(20),
    expositionvent VARCHAR(20),
    proximiteentree BOOLEAN DEFAULT FALSE,
    accessibilite VARCHAR(20),
    statut VARCHAR(10) NOT NULL DEFAULT 'Libre',
    typeemplacement VARCHAR(20) NOT NULL DEFAULT 'Tombe',
    idallee INTEGER NOT NULL REFERENCES allee(idallee)
);

CREATE TABLE IF NOT EXISTS concession (
    idconcession SERIAL PRIMARY KEY,
    datedebut DATE NOT NULL,
    -- Contrainte sur les durées autorisées par le sujet
    duree VARCHAR(20) NOT NULL CONSTRAINT chk_duree CHECK (duree IN ('5 ans', '10 ans', '20 ans', '30 ans', '50 ans', 'Perpetuite')),
    cout NUMERIC(10,2),
    datefinprevu DATE,
    idemplacement INTEGER NOT NULL REFERENCES emplacement(idemplacement)
);

CREATE TABLE IF NOT EXISTS personne (
    idpersonne SERIAL PRIMARY KEY,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(40) NOT NULL,
    adresse VARCHAR(200),
    telephone VARCHAR(15),
    idconcession INTEGER REFERENCES concession(idconcession)
);

CREATE TABLE IF NOT EXISTS occupant (
    idoccupant SERIAL PRIMARY KEY,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(40) NOT NULL,
    datenaissance DATE,
    datedeces DATE,
    typeoccupation VARCHAR(10) NOT NULL,
    idemplacement INTEGER NOT NULL REFERENCES emplacement(idemplacement)
);

CREATE TABLE IF NOT EXISTS personne_contact (
    idpersonnecontact SERIAL PRIMARY KEY,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(40) NOT NULL,
    telephone VARCHAR(15),
    lienavecoccupant VARCHAR(40)
);

CREATE TABLE IF NOT EXISTS relation (
    idoccupant INTEGER NOT NULL REFERENCES occupant(idoccupant),
    idpersonnecontact INTEGER NOT NULL REFERENCES personne_contact(idpersonnecontact),
    PRIMARY KEY (idoccupant, idpersonnecontact)
);

CREATE TABLE IF NOT EXISTS bien (
    idbien SERIAL PRIMARY KEY,
    description VARCHAR(200) NOT NULL,
    valeur NUMERIC(10,2),
    photoouinon BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS possession (
    idoccupant INTEGER NOT NULL REFERENCES occupant(idoccupant),
    idbien INTEGER NOT NULL REFERENCES bien(idbien),
    PRIMARY KEY (idoccupant, idbien)
);

CREATE TABLE IF NOT EXISTS deplacement (
    iddeplacement SERIAL PRIMARY KEY,
    datedeplacement DATE NOT NULL,
    motif VARCHAR(200)
);

CREATE TABLE IF NOT EXISTS car_deplacement (
    idemplacementorigine INTEGER NOT NULL REFERENCES emplacement(idemplacement),
    idemplacementdestination INTEGER NOT NULL REFERENCES emplacement(idemplacement),
    iddeplacement INTEGER NOT NULL REFERENCES deplacement(iddeplacement),
    idoccupant INTEGER NOT NULL REFERENCES occupant(idoccupant),
    PRIMARY KEY (iddeplacement, idoccupant)
);

-- Grille tarifaire en base (cahier des charges : les tarifs ne doivent pas etre codes en dur)
-- PK composite sur (typeemplacement, duree) pour garantir l'unicite du prix par combinaison
CREATE TABLE IF NOT EXISTS tarif (
    idtarif SERIAL,
    typeemplacement VARCHAR(20) NOT NULL,
    duree VARCHAR(20) NOT NULL,
    prix_base NUMERIC(10,2) NOT NULL,
    PRIMARY KEY (typeemplacement, duree)
);

-- VIDER LES TABLES (ordre inverse des FK pour eviter les violations)
DELETE FROM car_deplacement;
DELETE FROM deplacement;
DELETE FROM possession;
DELETE FROM bien;
DELETE FROM relation;
DELETE FROM personne_contact;
DELETE FROM occupant;
DELETE FROM personne;
DELETE FROM concession;
DELETE FROM emplacement;
DELETE FROM allee;
DELETE FROM tarif;
DELETE FROM utilisateur_site;

-- COMPTES
INSERT INTO utilisateur_site (login, mdp, nom, prenom, role, idconcession) VALUES
('admin01', 'admin01', 'Duval', 'Philippe', 'admin', NULL),
('gestionnaire01', 'gestionnaire01', 'Leroy', 'Marc', 'gestionnaire', NULL),
('client01', 'client01', 'Dupont', 'Jean', 'client', 1);

-- ALLEES
INSERT INTO allee (idallee, nomallee) VALUES
(1, 'Allee du Souvenir'),
(2, 'Allee de la Paix'),
(3, 'Allee des Chenes'),
(4, 'Allee des Roses'),
(5, 'Allee du Repos'),
(6, 'Allee du Columbarium');

-- EMPLACEMENTS (30)
INSERT INTO emplacement (idemplacement, numero, capacite, orientation, expositionvent, proximiteentree, accessibilite, statut, typeemplacement, idallee) VALUES
(1,  101, 2, 'Nord',  'Faible', TRUE,  'Facile',    'Occupe', 'Tombe', 1),
(2,  102, 2, 'Nord',  'Faible', TRUE,  'Facile',    'Occupe', 'Tombe', 1),
(3,  103, 2, 'Nord',  'Faible', FALSE, 'Facile',    'Occupe', 'Tombe', 1),
(4,  104, 2, 'Nord',  'Faible', FALSE, 'Facile',    'Libre',  'Tombe', 1),
(5,  105, 2, 'Nord',  'Faible', FALSE, 'Facile',    'Libre',  'Tombe', 1),
(6,  201, 4, 'Est',   'Moyen',  FALSE, 'Moyen',     'Occupe', 'Caveau', 2),
(7,  202, 6, 'Est',   'Moyen',  FALSE, 'Moyen',     'Occupe', 'Caveau', 2),
(8,  203, 4, 'Est',   'Moyen',  FALSE, 'Moyen',     'Occupe', 'Caveau', 2),
(9,  204, 4, 'Est',   'Moyen',  FALSE, 'Moyen',     'Libre',  'Caveau', 2),
(10, 205, 6, 'Est',   'Moyen',  FALSE, 'Moyen',     'Libre',  'Caveau', 2),
(11, 301, 2, 'Sud',   'Fort',   FALSE, 'Difficile', 'Occupe', 'Tombe', 3),
(12, 302, 2, 'Sud',   'Fort',   FALSE, 'Difficile', 'Occupe', 'Tombe', 3),
(13, 303, 2, 'Sud',   'Fort',   FALSE, 'Difficile', 'Libre',  'Tombe', 3),
(14, 304, 2, 'Sud',   'Fort',   FALSE, 'Difficile', 'Libre',  'Tombe', 3),
(15, 305, 2, 'Sud',   'Fort',   FALSE, 'Difficile', 'Libre',  'Tombe', 3),
(16, 401, 2, 'Ouest', 'Faible', TRUE,  'Facile',    'Occupe', 'Tombe', 4),
(17, 402, 2, 'Ouest', 'Faible', TRUE,  'Facile',    'Occupe', 'Tombe', 4),
(18, 403, 2, 'Ouest', 'Faible', FALSE, 'Facile',    'Libre',  'Tombe', 4),
(19, 404, 2, 'Ouest', 'Faible', FALSE, 'Facile',    'Libre',  'Tombe', 4),
(20, 405, 2, 'Ouest', 'Faible', FALSE, 'Facile',    'Libre',  'Tombe', 4),
(21, 501, 20, 'Nord', 'Faible', TRUE,  'Facile',    'Occupe', 'Ossuaire', 5),
(22, 502, 20, 'Nord', 'Faible', TRUE,  'Facile',    'Occupe', 'Ossuaire', 5),
(23, 601, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Occupe', 'Columbarium', 6),
(24, 602, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Occupe', 'Columbarium', 6),
(25, 603, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Occupe', 'Columbarium', 6),
(26, 604, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Libre',  'Columbarium', 6),
(27, 605, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Libre',  'Columbarium', 6),
(28, 606, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Libre',  'Columbarium', 6),
(29, 607, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Libre',  'Columbarium', 6),
(30, 608, 2, 'Sud',   'Faible', TRUE,  'Facile',    'Libre',  'Columbarium', 6);

-- CONCESSIONS (15) - durées corrigées : 15 ans remplace par 20 ans (valeur inexistante au cahier des charges)
-- datefinprevu recalculee : datedebut + 20 ans pour les concessions concernees
INSERT INTO concession (idconcession, datedebut, duree, cout, datefinprevu, idemplacement) VALUES
(1,  '1985-03-10', 'Perpetuite', 3500.00, NULL,          1),
(2,  '1998-07-22', '30 ans',     1800.00, '2028-07-22',  2),
(3,  '2005-01-15', '30 ans',     2000.00, '2035-01-15',  3),
(4,  '1975-06-01', 'Perpetuite', 4500.00, NULL,          6),
(5,  '1990-11-20', 'Perpetuite', 5000.00, NULL,          7),
(6,  '2010-04-05', '30 ans',     2200.00, '2040-04-05',  8),
(7,  '2000-09-12', '30 ans',     1500.00, '2030-09-12', 11),
(8,  '2015-02-28', '20 ans',     1000.00, '2035-02-28', 12),
(9,  '2018-08-10', '30 ans',     1800.00, '2048-08-10', 16),
(10, '2020-12-01', '20 ans',     1200.00, '2040-12-01', 17),
(11, '1960-01-01', 'Perpetuite', 2000.00, NULL,          21),
(12, '1970-01-01', 'Perpetuite', 2000.00, NULL,          22),
(13, '2019-05-20', '20 ans',      800.00, '2039-05-20', 23),
(14, '2021-03-14', '20 ans',      800.00, '2041-03-14', 24),
(15, '2023-10-01', '20 ans',      900.00, '2043-10-01', 25);

-- PERSONNES (12)
INSERT INTO personne (idpersonne, nom, prenom, adresse, telephone, idconcession) VALUES
(1,  'Dupont',    'Jean',      '12 rue de Paris, 75001 Paris',         '0612345678', 1),
(2,  'Moreau',    'Claire',    '45 rue de Lyon, 69003 Lyon',           '0623456789', 2),
(3,  'Martin',    'Sophie',    '8 avenue Victor Hugo, 33000 Bordeaux', '0634567890', 3),
(4,  'Lefebvre',  'Jacques',   '15 rue Pasteur, 75015 Paris',          '0645678901', 4),
(5,  'Bernard',   'Francoise', '3 place Bellecour, 69002 Lyon',        '0656789012', 5),
(6,  'Thomas',    'Patrick',   '22 rue de la Gare, 31000 Toulouse',    '0667890123', 6),
(7,  'Petit',     'Andre',     '7 rue du Port, 44000 Nantes',          '0678901234', 7),
(8,  'Roux',      'Nathalie',  '18 rue des Fleurs, 13001 Marseille',   '0689012345', 8),
(9,  'Garcia',    'Luis',      '5 impasse du Midi, 34000 Montpellier', '0690123456', 9),
(10, 'Bonnet',    'Marie',     '30 boulevard Carnot, 06000 Nice',      '0601234567', 10),
(11, 'Lambert',   'Henri',     '2 rue de la Mairie, 75004 Paris',      '0611111111', 13),
(12, 'Fontaine',  'Isabelle',  '9 rue Victor Hugo, 69001 Lyon',        '0622222222', 14);

-- OCCUPANTS (24) - occupant 24 : deces recents pour demo de la page contacts/decedes recents
INSERT INTO occupant (idoccupant, nom, prenom, datenaissance, datedeces, typeoccupation, idemplacement) VALUES
(1,  'Dupont',   'Robert',     '1920-03-15', '1985-03-08', 'Cercueil', 1),
(2,  'Dupont',   'Marguerite', '1922-07-20', '2001-11-30', 'Cercueil', 1),
(3,  'Moreau',   'Pierre',     '1935-01-10', '1998-07-15', 'Cercueil', 2),
(4,  'Martin',   'Georges',    '1940-05-12', '2005-01-10', 'Cercueil', 3),
(5,  'Martin',   'Jeanne',     '1942-09-03', '2019-06-22', 'Cercueil', 3),
(6,  'Lefebvre', 'Auguste',    '1910-12-25', '1975-05-30', 'Cercueil', 6),
(7,  'Lefebvre', 'Henriette',  '1915-04-18', '1988-09-14', 'Cercueil', 6),
(8,  'Lefebvre', 'Raymond',    '1938-08-02', '2010-03-21', 'Cercueil', 6),
(9,  'Bernard',  'Marcel',     '1918-06-11', '1990-11-15', 'Cercueil', 7),
(10, 'Bernard',  'Lucie',      '1920-02-28', '1995-04-07', 'Cercueil', 7),
(11, 'Bernard',  'Rene',       '1945-10-05', '2012-08-30', 'Cercueil', 7),
(12, 'Bernard',  'Colette',    '1948-03-17', '2020-01-19', 'Cercueil', 7),
(13, 'Thomas',   'Lucien',     '1930-07-14', '2010-04-01', 'Cercueil', 8),
(14, 'Petit',    'Emile',      '1925-11-11', '2000-09-10', 'Cercueil', 11),
(15, 'Roux',     'Germaine',   '1950-01-22', '2015-02-25', 'Cercueil', 12),
(16, 'Garcia',   'Carmen',     '1955-06-30', '2018-08-05', 'Cercueil', 16),
(17, 'Bonnet',   'Charles',    '1960-04-14', '2020-12-01', 'Cercueil', 17),
(18, 'Lambert',  'Suzanne',    '1932-08-19', '2019-05-15', 'Urne',     23),
(19, 'Fontaine', 'Michel',     '1940-12-03', '2021-03-10', 'Urne',     24),
(20, 'Duval',    'Therese',    '1928-05-07', '2023-09-28', 'Urne',     25),
(21, 'Inconnu',  'Soldat1',    '1895-01-01', '1918-11-11', 'Cercueil', 21),
(22, 'Inconnu',  'Soldat2',    '1896-01-01', '1918-11-11', 'Cercueil', 21),
(23, 'Blanc',    'Edouard',    '1880-03-20', '1945-05-08', 'Cercueil', 22),
(24, 'Leclerc',  'Marie',      '1940-03-12', '2026-05-20', 'Cercueil', 8);

-- CONTACTS (13) - contact 13 lie a l'occupant recemment decede (demo page decedes recents)
INSERT INTO personne_contact (idpersonnecontact, nom, prenom, telephone, lienavecoccupant) VALUES
(1,  'Dupont',   'Jean',      '0612345678', 'Fils'),
(2,  'Dupont',   'Marie',     '0612345679', 'Fille'),
(3,  'Moreau',   'Claire',    '0623456789', 'Epouse'),
(4,  'Martin',   'Sophie',    '0634567890', 'Fille'),
(5,  'Lefebvre', 'Jacques',   '0645678901', 'Fils'),
(6,  'Bernard',  'Francoise', '0656789012', 'Belle-fille'),
(7,  'Thomas',   'Patrick',   '0667890123', 'Fils'),
(8,  'Petit',    'Andre',     '0678901234', 'Fils'),
(9,  'Roux',     'Nathalie',  '0689012345', 'Fille'),
(10, 'Garcia',   'Luis',      '0690123456', 'Epoux'),
(11, 'Bonnet',   'Marie',     '0601234567', 'Epouse'),
(12, 'Lambert',  'Henri',     '0611111111', 'Fils'),
(13, 'Leclerc',  'Paul',      '0644444444', 'Fils');

INSERT INTO relation (idoccupant, idpersonnecontact) VALUES
(1,1),(2,1),(1,2),(2,2),(3,3),(4,4),(5,4),(6,5),(7,5),(8,5),
(9,6),(10,6),(11,6),(12,6),(13,7),(14,8),(15,9),(16,10),(17,11),(18,12),(24,13);

-- BIENS (10) - biens 9 et 10 : valeur > 5000 euros pour la page biens_precieux
INSERT INTO bien (idbien, description, valeur, photoouinon) VALUES
(1,  'Alliance en or',                        500.00,   TRUE),
(2,  'Montre a gousset ancienne',             800.00,   TRUE),
(3,  'Medaille militaire 14-18',              350.00,   TRUE),
(4,  'Croix en argent',                       200.00,   FALSE),
(5,  'Chapelet en ivoire',                    150.00,   FALSE),
(6,  'Bague avec rubis',                     1200.00,   TRUE),
(7,  'Broche en or',                          400.00,   FALSE),
(8,  'Medaille du travail',                    80.00,   TRUE),
(9,  'Tableau ancien XIXe siecle',           8500.00,   TRUE),
(10, 'Bijou de famille en or et diamants',  12000.00,   TRUE);

INSERT INTO possession (idoccupant, idbien) VALUES
(1,1),(1,2),(6,3),(9,4),(10,5),(13,6),(16,7),(17,8),(1,9),(9,10);

-- DEPLACEMENTS (3)
INSERT INTO deplacement (iddeplacement, datedeplacement, motif) VALUES
(1, '2015-06-10', 'Transfert vers ossuaire - concession expiree'),
(2, '2020-03-15', 'Regroupement familial'),
(3, '2023-01-20', 'Travaux allee des Chenes');

INSERT INTO car_deplacement (idemplacementorigine, idemplacementdestination, iddeplacement, idoccupant) VALUES
(11, 21, 1, 14),
(2,  7,  2, 3),
(12, 13, 3, 15);

-- GRILLE TARIFAIRE (durées corrigées : 20 ans et 50 ans au lieu de 15 ans)
INSERT INTO tarif (typeemplacement, duree, prix_base) VALUES
('Tombe',        '5 ans',       250.00),
('Tombe',        '10 ans',      450.00),
('Tombe',        '20 ans',      800.00),
('Tombe',        '30 ans',     1200.00),
('Tombe',        '50 ans',     2000.00),
('Tombe',        'Perpetuite', 3500.00),
('Caveau',       '5 ans',       500.00),
('Caveau',       '10 ans',      900.00),
('Caveau',       '20 ans',     1600.00),
('Caveau',       '30 ans',     2200.00),
('Caveau',       '50 ans',     3500.00),
('Caveau',       'Perpetuite', 5000.00),
('Columbarium',  '5 ans',       150.00),
('Columbarium',  '10 ans',      280.00),
('Columbarium',  '20 ans',      500.00),
('Columbarium',  '30 ans',      700.00),
('Columbarium',  '50 ans',     1100.00),
('Columbarium',  'Perpetuite', 1500.00),
('Ossuaire',     '5 ans',       100.00),
('Ossuaire',     '10 ans',      180.00),
('Ossuaire',     '20 ans',      320.00),
('Ossuaire',     '30 ans',      400.00),
('Ossuaire',     '50 ans',      600.00),
('Ossuaire',     'Perpetuite',  800.00);

-- Reset des sequences
SELECT setval('allee_idallee_seq', (SELECT MAX(idallee) FROM allee));
SELECT setval('emplacement_idemplacement_seq', (SELECT MAX(idemplacement) FROM emplacement));
SELECT setval('concession_idconcession_seq', (SELECT MAX(idconcession) FROM concession));
SELECT setval('personne_idpersonne_seq', (SELECT MAX(idpersonne) FROM personne));
SELECT setval('occupant_idoccupant_seq', (SELECT MAX(idoccupant) FROM occupant));
SELECT setval('personne_contact_idpersonnecontact_seq', (SELECT MAX(idpersonnecontact) FROM personne_contact));
SELECT setval('bien_idbien_seq', (SELECT MAX(idbien) FROM bien));
SELECT setval('deplacement_iddeplacement_seq', (SELECT MAX(iddeplacement) FROM deplacement));
SELECT setval('utilisateur_site_id_utilisateur_seq', (SELECT MAX(id_utilisateur) FROM utilisateur_site));
SELECT setval('tarif_idtarif_seq', (SELECT MAX(idtarif) FROM tarif));
