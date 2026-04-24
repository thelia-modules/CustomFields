<?php

namespace CustomFields\Hook;

use CustomFields\CustomFields;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Service\CustomFieldSortingService;
use CustomFields\Service\RepeaterDataLoaderService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

class TabHook extends BaseHook
{
    public function __construct(
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
        private readonly CustomFieldSortingService $sortingService,
        private readonly RepeaterDataLoaderService $repeaterDataLoader
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onProductTab(HookRenderBlockEvent $event): void
    {
        $productId = (int) $event->getArgument('id');
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());
        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();
        /** @var Product $product */
        $product = ProductQuery::create()
            ->findOneById($productId);

        if (!$product) {
            return;
        }

        // Get custom fields for product source
        $customFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
                ->filterBySource('product')
            ->endUse()
            ->find();

        // Get existing values for the current language
        $values = [];
        $valueIds = [];
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('product')
                ->filterBySourceId($productId)
                ->filterByRepeaterRowId(null)
                ->findOne();

            if ($value) {
                if (
                    in_array($customField->getType(), ['content', 'category', 'folder', 'product', 'image'])
                    || !$customField->isInternational()
                ) {
                    $values[$customField->getId()] = $value->getSimpleValue() ?? '';
                } else {
                    $value->setLocale($locale);
                    $values[$customField->getId()] = $value->getValue();
                }
                $valueIds[$customField->getId()] = $value->getId();
            } else {
                $values[$customField->getId()] = '';
                $valueIds[$customField->getId()] = null;
            }
        }

        // Group fields by parent
        $groupedFields = $this->sortingService->groupByParent($customFields);

        [$repeaterValues, $repeaterSubfields] = $this->repeaterDataLoader->loadRepeaterData($customFields, 'product', $productId, $locale);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
                    'value_ids' => $valueIds,
                    'repeater_values' => $repeaterValues,
                    'repeater_subfields' => $repeaterSubfields,
                    'source' => 'product',
                    'source_id' => $productId,
                    'edit_language_id' => $editLanguageId,
                    'page_url' => '/admin/products/update?product_id='.$productId,
                ]
            ),
        ]);
    }

    public function onContentTab(HookRenderBlockEvent $event): void
    {
        $contentId = (int) $event->getArgument('id');
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());
        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        /** @var Content $content */
        $content = ContentQuery::create()
            ->findOneById($contentId);

        if (!$content) {
            return;
        }

        $customFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
                ->filterBySource('content')
            ->endUse()
            ->find();

        $values = [];
        $valueIds = [];
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('content')
                ->filterBySourceId($contentId)
                ->filterByRepeaterRowId(null)
                ->findOne();

            if ($value) {
                if (
                    in_array($customField->getType(), ['content', 'category', 'folder', 'product', 'image'])
                    || !$customField->isInternational()
                ) {
                    $values[$customField->getId()] = $value->getSimpleValue() ?? '';
                } else {
                    $value->setLocale($locale);
                    $values[$customField->getId()] = $value->getValue();
                }
                $valueIds[$customField->getId()] = $value->getId();
            } else {
                $values[$customField->getId()] = '';
                $valueIds[$customField->getId()] = null;
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        [$repeaterValues, $repeaterSubfields] = $this->repeaterDataLoader->loadRepeaterData($customFields, 'content', $contentId, $locale);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
                    'value_ids' => $valueIds,
                    'repeater_values' => $repeaterValues,
                    'repeater_subfields' => $repeaterSubfields,
                    'source' => 'content',
                    'source_id' => $contentId,
                    'edit_language_id' => $editLanguageId,
                    'page_url' => '/admin/content/update/'.$contentId,
                ]
            ),
        ]);
    }

    public function onCategoryTab(HookRenderBlockEvent $event): void
    {
        $categoryId = (int) $event->getArgument('id');
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());
        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        /** @var Category $category */
        $category = CategoryQuery::create()
            ->findOneById($categoryId);

        if (!$category) {
            return;
        }

        $customFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
                ->filterBySource('category')
            ->endUse()
            ->find();

        $values = [];
        $valueIds = [];
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('category')
                ->filterBySourceId($categoryId)
                ->filterByRepeaterRowId(null)
                ->findOne();

            if ($value) {
                if (
                    in_array($customField->getType(), ['content', 'category', 'folder', 'product', 'image'])
                    || !$customField->isInternational()
                ) {
                    $values[$customField->getId()] = $value->getSimpleValue() ?? '';
                } else {
                    $value->setLocale($locale);
                    $values[$customField->getId()] = $value->getValue();
                }
                $valueIds[$customField->getId()] = $value->getId();
            } else {
                $values[$customField->getId()] = '';
                $valueIds[$customField->getId()] = null;
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        [$repeaterValues, $repeaterSubfields] = $this->repeaterDataLoader->loadRepeaterData($customFields, 'category', $categoryId, $locale);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
                    'value_ids' => $valueIds,
                    'repeater_values' => $repeaterValues,
                    'repeater_subfields' => $repeaterSubfields,
                    'source' => 'category',
                    'source_id' => $categoryId,
                    'edit_language_id' => $editLanguageId,
                    'page_url' => '/admin/categories/update?category_id='.$categoryId,
                ]
            ),
        ]);
    }

    public function onFolderTab(HookRenderBlockEvent $event): void
    {
        $folderId = (int) $event->getArgument('id');
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());
        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        /** @var Folder $folder */
        $folder = FolderQuery::create()
            ->findOneById($folderId);

        if (!$folder) {
            return;
        }

        $customFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
                ->filterBySource('folder')
            ->endUse()
            ->find();

        $values = [];
        $valueIds = [];
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('folder')
                ->filterBySourceId($folderId)
                ->filterByRepeaterRowId(null)
                ->findOne();

            if ($value) {
                if (
                    in_array($customField->getType(), ['content', 'category', 'folder', 'product', 'image'])
                    || !$customField->isInternational()
                ) {
                    $values[$customField->getId()] = $value->getSimpleValue() ?? '';
                } else {
                    $value->setLocale($locale);
                    $values[$customField->getId()] = $value->getValue();
                }
                $valueIds[$customField->getId()] = $value->getId();
            } else {
                $values[$customField->getId()] = '';
                $valueIds[$customField->getId()] = null;
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        [$repeaterValues, $repeaterSubfields] = $this->repeaterDataLoader->loadRepeaterData($customFields, 'folder', $folderId, $locale);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
                    'value_ids' => $valueIds,
                    'repeater_values' => $repeaterValues,
                    'repeater_subfields' => $repeaterSubfields,
                    'source' => 'folder',
                    'source_id' => $folderId,
                    'edit_language_id' => $editLanguageId,
                    'page_url' => '/admin/folders/update/'.$folderId,
                ]
            ),
        ]);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'product.tab' => [
                [
                    'type' => 'back',
                    'method' => 'onProductTab',
                ],
            ],
            'content.tab' => [
                [
                    'type' => 'back',
                    'method' => 'onContentTab',
                ],
            ],
            'category.tab' => [
                [
                    'type' => 'back',
                    'method' => 'onCategoryTab',
                ],
            ],
            'folder.tab' => [
                [
                    'type' => 'back',
                    'method' => 'onFolderTab',
                ],
            ],
        ];
    }
}
