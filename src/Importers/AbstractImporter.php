<?php

namespace WonderWp\Component\ImportFoundation\Importers;

use WonderWp\Component\ImportFoundation\Commands\ImportWebserviceData;
use WonderWp\Component\ImportFoundation\Exceptions\TransformException;
use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Repositories\ApiRepositoryInterface;
use WonderWp\Component\ImportFoundation\Repositories\BddRepositoryInterface;
use WonderWp\Component\ImportFoundation\Requests\ImportRequest;
use WonderWp\Component\ImportFoundation\Requests\ImportRequestInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequest;
use WonderWp\Component\ImportFoundation\Response\ImportResponse;
use WonderWp\Component\ImportFoundation\Response\ImportResponseInterface;
use WonderWp\Component\ImportFoundation\Syncers\SyncerInterface;
use WonderWp\Component\ImportFoundation\Transformers\TransformerInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;
use WP_Post;
use function WonderWp\Functions\trace;

abstract class AbstractImporter implements ImporterInterface
{
    protected ApiRepositoryInterface $apiRepository;
    protected TransformerInterface $apiTransformer;
    protected BddRepositoryInterface $bddRepository;
    protected TransformerInterface $bddTransformer;
    protected SyncerInterface $syncer;

    /**
     * @param BddRepositoryInterface $bddRepository
     * @param ApiRepositoryInterface $apiRepository
     * @param SyncerInterface $syncer
     */
    public function __construct(
        ApiRepositoryInterface $apiRepository,
        TransformerInterface   $apiTransformer,
        BddRepositoryInterface $bddRepository,
        TransformerInterface   $bddTransformer,
        SyncerInterface        $syncer
    )
    {
        $this->apiRepository = $apiRepository;
        $this->apiTransformer = $apiTransformer;
        $this->bddRepository = $bddRepository;
        $this->bddTransformer = $bddTransformer;
        $this->syncer = $syncer;
    }

    public function forgeRequest(array $args, array $assocArgs): ImportRequestInterface
    {
        $request = new ImportRequest();
        $request->setDryRun($assocArgs[ImportWebserviceData::DRY_RUN_ARG] ?? false);

        return $request;
    }

    public function migrate(
        ImportRequestInterface $request,
        LoggerInterface        $logger,
        ProgressInterface      $progress
    ): ImportResponseInterface
    {
        $isDryRun = $request->isDryRun();

        //Fetch Data from API
        $apiFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from API');
        $apiPosts = $this->apiRepository->findAll();
        $logger->info(sprintf('[Importer] %d API data fetched in %d seconds', count($apiPosts), microtime(true) - $apiFetchStart));
        $logger->info('[Importer] Transforming API data');

        $apiPosts = array_map(function (WP_Post $post) use ($logger, $isDryRun): ?WP_Post {
            try {
                return $this->apiTransformer->transform($post, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Erreur de transformation du produit api %s : %s', $post->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $apiPosts);
        $apiPosts = array_filter($apiPosts);

        $logger->info(sprintf('[Importer] %d API data transformed in %d seconds', count($apiPosts), microtime(true) - $apiFetchStart));

        //Fetch Data from BDD
        $bddFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from BDD');
        $bddPosts = $this->bddRepository->findAll();
        $logger->info(sprintf('[Importer] %d BDD data fetched in %d seconds', count($bddPosts), microtime(true) - $bddFetchStart));
        $logger->info('[Importer] Transforming BDD data');
        $bddPosts = array_map(function (WP_Post $post) use ($logger, $isDryRun): ?WP_Post {
            try {
                return $this->bddTransformer->transform($post, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Erreur de transformation du produit bdd %s : %s', $post->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $bddPosts);
        $bddPosts = array_filter($bddPosts);
        $logger->info(sprintf('[Importer] %d BDD data transformed in %d seconds', count($bddPosts), microtime(true) - $bddFetchStart));

        $syncStart = microtime(true);
        $logger->info('[Importer] Starting the syncing process');
        $syncRequest = new SyncRequest($apiPosts, $bddPosts, $isDryRun);
        $syncResponse = $this->syncer->sync($syncRequest, $logger, $progress);
        $syncResponse->setGenerationTime(microtime(true) - $syncStart);
        $logger->info(sprintf('[Importer] Syncing process done in %d seconds', $syncResponse->getGenerationTime()));

        $syncResponseCode = $syncResponse->getCode();
        if ($syncResponse->isSuccess()) {
            $importResponseMsgKey = ImportResponseInterface::SUCCESS;
        } else {
            $importResponseMsgKey = ImportResponseInterface::ERROR;
        }
        $importResponse = new ImportResponse($syncResponseCode, $importResponseMsgKey);
        $importResponse->setSyncResponse($syncResponse);

        return $importResponse;
    }
}
