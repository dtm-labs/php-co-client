<?php

declare(strict_types=1);
/**
 * This file is part of DTM-PHP.
 *
 * @license  https://github.com/dtm-php/dtm-client/blob/master/LICENSE
 */
namespace DtmClient;

use DtmClient\Constants\TransType;
use DtmClient\Exception\FailureException;

class Msg extends AbstractTransaction
{
    protected Barrier $barrier;

    public function __construct(Barrier $barrier)
    {
        $this->barrier = $barrier;
    }

    public function add(string $action, $payload)
    {
        TransContext::addStep(['action' => $action]);
        TransContext::addPayload(json_encode($payload));
    }

    public function prepare(string $queryPrepared)
    {
        TransContext::setQueryPrepared($queryPrepared);
        return $this->api->prepare(TransContext::toArray());
    }

    public function submit()
    {
        return $this->api->submit(TransContext::toArray());
    }

    public function doAndSubmit(string $queryPrepared, callable $businessCall)
    {
        $this->barrier->barrierFrom(TransType::MSG, TransContext::getGid(), '00', 'msg');
        $this->prepare($queryPrepared);
        try {
            $businessCall();
            $this->submit();
        } catch (FailureException $failureException) {
            $this->api->abort(TransContext::toArray());
        } catch (\Exception $exception) {
            // If busicall return an error other than failure, we will query the result
            $this->api->transRequestBranch('GET', [], TransContext::getBranchId(), TransContext::getOp(), $queryPrepared);
        }
    }
}
