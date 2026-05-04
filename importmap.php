<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'home' => [
        'path' => './assets/js/home.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'cropperjs' => [
        'path' => './assets/vendor/cropperjs/cropperjs.index.js',
    ],
    '@cropper/utils' => [
        'path' => './assets/vendor/@cropper/utils/utils.index.js',
    ],
    '@cropper/elements' => [
        'path' => './assets/vendor/@cropper/elements/elements.index.js',
    ],
    '@cropper/element' => [
        'path' => './assets/vendor/@cropper/element/element.index.js',
    ],
    '@cropper/element-canvas' => [
        'path' => './assets/vendor/@cropper/element-canvas/element-canvas.index.js',
    ],
    '@cropper/element-crosshair' => [
        'path' => './assets/vendor/@cropper/element-crosshair/element-crosshair.index.js',
    ],
    '@cropper/element-grid' => [
        'path' => './assets/vendor/@cropper/element-grid/element-grid.index.js',
    ],
    '@cropper/element-handle' => [
        'path' => './assets/vendor/@cropper/element-handle/element-handle.index.js',
    ],
    '@cropper/element-image' => [
        'path' => './assets/vendor/@cropper/element-image/element-image.index.js',
    ],
    '@cropper/element-selection' => [
        'path' => './assets/vendor/@cropper/element-selection/element-selection.index.js',
    ],
    '@cropper/element-shade' => [
        'path' => './assets/vendor/@cropper/element-shade/element-shade.index.js',
    ],
    '@cropper/element-viewer' => [
        'path' => './assets/vendor/@cropper/element-viewer/element-viewer.index.js',
    ],
    'sortablejs' => [
        'path' => './assets/vendor/sortablejs/sortablejs.index.js',
    ],
    ];
