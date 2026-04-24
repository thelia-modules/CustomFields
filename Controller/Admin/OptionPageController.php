<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\OptionPageForm;
use CustomFields\Model\CustomFieldOptionPage;
use CustomFields\Model\CustomFieldOptionPageQuery;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldRepeaterRowQuery;
use CustomFields\Model\CustomFieldValueQuery;
use CustomFields\Service\CustomFieldSortingService;
use CustomFields\Service\ImageService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

#[AsController]
#[Route(path: '/admin/module/customfields/option-pages', name: 'customfields_option_pages_')]
final class OptionPageController extends BaseAdminController
{
    private ImageService $imageService;

    public function __construct(
        private readonly CustomFieldSortingService $sortingService,
        private readonly EventDispatcherInterface $dispatcher
    ) {
        $this->imageService = new ImageService($this->dispatcher);
    }

    #[Route(path: '', name: 'list')]
    public function listOptionPages(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        $optionPages = CustomFieldOptionPageQuery::create()->orderByTitle()->find();

        return $this->render('option-pages-list', [
            'option_pages' => $optionPages,
        ]);
    }

    #[Route(path: '/create', name: 'create', methods: ['POST'])]
    public function createOptionPage(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(OptionPageForm::getName());

        try {
            $validatedForm = $this->validateForm($form);

            $optionPage = new CustomFieldOptionPage();
            $optionPage
                ->setTitle($validatedForm->get('title')->getData())
                ->setCode($validatedForm->get('code')->getData())
                ->save();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'success' => Translator::getInstance()->trans('Option page created successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'error' => $e->getMessage(),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'error' => Translator::getInstance()->trans('An error occurred. The code may already exist.', [], 'customfields'),
                ])
            );
        }
    }

    #[Route(path: '/delete/{id}', name: 'delete', methods: ['GET'])]
    public function deleteOptionPage(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $optionPage = CustomFieldOptionPageQuery::create()->findPk($id);

        if (!$optionPage) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'error' => Translator::getInstance()->trans('Option page not found', [], 'customfields'),
                ])
            );
        }

        try {
            $optionPage->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'success' => Translator::getInstance()->trans('Option page deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'current_tab' => 'option_pages',
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the option page', [], 'customfields'),
                ])
            );
        }
    }

    #[Route(path: '/view/{id}', name: 'view')]
    public function viewOptionPage(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        $optionPage = CustomFieldOptionPageQuery::create()->findPk($id);

        if (!$optionPage) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/option-pages', [
                    'error' => Translator::getInstance()->trans('Option page not found', [], 'customfields'),
                ])
            );
        }

        $source = 'option_page_' . $optionPage->getCode();
        $editLanguageId = (int) $this->getRequest()->query->get('edit_language_id', $this->getSession()->getLang()->getId());
        $locale = LangQuery::create()->findOneById($editLanguageId)->getLocale();

        // Get custom fields assigned to this option page source
        $customFields = CustomFieldQuery::create()
            ->useCustomFieldSourceQuery()
                ->filterBySource($source)
            ->endUse()
            ->find();

        $values = [];
        $valueIds = [];
        foreach ($customFields as $customField) {
            $value = CustomFieldValueQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->filterBySource($source)
                ->filterByRepeaterRowId(null)
                ->findOne();

            if (
                $value &&
                (in_array($customField->getType(), CustomFieldValueController::CUSTOM_FIELD_SIMPLE_VALUES)
                || !$customField->getIsInternational())
            ) {
                $values[$customField->getId()] = $value->getSimpleValue() ?? '';
                $valueIds[$customField->getId()] = $value->getId();
            } elseif ($value) {
                $value->setLocale($locale);
                $values[$customField->getId()] = $value->getValue();
                $valueIds[$customField->getId()] = $value->getId();
            } else {
                $values[$customField->getId()] = '';
                $valueIds[$customField->getId()] = null;
            }
        }

        $groupedFields = $this->sortingService->groupByParent($customFields);

        $repeaterValues = [];
        $repeaterSubfields = [];

        foreach ($customFields as $customField) {
            if ($customField->getType() !== 'repeater') {
                continue;
            }

            $repeaterId = $customField->getId();

            $subFields = CustomFieldQuery::create()
                ->filterByCustomFieldParentId($repeaterId)
                ->orderByPosition()
                ->find();

            $repeaterSubfields[$repeaterId] = $subFields;

            $rows = CustomFieldRepeaterRowQuery::create()
                ->filterByCustomFieldId($repeaterId)
                ->filterBySource($source)
                ->filterBySourceId(null)
                ->orderByPosition()
                ->find();

            $rowData = [];
            foreach ($rows as $row) {
                $rowId = $row->getId();
                $rowValues = [];

                foreach ($subFields as $subField) {
                    $value = CustomFieldValueQuery::create()
                        ->filterByCustomFieldId($subField->getId())
                        ->filterBySource($source)
                        ->filterBySourceId(null)
                        ->filterByRepeaterRowId($rowId)
                        ->findOne();

                    if ($value) {
                        if ($subField->getType() === 'image') {
                            $image = $value->getCustomFieldImages()->getFirst();
                            if ($image && $image->getFile()) {
                                [$fileUrl] = $this->imageService->imageProcess($image, false, 'none');
                                $rowValues[$subField->getId()] = [
                                    'id'  => $image->getId(),
                                    'url' => $fileUrl ?? '',
                                ];
                            } else {
                                $rowValues[$subField->getId()] = null;
                            }
                        } elseif (
                            in_array($subField->getType(), ['content', 'category', 'folder', 'product'])
                            || !$subField->isInternational()
                        ) {
                            $rowValues[$subField->getId()] = $value->getSimpleValue();
                        } else {
                            $value->setLocale($locale);
                            $rowValues[$subField->getId()] = $value->getValue();
                        }
                    } else {
                        $rowValues[$subField->getId()] = '';
                    }
                }

                $rowData[] = ['__row_id' => $rowId] + $rowValues;
            }

            $repeaterValues[$repeaterId] = $rowData;
        }

        return $this->render('option-page-view', [
            'option_page' => $optionPage,
            'custom_fields' => $customFields,
            'grouped_fields' => $groupedFields,
            'values' => $values,
            'value_ids' => $valueIds,
            'repeater_values' => $repeaterValues,
            'repeater_subfields' => $repeaterSubfields,
            'source' => $source,
            'edit_language_id' => $editLanguageId,
        ]);
    }
}
