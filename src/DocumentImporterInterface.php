<?php

namespace CultuurNet\UDB3\Model\Import;

interface DocumentImporterInterface
{
    /**
     * @param DecodedDocument $decodedDocument
     */
    public function import(DecodedDocument $decodedDocument);
}
