# SAE 2-04 : gestion d'un cimetière communal

SAE Base de données du BUT Informatique 1re année, IUT de Marne-la-Vallée, Université Gustave Eiffel (2025 - 2026).
Réalisée par Yasin Boukraa et Elie Pennetier-Msika.

Application web en PHP sur une base PostgreSQL pour gérer un cimetière communal : allées, emplacements, concessions, occupants, contacts des familles, déplacements et biens déposés.

## Fonctionnalités

Trois rôles avec des droits différents :

- **Client** (titulaire d'une concession) : tableau de bord, emplacements, concessions, occupants et tarifs, mais ne voit les données personnelles que de sa propre concession
- **Gestionnaire** : en plus, nouvelle inhumation, déplacements, déplacements prévus, contacts des défunts récents, biens de plus de 5 000 € et prolongation des concessions
- **Administrateur** : en plus, suppression de données et journal d'audit

Points notables :

- Grille tarifaire stockée en base (table `tarif`, 4 types d'emplacement x 6 durées)
- Contrainte `CHECK` sur les durées réglementaires (5, 10, 20, 30, 50 ans, perpétuité)
- Statut d'occupation calculé à partir du nombre réel d'occupants (libre, partiellement occupé, complet)
- Requêtes préparées PDO partout

Le détail des corrections et des limites assumées est dans [RAPPORT_MODIFICATIONS.md](RAPPORT_MODIFICATIONS.md).

## Technologies

PHP, PostgreSQL, PDO, HTML / CSS.

## Structure

```
.
├── config/config.php     Paramètres de connexion à la base
├── includes/             Connexion (db.php), authentification et rôles (auth.php), en-tête et pied de page
├── public/               Pages du site (point d'entrée : index.php)
│   ├── install.php       Recrée toutes les tables à partir de sql/dump.sql
│   └── assets/style.css
└── sql/
    ├── dump.sql          Création du schéma et jeu de données de démonstration
    └── migration.sql     Mise à jour d'une base existante sans perte de données
```

## Lancer en local

1. Créer une base PostgreSQL et renseigner ses paramètres dans `config/config.php`.
2. Lancer le serveur PHP sur le dossier `public` : `php -S localhost:8000 -t public`.
3. Ouvrir http://localhost:8000/install.php pour créer les tables et les données (attention : supprime les tables existantes).
4. Se connecter sur http://localhost:8000/login.php.

## Comptes de démonstration

| Rôle | Identifiant | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin01` | `admin01` |
| Gestionnaire | `gestionnaire01` | `gestionnaire01` |
| Client | `client01` | `client01` |

Ces mots de passe sont stockés en clair : c'est une limite connue de la V1 (voir le rapport).
