<?php

namespace Tests\Unit;

use App\Services\ProductImageService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductImageServiceTest extends TestCase
{
    #[Test]
    public function it_uses_same_origin_paths_for_stored_product_images(): void
    {
        $service = new ProductImageService;

        $this->assertSame(
            '/storage/product-images/combo.jpg',
            $service->resolveWebUrl('product-images/combo.jpg')
        );
    }

    #[Test]
    public function it_keeps_external_product_image_urls_unchanged(): void
    {
        $service = new ProductImageService;
        $url = 'https://cdn.example.com/products/combo.jpg';

        $this->assertSame($url, $service->resolveWebUrl($url));
    }
}
