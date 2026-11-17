<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Media\Models\Media;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

final class CatalogPdfService
{
    public function generate(Catalog $catalog): string
    {
        $products = $catalog->products()
            ->where('products.is_active', true)
            ->with(['primaryImage', 'activeVariants'])
            ->orderBy('products.name')
            ->get()
            ->map(function ($product): array {
                $media = $product->primaryImage;

                return [
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $media instanceof Media ? $this->imageDataUri($media) : null,
                    'variants' => $product->activeVariants
                        ->map(static fn ($variant): string => $variant->label)
                        ->all(),
                ];
            });

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('backoffice.catalogs.pdf', [
            'catalog' => $catalog,
            'products' => $products,
            'generatedAt' => now(),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function imageDataUri(Media $media): ?string
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            return null;
        }

        $contents = $disk->get($media->path);
        $mime = $media->mime_type;

        if ($mime === 'image/webp') {
            if (! function_exists('imagecreatefromstring')) {
                return null;
            }

            $image = @imagecreatefromstring($contents);

            if (! ($image instanceof \GdImage)) {
                return null;
            }

            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);

            if (! is_string($png) || $png === '') {
                return null;
            }

            $contents = $png;
            $mime = 'image/png';
        }

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
