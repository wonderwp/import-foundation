<?php

namespace WonderWp\Component\ImportFoundation\Commands;

use WonderWp\Component\ImportFoundation\Exceptions\ImportException;
use WonderWp\Component\ImportFoundation\Importers\ImporterInterface;
use WonderWp\Component\ImportFoundation\Responses\ImportResponse;
use WonderWp\Component\ImportFoundation\Responses\ImportResponseInterface;
use WonderWp\Component\Logging\LoggerInterface;
use WonderWp\Component\Logging\WpCliLogger;
use WonderWp\Component\Task\Definition\AbstractWpCliCommand;
use WonderWp\Component\Task\Traits\HasDryRun;
use WonderWp\Component\Task\Traits\HasDryRunArg;
use WonderWp\Component\Task\Traits\HasDryRunInterface;

abstract class AbstractImporterCommand extends AbstractWpCliCommand implements HasDryRunInterface
{
    const IMPORTER_KEY_ARG = 'importer';
    const RESET_ARG = 'reset';
    use HasDryRunArg;
    use HasDryRun;

    protected LoggerInterface $logger;

    public static function getArgsDefinition(): array
    {
        return [
            'shortdesc' => static::getCommandDescription(),
            'synopsis' => static::getCommandSynopsis(),
        ];
    }

    protected static function getCommandDescription(): string
    {
        return 'Abstract command for importing data.';
    }

    protected static function getCommandSynopsis(): array
    {
        return [
            self::getDryRunArgDefinition(),
            [
                'name' => self::IMPORTER_KEY_ARG,
                'description' => '(required) The service dependency injection key of the importer to use.',
                'type' => 'assoc',
                'optional' => false
            ],
            [
                'name' => self::RESET_ARG,
                'description' => 'If set, the command will not delete anything, but will output what it would have deleted.',
                'type' => 'assoc',
                'optional' => true
            ],
        ];
    }

    public function __invoke(array $args, array $assocArgs)
    {
        try {
            $start = microtime(true);
            $this->bootstrap();
            $importer = $this->loadImporter($assocArgs[self::IMPORTER_KEY_ARG]);
            $request = $importer->forgeRequest($args, $assocArgs);
            $response = $importer->import($request, $this->logger);
        } catch (\Throwable $e) {
            $errorCode = is_int($e->getCode()) ? $e->getCode() : 500;
            $msgKey = ImportResponseInterface::ERROR;
            $response = new ImportResponse($errorCode, $msgKey);
            $response->setError($e);
        }

        //Output Response
        $this->outputResponse($response, $start, $assocArgs[self::IMPORTER_KEY_ARG]);
    }

    protected function bootstrap(array $args = [], array $assocArgs = []): static
    {
        $this->logger = new WpCliLogger();

        // Set dry run mode if specified
        $this->setDryRun(isset($assoc_args[self::DRY_RUN_ARG]));

        return $this;
    }

    protected function loadImporter(string $importerKey): ImporterInterface
    {
        throw new ImportException('The method loadImporter must be implemented in the child class and return an ImporterInterface instance.');
    }

    protected function outputResponse(ImportResponse $response, $startTime, string $importerName)
    {
        $this->logger->info('[Command] Import finished, displaying response');
        $this->logger->info('------------------');
        $endTime = microtime(true);

        if ($response->isSuccess()) {
            $this->outputSuccessfulResponse($response, $startTime, $endTime, $importerName);
        } else {
            $this->outputErrorResponse($response, $startTime, $endTime, $importerName);
        }
    }

    protected function outputSuccessfulResponse(ImportResponse $response, $startTime, $endTime, string $importerName)
    {
        $this->logger->success('Import response');
        $this->logger->info('🟢 Duration time: ' . ($endTime - $startTime) . 's');
        $syncResponse = $response->getSyncResponse();
        $this->logger->info('🟢 Sync report (short) : ' . print_r($syncResponse->toShortArray(), true));
        $this->logger->info('🟢 Sync report (long) : ' . print_r($syncResponse, true));
    }

    protected function outputErrorResponse(ImportResponse $response, $startTime, $endTime, string $importerName)
    {
        $this->logger->error('Import response', ['exit' => false]);
        if (!empty($response->getError())) {
            $this->logger->info('🔴 Error : ' . $response->getError()->getMessage() . '. ' . $response->getError()->getFile() . '#' . $response->getError()->getLine());
        }
        $this->logger->info('🔴 Duration time: ' . ($endTime - $startTime) . 's');
        $this->logger->info('🔴 Response : ' . $response);
        $this->logger->info('🔴 MsgKey : ' . $response->getMsgKey());
    }
}
