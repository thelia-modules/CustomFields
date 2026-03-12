<?php

namespace CustomFields\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;
use Thelia\Form\BaseForm;

class CustomFieldImportForm extends BaseForm
{

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'json_file',
                FileType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('JSON File'),
                    'label_attr' => [
                        'for' => 'json_file',
                    ],
                    'constraints' => [
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/json',
                                'text/plain',
                            ],
                        ])
                    ],
                ]
            )
        ;
    }

    public static function getName(): string
    {
        return 'customfields_form_import';
    }
}
