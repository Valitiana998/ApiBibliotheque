# Rapport de documentation API

## Pourquoi documenter une API
Une documentation permet à toutes les équipes de comprendre les ressources disponibles, les formats d'entrée/sortie, les erreurs et les règles d'authentification sans devoir lire le code source. Elle réduit les erreurs d'intégration, facilite les tests et constitue une référence durable pour l'exploitation.

## Swagger UI et Postman
Swagger UI présente une spécification OpenAPI interactive. Il sert à décrire le contrat de l'API, à montrer les schémas et à effectuer des appels depuis le navigateur.
Postman organise les requêtes, les environnements et les tests automatisés. Il est surtout utile pour valider les parcours fonctionnels, partager des collections et exécuter des suites de tests répétables avec le Runner.

## Difficulté rencontrée
La principale difficulté concernait la vérification uniforme du JWT sans framework externe. Cette difficulté a été résolue avec un service HMAC SHA-256 qui signe les jetons, vérifie leur signature et contrôle leur expiration avant d'autoriser les routes protégées.

## Amélioration proposée
À moyen terme, l'API pourrait ajouter des rôles administrateur/lecteur, une pagination des listes, une limitation du taux de requêtes (rate limiting) et une rotation sécurisée des clés JWT.
