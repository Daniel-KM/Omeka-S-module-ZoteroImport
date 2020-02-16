<?php
namespace ZoteroImport\Job;

class Import extends AbstractZoteroSync
{
    /**
     * Priority map between Zotero item fields and Omeka properties
     *
     * @var array
     */
    protected $itemFieldMap = [];

    /**
     * Priority map between Zotero creator types and Omeka properties
     *
     * @var array
     */
    protected $creatorTypeMap = [];

    /**
     * Map tags as items.
     *
     * @var bool
     */
    protected $tagAsItem = false;

    /**
     * Language of the tags.
     *
     * @var string
     */
    protected $tagLanguage = null;

    /**
     * List of tags mapped to items.
     *
     * @var array
     */
    protected $tagsToItems = [];

    /**
     * Format of the person names.
     *
     * @var string
     */
    protected $personName = 'first';

    /**
     * Perform the import.
     *
     * Accepts the following arguments:
     *
     * - itemSet:       The Omeka item set ID (int)
     * - import:        The Omeka Zotero import ID (int)
     * - type:          The Zotero library type (user, group)
     * - id:            The Zotero library ID (int)
     * - collectionKey: The Zotero collection key (string)
     * - apiKey:        The Zotero API key (string)
     * - syncFiles:     Whether to import file attachments (bool)
     * - action:        What to do with existing items (string)
     * - version:       The Zotero Last-Modified-Version of the last import (int)
     * - timestamp:     The Zotero dateAdded timestamp (UTC) to begin importing (int)
     * - personName:    Format to use for the name (first name / last name) (string)
     * - tagLanguage:   The language of the tags for dcterms:subject (string)
     * - tagAsItem:     Uses items for tags, making them translatable (string)
     * - tagAsSkos:     With tags as items, create tags as skos concept (bool)
     * - tagMainItem:   With tags as items, relate the new tags to a main tag (int)
     * - tagItemSet:    With tags as items, store the new tags in an item set (int)
     *
     * Roughly follows Zotero's recommended steps for synchronizing a Zotero Web
     * API client with the Zotero server. But for the purposes of this job, a
     * "sync" only imports parent items (and their children) that have been
     * added to Zotero since the passed timestamp. Nevertheless, the user can
     * choose to update previous imported items.
     *
     * @see https://www.zotero.org/support/dev/web_api/v3/syncing#full-library_syncing
     */
    public function perform()
    {
        // Raise the memory limit to accommodate very large imports.
        ini_set('memory_limit', '500M');

        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $api = $this->api = $services->get('Omeka\ApiManager');

        $itemSet = $api->read('item_sets', $this->getArg('itemSet'))->getContent();

        $this->cacheResourceClasses();
        $this->cacheProperties();

        $this->itemTypeMap = $this->prepareMapping('item_type_map', 'resourceClasses');
        $this->itemFieldMap = $this->prepareMapping('item_field_map', 'properties');
        $this->creatorTypeMap = $this->prepareMapping('creator_type_map', 'properties');

        $this->personName = $this->getArg('personName');
        $this->tagLanguage = $this->getArg('tagLanguage');
        // TODO Do a first pass to create all items for tags.
        $this->tagAsItem = $this->getArg('tagAsItem');

        $this->setImportClient();
        $this->setImportUrl();

        $apiVersion = $this->getArg('version', 0);
        $collectionKey = $this->getArg('collectionKey');
        $action = $this->getArg('action');

        $params = [
            'since' => $apiVersion,
            'format' => 'versions',
            // Sort by ascending date added so items are imported roughly in the
            // same order. This way, if there is an error during an import,
            // users can estimate when to set the "Added after" field.
            'sort' => 'dateAdded',
            'direction' => 'asc',
            // Do not import notes.
            'itemType' => '-note',
        ];
        if ($collectionKey) {
            $url = $this->url->collectionItems($collectionKey, $params);
        } else {
            $url = $this->url->items($params);
        }
        $zItemKeys = array_keys(json_decode($this->getResponse($url)->getBody(), true));

        if (empty($zItemKeys)) {
            return;
        }

        list($zParentItems, $zChildItems) = $this->fetchZoteroItems($zItemKeys);

        switch ($action) {
            case self::ACTION_REPLACE:
                $existingItems = $this->existingItems(array_keys($zParentItems));
                // Keep order of the keys provided by Zotero.
                // TODO Order by "date modified" in Zotero?
                $existingItems = array_intersect_key(
                    array_replace($zParentItems, $existingItems),
                    $existingItems
                );
                break;

            case self::ACTION_CREATE:
            default:
                $existingItems = [];
                break;
        }

        // Map Zotero items to Omeka items. Pass by reference so PHP doesn't
        // create a copy of the array, saving memory.
        $oItems = [];
        $oItemsToUpdate = [];
        foreach ($zParentItems as $zParentItemKey => &$zParentItem) {
            $oItem = [];
            $oItem['o:item_set'] = [['o:id' => $itemSet->id()]];
            $oItem = $this->mapResourceClass($zParentItem, $oItem);
            $oItem = $this->mapNameValues($zParentItem, $oItem);
            $oItem = $this->tagAsItem
                ? $this->mapSubjectValuesAsItems($zParentItem, $oItem)
                : $this->mapSubjectValues($zParentItem, $oItem);
            $oItem = $this->mapValues($zParentItem, $oItem);
            $oItem = $this->mapAttachment($zParentItem, $oItem);
            if (isset($zChildItems[$zParentItemKey])) {
                foreach ($zChildItems[$zParentItemKey] as $zChildItem) {
                    $oItem = $this->mapAttachment($zChildItem, $oItem);
                }
            }
            if (isset($existingItems[$zParentItemKey])) {
                $oItem['id'] = $existingItems[$zParentItemKey];
                $oItemsToUpdate[$zParentItemKey] = $oItem;
            } else {
                $oItems[$zParentItemKey] = $oItem;
            }
            // Unset unneeded data to save memory.
            unset($zParentItems[$zParentItemKey]);
        }

        // Batch create Omeka items.
        $importId = $this->getArg('import');
        foreach (array_chunk($oItems, $this->sizeChunk, true) as $oItemsChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $response = $api->batchCreate('items', $oItemsChunk, [], ['continueOnError' => true]);

            // Batch create Zotero import items.
            $importItems = [];
            foreach ($response->getContent() as $zKey => $item) {
                $importItems[] = [
                    'o:item' => ['o:id' => $item->id()],
                    'o-module-zotero_import:import' => ['o:id' => $importId],
                    'o-module-zotero_import:zotero_key' => $zKey,
                ];
            }
            // The ZoteroImportItem entity cascade detaches items, which saves
            // memory during batch create.
            $api->batchCreate('zotero_import_items', $importItems, [], ['continueOnError' => true]);
        }

        // In the api manager, batchUpdate() allows to update a set of resources
        // with the same data. Here, data are specific to each row, so each
        // resource is updated separately.
        $options['isPartial'] = false;
        foreach (array_chunk($oItemsToUpdate, $this->sizeChunk, true) as $oItemsChunk) {
            $importItems = [];
            foreach ($oItemsChunk as $zItemKey => $oItem) {
                if ($this->shouldStop()) {
                    return;
                }
                $fileData = isset($oItem['o:media']) ? $oItem['o:media'] : [];
                try {
                    $response = $api->update('items', $oItem['id'], $oItem, $fileData, $options);
                } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                    $this->logger->err((string) $e);
                    continue;
                }

                $item = $response->getContent();
                $importItems[] = [
                    'o:item' => ['o:id' => $item->id()],
                    'o-module-zotero_import:import' => ['o:id' => $importId],
                    'o-module-zotero_import:zotero_key' => $zItemKey,
                ];
            }

            $api->batchCreate('zotero_import_items', $importItems, [], ['continueOnError' => true]);
        }
    }

    /**
     * Map Zotero item type to Omeka resource class.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    protected function mapResourceClass(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['itemType'])) {
            return $omekaItem;
        }
        $type = $zoteroItem['data']['itemType'];
        if (!isset($this->itemTypeMap[$type])) {
            return $omekaItem;
        }
        // All the fields are already checked.
        $localName = reset($this->itemTypeMap[$type]);
        $prefix = key($this->itemTypeMap[$type]);
        $classId = $this->resourceClasses[$prefix][$localName];
        $omekaItem['o:resource_class'] = ['o:id' => $classId];
        return $omekaItem;
    }

    /**
     * Map Zotero item data to Omeka item values.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    protected function mapValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data'])) {
            return $omekaItem;
        }
        foreach ($zoteroItem['data'] as $key => $value) {
            if (!$value) {
                continue;
            }
            if (!isset($this->itemFieldMap[$key])) {
                continue;
            }
            // All the fields are already checked.
            foreach ($this->itemFieldMap[$key] as $prefix => $localName) {
                $valueObject = [];
                $valueObject['property_id'] = $this->properties[$prefix][$localName];
                // Manage an exception.
                if ('bibo' == $prefix && 'uri' == $localName) {
                    $valueObject['@id'] = $value;
                    $valueObject['type'] = 'uri';
                } else {
                    $valueObject['@value'] = $value;
                    $valueObject['type'] = 'literal';
                }
                $omekaItem[$prefix . ':' . $localName][] = $valueObject;
            }
        }
        return $omekaItem;
    }

    /**
     * Map Zotero creator names to the Omeka item values.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    protected function mapNameValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['creators'])) {
            return $omekaItem;
        }
        $creators = $zoteroItem['data']['creators'];
        foreach ($creators as $creator) {
            $creatorType = $creator['creatorType'];
            if (!isset($this->creatorTypeMap[$creatorType])) {
                continue;
            }

            $name = '';
            $testName = '';
            $testFirst = '';
            $testLast = '';
            if (isset($creator['name'])) {
                $name = $creator['name'];
                $testName = $creator['name'];
            }
            switch ($this->personName) {
                case 'last_comma':
                    if (isset($creator['lastName'])) {
                        $name .= ' ' . $creator['lastName'];
                        $testLast = $creator['lastName'];
                    }
                    if (isset($creator['firstName'])) {
                        $name .= ', ' . $creator['firstName'];
                        $testFirst = $creator['firstName'];
                    }
                    break;
                case 'last':
                    if (isset($creator['lastName'])) {
                        $name .= ' ' . $creator['lastName'];
                        $testLast = $creator['lastName'];
                    }
                    if (isset($creator['firstName'])) {
                        $name .= ' ' . $creator['firstName'];
                        $testFirst = $creator['firstName'];
                    }
                    break;
                case 'first':
                default:
                    if (isset($creator['firstName'])) {
                        $name .= ' ' . $creator['firstName'];
                        $testFirst = $creator['firstName'];
                    }
                    if (isset($creator['lastName'])) {
                        $name .= ' ' . $creator['lastName'];
                        $testLast = $creator['lastName'];
                    }
                    break;
            }

            if (!$name) {
                continue;
            }

            // Add a check to avoid to duplicate the main name.
            $name = trim($name);
            if ($testName === ($testFirst . ' ' . $testLast)
                || $testName === ($testLast . ' ' . $testFirst)
                || $testName === ($testLast . ', ' . $testFirst)
            ) {
                $name = $testName;
            }

            foreach ($this->creatorTypeMap[$creatorType] as $prefix => $localName) {
                $omekaItem[$prefix . ':' . $localName][] = [
                    '@value' => $name,
                    'property_id' => $this->properties[$prefix][$localName],
                    'type' => 'literal',
                ];
            }
        }
        return $omekaItem;
    }

    /**
     * Map Zotero tags to Omeka item values (dcterms:subject).
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    protected function mapSubjectValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['tags'])) {
            return $omekaItem;
        }

        $propertyId = $this->properties['dcterms']['subject'];
        foreach ($zoteroItem['data']['tags'] as $tag) {
            $omekaItem['dcterms:subject'][] = [
                'property_id' => $propertyId,
                'type' => 'literal',
                '@value' => $tag['tag'],
                '@language' => $this->tagLanguage,
            ];
        }
        return $omekaItem;
    }

    /**
     * Map Zotero tags to Omeka items.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    protected function mapSubjectValuesAsItems(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['tags'])) {
            return $omekaItem;
        }

        $propertyId = $this->properties['dcterms']['subject'];
        foreach ($zoteroItem['data']['tags'] as $tag) {
            $tagId = $this->readOrCreateItemForTag($tag['tag']);
            $omekaItem['dcterms:subject'][] = [
                'property_id' => $propertyId,
                'type' => 'resource:item',
                '@value' => null,
                '@language' => null,
                'value_resource_id' => $tagId,
            ];
        }
        return $omekaItem;
    }

    /**
     * Map an attachment.
     *
     * There are four kinds of Zotero attachments: imported_url, imported_file,
     * linked_url, and linked_file. Only imported_url and imported_file have
     * files, and only when the response includes an enclosure link. For
     * linked_url, the @id URL was already mapped in mapValues(). For
     * linked_file, there is nothing to save.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return string
     */
    protected function mapAttachment($zoteroItem, $omekaItem)
    {
        if ('attachment' === $zoteroItem['data']['itemType']
            && isset($zoteroItem['links']['enclosure'])
            && $this->getArg('syncFiles')
            && $this->getArg('apiKey')
        ) {
            $omekaItem['o:media'][] = [
                'o:ingester' => 'url',
                'o:source' => $this->url->itemFile($zoteroItem['key']),
                'ingest_url' => $this->url->itemFile(
                    $zoteroItem['key'],
                    ['key' => $this->getArg('apiKey')]
                ),
                'dcterms:title' => [
                    [
                        '@value' => $zoteroItem['data']['title'],
                        'property_id' => $this->properties['dcterms']['title'],
                        'type' => 'literal',
                    ],
                ],
            ];
        }
        return $omekaItem;
    }

    /**
     * Create a tag as item if not exist.
     *
     * @param string $tag
     * @return int
     */
    protected function readOrCreateItemForTag($tag)
    {
        static $tagAsSkos;
        static $defaultParams;
        static $defaultTagData;
        static $defaultTagTerm;

        if (isset($this->tagsToItems[$tag])) {
            return $this->tagsToItems[$tag];
        }

        // Prepare search one time.
        if (is_null($defaultParams)) {
            $tagAsSkos = $this->getArg('tagAsSkos')
                && (
                    $this->api->search('vocabularies', ['namespace_uri' => 'http://www.w3.org/2004/02/skos/core#'])->getTotalResults()
                    || $this->api->search('vocabularies', ['namespace_uri' => 'http://www.w3.org/2004/02/skos/core'])->getTotalResults()
                );

            $tagMainItemId = $this->getArg('tagMainItem');
            // Check if the item exists, because it could have been removed.
            if ($tagMainItemId) {
                try {
                    $tagMainItemId = $this->api->read('items', ['id' => $tagMainItemId])->getContent()->id();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $this->logger->warn(sprintf('The item "%s" is not available to relate tag as item.', $tagMainItemId)); // @translate
                    $tagMainItemId = null;
                }
            }

            $tagItemSetId = $this->getArg('tagItemSet');
            // Check if the item set exists, because it could have been removed.
            if ($tagItemSetId) {
                try {
                    $tagItemSetId = $this->api->read('item_sets', ['id' => $tagItemSetId])->getContent()->id();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $this->logger->warn(sprintf('The item set "%s" is not available to store tag as item.', $tagItemSetId)); // @translate
                    $tagItemSetId = null;
                }
            }

            $tagLanguage = $this->getArg('tagLanguage');

            $propertyTerm = $tagAsSkos ? 'skos:prefLabel' : 'dcterms:title';
            $propertyId = $tagAsSkos ? $this->properties['skos']['prefLabel'] : $this->properties['dcterms']['title'];
            $defaultTagTerm = $propertyTerm;

            // Prepare the request to check if a tag exists as item.
            $defaultParams = [
                'limit' => 1,
                'sort_by' => 'id',
                'sort_order' => 'asc',
                'property' => [
                    [
                        'property' => $propertyId,
                        'type' => 'eq',
                        'joiner' => 'and',
                        'text' => null,
                    ],
                ],
            ];
            if ($tagItemSetId) {
                $defaultParams['item_set_id'] = [$tagItemSetId];
            }

            // Prepare the data to create a new tag as item.
            $defaultTagData[$propertyTerm][] = [
                'property_id' => $propertyId,
                'type' => 'literal',
                '@value' => null,
                '@language' => $tagLanguage,
            ];
            if ($tagItemSetId) {
                $defaultTagData['o:item_set'][] = ['o:id' => $tagItemSetId];
            }
            if ($tagAsSkos) {
                $defaultTagData['o:resource_class'] = ['o:id' => $this->resourceClasses['skos']['Concept']];

                $template = $this->api->search('resource_templates', ['label' => 'Thesaurus Concept', 'limit' => 1])->getContent();
                if ($template) {
                    $template = reset($template);
                } else {
                    // Try to get the resource template of the module Thesaurus if the name was changed.
                    try {
                        $template = $this->api->read('resource_templates', ['resourceClass' => $this->resourceClasses['skos']['Concept']])->getContent();
                    } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    }
                }
                if ($template) {
                    $defaultTagData['o:resource_template'] = ['o:id' => $template->id()];
                }
            }
            if ($tagMainItemId) {
                $defaultTagData[$tagAsSkos ? 'skos:inScheme' : 'dcterms:isPartOf'][] = [
                    'property_id' => $tagAsSkos ? $this->properties['skos']['inScheme'] : $this->properties['dcterms']['isPartOf'],
                    'type' => 'resource:item',
                    '@value' => null,
                    '@language' => null,
                    'value_resource_id' => $tagMainItemId,
                ];
            }
        }

        // Do the search or create of the tag.
        $params = $defaultParams;
        $params['property'][0]['text'] = $tag;

        $tagIds = $this->api->search('items', $params, ['initialize' => false, 'finalize' => false, 'returnScalar' => 'id'])->getContent();
        if ($tagIds) {
            $tagId = reset($tagIds);
        } else {
            $tagData = $defaultTagData;
            $tagData[$defaultTagTerm][0]['@value'] = $tag;
            $tagId = $this->api->create('items', $tagData)->getContent();
            $tagId = $tagId->id();
        }

        $this->tagsToItems[$tag] = $tagId;
        return $tagId;
    }
}
