<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Model\Import\JsonImporterInterface;
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\CategoryResolverInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryID;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use Respect\Validation\Exceptions\ValidationException;

class PreProcessingEventJsonImporter implements JsonImporterInterface
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
     * Pre-processes the Event JSON-LD to polyfill auto-generated properties.
     * Only validates that the JSON-LD is valid JSON.
     * Any other validation is done by the dedicated validators.
     *
     * @param JsonDocument $jsonDocument
     */
    public function import(JsonDocument $jsonDocument)
    {
        $data = json_decode($jsonDocument->getRawBody(), true);

        if (!$data) {
            throw new ValidationException('The given json document is invalid and could not be parsed.');
        }

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