<?php

namespace ClickAndMortar\AkeneoRekognitionBundle\Connector\Writer\Database;

use Akeneo\Component\Batch\Item\InitializableInterface;
use Akeneo\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Component\StorageUtils\Saver\BulkSaverInterface;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Pim\Component\Catalog\Model\Product;
use Pim\Component\Catalog\Model\ProductModel;

/**
 * Product and product model writer
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoRekognitionBundle\Connector\Writer\Database
 */
class ProductAndProductModelWriter implements ItemWriterInterface, StepExecutionAwareInterface, InitializableInterface
{
    /** @var VersionManager */
    protected $versionManager;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var BulkSaverInterface */
    protected $productModelSaver;

    /** @var BulkSaverInterface */
    protected $productSaver;

    /**
     * Constructor
     *
     * @param VersionManager     $versionManager
     * @param BulkSaverInterface $productModelSaver
     * @param BulkSaverInterface $productSaver
     */
    public function __construct(
        VersionManager $versionManager,
        BulkSaverInterface $productModelSaver,
        BulkSaverInterface $productSaver
    )
    {
        $this->versionManager    = $versionManager;
        $this->productModelSaver = $productModelSaver;
        $this->productSaver      = $productSaver;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $products       = [];
        $productsModels = [];
        foreach ($items as $item) {
            $this->incrementCount($item);
            if ($item instanceof Product) {
                $products[] = $item;
            } else {
                $productsModels[] = $item;
            }
        }

        $this->productSaver->saveAll($products);
        $this->productModelSaver->saveAll($productsModels);
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $jobParameters      = $this->stepExecution->getJobParameters();
        $realTimeVersioning = $jobParameters->get('realTimeVersioning');
        $this->versionManager->setRealTimeVersioning($realTimeVersioning);
    }

    /**
     * @param Product | ProductModel $product
     */
    protected function incrementCount($product)
    {
        if ($product->getId()) {
            $this->stepExecution->incrementSummaryInfo('process');
        } else {
            $this->stepExecution->incrementSummaryInfo('create');
        }
    }
}
