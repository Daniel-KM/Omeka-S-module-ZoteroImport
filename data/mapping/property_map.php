<?php
// Warning: the mapping is not one-to-one, so some data may be lost when the
// mapping is reverted. You may adapt it to your needs.

// https://www.zotero.org/support/kb/item_types_and_fields#item_fields
// Note: Many Zotero fields depends on Zotero item type.
// TODO Manage properties according to Zotero item type.
return [
    'bibo:court'                => 'court',
    'bibo:distributor'          => 'distributor',
    'bibo:doi'                  => 'DOI',
    'bibo:edition'              => 'edition',
    'bibo:isbn'                 => 'ISBN',
    'bibo:issn'                 => 'ISSN',
    'bibo:issue'                => 'issue',
    'bibo:issuer'               => 'issuingAuthority',
    'bibo:lccn'                 => 'callNumber',
    // 'bibo:number'               => 'applicationNumber',
    // 'bibo:number'               => 'billNumber',
    // 'bibo:number'               => 'codeNumber',
    // 'bibo:number'               => 'docketNumber',
    'bibo:number'               => 'documentNumber',
    // 'bibo:number'               => 'episodeNumber',
    // 'bibo:number'               => 'patentNumber',
    // 'bibo:number'               => 'publicLawNumber',
    // 'bibo:number'               => 'reportNumber',
    // 'bibo:number'               => 'seriesNumber',
    // 'bibo:number'               => 'versionNumber',
    'bibo:numberOfVolumes'      => 'numberOfVolumes',
    'bibo:numPages'             => 'numPages',
    'bibo:organizer'            => 'committee',
    // 'bibo:organizer'            => 'legislativeBody',
    'bibo:pages'                => 'pages',
    'bibo:pageStart'            => 'firstPage',
    'bibo:presentedAt'          => 'conferenceName',
    // 'bibo:presentedAt'          => 'meetingName',
    // 'bibo:presentedAt'          => 'session',
    'bibo:section'              => 'section',
    // 'bibo:shortTitle'           => 'journalAbbreviation',
    'bibo:shortTitle'           => 'shortTitle',
    'bibo:status'               => 'legalStatus',
    'bibo:uri'                  => 'url',
    'bibo:volume'               => 'volume',
    'dcterms:abstract'          => 'abstractNote',
    'dcterms:date'              => 'date',
    'dcterms:dateSubmitted'     => 'filingDate',
    'dcterms:description'       => 'seriesText',
    'dcterms:extent'            => 'artworkSize',
    // 'dcterms:extent'            => 'runningTime',
    // 'dcterms:extent'            => 'scale',
    // 'dcterms:format'            => 'audioRecordingFormat',
    'dcterms:format'            => 'videoRecordingFormat',
    // 'dcterms:isPartOf'          => 'bookTitle',
    // 'dcterms:isPartOf'          => 'dictionaryTitle',
    // 'dcterms:isPartOf'          => 'encyclopediaTitle',
    // 'dcterms:isPartOf'          => 'proceedingsTitle',
    // 'dcterms:isPartOf'          => 'programTitle',
    'dcterms:issued'            => 'issueDate',
    'dcterms:language'          => 'language',
    'dcterms:medium'            => 'artworkMedium',
    // 'dcterms:medium'            => 'interviewMedium',
    // 'dcterms:publisher'         => 'archive',
    // 'dcterms:publisher'         => 'blogTitle',
    // 'dcterms:publisher'         => 'country',
    // 'dcterms:publisher'         => 'forumTitle',
    // 'dcterms:publisher'         => 'place',
    'dcterms:publisher'         => 'publisher',
    // 'dcterms:publisher'         => 'websiteTitle',
    'dcterms:rights'            => 'rights',
    'dcterms:source'            => 'archiveLocation',
    // 'dcterms:subject'           => '', // Managed as Zotero tags.
    // 'dcterms:title'             => 'caseName',
    // 'dcterms:title'             => 'code',
    // 'dcterms:title'             => 'publicationTitle',
    // 'dcterms:title'             => 'reporter',
    // 'dcterms:title'             => 'series',
    // 'dcterms:title'             => 'seriesTitle',
    'dcterms:title'             => 'title',
    // 'dcterms:type'              => 'audioFileType',
    'dcterms:type'              => 'genre',
    // 'dcterms:type'              => 'letterType',
    // 'dcterms:type'              => 'manuscriptType',
    // 'dcterms:type'              => 'mapType',
    // 'dcterms:type'              => 'postType',
    // 'dcterms:type'              => 'presentationType',
    // 'dcterms:type'              => 'reportType',
    // 'dcterms:type'              => 'thesisType',
    // 'dcterms:type'              => 'websiteType',
];
