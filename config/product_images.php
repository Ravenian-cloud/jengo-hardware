<?php

function product_image_url(?string $photo): string
{
    $publicDirectory = __DIR__ . '/../public';

    if ($photo !== null && strpos($photo, 'uploads/products/') === 0) {
        $filename = substr($photo, strlen('uploads/products/'));
        if ($filename === basename($filename) && is_file($publicDirectory . '/uploads/products/' . $filename)) {
            return 'uploads/products/' . $filename;
        }
    }

    if ($photo !== null && strpos($photo, 'images/products/') === 0) {
        $filename = substr($photo, strlen('images/products/'));
        if ($filename === basename($filename) && is_file($publicDirectory . '/images/products/' . $filename)) {
            return 'images/products/' . $filename;
        }
    }

    $legacyImages = [
        'bamburi.jpg' => 'cement-bags.jpg',
        'cable_red.jpg' => 'electrical-wires.jpg',
        'cement.jpg' => 'cement-bags.jpg',
        'grinder.jpg' => 'angle-grinder.jpg',
        'hammer.jpg' => 'hammer.jpg',
        'paint_black.jpg' => 'paint-buckets.jpg',
        'paint_white.jpg' => 'paint-buckets.jpg',
        'ppr_pipe.jpg' => 'ppr-pipe.jpg',
        'ppr_tee.jpg' => 'ppr-fittings.jpg',
    ];
    $filename = $photo === null ? '' : basename($photo);
    $imageFilename = $legacyImages[$filename] ?? 'hardware.svg';
    $imagePath = 'images/products/' . $imageFilename;

    return is_file($publicDirectory . '/' . $imagePath) ? $imagePath : 'images/products/hardware.svg';
}

function product_image_for_item(string $name, string $category): string
{
    $item = strtolower($name . ' ' . $category);

    if (preg_match('/cement|concrete|mortar/', $item)) {
        $image = 'cement-bags.jpg';
    } elseif (preg_match('/paint|emulsion|gloss/', $item)) {
        $image = 'paint-buckets.jpg';
    } elseif (preg_match('/hammer/', $item)) {
        $image = 'hammer.jpg';
    } elseif (preg_match('/grinder/', $item)) {
        $image = 'angle-grinder.jpg';
    } elseif (preg_match('/tee|fitting|elbow|connector/', strtolower($name))) {
        $image = 'ppr-fittings.jpg';
    } elseif (preg_match('/pipe|ppr|plumbing/', $item)) {
        $image = 'ppr-pipe.jpg';
    } elseif (preg_match('/cable|wire|electrical/', $item)) {
        $image = 'electrical-wires.jpg';
    } else {
        $image = 'hardware-tools.jpg';
    }

    return 'images/products/' . $image;
}
