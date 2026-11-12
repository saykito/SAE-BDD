# SAE BDD, gestion d'un cimetière communal

Projet de SAE Base de données, BUT Informatique 1re année, IUT de Marne-la-Vallée (2026).
Réalisé en binôme (Boukraa, Pennetier-Msika).

Application web qui permet à une mairie de gérer son cimetière : emplacements, concessions, occupants, inhumations, déplacements, contacts, biens précieux, tarifs et statistiques.

## Fonctionnalités

- Connexion des agents et journal d'audit des actions
- Tableau de bord avec plan des allées et état des emplacements
- Gestion des concessions (6 durées réglementaires : 5, 10, 20, 30, 50 ans et perpétuité), avec prolongation
- Gestion des occupants, des admissions et des déplacements prévus
- Grille tarifaire stockée en base (table `tarif`)
- Prévisions des concessions arrivant à échéance

## Technologies

- PHP sans framework, PDO
- PostgreSQL (contraintes `CHECK`, clés étrangères, vues)
- HTML et CSS

## Structure

```
config/     paramètres de connexion à la base
includes/   connexion PDO, authentification, en-tête et pied de page
public/     pages de l'application (point d'entrée : index.php)
sql/        dump.sql (schéma et données), migration.sql
```

## Lancer en local

1. Créer une base PostgreSQL, puis exécuter `sql/dump.sql`.
2. Renseigner les paramètres de connexion dans `config/config.php`.
3. Depuis la racine du projet : `php -S localhost:8000 -t public`, puis ouvrir http://localhost:8000.

## Documentation

Le fichier [RAPPORT_MODIFICATIONS.md](RAPPORT_MODIFICATIONS.md) détaille les écarts corrigés par rapport au sujet, les limites assumées et la liste des fichiers modifiés.
