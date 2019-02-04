<?php

declare(strict_types=1);

namespace ClickAndMortar\AkeneoRekognitionBundle\Connector\Job\JobParameters\DefaultValuesProvider;

use Pim\Bundle\EnrichBundle\Connector\Job\JobParameters\DefaultValuesProvider\ProductMassEdit;
use Pim\Component\Catalog\Query\Filter\Operators;

/**
 * DefaultParameters for add Rekognition data mass edit
 */
class AddRekognitionDataMassEdit extends ProductMassEdit
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        $defaultValues = parent::getDefaultValues();
        $defaultValues['filters'] = $this->getFilters($defaultValues);

        return $defaultValues;
    }

    /**
     * @param array $defaultValues
     * @return array
     */
    protected function getFilters(array $defaultValues): array
    {
        // A product model with a parent is a "1st variant Color"
        // See https://help.akeneo.com/articles/what-about-products-variants.html
        $defaultValues['filters'] = [
            [
                'field' => 'parent',
                'operator' => Operators::IS_NOT_EMPTY,
                'value' => true,
            ],
            [
                'field' => 'picture',
                'operator' => Operators::IS_NOT_EMPTY,
                'value' => true,
            ],
        ];
        return $defaultValues;
    }
}
