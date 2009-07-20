<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт. */

/* Функции и переменные до mkpath приведены для примера использования. */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

define('SITEEXPORT_VERSION', "1.0.3, 2008-12-06");

$wgSiteExportHandlers = array(
    'GT-Service:' => array('seGtsrFixLinks', '/home/gtsr/pages/', array(
        'GT-Service: Новости' => 'seGtsrHandleNews'
    )),
);

if (!wfIsWindows())
    $wgHooks['ArticleSaveComplete'][] = 'fnSiteExportAfterTidy';

function seGtsrHandleNews($article, $html)
{
    global $wgSiteExportHandlers;
    $wgGTSRpath = $wgSiteExportHandlers['GT-Service:'][1];
    preg_match_all('/<li(?:\s+[^<>]+)?>(?:.*?)<\/li\s*>/is', $html, $all, PREG_PATTERN_ORDER);
    $all = '<ul>'.implode("\n", array_splice($all[0], 0, 3)).'</ul>';
    if ($fp = fopen($wgGTSRpath . 'lastnews.htm', "wb"))
    {
        fwrite($fp, $all);
        fclose($fp);
    }
    return $html;
}

function seGtsrFixLinks($article, $html)
{
    $html = preg_replace('#/wiki/index.php/GT-Service:_([^<>\'"]*)#is', '/\1.htm', $html);
    $html = preg_replace('#/wiki/index.php/(Image|Изображение|%D0%98%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5):([^<>\'"]*)#is', 'http://www.yourcmc.ru\0', $html);
    $html = str_replace('/wiki/images/', 'http://www.yourcmc.ru/wiki/images/', $html);
    $html = preg_replace('#<p(?:\s+[^<>]+)?>((?:\s*<!--.*?-->)*\s*)</p\s*>#is', '\1', $html);
    return $html;
}

function mkpath($path, $mode = 0755)
{
    if (preg_match('#[^/\\\\]+[/\\\\]*$#is', $path, $m))
    {
        if (!mkpath(substr($path, 0, strlen($path)-strlen($m[0]))))
            return false;
        if (!file_exists($path))
        {
            mkdir($path);
            chmod($path, $mode);
        }
        return file_exists($path);
    }
    return true;
}

function seGetLinksTo ($title)
{
    $dbr = wfGetDB(DB_SLAVE);
    $res = $dbr->select(
        'templatelinks',
        array('tl_from'),
        array('tl_title' => $title->getDBkey(), 'tl_namespace' => $title->getNamespace()),
        __METHOD__);
    $pages = array();
    while ($row = $dbr->fetchRow($res))
        $pages[] = Title::newFromId($row['tl_from']);
    return $pages;
}

function seUpdateArticle($article, $text = NULL, $revision = NULL)
{
    global $wgSiteExportHandlers, $wgParser;
    $res = false;
    $title = $article->getTitle();
    $dbkey = $title->getDBkey();
    foreach ($wgSiteExportHandlers as $prefix => $hspec)
    {
        if (substr($dbkey, 0, strlen($prefix)) == $prefix)
        {
            $func = $hspec[0];
            $fn = $hspec[1] . trim(substr($dbkey, strlen($prefix)), "_ \t\n\r") . '.htm';
            if (!mkpath(dirname($fn)))
                continue;
            if ($fp = @fopen($fn, "wb"))
            {
                $options = new ParserOptions;
                $options->setTidy(true);
                $options->setEditSection(false);
                $options->setRemoveComments(false);
                if (is_null($text))
                {
                    $article->loadContent();
                    $text = $article->mContent;
                }
                if (is_null($revision) && $article->mRevision)
                    $revision = $article->mRevision->getId();
                $mod = $wgParser->parse($text, $title, $options, true, true, $revision)->getText();
                $mod = $func($article, $mod);
                if ($f = $hspec[2][$title->getText()])
                    $mod = $f($article, $mod);
                fwrite($fp, $mod);
                fclose($fp);
            }
            $res = true;
            break;
        }
    }
    $pages = seGetLinksTo($title);
    foreach ($pages as $page)
        seUpdateArticle(new Article($page));
    return $res;
}

function fnSiteExportAfterTidy(&$article, &$user, $text, $summary, &$minoredit, $watchthis, $sectionanchor, &$flags, $revision)
{
    seUpdateArticle($article, $text, $revision);
    return true;
}

?>
