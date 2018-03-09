<?php

namespace CultuurNet\UDB3\Model\Import;

use CultuurNet\UDB3\ReadModel\JsonDocument;

interface JsonImporterInterface
{
    /**
     * @param JsonDocument $jsonDocument
     */
    public function import(JsonDocument $jsonDocument);
}
