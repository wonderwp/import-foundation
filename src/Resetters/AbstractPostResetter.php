<?php

namespace WonderWp\Component\ImportFoundation\Resetters;

use WonderWp\Component\ImportFoundation\Response\ResetResponse;
use WonderWp\Component\ImportFoundation\Response\ResetResponseInterface;
use Throwable;

abstract class AbstractPostResetter implements ResetterInterface
{
    public function reset(): ResetResponseInterface
    {
        try {
            global $wpdb;

            $postType = $this->getPostTypeToReset();

            $deleted = $wpdb->delete($wpdb->posts, array('post_type' => $postType));

            $response = new ResetResponse(200, ResetResponseInterface::SUCCESS);
            $response->setDeleted($deleted);
        } catch (Throwable $e) {
            $responseCode = is_int($e->getCode()) ? $e->getCode() : 500;
            $response = new ResetResponse($responseCode, ResetResponseInterface::ERROR);
            $response->setError($e);
        }

        return $response;
    }

    abstract protected function getPostTypeToReset(): string;
}
