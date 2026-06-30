<?php

namespace CustomFields\Twig;

use CustomFields\Model\CustomFieldImage;
use CustomFields\Model\CustomFieldImageQuery;
use CustomFields\Service\CustomFieldService;
use CustomFields\Service\ImageService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomFieldsTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CustomFieldService $customFieldService,
        private readonly ImageService $imageService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('custom_field_value', [$this, 'getCustomFieldValue']),
            new TwigFunction('custom_field_image', [$this, 'getCustomFieldImage']),
            new TwigFunction('custom_field_images', [$this, 'getCustomFieldImages']),
            new TwigFunction('custom_field_repeater', [$this, 'getRepeaterValues']),
        ];
    }

    /**
     * Return the images attached to a custom field value, as a list of {id, url}.
     * Reproduces the legacy custom_field_image_loop for the Twig back-office.
     *
     * @return array<int, array{id: int, url: string}>
     */
    public function getCustomFieldImages(?int $customFieldValueId): array
    {
        if ($customFieldValueId === null) {
            return [];
        }

        $images = CustomFieldImageQuery::create()
            ->filterByCustomFieldValueId($customFieldValueId)
            ->find();

        $result = [];
        /** @var CustomFieldImage $image */
        foreach ($images as $image) {
            [$fileUrl] = $this->imageService->imageProcess($image, false, 'none');
            $result[] = [
                'id' => $image->getId(),
                'url' => $fileUrl ?? '',
            ];
        }

        return $result;
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
            $locale = $this->resolveCurrentLocale();
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
            $locale = $this->resolveCurrentLocale();
        }

        $imageId = $this->customFieldService->getCustomFieldValue($code, $source, $sourceId, $locale);

        return $imageId !== null ? (int) $imageId : null;
    }

    public function getRepeaterValues(string $code, ?string $source = 'general', ?int $sourceId = null, ?string $locale = null): array
    {
        if ($locale === null) {
            $locale = $this->resolveCurrentLocale();
        }

        return $this->customFieldService->getRepeaterValues($code, $source, $sourceId, $locale);
    }

    /**
     * Resolve the current locale from the session, falling back to the default
     * language locale in sessionless contexts (CLI, error pages).
     */
    private function resolveCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request && $request->hasSession()) {
            return $request->getSession()->getLang()->getLocale();
        }

        return \Thelia\Model\LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US';
    }
}
