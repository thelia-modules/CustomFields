<?php

namespace CustomFields\Loop;

use CustomFields\Model\CustomFieldImage;
use CustomFields\Model\CustomFieldImageQuery;
use CustomFields\Service\ImageService;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * @method getUseTheliaLibrary()
 * @method getWidth()
 * @method getHeight()
 * @method getResizeMode()
 * @method getFormat()
 */
class CustomFieldImageLoop extends BaseLoop implements PropelSearchLoopInterface
{
    public function __construct(private ImageService $imageService)
    {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntTypeArgument('custom_field_value_id'),
            Argument::createIntListTypeArgument('ids'),
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createBooleanTypeArgument('use_thelia_library', false),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(['crop', 'borders', 'none'])
                ),
                'none'
            ),
            Argument::createAlphaNumStringTypeArgument('format')
        );
    }

    public function buildModelCriteria(): CustomFieldImageQuery
    {
        $query = CustomFieldImageQuery::create();

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $customFieldValueId = $this->getCustomFieldValueId()) {
            $query->filterByCustomFieldValueId($customFieldValueId);
        }

        if (null !== $ids = $this->getIds()) {
            $query->filterById($ids, Criteria::IN);
        }

        return $query;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var CustomFieldImage $customFieldImage */
        foreach ($loopResult->getResultDataCollection() as $customFieldImage) {
            $loopResultRow = new LoopResultRow($customFieldImage);

            [$fileUrl, $originalFileUrl] = $this->imageService->imageProcess(
                customFieldImage: $customFieldImage,
                useTheliaLibrary: $this->getUseTheliaLibrary(),
                resizeMode: $this->getResizeMode(),
                width: $this->getWidth(),
                height: $this->getHeight(),
                format: $this->getFormat(),
            );

            $uploadDir = $customFieldImage->getUploadDir();
            $file = $customFieldImage->getFile();
            $webPath = str_replace(THELIA_LOCAL_DIR, '/local/', $uploadDir);
            $imageUrl = $webPath . '/' . $file;

            $loopResultRow
                ->set('ID', $customFieldImage->getId())
                ->set('CUSTOM_FIELD_VALUE_ID', $customFieldImage->getCustomFieldValueId())
                ->set('FILE', $file)
                ->set('CUSTOM_FIELD_IMAGE_URL', $imageUrl)
                ->set('IMAGE_URL', $fileUrl)
                ->set('ORIGINAL_IMAGE_URL', $originalFileUrl)
                ->set('UPLOAD_DIR', $uploadDir)
                ->set('UPDATE_AT', $customFieldImage->getUpdatedAt()?->format('Y-m-d'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
