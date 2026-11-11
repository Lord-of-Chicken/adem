# Plan de travail — Ruelle d'Adem

Lots A→F (audit 2026-06) et audit backend / reçus / optimisations (2026-10) : terminés, voir `tasks.md`. Definition of Done : phpstan 0 erreur + phpunit vert + lint container/twig/yaml.

## Reste à faire
- [ ] Déploiement OVH : débloquer l'accès SSH, puis suivre `docs/DEPLOY-OVH-2026-10-07.md` (accord explicite de Gaël requis).
- [ ] Merge `audit/backend-2026-10-06` → `main` (accord requis).
- [ ] Activer les reçus en production après vérification SMTP.
- [ ] Textes légaux : placeholders et traductions EN/NL (humain).
- [ ] Newsletter : router le toggle profil vers `NewsletterSubscriptionService::startDoubleOptIn()`.
- [ ] Anonymisation des metadata Stripe (`donor_name`) si exigée.
- [ ] Carousel admin non paginé (drag-reorder sur `sortOrder` global) : décision différée.
- Différé (sur-ingénierie) : panier session→DB, abstraction PaymentGateway.
