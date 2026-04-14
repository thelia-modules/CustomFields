<?php

namespace CustomFields\Model;

use CustomFields\CustomFields;
use CustomFields\Model\Base\CustomFieldImage as BaseCustomFieldImage;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;

/**
 * Skeleton subclass for representing a row from the 'custom_field_image' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CustomFieldImage extends BaseCustomFieldImage implements FileModelInterface
{
    public function setParentId($parentId): static
    {
        $this->setCustomFieldValueId($parentId);
        return $this;
    }

    public function getParentId(): int
    {
        return $this->getCustomFieldValueId();
    }

    public function getParentFileModel(): CustomFieldImage
    {
        return new static();
    }

    public function getUpdateFormId(): string
    {
        return 'custom_field_image';
    }

    public function getUploadDir(): string
    {
        return THELIA_LOCAL_DIR . 'media' . DS . 'images' . DS . 'customField';
    }

    public function getRedirectionUrl(): string
    {
        return '/admin/module/CustomFields';
    }

    public function getQueryInstance(): CustomFieldImageQuery|ModelCriteria
    {
        return CustomFieldImageQuery::create();
    }

    public function getFile(): string
    {
        return parent::getFile() ?? '';
    }

    public function setTitle($title)
    {
        // TODO: Implement setTitle() method.
    }

    public function getTitle()
    {
        return null;
    }

    public function setChapo($chapo)
    {
        // TODO: Implement setChapo() method.
    }

    public function setDescription($description)
    {
        // TODO: Implement setDescription() method.
    }

    public function setPostscriptum($postscriptum)
    {
        // TODO: Implement setPostscriptum() method.
    }

    public function setLocale($locale)
    {
        // TODO: Implement setLocale() method.
    }

    public function setVisible($visible)
    {
        // TODO: Implement setVisible() method.
    }

    public function getDescription(): null
    {
        return null;
    }
    public function getChapo(): null
    {
        return null;
    }
    public function getPostscriptum(): null
    {
        return null;
    }
}
