<?php

namespace CultuurNet\UDB3\Model\Import\PreProcessing;

use CultuurNet\UDB3\Model\Import\JsonImporterInterface;
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\CategoryResolverInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryID;
use CultuurNet\UDB3\ReadModel\JsonDocument;

class TermPreProcessingJsonImporter implements JsonImporterInterface
{
    /**
     * @var JsonImporterInterface
     */
    private $jsonImporter;

    /**
     * @var CategoryResolverInterface
     */
    private $categoryResolver;

    /**
     * @param JsonImporterInterface $jsonImporter
     * @param CategoryResolverInterface $categoryResolver
     */
    public function __construct(
        JsonImporterInterface $jsonImporter,
        CategoryResolverInterface $categoryResolver
    ) {
        $this->jsonImporter = $jsonImporter;
        $this->categoryResolver = $categoryResolver;
    }

    /**
     * Pre-processes the JSON to polyfill missing term properties if possible.
     *
     * @param JsonDocument $jsonDocument
     */
    public function import(JsonDocument $jsonDocument)
    {
        $data = json_decode($jsonDocument->getRawBody(), true);

        // Attempt to add label and/or domain to terms, or fix them if they're incorrect.
        if (isset($data['terms']) && is_array($data['terms'])) {
            $data['terms'] = array_map(
                function (array $term) {
                    if (isset($term['id']) && is_string($term['id'])) {
                        $id = $term['id'];
                        $category = $this->categoryResolver->byId(new CategoryID($id));

                        if ($category) {
                            $term['label'] = $category->getLabel()->toString();
                            $term['domain'] = $category->getDomain()->toString();
                        }
                    }

                    return $term;
                },
                $data['terms']
            );
        }

        $jsonDocument = new JsonDocument(
            $jsonDocument->getId(),
            json_encode($data)
        );

        $this->jsonImporter->import($jsonDocument);
    }
}
