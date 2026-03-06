<?php

namespace CustomFields\Hook;

use CustomFields\CustomFields;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Service\CustomFieldSortingService;
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
        private readonly CustomFieldSortingService $sortingService
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
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('product')
                ->filterBySourceId($productId)
                ->findOne();

            if ($value) {
                $value->setLocale($locale);
                $values[$customField->getId()] = $value->getValue();
            } else {
                $values[$customField->getId()] = '';
            }
        }

        // Group fields by parent
        $groupedFields = $this->sortingService->groupByParent($customFields);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
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
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('content')
                ->filterBySourceId($contentId)
                ->findOne();

            if ($value) {
                $value->setLocale($locale);
                $values[$customField->getId()] = $value->getValue();
            } else {
                $values[$customField->getId()] = '';
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
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
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('category')
                ->filterBySourceId($categoryId)
                ->findOne();

            if ($value) {
                $value->setLocale($locale);
                $values[$customField->getId()] = $value->getValue();
            } else {
                $values[$customField->getId()] = '';
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
                    'source' => 'category',
                    'source_id' => $categoryId,
                    'edit_language_id' => $editLanguageId,
                    'page_url' => '/admin/categories/update/'.$categoryId,
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
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource('folder')
                ->filterBySourceId($folderId)
                ->findOne();

            if ($value) {
                $value->setLocale($locale);
                $values[$customField->getId()] = $value->getValue();
            } else {
                $values[$customField->getId()] = '';
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        $event->add([
            'id' => 'custom_fields',
            'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            'content' => $this->render(
                'custom-fields-tab.html',
                [
                    'custom_fields' => $customFields,
                    'grouped_fields' => $groupedFields,
                    'values' => $values,
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
