<?php

namespace CustomFields\Twig;

use CustomFields\Model\CustomFieldImage;
use CustomFields\Model\CustomFieldImageQuery;
use CustomFields\Service\CustomFieldService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomFieldsTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('custom_field_value', [$this, 'getCustomFieldValue']),
            new TwigFunction('custom_field_image', [$this, 'getCustomFieldImage']),
            new TwigFunction('custom_field_repeater', [$this, 'getRepeaterValues']),
        ];
    }

    /**
     * Get custom field value by code, source and source_id
     * Usage in Twig: {{ custom_field_value('my_field_code', 'product', product_id) }}
     * Or with specific locale: {{ custom_field_value('my_field_code', 'product', product_id, 'en_US') }}
     *
     * @param string $code The custom field code
     * @param string $source The source type (product, content, category, folder, general)
     * @param int|null $sourceId The source entity ID (no ID if general)
     * @param string|null $locale Optional locale. If not provided, uses current session locale
     * @return string|null The custom field value or null if not found
     */
    public function getCustomFieldValue(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): ?string
    {
        // If no locale provided, use current session locale
        if ($locale === null) {
            $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
        }

        return $this->customFieldService->getCustomFieldValue($code, $source, $sourceId, $locale);
    }

    /**
     * Get image ID for a custom image field, usable with custom_field_image_loop.
     * Usage: {{ custom_field_image('my_image_code', 'product', product_id) }}
     */
    public function getCustomFieldImage(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): ?int
    {
        if ($locale === null) {
            $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
        }

        $imageId = $this->customFieldService->getCustomFieldValue($code, $source, $sourceId, $locale);

        return $imageId !== null ? (int) $imageId : null;
    }

    public function getRepeaterValues(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): array
    {
        if ($locale === null) {
            $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
        }

        return $this->customFieldService->getRepeaterValues($code, $source, $sourceId, $locale);
    }
}
