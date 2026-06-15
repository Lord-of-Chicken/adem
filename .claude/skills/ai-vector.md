# skills/ai-vector.md

## Rôle
Gérer le moteur de matching visuel entre les animaux perdus et trouvés[cite: 2, 3].

## Stack Technique
* **Modèle :** OpenAI CLIP pour la génération d'embeddings[cite: 2, 3].
* **Backend :** FastAPI (Python) pour le microservice de traitement[cite: 2, 3].
* **Stockage :** Extension `pgvector` dans PostgreSQL pour la recherche vectorielle[cite: 2, 3].

## Flux de Travail
1. Réception de l'image via Symfony[cite: 2, 3].
2. Génération du vecteur d'embedding[cite: 2, 3].
3. Calcul de la similarité cosinus en base de données[cite: 2, 3].
4. Retour des résultats classés par score de confiance[cite: 2, 3].
