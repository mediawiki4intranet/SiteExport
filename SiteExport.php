<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт.
   (c) Виталий Филиппов <vitalif@yourcmc.ru>, 2009+
   Лицензия: GPLv3 или более поздняя */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

define('SITEEXPORT_VERSION', "1.0.6, 2010-03-03");

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['MediaWikiSiteExport'] = $dir . 'SiteExport.class.php';
$wgExtensionFunctions[] = 'wfSiteExport_INit';

$wgExtensionCredits['other'][] = array(
    'path'        => __FILE__,
    'name'        => 'SiteExport',
    'version'     => SITEEXPORT_VERSION,
    'author'      => 'Vitaliy Filippov',
    'url'         => 'http://www.yourcmc.ru/wiki/index.php/SiteExport_(MediaWiki)',
    'description' => 'Support for exporting Wiki page text into static HTML automatically on article updates',
);

// Initialize extension (select proper hook depending on MW version)
// Hooks are here, class is autoloaded lazily
function wfSiteExport_Init()
{
    global $wgVersion, $wgHooks;
    if ($wgVersion < '1.14')
        $wgHooks['NewRevisionFromEditComplete'][] = 'wfSiteExport_NewRevisionFromEditComplete';
    else
        $wgHooks['ArticleEditUpdates'][] = 'wfSiteExport_ArticleEditUpdates';
}

function wfSiteExport_NewRevisionFromEditComplete($article, $rev, $baseID, $user)
{
    wfSiteExport_Update($article);
    return true;
}

function wfSiteExport_ArticleEditUpdates($article, $editInfo, $changed)
{
    wfSiteExport_Update($article);
    return true;
}

// Queues the article for update
function wfSiteExport_Update($article)
{
    global $wgSiteExportHandlers, $wgSiteExportExporter, $wgDeferredUpdateList;
    $key = $article->getTitle()->getText();
    foreach ($wgSiteExportHandlers as $prefix => $hspec)
    {
        if (substr($key, 0, strlen($prefix)) == $prefix)
        {
            if (!$wgSiteExportExporter)
            {
                $wgSiteExportExporter = new MediaWikiSiteExport();
                $wgDeferredUpdateList[] = $wgSiteExportExporter;
            }
            $wgSiteExportExporter->enqueue($article);
        }
    }
    return true;
}
