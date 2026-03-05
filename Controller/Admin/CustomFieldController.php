<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\CustomFieldForm;
use CustomFields\Model\CustomField;
use CustomFields\Model\CustomFieldQuery;
use CustomFields\Model\CustomFieldSource;
use CustomFields\Model\CustomFieldSourceQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

final class CustomFieldController extends BaseAdminController
{
    #[Route(path: '/admin/module/customfields', name: 'customfields-list')]
    public function listCustomFields(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::VIEW)) {
            return $response;
        }

        $customFields = CustomFieldQuery::create()->find();

        return $this->render('custom-field-list', [
            'custom_fields' => $customFields,
        ]);
    }

    #[Route(path: '/admin/module/customfields/create', name: 'customfields-create', methods: ['GET', 'POST'])]
    public function createCustomField(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(CustomFieldForm::getName());
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            $customField = new CustomField();
            $customField
                ->setTitle($validatedForm->get('title')->getData())
                ->setCode($validatedForm->get('code')->getData())
                ->setType($validatedForm->get('type')->getData())
                ->save();

            // Save sources
            $sources = $validatedForm->get('sources')->getData();
            if (is_array($sources)) {
                foreach ($sources as $source) {
                    $customFieldSource = new CustomFieldSource();
                    $customFieldSource
                        ->setCustomFieldId($customField->getId())
                        ->setSource($source)
                        ->save();
                }
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field created successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while saving the custom field', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display empty form
        }

        return $this->render('custom-field-form', [
            'error' => $error,
            'custom_field' => null,
        ]);
    }

    #[Route(path: '/admin/module/customfields/update/{id}', name: 'customfields-update', methods: ['GET', 'POST'])]
    public function updateCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::UPDATE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        // Get existing sources
        $existingSources = CustomFieldSourceQuery::create()
            ->filterByCustomFieldId($id)
            ->find();
        $sourcesArray = [];
        foreach ($existingSources as $existingSource) {
            $sourcesArray[] = $existingSource->getSource();
        }

        $form = $this->createForm(CustomFieldForm::getName(), FormType::class, [
            'id' => $customField->getId(),
            'title' => $customField->getTitle(),
            'code' => $customField->getCode(),
            'type' => $customField->getType(),
            'sources' => $sourcesArray,
        ]);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            $customField
                ->setTitle($validatedForm->get('title')->getData())
                ->setCode($validatedForm->get('code')->getData())
                ->setType($validatedForm->get('type')->getData())
                ->save();

            // Delete existing sources and save new ones
            CustomFieldSourceQuery::create()
                ->filterByCustomFieldId($customField->getId())
                ->delete();

            $sources = $validatedForm->get('sources')->getData();
            if (is_array($sources)) {
                foreach ($sources as $source) {
                    $customFieldSource = new CustomFieldSource();
                    $customFieldSource
                        ->setCustomFieldId($customField->getId())
                        ->setSource($source)
                        ->save();
                }
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field updated successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while updating the custom field', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display form with current values
        }

        return $this->render('custom-field-form', [
            'error' => $error,
            'custom_field' => $customField,
        ]);
    }

    #[Route(path: '/admin/module/customfields/delete/{id}', name: 'customfields-delete', methods: ['GET'])]
    public function deleteCustomField(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $customField = CustomFieldQuery::create()->findPk($id);

        if (!$customField) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('Custom field not found', [], 'customfields'),
                ])
            );
        }

        try {
            $customField->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'success' => Translator::getInstance()->trans('Custom field deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields', [
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the custom field', [], 'customfields'),
                ])
            );
        }
    }
}
