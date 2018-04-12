<?php

namespace CultuurNet\UDB3\Model\Import;

use CultuurNet\UDB3\ApiGuard\Consumer\ConsumerInterface;

interface ConsumerAwareDocumentImporterInterface extends DocumentImporterInterface
{
    /**
     * @param ConsumerInterface $consumer
     * @return ConsumerAwareDocumentImporterInterface
     */
    public function forConsumer(ConsumerInterface $consumer);
}
