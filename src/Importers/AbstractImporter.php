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

        $sourceData = $this->fetchSourceData($request, $logger);
        $sourceData = $this->transformSourceData($sourceData, $logger, $isDryRun);

        $destinationData = $this->fetchDestinationData($request, $logger);
        $destinationData = $this->transformDestinationData($destinationData, $logger, $isDryRun);

        $syncResponse = $this->syncData($sourceData, $destinationData, $request, $logger);

        return $this->forgeImportResponse($syncResponse);
    }

    protected function fetchSourceData(ImportRequestInterface $request, LoggerInterface $logger): array
    {
        $sourceFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from SOURCE');
        $sourceData = $this->sourceRepository->findAll();
        $logger->info(sprintf('[Importer] %d SOURCE data fetched in %d seconds', count($sourceData), microtime(true) - $sourceFetchStart));
        return $sourceData;
    }

    protected function transformSourceData(array $sourceData, LoggerInterface $logger, bool $isDryRun): array
    {
        $logger->info('[Importer] Transforming SOURCE data');
        $sourceData = array_map(function ($sourceItem) use ($logger, $isDryRun) {
            try {
                return $this->sourceTransformer->transform($sourceItem, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Error transforming source item %s : %s', $sourceItem->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $sourceData);
        $sourceData = array_filter($sourceData);
        $logger->info(sprintf('[Importer] %d SOURCE data transformed', count($sourceData)));
        return $sourceData;
    }

    protected function fetchDestinationData(ImportRequestInterface $request, LoggerInterface $logger): array
    {
        $bddFetchStart = microtime(true);
        $logger->info('[Importer] Fetching data from BDD');
        $destinationData = $this->destinationRepository->findAll();
        $logger->info(sprintf('[Importer] %d DESTINATION data fetched in %d seconds', count($destinationData), microtime(true) - $bddFetchStart));
        return $destinationData;
    }

    protected function transformDestinationData(array $destinationData, LoggerInterface $logger, bool $isDryRun): array
    {
        $logger->info('[Importer] Transforming DESTINATION data');
        $destinationData = array_map(function ($destinationItem) use ($logger, $isDryRun) {
            try {
                return $this->destinationTransformer->transform($destinationItem, $isDryRun);
            } catch (TransformException $e) {
                $logger->error(sprintf('Error transforming destination item %s : %s', $destinationItem->post_title, $e->getMessage()), ['exit' => false]);
                return null;
            }
        }, $destinationData);
        $destinationData = array_filter($destinationData);
        $logger->info(sprintf('[Importer] %d DESTINATION data transformed', count($destinationData)));
        return $destinationData;
    }

    protected function syncData(array $sourceData, array $destinationData, ImportRequestInterface $request, LoggerInterface $logger)
    {
        $syncStart = microtime(true);
        $logger->info('[Importer] Starting the syncing process');
        $syncRequest = new SyncRequest($sourceData, $destinationData, $request->isDryRun(), $request->isDeletionEnabled());
        $syncResponse = $this->syncer->sync($syncRequest, $logger);
        $syncResponse->setGenerationTime(microtime(true) - $syncStart);
        $logger->info(sprintf('[Importer] Syncing process done in %d seconds', $syncResponse->getGenerationTime()));
        return $syncResponse;
    }

    protected function forgeImportResponse($syncResponse): ImportResponseInterface
    {
        $syncResponseCode = $syncResponse->getCode();
        $importResponseMsgKey = $syncResponse->isSuccess()
            ? ImportResponseInterface::SUCCESS
            : ImportResponseInterface::ERROR;
        $importResponse = new ImportResponse($syncResponseCode, $importResponseMsgKey);
        $importResponse->setSyncResponse($syncResponse);
        return $importResponse;
    }
}
