# Rapport de modifications – SAE 2-04 Gestion de cimetière communal
**Auteur :** Boukraa / Pennetier-Msika  
**Formation :** BUT Informatique 1ère année – 2026  
**Objet :** Ce rapport liste les écarts identifiés par rapport au sujet officiel, les corrections apportées, les limites assumées, et résume tous les fichiers modifiés ou créés.

---

## 1. Écarts identifiés et corrections apportées

### A. Durées de concession incorrectes

**Problème :** Le sujet impose six durées valides : 5 ans, 10 ans, 20 ans, 30 ans, 50 ans et Perpétuité. Le code contenait « 15 ans » au lieu de « 20 ans », et la durée « 50 ans » était absente. Concrètement, cinq concessions (n° 8, 10, 13, 14, 15) étaient stockées avec `duree = '15 ans'`, ce qui n'est pas une valeur réglementaire.

**Correction :**
- Ajout d'une contrainte `CHECK` sur `concession.duree` dans `dump.sql` pour que la base rejette toute valeur non autorisée.
- Remplacement de « 15 ans » par « 20 ans » dans les cinq concessions concernées, avec recalcul de `datefinprevu = datedebut + 20 ans`.
- Mise à jour de toutes les listes déroulantes PHP (tarifs.php, emplacements.php) pour proposer les six valeurs correctes.

---

### B. Table tarif absente de la base de données

**Problème :** La grille tarifaire était codée en dur dans un tableau PHP dans `tarifs.php`. Pour un projet SAE Base de Données, stocker des données métier en PHP est contraire à l'objectif pédagogique. De plus, `install.php` référençait déjà une table `tarif` dans sa liste de DROP sans qu'elle soit définie.

**Correction :**
- Création de la table `tarif(idtarif SERIAL, typeemplacement VARCHAR, duree VARCHAR, prix_base NUMERIC, PRIMARY KEY (typeemplacement, duree))` dans `dump.sql`.
- Peuplement avec 24 lignes (4 types × 6 durées) aux tarifs ajustés pour inclure 20 ans et 50 ans.
- Modification de `tarifs.php` pour lire les tarifs depuis la base via une requête SQL.
- Conservation d'un tableau PHP de secours, commenté, activé uniquement si la table est vide (normalement impossible après install).

---

### C. Fuites de données côté client (RGPD)

**Problème :** Trois pages exposaient des informations personnelles appartenant à d'autres familles :

1. `concessions.php` : le client voyait les 15 concessions de toutes les familles (noms, adresses, téléphones, coûts).
2. `emplacements.php?id=X` : le bloc « Concession » (titulaire, téléphone, adresse, coût) s'affichait pour le client sur n'importe quel emplacement.
3. `occupants.php` : le client voyait les 24 défunts de tout le cimetière.

**Correction :**
- `concessions.php` : ajout d'un filtre `AND c.idconcession = :id_conc` conditionné à `is_client()`, avec `$_SESSION['user_concession']` comme paramètre préparé.
- `emplacements.php` (détail) : le bloc concession n'est rendu que si `is_gestionnaire()` ou si `$conc['idconcession'] == $_SESSION['user_concession']`. Pour les autres emplacements, le client voit un message neutre.
- `occupants.php` (liste et détail) : calcul préalable de l'emplacement lié à la concession du client, puis filtre `AND o.idemplacement = :empl`. En vue détail, redirection avec message d'erreur si l'occupant n'appartient pas à l'emplacement du client.

---

### D. Statut d'occupation incohérent

**Problème :** Un caveau de 6 places avec 2 occupants était affiché avec le tag « Occupé » (rouge), alors qu'il est seulement partiellement rempli. Le champ `emplacement.statut` est une valeur stockée qui peut se désynchroniser du nombre réel d'occupants.

**Correction :**
- Ajout de la fonction `statut_reel($capacite, $nb_occ)` dans `auth.php`. Elle retourne `['libre', 'Libre']`, `['partiel', 'Partiellement occupe']` ou `['occupe', 'Complet']` selon le count réel.
- Utilisation de cette fonction dans `emplacements.php` (liste et détail) et `dashboard.php` (cartes KPI et tableau par allée).
- Ajout de la classe CSS `.tag-partiel` (fond jaune-ambre, texte brun) dans `style.css`, cohérent avec la palette `.alert-warning` existante.

---

### E. Allées non cliquables dans le tableau de bord

**Problème :** Le tableau « Répartition par allée » du dashboard affichait les noms d'allées comme texte brut, sans lien vers la liste des emplacements correspondants.

**Correction :**
- Dans `dashboard.php` : les noms d'allée deviennent des liens `<a href="emplacements.php?allee=NOM">`.
- Dans `emplacements.php` (liste) : ajout d'un paramètre GET `allee`, filtré par paramètre préparé dans la requête SQL. Quand le filtre est actif, un bandeau d'information s'affiche avec le nom de l'allée et un lien « Voir toutes les allées ». Le filtre allée est transmis en champ caché dans le formulaire type/statut pour ne pas être perdu.
- Le tableau du dashboard affiche maintenant 5 colonnes : Emplacements, Libres, Partiels, Complets, Défunts (statuts réels, tâche D).

---

### F. Absence d'action « Prolonger » sur les concessions

**Problème :** Le sujet demande de pouvoir renouveler une concession arrivant à échéance. Cette fonctionnalité n'existait pas.

**Correction :**
- Ajout dans `emplacements.php` (vue détail) d'un mini-formulaire POST visible uniquement pour les gestionnaires/admins, et uniquement si la concession a une `datefinprevu` (i.e. n'est pas en perpétuité).
- Le traitement POST est géré **avant** `require_once header.php` pour permettre une redirection propre (`header('Location: ...')`).
- Calcul de la nouvelle `datefinprevu = ancienne datefinprevu + CAST(:intervalle AS INTERVAL)`, où l'intervalle est construit depuis une whitelist PHP (pas de saisie libre dans la requête).
- Lecture du supplément de tarif depuis la table `tarif` (tâche B), additionné au `cout` existant.
- Passage en perpétuité possible : `datefinprevu` passe à `NULL`, tarif perpétuel ajouté.
- Message flash transmis par redirection GET (`?prolongee=1` ou `?err_prolong=1`).

---

### G. Page « Déplacements prévus » manquante

**Problème :** Le sujet demande de lister les déplacements prévus du mois en cours, du mois à venir et de l'année à venir. Aucune page de ce type n'existait.

**Correction :**
- Création de `public/previsions.php` (accès gestionnaire + admin uniquement).
- Sélecteur de période (mois en cours / mois suivant / année à venir) avec liens GET, filtre par whitelist.
- Tableau des déplacements : date, occupant, motif, emplacement d'origine, emplacement de destination avec allées.
- Lien ajouté dans `header.php` entre « Déplacement » et « Contacts » dans la section Gestionnaire.

*Note :* Les données de seed contenant des déplacements passés (2015, 2020, 2023), la page affiche « Aucun déplacement prévu » sur les périodes futures. La fonctionnalité est opérationnelle dès que des déplacements à venir sont saisies via la page `deplacement.php`.

---

### H. Vues du sujet manquantes

**Problème :** Le sujet cite deux exemples de requêtes à pouvoir répondre, absents de l'interface :
1. « Personnes à contacter pour les décédés de la semaine dernière »
2. « Biens de plus de 5000 € et leur emplacement »

**Correction :**
- `contacts.php` enrichi d'une section filtrable (semaine / mois / tous) qui joint `personne_contact → relation → occupant` et filtre sur `o.datedeces >= CURRENT_DATE - INTERVAL '7 days'`. Un occupant avec un décès récent (Marie Leclerc, 2026-05-20) a été ajouté aux données de seed pour que la démonstration soit immédiatement visible.
- Création de `public/biens_precieux.php` (accès gestionnaire + admin) : requête `JOIN bien → possession → occupant → emplacement → allee` avec `WHERE b.valeur > 5000`. Deux biens de haute valeur (tableau 8 500 € et bijou 12 000 €) ont été ajoutés aux données de seed.
- Lien « Biens précieux » ajouté dans le menu gestionnaire de `header.php`.

---

## 2. Limites assumées (non corrigées, mentionnées au cahier des charges comme hors périmètre V1)

### Mots de passe en clair
Les mots de passe sont stockés en clair (`admin01`, `gestionnaire01`, `client01`). Le hachage avec `password_hash()` / `password_verify()` est la correction évidente, mais elle nécessite de régénérer tous les seeds et n'est pas demandée explicitement par le sujet. Je la signale comme dette technique à traiter en V2.

### Occupants autorisés vs réels
Le sujet distingue les « occupants autorisés » (personnes ayant droit à l'emplacement) des « occupants réels » (personnes effectivement inhumées). J'ai simplifié en ne modélisant que les occupants réels. Ajouter une table `occupant_autorise` nécessiterait de revoir les formulaires d'admission et sortirait du périmètre de cette SAE.

### Notion d'héritier
Le sujet mentionne la possibilité de désigner un héritier pour une concession. Je n'ai pas implémenté cette notion : elle nécessiterait une relation supplémentaire entre `personne` et `personne` (auto-référence) ou une table dédiée `heritier`. Je la signale comme amélioration future.

### Capacité urne vs cercueil
La capacité d'un emplacement est un entier unique (`capacite`). Le sujet distingue la capacité pour cercueil de celle pour urne (un columbarium de 2 niches peut accueillir 2 urnes mais pas 2 cercueils). J'ai gardé la capacité unifiée pour rester simple : le type d'occupation est enregistré par occupant via `typeoccupation`, mais le contrôle de capacité ne distingue pas les types.

### Exhumation temporaire
Le sujet évoque la possibilité d'enregistrer une exhumation temporaire (sortie provisoire d'un occupant). Je n'ai pas modélisé ce cas : il nécessiterait un statut d'occupant (`inhumé`, `exhumé temporairement`, etc.) et un historique de sorties. Cette notion est absente du schéma actuel.

### Transfert automatique en ossuaire
Quand une concession expire, les occupants pourraient être automatiquement transférés à l'ossuaire. Cette logique automatique (trigger ou tâche planifiée) dépasse le cadre d'un projet PHP procédural de BUT1. Le déplacement vers l'ossuaire est géré manuellement via la page `deplacement.php`.

---

## 3. Fichiers modifiés

| Fichier | Nature | Résumé de la modification |
|---|---|---|
| `sql/dump.sql` | Modifié | Ajout table `tarif`, contrainte `CHECK duree`, correction concessions 8/10/13/14/15 (15→20 ans), ajout occupant récent (Marie Leclerc), ajout 2 biens > 5 000 €, ajout contact/relation de démonstration |
| `includes/auth.php` | Modifié | Ajout de la fonction `statut_reel($capacite, $nb_occ)` |
| `includes/header.php` | Modifié | Ajout des liens « Prévisions » et « Biens précieux » dans le menu Gestionnaire |
| `public/assets/style.css` | Modifié | Ajout de la classe `.tag-partiel` (fond jaune, tâche D) |
| `public/emplacements.php` | Modifié | Traitement POST prolongation (avant header), restriction RGPD bloc concession (client), formulaire Prolonger (gestionnaire), statut réel (tâche D), filtre allée avec bandeau informatif (tâche E) |
| `public/concessions.php` | Modifié | Filtre RGPD client : une seule concession visible |
| `public/occupants.php` | Modifié | Filtre RGPD client : liste et détail restreints à l'emplacement de la concession du client |
| `public/tarifs.php` | Modifié | Lecture depuis la table `tarif`, ordre d'affichage corrigé (5/10/20/30/50/Perpétuité), fallback PHP commenté |
| `public/dashboard.php` | Modifié | Comptages libres/partiels/complets par subquery réelle, allées cliquables, colonne « Partiels » dans le tableau |
| `public/contacts.php` | Modifié | Ajout section « Contacts des défunts récents » avec filtre semaine/mois/tous |

## 4. Fichiers créés

| Fichier | Résumé |
|---|---|
| `public/previsions.php` | Page « Déplacements prévus » (mois en cours / mois suivant / année à venir), accès gestionnaire + admin |
| `public/biens_precieux.php` | Page « Biens > 5 000 € et leur emplacement », accès gestionnaire + admin, jointure 5 tables |
| `sql/migration.sql` | Script SQL de migration sans perte de données (ALTER, UPDATE, CREATE TABLE tarif, INSERT ON CONFLICT) |
| `RAPPORT_MODIFICATIONS.md` | Ce document |
