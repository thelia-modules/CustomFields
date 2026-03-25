<?php

namespace CustomFields\Model;

use CustomFields\Model\Base\CustomFieldImageQuery as BaseCustomFieldImageQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\UnexpectedValueException;

/**
 * Skeleton subclass for performing query and update operations on the 'custom_field_image' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CustomFieldImageQuery extends BaseCustomFieldImageQuery
{
    public function filterByVisible(?int $visible = null)
    {
        return $this;
    }
    public function orderBy(string $columnName, string $order = Criteria::ASC)
    {
        if ($columnName === 'Position') {
            return $this;
        }
       $this->orderBy($columnName, $order);
    }

}
