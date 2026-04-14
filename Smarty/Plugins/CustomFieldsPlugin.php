<?php

namespace CustomFields\Smarty\Plugins;

use CustomFields\Service\CustomFieldService;
use Symfony\Component\HttpFoundation\RequestStack;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class CustomFieldsPlugin extends AbstractSmartyPlugin
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'custom_field_value', $this, 'getCustomFieldValue')
        ];
    }

    public function getCustomFieldValue(array $params, \Smarty_Internal_Template $smarty) {
        $code = $params['code'];
        $source = $params['source'] ?? 'general';
        $sourceId = $params['source_id'] ? (int) $params['source_id'] : null;
        $locale = $params['locale'] ?? null;
        // If no locale provided, use current session locale
        if ($locale === null) {
            $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
        }
        return $this->customFieldService->getCustomFieldValue($code, $source, $sourceId, $locale);
    }
}
