# Performance TODO

## Images / OG preview (Lot E — fait partiellement)

- **og-home-preview** : la source PNG faisait ~1,1 Mo. Généré une version JPEG
  compressée `public/img/Social/og-home-preview.jpg` (~199 Ko, qualité 60 via
  `sips`, 1200x630). Toutes les références `{% block og_image %}` + `TwigGlobalsSubscriber`
  pointent désormais vers le `.jpg`. Le PNG d'origine est conservé comme source.
  - Choix du JPEG (et non WebP) : les crawlers OG (Facebook/LinkedIn/anciens Twitter)
    ne rendent pas le WebP de façon fiable pour `og:image`. JPEG = compat maximale.
  - `pngquant` n'était pas installé sur la machine ; `cwebp` l'était mais écarté
    pour la raison ci-dessus.

### À faire (différé)
- Optionnel : régénérer un WebP/AVIF additionnel pour l'usage `<img>` interne
  (pas OG) si on ajoute des previews dans le site.
- Si `pngquant`/`optipng` deviennent dispo, recompresser les PNG restants du
  dossier `public/img/` (audit taille à faire).
- Envisager un pipeline AssetMapper/build pour générer automatiquement les
  variantes responsives (srcset) de la galerie.

## Galerie / CLS
- `.gallery-item img` : passé de `height` fixe à `aspect-ratio: 1 / 1` +
  `object-fit: cover` (CSS `assets/styles/ruelle.css`). Les `<img>` portent déjà
  `width`/`height` + `loading="lazy"` + `decoding="async"`.

## Cache galerie
- `MediaItemRepository::findPublishedOrdered()` utilise un result cache Doctrine
  (`enableResultCache(3600, 'media_published')`). Le pool `doctrine.result_cache_pool`
  n'est câblé qu'en `when@prod` ; en dev/test le cache est un no-op (ArrayAdapter
  par requête). Invalidation automatique sur save/remove/updateSortOrders via
  `Configuration::getResultCache()->deleteItem('media_published')`.
