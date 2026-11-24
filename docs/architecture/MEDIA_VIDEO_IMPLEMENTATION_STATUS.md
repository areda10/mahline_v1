# MEDIA — socle vidéo V1 et préparation VPS

## État de cette étape

Cette étape ajoute un socle isolé et testable localement pour inspecter les métadonnées vidéo réelles. Elle ne constitue pas encore l'intégration complète du téléversement et de la diffusion vidéo dans MEDIA.

Éléments ajoutés :
- contrat injectable `MediaVideoInspector` ;
- implémentation système `FfprobeMediaVideoInspector`, sans interpolation shell des chemins ;
- validation vidéo MP4, MOV et WebM, avec contrôle MIME réel, conteneur, durée, dimensions et poids ;
- tests unitaires utilisant un faux inspecteur : aucun binaire FFmpeg/FFprobe n'est requis pour ces tests ;
- configuration des chemins FFmpeg/FFprobe et des limites.

## Test local

Depuis la racine du projet :

```bash
php artisan test tests/Framework/Domains/Media/Services/MediaVideoValidationServiceTest.php
```

Les tests de validation utilisent un double de `MediaVideoInspector`. Ils ne lancent donc pas FFprobe et doivent pouvoir être exécutés avant l'achat ou la configuration du VPS.

## Configuration réservée au VPS

Ne pas considérer les binaires comme installés. Au moment de la mise en service du VPS, installer et vérifier FFmpeg/FFprobe depuis les dépôts adaptés à la distribution choisie, puis renseigner dans l'environnement :

```dotenv
MEDIA_FFMPEG_BINARY=/usr/bin/ffmpeg
MEDIA_FFPROBE_BINARY=/usr/bin/ffprobe
MEDIA_VIDEO_PROBE_TIMEOUT=15
MEDIA_VIDEO_MAX_BYTES=20971520
MEDIA_VIDEO_MAX_DURATION=60
MEDIA_VIDEO_MAX_RESOLUTION=1080
MEDIA_VIDEO_MAX_ATTEMPTS=3
MEDIA_VIDEO_ORIGINAL_RETENTION_HOURS=24
```

Les chemins ci-dessus sont des valeurs indicatives à confirmer sur le VPS ; ne pas les recopier sans vérifier le résultat de `command -v ffmpeg` et `command -v ffprobe`.

La configuration opérationnelle devra aussi couvrir :
- worker Laravel dédié ou capacité réservée aux conversions vidéo ;
- gestionnaire de processus (Supervisor ou systemd) et redémarrage après déploiement ;
- planificateur Laravel pour la purge des originaux après 24 heures ;
- limites CPU, mémoire, durée d'exécution et espace temporaire ;
- permissions minimales du compte système et stockage privé ;
- tests d'intégration réels de conversion MP4 H.264/AAC et WebM VP9, puis lecture avec FFprobe ;
- surveillance des échecs et vérification du nettoyage des fichiers temporaires.

## Travail restant avant clôture MEDIA V1

- intégrer la catégorie vidéo au modèle et au flux de téléversement MEDIA sans rendre l'original public avant autorisation ;
- ajouter les champs d'état de conversion et une migration additive (ne pas modifier la migration MEDIA déjà exécutée) ;
- créer le job asynchrone, générer les deux rendus MP4 H.264/AAC et WebM VP9, vérifier leur lecture et exposer les URLs contrôlées ;
- conserver l'original jusqu'à validation puis le purger 24 heures plus tard ;
- implémenter trois tentatives automatiques et la relance manuelle autorisée ;
- ajouter tests du cycle de vie, des échecs, de la concurrence, de la purge et des autorisations ;
- exécuter localement toute la suite PHPUnit et Pint ; exécuter les tests d'intégration FFmpeg sur le VPS.

L'antivirus et la quarantaine restent hors de MEDIA V1 conformément au périmètre validé ; ils ne sont pas inclus dans ce socle.
