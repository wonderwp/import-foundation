<?php

namespace WonderWp\Component\ImportFoundation\Syncers;

use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequestInterface;
use WonderWp\Component\ImportFoundation\Response\SyncResponse;
use WonderWp\Component\ImportFoundation\Response\SyncResponseInterface;
use Throwable;
use WonderWp\Component\Logging\HasLoggerInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;
use WP_Error;
use WP_Post;

abstract class AbstractPostsSyncer implements SyncerInterface
{
    protected PersisterInterface $persister;

    protected array $postComparisonIndexes = [
        'post_title',
        'post_excerpt'
    ];
    protected array $postMetaComparisonIndexes = [];
    protected array $postTermsComparisonIndexes = [];

    /**
     * @param PersisterInterface $persister
     */
    public function __construct(PersisterInterface $persister)
    {
        $this->persister = $persister;
    }

    public function sync(
        SyncRequestInterface $syncRequest,
        LoggerInterface      $logger,
        ProgressInterface    $progress
    ): SyncResponseInterface
    {
        try {
            $syncResponse = new SyncResponse(200, SyncResponseInterface::SUCCESS);

            //Analyse the sync request and prepare the sync operation
            $logger->info('[Syncer] Analysing sync request');
            [$postsToCreate, $postsToUpdate, $postsToDelete] = $this->prepareSync($syncRequest, $syncResponse);
            $opCount = count($postsToCreate) + count($postsToUpdate) + count($postsToDelete);

            $logger->info(sprintf('[Syncer] Sync request analysed, %d operations to execute', $opCount));
            $logger->info(sprintf('[Syncer] Posts to create: %d, Posts to update: %d, Posts to delete: %d, Posts to skip: %d',
                count($postsToCreate),
                count($postsToUpdate),
                count($postsToDelete),
                count($syncResponse->getSkippedItems())
            ));

            if ($opCount <= 0) {
                $syncResponse->setMsgKey(SyncResponseInterface::NOOP);
                return $syncResponse;
            }

            //Execute the sync operation
            $this->executeSync(
                $postsToCreate,
                $postsToUpdate,
                $postsToDelete,
                $syncRequest->isDryRun(),
                $opCount,
                $syncResponse,
                $progress,
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
        $postsToCreate = [];
        $postsToUpdate = [];
        $postsToDelete = [];

        $newPosts = $syncRequest->getNewPosts();
        $existingPosts = $syncRequest->getExistingPosts();

        //Store new posts ids in $syncResponse->newPosts using an array_map
        $newPostsIds = array_map(function ($newPost) {
            return $this->idToLog($newPost);
        }, $newPosts);
        $syncResponse->setNewItems($newPostsIds);

        //Store existing posts ids in $syncResponse->existingPosts using an array_map
        $existingPostsIds = array_map(function ($existingPost) {
            return $this->idToLog($existingPost);
        }, $existingPosts);
        $syncResponse->setExistingItems($existingPostsIds);

        //Start comparing posts

        //We compare the new products with the existing ones
        //If a new product is not in the existing products, we add it
        //If a new product is in the existing products, we update it
        foreach ($newPosts as $newProduct) {
            $existingProduct = $this->findPost($existingPosts, $newProduct);

            if (empty($existingProduct)) {
                $postsToCreate[] = $newProduct;
            } else {
                //We update the product
                $updateReasons = $this->checkIfPostNeedsUpdate($newProduct, $existingProduct);
                if (!empty($updateReasons)) {
                    $postsToUpdate[] = [$newProduct, $existingProduct->ID, $updateReasons];
                } else {
                    $syncResponse->addSkippedItem($this->idToLog($newProduct));
                }
            }
        }

        //We compare the existing products with the new ones
        //If an existing product is not in the new products, we delete it
        foreach ($existingPosts as $existingProduct) {
            $p = $this->findPost($newPosts, $existingProduct);

            if (empty($p)) {
                $postsToDelete[] = $existingProduct;
            }
        }

        return [$postsToCreate, $postsToUpdate, $postsToDelete];
    }

    protected function executeSync(
        array                 $postsToCreate,
        array                 $postsToUpdate,
        array                 $postsToDelete,
        bool                  $isDryRun,
        int                   $opCount,
        SyncResponseInterface $syncResponse,
        ProgressInterface     $progress,
        LoggerInterface       $logger
    )
    {
        if ($this->persister instanceof HasLoggerInterface) {
            $this->persister->setLogger($logger);
        }

        //Run the sync operation
        $progress->initWith(sprintf('[Syncer] Executing %d operations', $opCount), $opCount);

        //We create the products
        if (!empty($postsToCreate)) {
            foreach ($postsToCreate as $i => $newProduct) {
                $pId = $this->persister->create($newProduct, $isDryRun);
                if (is_wp_error($pId)) {
                    /** @var WP_Error $pId */
                    $pId->add_data(['context' => 'create']);
                    $syncResponse->addErroredItem($this->idToLog($newProduct), $pId);
                } else {
                    $newProduct->ID = $pId;
                    $syncResponse->addCreatedItem($this->idToLog($newProduct));
                }
                unset($postsToCreate[$i]);
                $progress->tick();
            }
        }

        //We update the products
        if (!empty($postsToUpdate)) {
            foreach ($postsToUpdate as $j => $update) {
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
                unset($postsToUpdate[$j]);
                $progress->tick();
            }
        }

        //We delete the products
        if (!empty($postsToDelete)) {
            foreach ($postsToDelete as $k => $existingProduct) {
                $this->persister->delete($existingProduct, $isDryRun);
                $syncResponse->addDeletedItem($this->idToLog($existingProduct));
                unset($postsToDelete[$k]);
                $progress->tick();
            }
        }

        $progress->finish();
    }

    protected function idToLog(WP_Post $post): string
    {
        return $post->post_name . '#' . ($this->findPostId($post));
    }

    protected function findPostId(WP_Post $post): int|string
    {
        $metaInputAttribute = PersisterInterface::META_INPUT;
        //Test with post->meta_input['post_id']
        if (isset($post->$metaInputAttribute[PersisterInterface::SYNC_ID])) {
            return $post->$metaInputAttribute[PersisterInterface::SYNC_ID];
        }

        //If empty, test with post acf meta post_id
        if (function_exists('get_field')) {
            $postId = get_field(PersisterInterface::SYNC_ID, $post->ID);
            if (!empty($postId)) {
                return (int)$postId;
            }
        }

        //If empty, test with post->post_name
        return $post->post_name;
    }

    /**
     * @param WP_Post[] $postsToSearch
     * @param WP_Post $postToFind
     * @return WP_Post|null
     */
    protected function findPost(array $postsToSearch, WP_Post $postToFind): ?WP_Post
    {
        $postToFindId = $this->findPostId($postToFind);
        //We search for the Post in the Posts to search based on its post_name
        foreach ($postsToSearch as $post) {
            $postId = $this->findPostId($post);
            if ($postId === $postToFindId) {
                return $post;
            }
        }
        return null;
    }

    protected function checkIfPostNeedsUpdate(WP_Post $apiPost, WP_Post $existingPost): array
    {
        $updateReasons = [];

        //First, check if an update is needed by comparing the new post with the existing one
        $newPostData = $apiPost->to_array();
        $existingPostData = $existingPost->to_array();

        $indexesToCheck = $this->getPostIndexesToCheck();
        if (empty($indexesToCheck)) {
            return $updateReasons;
        }


        //Check if the post needs an update based on the indexes to check

        foreach ($indexesToCheck as $index) {
            //Keep the comparison operator loose here to avoid type comparison issues
            if ($this->postValueChanged($index, $newPostData[$index], $existingPostData[$index])) {
                $updateReasons[$index] = [
                    $newPostData[$index] ?? null,
                    $existingPostData[$index] ?? null
                ];
            }
        }

        $metasToCheck = $this->getPostMetasIndexesToCheck($newPostData);
        //Check if the post needs an update based on the metas to check
        foreach ($metasToCheck as $metaKey) {
            $existingPostMetaValue = $existingPostData[PersisterInterface::META_INPUT][$metaKey] ?? null;
            if (empty($existingPostMetaValue)) {
                $existingPostMetaValue = get_post_meta($existingPost->ID, $metaKey, true);
            }
            //Keep the comparison operator loose here to avoid type comparison issues
            if ($this->postValueChanged($metaKey, $newPostData[PersisterInterface::META_INPUT][$metaKey], $existingPostMetaValue)) {
                $updateReasons[$metaKey] = [
                    $newPostData[PersisterInterface::META_INPUT][$metaKey] ?? null,
                    $existingPostMetaValue
                ];
            }
        }

        if (function_exists('get_field')) {
            $acfToCheck = $this->getPostAcfIndexesToCheck($newPostData);
            //Check if the post needs an update based on the metas to check
            foreach ($acfToCheck as $metaKey) {
                $existingPostMetaValue = get_field($metaKey, $existingPost->ID);
                //Keep the comparison operator loose here to avoid type comparison issues
                if ($this->postValueChanged($metaKey, $newPostData[PersisterInterface::ACF_INPUT][$metaKey], $existingPostMetaValue)) {
                    $updateReasons[$metaKey] = [
                        $newPostData[PersisterInterface::ACF_INPUT][$metaKey] ?? null,
                        $existingPostMetaValue
                    ];
                }
            }
        }

        $termsToCheck = $this->getPostTermsIndexesToCheck($newPostData);
        //Check if the post needs an update based on the metas to check
        foreach ($termsToCheck as $termKey) {
            $existingPostTermsValue = $existingPostData[PersisterInterface::TAX_INPUT][$termKey] ?? null;
            if (empty($existingPostMetaValue)) {
                $existingPostTermsValue = wp_get_post_terms($existingPost->ID, $termKey);
                usort($existingPostTermsValue, fn($a, $b) => $a->term_id <=> $b->term_id);
            }

            if ($this->postValueChanged($termKey, $newPostData[PersisterInterface::TAX_INPUT][$termKey], $existingPostTermsValue)) {
                $updateReasons[$termKey] = [
                    $newPostData[PersisterInterface::TAX_INPUT][$termKey] ?? null,
                    $existingPostTermsValue
                ];
            }
        }

        return $updateReasons;
    }

    protected function getPostIndexesToCheck(): array
    {
        return $this->getPostComparisonIndexes();
    }

    protected function getPostMetasIndexesToCheck(array $newPostData): array
    {
        //Metas
        $existingPostDataMetaInputs = $newPostData[PersisterInterface::META_INPUT] ?? [];
        return array_keys($existingPostDataMetaInputs);
    }

    protected function getPostAcfIndexesToCheck(array $newPostData): array
    {
        //Acf fields
        $existingPostDataAcfInputs = $newPostData[PersisterInterface::ACF_INPUT] ?? [];
        return array_keys($existingPostDataAcfInputs);
    }

    protected function getPostTermsIndexesToCheck(array $newPostData): array
    {
        //Terms
        $existingPostDataTerms = $newPostData[PersisterInterface::TAX_INPUT] ?? [];
        return array_keys($existingPostDataTerms);
    }

    protected function postValueChanged($index, $newVal, $existingVal): bool
    {
        if (is_array($newVal)) {
            $newVal = json_encode($newVal);
        }
        if (is_array($existingVal)) {
            $existingVal = json_encode($existingVal);
        }
        return !isset($existingVal) || (trim($newVal) != trim($existingVal));
    }

    public function getPostComparisonIndexes(): array
    {
        return $this->postComparisonIndexes;
    }

    public function setPostComparisonIndexes(array $postComparisonIndexes): static
    {
        $this->postComparisonIndexes = $postComparisonIndexes;
        return $this;
    }

    public function getPostMetaComparisonIndexes(): array
    {
        return $this->postMetaComparisonIndexes;
    }

    public function getPostTermsComparisonIndexes(): array
    {
        return $this->postTermsComparisonIndexes;
    }

    public function setPostMetaComparisonIndexes(array $postMetaComparisonIndexes): static
    {
        $this->postMetaComparisonIndexes = $postMetaComparisonIndexes;
        return $this;
    }

}

