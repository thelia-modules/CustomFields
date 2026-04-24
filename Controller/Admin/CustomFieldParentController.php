<?php

declare(strict_types=1);

namespace CustomFields\Controller\Admin;

use CustomFields\Form\CustomFieldParentForm;
use CustomFields\Model\CustomFieldParent;
use CustomFields\Model\CustomFieldParentQuery;
use Propel\Runtime\Exception\PropelException;
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
use Thelia\Tools\URL;

#[AsController]
#[Route(path: '/admin/module/customfields/parent', name: 'customfields_parent_')]
final class CustomFieldParentController extends BaseAdminController
{
    #[Route(path: '/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(CustomFieldParentForm::getName(), FormType::class);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            $parent = new CustomFieldParent();
            $parent
                ->setTitle($validatedForm->get('title')->getData())
                ->setSource($validatedForm->get('source')->getData())
                ->save();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'success' => Translator::getInstance()->trans('Group created successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while saving the group', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display empty form
        }

        return $this->render('custom-field-parent-form', [
            'error' => $error,
            'parent' => null,
        ]);
    }

    #[Route(path: '/update/{id}', name: 'update', methods: ['GET', 'POST'])]
    public function update(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::UPDATE)) {
            return $response;
        }

        $parent = CustomFieldParentQuery::create()->findPk($id);

        if (!$parent) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Group not found', [], 'customfields'),
                ])
            );
        }

        $form = $this->createForm(CustomFieldParentForm::getName(), FormType::class, [
            'id' => $parent->getId(),
            'title' => $parent->getTitle(),
            'source' => $parent->getSource(),
        ]);
        $this->getParserContext()->addForm($form);
        $error = null;

        try {
            $validatedForm = $this->validateForm($form);

            $parent
                ->setTitle($validatedForm->get('title')->getData())
                ->setSource($validatedForm->get('source')->getData())
                ->save();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'success' => Translator::getInstance()->trans('Group updated successfully', [], 'customfields'),
                ])
            );
        } catch (FormValidationException $e) {
            $error = $e->getMessage();
        } catch (PropelException $e) {
            $error = Translator::getInstance()->trans('An error occurred while updating the group', [], 'customfields');
        } catch (\Exception $e) {
            // Form not submitted, display form with current values
        }

        return $this->render('custom-field-parent-form', [
            'error' => $error,
            'parent' => $parent,
        ]);
    }

    #[Route(path: '/delete/{id}', name: 'delete', methods: ['GET'])]
    public function delete(int $id): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CustomFields', AccessManager::DELETE)) {
            return $response;
        }

        $parent = CustomFieldParentQuery::create()->findPk($id);

        if (!$parent) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('Group not found', [], 'customfields'),
                ])
            );
        }

        try {
            $parent->delete();

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'success' => Translator::getInstance()->trans('Group deleted successfully', [], 'customfields'),
                ])
            );
        } catch (PropelException $e) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl('/admin/module/customfields/list', [
                    'error' => Translator::getInstance()->trans('An error occurred while deleting the group. Make sure no fields are associated with it.', [], 'customfields'),
                ])
            );
        }
    }
}
