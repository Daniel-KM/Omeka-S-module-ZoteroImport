<?php
namespace ZoteroImport\Job;

use DateTime;
use Omeka\Api\Manager;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;
use Omeka\Stdlib\Message;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Log\Logger;
use ZoteroImport\Zotero\Url;

abstract class AbstractZoteroSync extends AbstractJob
{
    const ACTION_CREATE = 'create'; // @translate
    const ACTION_REPLACE = 'replace'; // @translate

    /**
     * Number of resources to process by batch (Zotero default limit is 50).
     *
     * @var int
     */
    protected $sizeChunk = 50;

    /**
     * @var Manager
     */
    protected $api;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Zotero API client
     *
     * @var Client
     */
    protected $client;

    /**
     * Zotero API URL
     *
     * @var Url
     */
    protected $url;

    /**
     * Cache of Omeka resource classes.
     *
     * @var array
     */
    protected $resourceClasses = [];

    /**
     * Cache of Omeka properties.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Priority map between Zotero item types and Omeka resource classes
     *
     * @var array
     */
    protected $itemTypeMap = [];

    abstract public function perform();

    /**
     * Cache resource classes.
     */
    protected function cacheResourceClasses()
    {
        /** @var \Omeka\Api\Representation\VocabularyRepresentation[] $vocabularies */
        $vocabularies = $this->api->search('vocabularies')->getContent();
        foreach ($vocabularies as $vocabulary) {
            $prefix = $vocabulary->prefix();
            foreach ($vocabulary->resourceClasses() as $class) {
                $this->resourceClasses[$prefix][$class->localName()] = $class->id();
            }
        }
    }

    /**
     * Cache properties.
     */
    protected function cacheProperties()
    {
        /** @var \Omeka\Api\Representation\VocabularyRepresentation[] $vocabularies */
        $vocabularies = $this->api->search('vocabularies')->getContent();
        foreach ($vocabularies as $vocabulary) {
            $prefix = $vocabulary->prefix();
            foreach ($vocabulary->properties() as $property) {
                $this->properties[$prefix][$property->localName()] = $property->id();
            }
        }
    }

    /**
     * Load a mapping between Zotero and Omeka.
     *
     * @param string $mapping
     * @return array
     */
    protected function loadMapping($mapping)
    {
        return require dirname(dirname(__DIR__)) . '/data/mapping/' . $mapping . '.php';
    }

    /**
     * Convert a mapping with terms into a mapping with prefix and local name.
     *
     * Only the existing terms are mapped.
     *
     * @param string $mapping
     * @param string $termType
     * @return array
     */
    protected function prepareMapping($mapping, $termType)
    {
        $map = $this->loadMapping($mapping);
        foreach ($map as $key => &$term) {
            if ($term) {
                $value = explode(':', $term);
                if (isset($this->$termType[$value[0]][$value[1]])) {
                    $term = [$value[0] => $value[1]];
                } else {
                    unset($map[$key]);
                }
            } else {
                unset($map[$key]);
            }
        }
        return $map;
    }

    /**
     * Flip a mapping between Zotero and Omeka.
     *
     * @param string $mapping
     * @return array
     */
    protected function invertMapping($mapping)
    {
        return array_flip($this->loadMapping($mapping));
    }

    /**
     * Set the HTTP client to use during this import.
     */
    protected function setImportClient()
    {
        $headers = ['Zotero-API-Version' => '3'];
        if ($apiKey = $this->getArg('apiKey')) {
            $headers['Authorization'] = sprintf('Bearer %s', $apiKey);
        }
        $this->client = $this->getServiceLocator()->get('Omeka\HttpClient')
            ->setHeaders($headers)
            // Decrease the chance of timeout by increasing to 20 seconds,
            // which splits the time between Omeka's default (10) and Zotero's
            // upper limit (30).
            ->setOptions(['timeout' => 20]);
    }

    /**
     * Set the Zotero URL object to use during this import.
     */
    protected function setImportUrl()
    {
        $this->url = new Url($this->getArg('type'), $this->getArg('id'));
    }

    /**
     * Get a response from the Zotero API.
     *
     * @param string $url
     * @return Response
     */
    protected function getResponse($url)
    {
        $response = $this->client->setUri($url)->send();
        if (!$response->isSuccess()) {
            $message = new Message(
                'Requested "%s" got "%s".', // @translate
                $url, $response->renderStatusLine()
            );
            if ($body = trim($response->getBody())) {
                $message .= PHP_EOL . $body . PHP_EOL;
            }
            throw new Exception\RuntimeException($message);
        }
        return $response;
    }

    /**
     * Get a URL from the Link header.
     *
     * @param Response $response
     * @param string $rel The relationship from the current document. Possible
     * values are first, prev, next, last, alternate.
     * @return string|null
     */
    protected function getLink(Response $response, $rel)
    {
        $linkHeader = $response->getHeaders()->get('Link');
        if (!$linkHeader) {
            return null;
        }
        preg_match_all(
            '/<([^>]+)>; rel="([^"]+)"/',
            $linkHeader->getFieldValue(),
            $matches
        );
        if (!$matches) {
            return null;
        }
        $key = array_search($rel, $matches[2]);
        if (false === $key) {
            return null;
        }
        return $matches[1][$key];
    }

    /**
     *Fetch all Zotero parent and child items.
     *
     * Note: this is the full Zotero item, not only the "data" part.
     *
     * @param array $zoteroItemKeys
     * @returrn array Array with parent and child Zotero items, keyed by the
     * Zotero key.
     */
    protected function fetchZoteroItems(array $zoteroItemKeys)
    {
        $zParentItems = [];
        $zChildItems = [];
        foreach (array_chunk($zoteroItemKeys, $this->sizeChunk, true) as $zItemKeysChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $url = $this->url->items([
                'itemKey' => implode(',', $zItemKeysChunk),
                // Include the Zotero key so Zotero adds enclosure links to the
                // response. An attachment can only be downloaded if an
                // enclosure link is included.
                'key' => $this->getArg('apiKey'),
            ]);
            $zItems = json_decode($this->getResponse($url)->getBody(), true);

            foreach ($zItems as $zItem) {
                $dateAdded = new DateTime($zItem['data']['dateAdded']);
                if ($dateAdded->getTimestamp() < $this->getArg('timestamp', 0)) {
                    // Only import items added since the passed timestamp. Note
                    // that the timezone must be UTC.
                    continue;
                }

                // Unset unneeded data to save memory.
                unset($zItem['library']);
                unset($zItem['version']);
                unset($zItem['meta']);
                unset($zItem['links']['self']);
                unset($zItem['links']['alternate']);

                if (isset($zItem['data']['parentItem'])) {
                    $zChildItems[$zItem['data']['parentItem']][] = $zItem;
                } else {
                    $zParentItems[$zItem['key']] = $zItem;
                }
            }
        }

        return [
            $zParentItems,
            $zChildItems,
        ];
    }

    /**
     * Fetch all attachments of one Zotero item.
     *
     * @todo Manage pagination (but very rare for an item in Zotero).
     *
     * @param string $zoteroKey
     * @return array
     */
    protected function fetchItemAttachments($zoteroItemKey)
    {
        $this->setImportClient();
        $url = $this->url->itemChildren($zoteroItemKey, ['itemType' => 'attachment']);
        return json_decode($this->getResponse($url)->getBody(), true);
    }

    /**
     * Delete a Zotero item without check.
     *
     * @param array|string $zoteroItem
     */
    protected function deleteZoteroItem($zoteroItem)
    {
        if (is_array($zoteroItem)) {
            $zoteroItem = $zoteroItem['key'];
        }

        $url = $this->url->item($zoteroItem);
        $this->setImportClient();
        $client = $this->client;
        $client->setMethod(Request::METHOD_DELETE);
        $request = $client->getRequest();
        $headers = $request->getHeaders();
        $headers->addHeaderLine('If-Unmodified-Since-Version', 999999999);
        $response = $this->getResponse($url);
    }

    /**
     * Delete a list of Zotero items without check.
     *
     * @param array $zoteroItemKeys
     */
    protected function deleteZoteroItems($zoteroItems)
    {
        foreach (array_chunk($zoteroItems, $this->sizeChunk, true) as $chunk) {
            $url = $this->url->items(['itemKey' => implode(',', $chunk)]);
            $this->setImportClient();
            $client = $this->client;
            $client->setMethod(Request::METHOD_DELETE);
            $request = $client->getRequest();
            $headers = $request->getHeaders();
            $headers->addHeaderLine('If-Unmodified-Since-Version', 999999999);
            $response = $this->getResponse($url);
        }
    }

    /**
     * Get the Omeka item ids of Zotero items that are already managed.
     *
     * Note: Only the first Omeka item id is returned when an item as been
     * created multiple times.
     *
     * @param array $zoteroItems
     * @return array Associative array of Zotero item keys and Omeka item ids.
     */
    protected function existingItems(array $zoteroItems)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        // TODO How to do a "WHERE IN" with doctrine and strings?
        $quotedZoteroItemKeys = array_map([$connection, 'quote'], $zoteroItems);
        $qb
            ->select([
                // Should be the first column.
                'zotero_key' => 'zotero_import_item.zotero_key',
                'item_id' => 'zotero_import_item.item_id',
            ])
            ->from('zotero_import_item', 'zotero_import_item')
            // ->where($qb->expr()->in('zotero_import_item.zotero_key', ':zotero_keys'))
            // ->setParameter('zotero_keys', $zParentItems)
            ->where($qb->expr()->in('zotero_import_item.zotero_key', $quotedZoteroItemKeys))
            // Only one identifier by resource, and the first one.
            ->groupBy(['zotero_import_item.zotero_key'])
            ->orderBy('zotero_import_item.id', 'ASC');
        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $existingItems = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $existingItems;
    }

    /**
     * Get the Zotero item keys of Omeka items that are already managed.
     *
     * Note: Only the first Zotero item key is returned when an item as been
     * created multiple times.
     *
     * @param array $itemIds
     * @return array Associative array of Omeka items id and Zotero item keys.
     */
    protected function existingZoteroItems(array $itemIds)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $itemIds = array_filter(array_map('intval', $itemIds));
        $qb
            ->select([
                // Should be the first column.
                'item_id' => 'zotero_import_item.item_id',
                'zotero_key' => 'zotero_import_item.zotero_key',
            ])
            ->from('zotero_import_item', 'zotero_import_item')
            ->where($qb->expr()->in('zotero_import_item.item_id', ':item_ids'))
            ->setParameter('item_ids', implode(',', $itemIds))
            // Only one identifier by resource, and the first one.
            ->groupBy(['zotero_import_item.item_id'])
            ->orderBy('zotero_import_item.id', 'ASC');
        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $existingZoteroItems = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $existingZoteroItems;
    }
}
