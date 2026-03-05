<?php

declare(strict_types=1);

namespace CustomFields\Form;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;

final class CustomFieldValueForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'source_id',
                HiddenType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'source',
                HiddenType::class,
                [
                    'required' => true,
                ]
            );
    }

    public static function getName(): string
    {
        return 'customfields_form_custom_field_value';
    }
}
