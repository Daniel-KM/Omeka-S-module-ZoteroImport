<?php
namespace ZoteroImport\Form;

use Omeka\Form\Element\ItemSetSelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\Validator\Callback;
use ZoteroImport\Job\Import;

class ImportForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'itemSet',
            'type' => ItemSetSelect::class,
            'options' => [
                'label' => 'Import into', // @translate
                'info' => 'Required. Import items into this item set.', // @translate
                'empty_option' => 'Select item set…', // @translate
                'query' => ['is_open' => true],
            ],
            'attributes' => [
                'required' => true,
                'class' => 'chosen-select',
                'id' => 'library-item-set',
            ],
        ]);

        $this->add([
            'name' => 'type',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Library Type', // @translate
                'info' => 'Required. Is this a user or group library?', // @translate
                'value_options' => [
                    'user' => 'User', // @translate
                    'group' => 'Group', // @translate
                ],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'id',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Library ID', // @translate
                'info' => 'Required. The user ID can be found on the "Feeds/API" section of the Zotero settings page. The group ID can be found on the Zotero group library page by looking at the URL of "Subscribe to this feed".', // @translate
            ],
            'attributes' => [
                'required' => true,
                'id' => 'library-id',
            ],
        ]);

        $this->add([
            'name' => 'collectionKey',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Collection Key', // @translate
                'info' => 'Not required. The collection key can be found on the Zotero library page by looking at the URL when looking at the collection.', // @translate
            ],
            'attributes' => [
                'id' => 'collection-key',
            ],
        ]);

        $this->add([
            'name' => 'apiKey',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'API Key', // @translate
                'info' => 'Required for non-public libraries and file import.', // @translate
            ],
            'attributes' => [
                'id' => 'api-key',
            ],
        ]);

        $this->add([
            'name' => 'syncFiles',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Import Files', // @translate
                'info' => 'The API key is required to import files.', // @translate
            ],
            'attributes' => [
                'id' => 'import-files',
            ],
        ]);

        $actionOptions = [
            Import::ACTION_CREATE => 'Create a new item', // @translate
            Import::ACTION_REPLACE => 'Replace all metadata and files of the item', // @translate
        ];
        $this->add([
            'name' => 'action',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Action for already imported items', // @translate
                'info' => 'The default "Create" duplicates the items that were already imported, so local changes may be kept separately.
"Replace" removes all properties of the existing items, and fill them with the Zotero data and files.
In case of multiple duplicates, only the first one is updated.', // @translate
                'value_options' => $actionOptions,
            ],
            'attributes' => [
                'id' => 'action',
                'class' => 'chosen-select',
            ],
        ]);

        $this->add([
            'name' => 'addedAfter',
            'type' => Element\DateTimeLocal::class,
            'options' => [
                'format' => 'Y-m-d\TH:i',
                'label' => 'Added after', // @translate
                'info' => 'Only import items that have been added to Zotero after this datetime.', // @translate
            ],
            'attributes' => [
                'id' => 'added-after',
            ],
        ]);

        $this
            ->add([
                'name' => 'zoteroimport_tag_language',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Language of tags', // @translate
                ],
                'attributes' => [
                    'id' => 'zoteroimport_tag_language',
                ],
            ])
            ->add([
                'name' => 'zoteroimport_tag_as_item',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Map tags as items', // @translate
                    'info' => 'To use tags as items allows to make them translatable and to build a full thesaurus, manageable with CustomVocab.', // @translate
                ],
                'attributes' => [
                    'id' => 'zoteroimport_tag_as_item',
                ],
            ])
            ->add([
                'name' => 'zoteroimport_tag_as_skos',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use skos for tag as items', // @translate
                    'info' => 'Create items with the ontology skos (see module Thesaurus).', // @translate
                ],
                'attributes' => [
                    'id' => 'zoteroimport_tag_as_skos',
                ],
            ])
            ->add([
                'name' => 'zoteroimport_tag_main_item',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Main item id for tags', // @translate
                    'info' => 'Add a relation "dcterms:isPartOf" or "skos:inScheme".', // @translate
                ],
                'attributes' => [
                    'id' => 'zoteroimport_tag_main_item',
                ],
            ])
            ->add([
                'name' => 'zoteroimport_tag_item_set',
                'type' => ItemSetSelect::class,
                'options' => [
                    'label' => 'item set for tags as items', // @translate
                    'info' => 'Gather all tags in one item set.', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'zoteroimport_tag_item_set',
                    'multiple' => false,
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select an item set…', // @translate
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();

        $inputFilter->add([
            'name' => 'itemSet',
            'required' => true,
            'filters' => [
                ['name' => 'Int'],
            ],
            'validators' => [
                ['name' => 'Digits'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'type',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StringToLower'],
            ],
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => ['user', 'group'],
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'id',
            'required' => true,
            'filters' => [
                ['name' => 'Int'],
            ],
            'validators' => [
                ['name' => 'Digits'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'collectionKey',
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'Null'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'apiKey',
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'Null'],
            ],
        ]);

        $inputFilter->add([
            'name' => 'syncFiles',
            'required' => false,
            'filters' => [
                ['name' => 'ToInt'],
            ],
            'validators' => [
                [
                    'name' => 'Callback',
                    'options' => [
                        'messages' => [
                            Callback::INVALID_VALUE => 'An API key is required to import files.', // @translate
                        ],
                        'callback' => function ($syncFiles, $context) {
                            return $syncFiles ? (bool) $context['apiKey'] : true;
                        },
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'action',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StringToLower'],
            ],
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => array_keys($actionOptions),
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'addedAfter',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'zoteroimport_tag_main_item',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'zoteroimport_tag_item_set',
            'required' => false,
        ]);
    }
}
