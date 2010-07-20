<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт.
   (c) Виталий Филиппов <vitalif@yourcmc.ru>, 2009-2010 */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

define('SITEEXPORT_VERSION', "1.0.6, 2010-03-03");

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['MediaWikiSiteExport'] = $dir . 'SiteExport.class.php';
$wgHooks['ArticleSaveComplete'][] = 'wfSiteExportArticleSaveComplete';

$wgExtensionCredits['specialpage'][] = array(
    'path'        => __FILE__,
    'name'        => 'SiteExport',
    'version'     => SITEEXPORT_VERSION,
    'author'      => 'Vitaliy Filippov',
    'url'         => 'http://www.yourcmc.ru/wiki/index.php/SiteExport_(MediaWiki)',
    'description' => 'Support for exporting Wiki page text into static HTML automatically on article updates',
);

/* Хук здесь, а класс подгружается по необходимости */
function wfSiteExportArticleSaveComplete(&$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision)
{
    global $wgSiteExportHandlers;
    $key = $article->getTitle()->getText();
    foreach ($wgSiteExportHandlers as $prefix => $hspec)
    {
        if (substr($key, 0, strlen($prefix)) == $prefix)
        {
            $exporter = MediaWikiSiteExport::singleton();
            $exporter->update_article($article, $text, $revision);
        }
    }
    return true;
}
