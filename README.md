# CLOUX-SEO

Extension WordPress SEO complète, développée pour cloux.net et utilisable sur n’importe quel site WordPress. Équivalent WordPress du projet claude-seo (AgriciDaniel) : les sub-skills deviennent des modules PHP natifs pilotés depuis l’admin, sans dépendance externe ni clé API.

## Modules

| claude-seo (Claude Code) | CLOUX-SEO (WordPress) |
| --- | --- |
| Technical SEO audit | Audit technique du site (HTML rendu + base WordPress) |
| Audit de n’importe quel domaine | Audit multi-domaines : ajout de domaines, score par domaine, cron hebdomadaire |
| E-E-A-T | Encart auteur, biographies, sameAs, dates de publication et mise à jour |
| Schema.org | JSON-LD : Organization, LocalBusiness, WebSite, Article, BreadcrumbList |
| GEO / AEO (llms.txt) | llms.txt, llms-full.txt, ai-catalog.json, règles robots.txt pour 12 agents IA |
| Scoring et rapports | Score pondéré 0-100, severities, vérifications reproductibles, exports CSV et JSON |
| Backlinks, local, e-commerce | Non couvert en 1.0 (roadmap) |

## Installation

1. Télécharger le ZIP (Code > Download ZIP) puis Extensions > Ajouter > Téléverser une extension, ou cloner le dépôt dans wp-content/plugins.
2. Activer l’extension : les règles de réécriture (llms.txt) sont créées à l’activation.
3. Configurer identité, modules et agents IA dans CLOUX SEO > Réglages.

Compatibilité Rank Math : en mode auto, le module Schema se désactive si Rank Math est actif (doublons évités) ; l’audit lit les metas rank_math_description. Les autres modules sont complémentaires.

## Points d’entrée générés

- /llms.txt — inventaire du site pour les agents IA
- /llms-full.txt — idem avec extraits complets
- /ai-catalog.json — catalogue JSON des contenus
- robots.txt — blocs User-agent par agent IA (Allow ou Disallow selon réglages)

## Audit

Chaque contrôle renvoie : statut (pass, warn, fail), poids, severity, constat, recommandation et vérification reproductible (commande curl ou vérification dans l’admin). Score global et par domaine de contrôle : titres, contenu, technique, Schema, GEO, E-E-A-T. Audit complet automatique chaque semaine (WP-Cron).

L’audit multi-domaines exécute la batterie de contrôles applicables à distance (HTML, metas, canonique, JSON-LD, robots.txt, sitemap, llms.txt) sur n’importe quel domaine ; les contrôles basés sur la base WordPress (metas Rank Math, auteurs) ne s’appliquent qu’au site local.

## Structure

- cloux-seo.php — bootstrap, options, activation, cron
- includes/class-cs-audit.php — moteur de contrôles et scoring
- includes/class-cs-remote.php — audit multi-domaines
- includes/class-cs-schema.php — JSON-LD
- includes/class-cs-geo.php — llms.txt, ai-catalog.json, robots.txt IA
- includes/class-cs-eeat.php — signaux E-E-A-T
- includes/class-cs-report.php — tableaux et exports
- includes/class-cs-admin.php — pages admin, AJAX, réglages

## Sécurité

Vérifications de nonce et capability manage_options sur toutes les actions AJAX et exports. Sorties échappées (esc_html, esc_attr, esc_url). Aucune donnée ne quitte le site (aucun appel API externe).

## Roadmap 1.1

- Suivi des positions et des backlinks
- Rapport PDF
- Alertes email en cas de chute de score
- Historique des scores par domaine

## Licence

GPL-2.0-or-later. Auteur : OCLOUX — https://www.cloux.net
