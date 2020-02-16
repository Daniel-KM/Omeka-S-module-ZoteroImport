<?php
// The mapping is one-to-one when the ontology FaBiO is available.
// Without it, some data may be lost when the mapping is reverted.
// You may adapt it to your needs.

// https://www.zotero.org/support/kb/item_types_and_fields#item_types
return [
    'artwork'             => [
                                'fabio:artisticWork',
                                'bibo:Image',
                             ],
    'attachment'          => [
                                'bibo:DocumentPart',
                                'bibo:Document',
                             ],
    'audioRecording'      => 'bibo:AudioDocument',
    'bill'                => 'bibo:Bill',
    'blogPost'            => [
                                'fabio:blogPost',
                                'bibo:Article',
                             ],
    'book'                => 'bibo:Book',
    'bookSection'         => 'bibo:BookSection',
    'case'                => 'bibo:LegalCaseDocument',
    'computerProgram'     => [
                                'dctype:software',
                                'bibo:Document',
                             ],
    'conferencePaper'     => [
                                'fabio:ConferencePaper',
                                'bibo:Article',
                             ],
    'dictionaryEntry'     => [
                                'fabio:ReferenceEntry',
                                'bibo:Article',
                             ],
    'document'            => 'bibo:Document',
    'email'               => 'bibo:Email',
    'encyclopediaArticle' => [
                                'fabio:Entry',
                                'bibo:Article',
                             ],
    'film'                => 'bibo:Film',
    'forumPost'           => [
                                'fabio:Opinion',
                                'bibo:Article',
                             ],
    'hearing'             => 'bibo:Hearing',
    'instantMessage'      => 'bibo:PersonalCommunication',
    'interview'           => 'bibo:Interview',
    'journalArticle'      => 'bibo:AcademicArticle',
    'letter'              => 'bibo:Letter',
    'magazineArticle'     => [
                                'fabio:MagazineArticle',
                                'bibo:Article',
                             ],
    'manuscript'          => 'bibo:Manuscript',
    'map'                 => 'bibo:Map',
    'newspaperArticle'    => [
                                'fabio:NewspaperArticle',
                                'bibo:Article',
                             ],
    'note'                => 'bibo:Note',
    'patent'              => 'bibo:Patent',
    'podcast'             => [
                                'fabio:AudioDocument',
                                'bibo:AudioDocument',
                             ],
    'presentation'        => 'bibo:Slideshow',
    'radioBroadcast'      => [
                                'dctype:Sound',
                                'bibo:AudioDocument',
                             ],
    'report'              => 'bibo:Report',
    'statute'             => 'bibo:Statute',
    'tvBroadcast'         => [
                                'fabio:MovingImage',
                                'bibo:AudioVisualDocument',
                             ],
    'thesis'              => 'bibo:Thesis',
    'videoRecording'      => 'bibo:AudioVisualDocument',
    'webpage'             => 'bibo:Webpage',
];
