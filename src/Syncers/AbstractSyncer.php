<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequestInterface;
use WonderWp\Component\ImportFoundation\Responses\SyncResponse;
use WonderWp\Component\ImportFoundation\Responses\SyncResponseInterface;
use WonderWp\Component\Logging\HasLoggerInterface;
use WonderWp\Component\Logging\LoggerInterface;

abstract class AbstractSyncer implements SyncerInterface
{
    protected PersisterInterface $persister;

    /**
     * @param PersisterInterface $persister
     */
    public function __construct(PersisterInterface $persister)
    {
        $this->persister = $persister;
    }

    public function sync(
        SyncRequestInterface $syncRequest,
        LoggerInterface      $logger
    ): SyncResponseInterface {
        try {
            $syncResponse = new SyncResponse(200, SyncResponseInterface::SUCCESS);

            //Analyse the sync request and prepare the sync operation
            $logger->info('[Syncer] Analysing sync request');
            [$itemsToCreate, $itemsToUpdate, $itemsToDelete] = $this->prepareSync($syncRequest, $syncResponse);
            $opCount = count($itemsToCreate) + count($itemsToUpdate) + count($itemsToDelete);

            $logger->info(sprintf('[Syncer] Sync request analysed, %d operations to execute', $opCount));
            $logger->info(sprintf(
                '[Syncer] Items to create: %d, Items to update: %d, Items to delete: %s, Items to skip: %d',
                count($itemsToCreate),
                count($itemsToUpdate),
                $syncRequest->isDeletionEnabled() ? count($itemsToDelete) : 'Disabled',
                count($syncResponse->getSkippedItems())
            ));

            if ($opCount <= 0) {
                $syncResponse->setMsgKey(SyncResponseInterface::NOOP);
                return $syncResponse;
            }

            //Execute the sync operation
            $this->executeSync(
                $itemsToCreate,
                $itemsToUpdate,
                $itemsToDelete,
                $syncRequest->isDryRun(),
                $opCount,
                $syncResponse,
                $logger
            );
            $logger->info('[Syncer] Sync operation executed');

            return $syncResponse;
        } catch (Throwable $e) {
            $errorCode = is_int($e->getCode()) ? $e->getCode() : 500;
            $syncResponse = new SyncResponse($errorCode, SyncResponseInterface::ERROR);
            $syncResponse->setError($e);
            return $syncResponse;
        }
    }

    protected function prepareSync(SyncRequestInterface $syncRequest, SyncResponseInterface $syncResponse): array
    {
        $itemsToCreate = [];
        $itemsToUpdate = [];
        $itemsToDelete = [];

        $newItems = $syncRequest->getNewItems();
        $existingItems = $syncRequest->getExistingItems();

        //Store new items ids in $syncResponse->newItems using an array_map
        $newItemsIds = array_map(function ($newItem) {
            return $this->idToLog($newItem);
        }, $newItems);
        $syncResponse->setNewItems($newItemsIds);

        //Store existing items ids in $syncResponse->existingItems using an array_map
        $existingItemsIds = array_map(function ($existingItem) {
            return $this->idToLog($existingItem);
        }, $existingItems);
        $syncResponse->setExistingItems($existingItemsIds);

        //Start comparing items

        //We compare the new products with the existing ones
        //If a new product is not in the existing products, we add it
        //If a new product is in the existing products, we update it
        foreach ($newItems as $newProduct) {
            $existingProduct = $this->findItem($existingItems, $newProduct);

            if (empty($existingProduct)) {
                $itemsToCreate[] = $newProduct;
            } else {
                //We update the product
                $updateReasons = $this->checkIfItemNeedsUpdate($newProduct, $existingProduct);
                if (!empty($updateReasons)) {
                    $itemsToUpdate[] = [$newProduct, $existingProduct->ID, $updateReasons];
                } else {
                    $syncResponse->addSkippedItem($this->idToLog($newProduct));
                }
            }
        }

        //We compare the existing products with the new ones
        //If an existing product is not in the new products, we delete it
        if ($syncRequest->isDeletionEnabled()) {
            foreach ($existingItems as $existingProduct) {
                $p = $this->findItem($newItems, $existingProduct);

                if (empty($p)) {
                    $itemsToDelete[] = $existingProduct;
                }
            }
        }
        return [$itemsToCreate, $itemsToUpdate, $itemsToDelete];
    }

    protected function executeSync(
        array                 $itemsToCreate,
        array                 $itemsToUpdate,
        array                 $itemsToDelete,
        bool                  $isDryRun,
        int                   $opCount,
        SyncResponseInterface $syncResponse,
        LoggerInterface       $logger
    ) {
        if ($this->persister instanceof HasLoggerInterface) {
            $this->persister->setLogger($logger);
        }

        //Run the sync operation
        //$progress->initWith(sprintf('[Syncer] Executing %d operations', $opCount), $opCount);

        //We create the products
        if (!empty($itemsToCreate)) {
            foreach ($itemsToCreate as $i => $newProduct) {
                $pId = $this->persister->create($newProduct, $isDryRun);
                if (is_wp_error($pId)) {
                    /** @var WP_Error $pId */
                    $pId->add_data(['context' => 'create']);
                    $syncResponse->addErroredItem($this->idToLog($newProduct), $pId);
                } else {
                    $newProduct->ID = $pId;
                    $syncResponse->addCreatedItem($this->idToLog($newProduct));
                }
                unset($itemsToCreate[$i]);
                //$progress->tick();
            }
        }

        //We update the products
        if (!empty($itemsToUpdate)) {
            foreach ($itemsToUpdate as $j => $update) {
                [$newProduct, $existingProductId, $updateReasons] = $update;
                $pId = $this->persister->update($newProduct, $existingProductId, $updateReasons, $isDryRun);
                if (is_wp_error($pId)) {
                    $pId->add_data([
                        'context' => 'update',
                        'product' => $newProduct,
                        'updateReasons' => $updateReasons
                    ]);
                    $syncResponse->addErroredItem($this->idToLog($newProduct), $pId);
                } else {
                    $syncResponse->addUpdatedItem($this->idToLog($newProduct), $updateReasons);
                }
                unset($itemsToUpdate[$j]);
                //$progress->tick();
            }
        }

        //We delete the products
        if (!empty($itemsToDelete)) {
            foreach ($itemsToDelete as $k => $existingProduct) {
                $this->persister->delete($existingProduct, $isDryRun);
                $syncResponse->addDeletedItem($this->idToLog($existingProduct));
                unset($itemsToDelete[$k]);
                //$progress->tick();
            }
        }

        //$progress->finish();
    }

    abstract protected function idToLog($item): string;

    abstract protected function findItemId(mixed $item): int|string;

    /**
     * @param WP_Item[] $itemsToSearch
     * @param WP_Item $itemToFind
     * @return WP_Item|null
     */
    protected function findItem(array $itemsToSearch, WP_Item $itemToFind): ?WP_Item
    {
        $itemToFindId = $this->findItemId($itemToFind);
        //We search for the Item in the Items to search based on its item_name
        foreach ($itemsToSearch as $item) {
            $itemId = $this->findItemId($item);
            if ($itemId === $itemToFindId) {
                return $item;
            }
        }
        return null;
    }

    protected function checkIfItemNeedsUpdate(mixed $sourceItem, mixed $destinationItem): array
    {
        return $updateReasons = [];
    }

    protected function itemValueChanged($index, $newVal, $existingVal): bool
    {
        if (is_array($newVal)) {
            $newVal = json_encode($newVal);
        }
        if (is_array($existingVal)) {
            $existingVal = json_encode($existingVal);
        }
        return !isset($existingVal) || (trim($newVal) != trim($existingVal));
    }

}
