<?php

declare(strict_types=1);

namespace ClickAndMortar\AkeneoRekognitionBundle\Connector\Processor\MassEdit\Product;

use ClickAndMortar\Rekognition\Label;
use ClickAndMortar\Rekognition\Service\DetectService;
use ClickAndMortar\Rekognition\Text;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Product;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModel;
use Akeneo\Pim\Enrichment\Component\Product\Updater\PropertySetter;

/**
 * Class AddRekognitionDataProcessor
 */
class AddRekognitionDataProcessor extends AbstractProcessor
{
    /** @var string */
    const ATTRIBUTE_CODE_LABELS = 'rekognition_labels';

    /** @var string */
    const ATTRIBUTE_CODE_TEXTS_WORDS = 'rekognition_texts_words';

    /** @var string */
    const ATTRIBUTE_CODE_TEXTS_LINES = 'rekognition_texts_lines';

    /**
     * Rekognition attributes codes
     *
     * @var array
     */
    protected $rekognitionAttributesCodes = [
        self::ATTRIBUTE_CODE_LABELS,
        self::ATTRIBUTE_CODE_TEXTS_WORDS,
        self::ATTRIBUTE_CODE_TEXTS_LINES,
    ];

    /**
     * Loaded rekognition attributes
     *
     * @var array
     */
    protected $rekognitionAttributes = [];

    /** @var string */
    protected $catalogStorageDir;

    /** @var PropertySetter */
    protected $propertySetter;

    /** @var AttributeRepository */
    protected $attributeRepository;

    /** @var string */
    protected $awsAccessKeyId;

    /** @var string */
    protected $awsSecretAccessKey;

    /** @var int */
    protected $minimumConfidence;

    /** @var DetectService */
    protected $detectService;

    /**
     * @param string              $catalogStorageDir
     * @param PropertySetter      $propertySetter
     * @param AttributeRepository $attributeRepository
     * @param string              $awsAccessKeyId
     * @param string              $awsSecretAccessKey
     * @param int                 $minimumConfidence
     */
    public function __construct(
        string $catalogStorageDir,
        PropertySetter $propertySetter,
        AttributeRepository $attributeRepository,
        string $awsAccessKeyId,
        string $awsSecretAccessKey,
        int $minimumConfidence
    )
    {
        $this->catalogStorageDir   = $catalogStorageDir;
        $this->propertySetter      = $propertySetter;
        $this->attributeRepository = $attributeRepository;
        $this->awsAccessKeyId      = $awsAccessKeyId;
        $this->awsSecretAccessKey  = $awsSecretAccessKey;
        $this->minimumConfidence   = $minimumConfidence;

        // TODO avoid using "new" here
        $this->detectService = new DetectService(
            [
                'credentials' => [
                    'key'    => $this->awsAccessKeyId,
                    'secret' => $this->awsSecretAccessKey,
                ],
            ]
        );

        $this->rekognitionAttributes = $this->getRekognitionAttributes();
    }

    /**
     * @param Product | ProductModel $product
     *
     * @return Product | ProductModel
     */
    public function process($product)
    {
        // Check if product has editable rekognition attributes
        if (!$this->hasEditableRekognitionAttributes($product)) {
            return $product;
        }

        // Check image attribute
        $filePath = $this->getImageFilePath($product);
        if ($filePath === null || !file_exists($filePath)) {
            return $product;
        }

        // Get data from AWS Rekognition
        $filePointerImage = fopen($filePath, 'r');
        $image            = fread($filePointerImage, filesize($filePath));
        fclose($filePointerImage);
        $rekognitionImage = $this->detectService->detect($image);
        $labels           = $rekognitionImage->getLabels($this->minimumConfidence);

        // And set data in attributes
        $this->propertySetter->setData(
            $product,
            self::ATTRIBUTE_CODE_LABELS,
            implode("\n", $this->getLabelsToStore($labels)),
            ['locale' => null, 'scope' => null]
        );

        $texts = $rekognitionImage->getTexts($this->minimumConfidence);
        $this->propertySetter->setData(
            $product,
            self::ATTRIBUTE_CODE_TEXTS_WORDS,
            implode("\n", $this->getTextsToStore($texts, Text::TYPE_WORD)),
            ['locale' => null, 'scope' => null]
        );

        $this->propertySetter->setData(
            $product,
            self::ATTRIBUTE_CODE_TEXTS_LINES,
            implode("\n", $this->getTextsToStore($texts, Text::TYPE_LINE)),
            ['locale' => null, 'scope' => null]
        );

        return $product;
    }

    /**
     * @param Product | ProductModel $productModel
     *
     * @return string | null
     */
    protected function getImageFilePath($product)
    {
        $imageAttribute = $product->getImage();
        if ($imageAttribute === null) {
            return null;
        }

        return implode(
            DIRECTORY_SEPARATOR,
            [
                $this->catalogStorageDir,
                $imageAttribute,
            ]
        );
    }

    /**
     * @param Label[] $labels
     *
     * @return array
     */
    protected function getLabelsToStore(array $labels): array
    {
        $labelsToStore = [];
        foreach ($labels as $label) {
            $labelsToStore[] = $label->getName();
        }

        return $labelsToStore;
    }

    /**
     * @param Text[] $texts
     * @param string $type
     *
     * @return array
     */
    protected function getTextsToStore(array $texts, string $type): array
    {
        $textsToStore = array_filter(
            $texts,
            function (Text $text) use ($type): bool {
                return $text->getType() === $type;
            }
        );

        return array_map(function (Text $text): string {
            return $text->getDetectedText();
        }, $textsToStore);
    }

    /**
     * Get rekognition attributes
     *
     * @return AttributeInterface[]
     */
    protected function getRekognitionAttributes(): array
    {
        $attributes = [];
        foreach ($this->rekognitionAttributesCodes as $rekognitionAttributeCode) {
            $attribute = $this->attributeRepository->findOneByIdentifier($rekognitionAttributeCode);
            if ($attribute !== null) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * @param Product | ProductModel $product
     *
     * @return bool
     */
    protected function hasEditableRekognitionAttributes($product): bool
    {
        foreach ($this->rekognitionAttributes as $rekognitionAttribute) {
            if (!$this->isAttributeEditable($product, $rekognitionAttribute)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param EntityWithFamilyInterface $entity
     * @param AttributeInterface        $attribute
     *
     * @return bool
     */
    protected function isAttributeEditable(EntityWithFamilyInterface $entity, AttributeInterface $attribute): bool
    {
        $family = $entity->getFamily();
        if (null === $family) {
            return true;
        }

        if (!$family->hasAttribute($attribute)) {
            return false;
        }

        if ($this->isNonVariantProduct($entity)) {
            return true;
        }

        $familyVariant = $entity->getFamilyVariant();
        if (null === $familyVariant) {
            return true;
        }

        $level = $entity->getVariationLevel();
        if (0 === $level) {
            foreach ($familyVariant->getCommonAttributes() as $familyVariantAttribute) {
                if ($familyVariantAttribute->getCode() === $attribute->getCode()) {
                    return true;
                }
            }

            return false;
        }

        $attributeSet = $familyVariant->getVariantAttributeSet($level);
        if (null === $attributeSet) {
            throw new \Exception(
                sprintf(
                    'The variant attribute set of level "%d" was expected for the family variant "%s".',
                    $level,
                    $familyVariant->getCode()
                )
            );
        }

        return $attributeSet->hasAttribute($attribute);
    }

    /**
     * @param EntityWithFamilyInterface $entity
     *
     * @return bool
     */
    protected function isNonVariantProduct(EntityWithFamilyInterface $entity): bool
    {
        if ($entity instanceof ProductModelInterface) {
            return false;
        }

        if ($entity instanceof ProductInterface) {
            return !$entity->isVariant();
        }

        return false;
    }
}
