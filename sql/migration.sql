-- =============================================================
-- MIGRATION : passage de l'ancienne base vers la nouvelle
-- À exécuter UNE SEULE FOIS sur une base déjà en production
-- (alternative à install.php qui efface tout)
-- =============================================================

-- ----------------------------------------------------------------
-- 1. Correction des durées invalides dans la table concession
--    Les concessions en "15 ans" n'existent pas dans le cahier des charges
--    → remplacées par "20 ans", datefinprevu recalculée à datedebut + 20 ans
-- ----------------------------------------------------------------
UPDATE concession
SET    duree       = '20 ans',
       datefinprevu = datedebut + INTERVAL '20 years'
WHERE  duree = '15 ans';

-- ----------------------------------------------------------------
-- 2. Ajout de la contrainte CHECK sur concession.duree
--    Ne peut être ajoutée qu'APRÈS correction des données (étape 1)
--    Valeurs autorisées par le cahier des charges
-- ----------------------------------------------------------------
ALTER TABLE concession
    ADD CONSTRAINT chk_duree
    CHECK (duree IN ('5 ans', '10 ans', '20 ans', '30 ans', '50 ans', 'Perpetuite'));

-- ----------------------------------------------------------------
-- 3. Création de la table tarif (absente de l'ancienne base)
--    PK composite (typeemplacement, duree) pour unicité par paire
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tarif (
    idtarif         SERIAL,
    typeemplacement VARCHAR(20) NOT NULL,
    duree           VARCHAR(20) NOT NULL,
    prix_base       NUMERIC(10,2) NOT NULL,
    PRIMARY KEY (typeemplacement, duree)
);

-- ----------------------------------------------------------------
-- 4. Peuplement de la table tarif avec les tarifs officiels
--    ON CONFLICT DO NOTHING pour pouvoir rejouer la migration sans erreur
-- ----------------------------------------------------------------
INSERT INTO tarif (typeemplacement, duree, prix_base) VALUES
('Tombe',       '5 ans',       250.00),
('Tombe',       '10 ans',      450.00),
('Tombe',       '20 ans',      800.00),
('Tombe',       '30 ans',     1200.00),
('Tombe',       '50 ans',     2000.00),
('Tombe',       'Perpetuite', 3500.00),
('Caveau',      '5 ans',       500.00),
('Caveau',      '10 ans',      900.00),
('Caveau',      '20 ans',     1600.00),
('Caveau',      '30 ans',     2200.00),
('Caveau',      '50 ans',     3500.00),
('Caveau',      'Perpetuite', 5000.00),
('Columbarium', '5 ans',       150.00),
('Columbarium', '10 ans',      280.00),
('Columbarium', '20 ans',      500.00),
('Columbarium', '30 ans',      700.00),
('Columbarium', '50 ans',     1100.00),
('Columbarium', 'Perpetuite', 1500.00),
('Ossuaire',    '5 ans',       100.00),
('Ossuaire',    '10 ans',      180.00),
('Ossuaire',    '20 ans',      320.00),
('Ossuaire',    '30 ans',      400.00),
('Ossuaire',    '50 ans',      600.00),
('Ossuaire',    'Perpetuite',  800.00)
ON CONFLICT (typeemplacement, duree) DO NOTHING;

-- ----------------------------------------------------------------
-- 5. Ajout de l'occupant de démonstration (décès récent pour la page contacts)
--    L'emplacement 8 (Caveau 203, capacité 4) a de la place (1 occupant existant)
-- ----------------------------------------------------------------
INSERT INTO occupant (nom, prenom, datenaissance, datedeces, typeoccupation, idemplacement)
SELECT 'Leclerc', 'Marie', '1940-03-12', CURRENT_DATE - INTERVAL '6 days', 'Cercueil', 8
WHERE NOT EXISTS (
    SELECT 1 FROM occupant WHERE nom = 'Leclerc' AND prenom = 'Marie'
);

-- Contact lié à l'occupant récent
INSERT INTO personne_contact (nom, prenom, telephone, lienavecoccupant)
SELECT 'Leclerc', 'Paul', '0644444444', 'Fils'
WHERE NOT EXISTS (
    SELECT 1 FROM personne_contact WHERE nom = 'Leclerc' AND prenom = 'Paul'
);

-- Relation entre l'occupant et son contact
INSERT INTO relation (idoccupant, idpersonnecontact)
SELECT o.idoccupant, pc.idpersonnecontact
FROM occupant o, personne_contact pc
WHERE o.nom = 'Leclerc' AND o.prenom = 'Marie'
  AND pc.nom = 'Leclerc' AND pc.prenom = 'Paul'
  AND NOT EXISTS (
    SELECT 1 FROM relation r
    WHERE r.idoccupant = o.idoccupant AND r.idpersonnecontact = pc.idpersonnecontact
  );

-- ----------------------------------------------------------------
-- 6. Ajout des biens de valeur élevée (pour la page biens_precieux.php)
-- ----------------------------------------------------------------
INSERT INTO bien (description, valeur, photoouinon)
SELECT 'Tableau ancien XIXe siecle', 8500.00, TRUE
WHERE NOT EXISTS (SELECT 1 FROM bien WHERE description = 'Tableau ancien XIXe siecle');

INSERT INTO bien (description, valeur, photoouinon)
SELECT 'Bijou de famille en or et diamants', 12000.00, TRUE
WHERE NOT EXISTS (SELECT 1 FROM bien WHERE description = 'Bijou de famille en or et diamants');

-- Possession : lier le tableau à l'occupant 1 (Dupont Robert)
INSERT INTO possession (idoccupant, idbien)
SELECT 1, b.idbien FROM bien b
WHERE b.description = 'Tableau ancien XIXe siecle'
  AND NOT EXISTS (SELECT 1 FROM possession WHERE idoccupant = 1 AND idbien = b.idbien);

-- Possession : lier le bijou à l'occupant 9 (Bernard Marcel)
INSERT INTO possession (idoccupant, idbien)
SELECT 9, b.idbien FROM bien b
WHERE b.description = 'Bijou de famille en or et diamants'
  AND NOT EXISTS (SELECT 1 FROM possession WHERE idoccupant = 9 AND idbien = b.idbien);

-- ----------------------------------------------------------------
-- 7. Mise à jour du statut de l'emplacement 8 si besoin
--    (déjà "Occupe" normalement, mais on s'assure de la cohérence)
-- ----------------------------------------------------------------
UPDATE emplacement SET statut = 'Occupe' WHERE idemplacement = 8;
