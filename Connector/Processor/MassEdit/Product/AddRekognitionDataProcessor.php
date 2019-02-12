<?php

declare(strict_types=1);

namespace ClickAndMortar\AkeneoRekognitionBundle\Connector\Processor\MassEdit\Product;

use ClickAndMortar\Rekognition\Label;
use ClickAndMortar\Rekognition\Service\DetectService;
use ClickAndMortar\Rekognition\Text;
use Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor;
use Pim\Component\Catalog\Model\ProductModel;
use Pim\Component\Catalog\Model\ProductModelInterface;
use Pim\Component\Catalog\Updater\PropertySetter;

/**
 * Class AddRekognitionDataProcessor
 */
class AddRekognitionDataProcessor extends AbstractProcessor
{
    /** @var string */
    private $catalogStorageDir;

    /** @var PropertySetter */
    private $propertySetter;

    /** @var string */
    private $awsAccessKeyId;

    /** @var string */
    private $awsSecretAccessKey;

    /** @var int */
    private $minimumConfidence;

    /** @var DetectService */
    private $detectService;

    /**
     * @param string $catalogStorageDir
     * @param PropertySetter $propertySetter
     * @param string $awsAccessKeyId
     * @param string $awsSecretAccessKey
     * @param int $minimumConfidence
     */
    public function __construct(
        string $catalogStorageDir,
        PropertySetter $propertySetter,
        string $awsAccessKeyId,
        string $awsSecretAccessKey,
        int $minimumConfidence
    ) {
        $this->catalogStorageDir = $catalogStorageDir;
        $this->propertySetter = $propertySetter;
        $this->awsAccessKeyId = $awsAccessKeyId;
        $this->awsSecretAccessKey = $awsSecretAccessKey;
        $this->minimumConfidence = $minimumConfidence;

        // TODO avoid using "new" here
        $this->detectService = new DetectService(
            [
                'credentials' => [
                    'key' => $this->awsAccessKeyId,
                    'secret' => $this->awsSecretAccessKey,
                ]
            ]
        );
    }

    /**
     * @param ProductModel $productModel
     * @return ProductModel
     */
    public function process($productModel)
    {
        // Needed for mass edit from web interface.
        // Prevent process of product model without parent as it is not a "1st variant Color"
        // See https://help.akeneo.com/articles/what-about-products-variants.html
        if (!$productModel->getParent() instanceof ProductModelInterface) {
            return $productModel;
        }

        if (!$productModel->getImage()) {
            return $productModel;
        }

        $filename = $this->getImageFilename($productModel);

        if (!file_exists($filename)) {
            return $productModel;
        }

        $filePointerImage = fopen($filename, 'r');
        $image = fread($filePointerImage, filesize($filename));
        fclose($filePointerImage);

        $rekognitionImage = $this->detectService->detect($image);

        $labels = $rekognitionImage->getLabels($this->minimumConfidence);

        $this->propertySetter->setData(
            $productModel,
            'rekognition_labels',
            implode("\n", $this->getLabelsToStore($labels)),
            ['locale' => null, 'scope' => null]
        );

        $texts = $rekognitionImage->getTexts($this->minimumConfidence);

        $this->propertySetter->setData(
            $productModel,
            'rekognition_texts_words',
            implode("\n", $this->getTextsToStore($texts, Text::TYPE_WORD)),
            ['locale' => null, 'scope' => null]
        );

        $this->propertySetter->setData(
            $productModel,
            'rekognition_texts_lines',
            implode("\n", $this->getTextsToStore($texts, Text::TYPE_LINE)),
            ['locale' => null, 'scope' => null]
        );

        return $productModel;
    }

    /**
     * @param ProductModel $productModel
     * @return string
     */
    protected function getImageFilename(ProductModel $productModel): string
    {
        $filename = implode(
            DIRECTORY_SEPARATOR,
            [
                $this->catalogStorageDir,
                $productModel->getImage(),
            ]
        );

        return $filename;
    }

    /**
     * @param Label[] $labels
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
}
