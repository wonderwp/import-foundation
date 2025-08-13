<?php

namespace WonderWp\Component\ImportFoundation\Importers;

use WonderWp\Component\ImportFoundation\Commands\ImportWebserviceData;
use WonderWp\Component\ImportFoundation\Exceptions\TransformException;
use WonderWp\Component\ImportFoundation\Persisters\PersisterInterface;
use WonderWp\Component\ImportFoundation\Repositories\SourceRepositoryInterface;
use WonderWp\Component\ImportFoundation\Repositories\DestinationRepositoryInterface;
use WonderWp\Component\ImportFoundation\Repositories\RepositoryInterface;
use WonderWp\Component\ImportFoundation\Requests\ImportRequest;
use WonderWp\Component\ImportFoundation\Requests\ImportRequestInterface;
use WonderWp\Component\ImportFoundation\Requests\SyncRequest;
use WonderWp\Component\ImportFoundation\Responses\ImportResponse;
use WonderWp\Component\ImportFoundation\Responses\ImportResponseInterface;
use WonderWp\Component\ImportFoundation\Syncers\SyncerInterface;
use WonderWp\Component\ImportFoundation\Transformers\TransformerInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Task\Progress\ProgressInterface;
use WonderWp\Component\Task\Traits\HasDryRunInterface;
use WP_Post;
use function WonderWp\Functions\trace;

abstract class AbstractImporter implements ImporterInterface
{
    protected RepositoryInterface $sourceRepository;
    protected TransformerInterface $sourceTransformer;
    protected RepositoryInterface $destinationRepository;
    protected TransformerInterface $destinationTransformer;
    protected SyncerInterface $syncer;

    public function __construct(
        RepositoryInterface  $sourceRepository,
        TransformerInterface $sourceTransformer,
        RepositoryInterface  $destinationRepository,
        TransformerInterface $destinationTransformer,
        SyncerInterface      $syncer
    )
    {
        $this->sourceRepository = $sourceRepository;
        $this->sourceTransformer = $sourceTransformer;
        $this->destinationRepository = $destinationRepository;
        $this->destinationTransformer = $destinationTransformer;
        $this->syncer = $syncer;
    }

    public function forgeRequest(array $args, array $assocArgs): ImportRequestInterface
    {
        $request = new ImportRequest();
        $request->setDryRun($assocArgs[HasDryRunInterface::DRY_RUN_ARG] ?? false);
        $request->setDeletionEnabled($assocArgs[ImportRequest::DELETION_ENABLED_ARG] ?? false);

        return $request;
    }

    public function import(
        ImportRequestInterface $request,
        LoggerInterface        $logger
    ): ImportResponseInterface
    {
        $isDryRun = $request->isDryRun();

        //Fetch Data from SOURCE
        $sourceFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from SOURCE');
        $sourcePosts = $this->sourceRepository->findAll();
        $logger->info(sprintf('[Importer] %d SOURCE data fetched in %d seconds', count($sourcePosts), microtime(true) - $sourceFetchStart));
        $logger->info('[Importer] Transforming SOURCE data');

        $sourcePosts = array_map(function (WP_Post $post) use ($logger, $isDryRun): ?WP_Post {
            try {
                return $this->sourceTransformer->transform($post, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Erreur de transformation du produit source %s : %s', $post->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $sourcePosts);
        $sourcePosts = array_filter($sourcePosts);

        $logger->info(sprintf('[Importer] %d SOURCE data transformed in %d seconds', count($sourcePosts), microtime(true) - $sourceFetchStart));

        //Fetch Data from BDD
        $bddFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from BDD');
        $bddPosts = $this->destinationRepository->findAll();
        $logger->info(sprintf('[Importer] %d DESTINATION data fetched in %d seconds', count($bddPosts), microtime(true) - $bddFetchStart));
        $logger->info('[Importer] Transforming DESTINATION data');
        $bddPosts = array_map(function (WP_Post $post) use ($logger, $isDryRun): ?WP_Post {
            try {
                return $this->destinationTransformer->transform($post, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Erreur de transformation du produit bdd %s : %s', $post->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $bddPosts);
        $bddPosts = array_filter($bddPosts);
        $logger->info(sprintf('[Importer] %d DESTINATION data transformed in %d seconds', count($bddPosts), microtime(true) - $bddFetchStart));

        $syncStart = microtime(true);
        $logger->info('[Importer] Starting the syncing process');
        $syncRequest = new SyncRequest($sourcePosts, $bddPosts, $isDryRun, $request->isDeletionEnabled());
        $syncResponse = $this->syncer->sync($syncRequest, $logger);
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
