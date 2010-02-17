<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт. */

/* Функции и переменные до mkpath приведены для примера использования. */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

define('SITEEXPORT_VERSION', "1.0.5, 2009-11-11");

$wgSiteExportHandlers = array(
    'GT-Service:' => array('seGtsrFixLinks', '/home/gtsr/pages/', array(
        'GT-Service: Новости' => array('seGtsrFixLinks', 'seGtsrHandleNews'),
    )),
    'Emotion/' => array(
        array('seEmotionFixLinks', 'seEmotionCompose'),
        '/home/yourcmc/WWW/emotion/pages/',
        array(
            'Emotion/Элемент/Меню' =>
                array('seEmotionFixLinks', 'xslt:emotion-menu.xsl'),
            'Emotion/Элемент/Новинки' =>
                array('seEmotionFixLinks', 'xslt:emotion-novelty.xsl'),
            'Emotion/Элемент/Последние новости' =>
                array('seEmotionFixLinks', 'xslt:emotion-lastnews.xsl'),
            'Emotion/Элемент/Вечерние и свадебные платья' =>
                array('seEmotionFixLinks', 'xslt:emotion-dress.xsl'),
        )
    ),
);

if (!wfIsWindows())
    $wgHooks['ArticleSaveComplete'][] = 'fnSiteExportAfterTidy';

/* ОБРАБОТЧИКИ САЙТОВ */

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
    $html = preg_replace('!/wiki/(?:index\.php/)?GT-Service:_([^<>\'"#]*)!is', '/\1.htm', $html);
    $html = str_replace('title="GT-Service:_', 'title="', $html);
    $html = str_replace('/wiki/images/', 'http://www.yourcmc.ru/wiki/images/', $html);
    $html = preg_replace_callback('#/wiki/(?:index\.php/)?(Image|File|Файл|%D0%A4%D0%B0%D0%B9%D0%BB|Изображение|%D0%98%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5):([^<>\'"]*)#is', 'sePopupCallback', $html);
    $html = preg_replace('#<p(?:\s+[^<>]+)?>((?:\s*<!--.*?-->)*\s*)</p\s*>#is', '\1', $html);
    return $html;
}

function seEmotionFixLinks($article, $html)
{
    global $seEmotionSet;
    preg_match_all('/<!--#set.*?-->/is', $html, $m);
    $seEmotionSet = join("\n", $m[0]);
    $html = preg_replace('/<!--(#set|EXPORTFLUSH:).*?-->/is', '', $html);
    if (stripos($seEmotionSet, 'var="title"') === false)
        $seEmotionSet = '<!--#set var="title" value="' . $article->getTitle()->getSubpageText() . '"-->' . $seEmotionSet;
    $html = preg_replace('!/wiki/(?:index\.php/)?Emotion/([^<>\'"#]*)!is', '/\1.htm', $html);
    $html = str_replace('title="Emotion/', 'title="', $html);
    $html = str_replace('/wiki/images/', 'http://www.yourcmc.ru/wiki/images/', $html);
    //$html = preg_replace('#/wiki/(?:index\.php/)?(Image|File|Файл|%D0%A4%D0%B0%D0%B9%D0%BB|Изображение|%D0%98%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5):([^<>\'"]*)#is', 'http://yourcmc.ru\0', $html);
    $html = preg_replace_callback('#/wiki/(?:index\.php/)?(Image|File|Файл|%D0%A4%D0%B0%D0%B9%D0%BB|Изображение|%D0%98%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5):([^<>\'"]*)#is', 'sePopupCallback', $html);
    $html = preg_replace('#<p(?:\s+[^<>]+)?>((?:\s*<!--.*?-->)*\s*)</p\s*>#is', '\1', $html);
    return $html;
}

function seEmotionCompose($article, $html)
{
    if (substr($article->getTitle()->getText(), 0, strlen('Emotion/Элемент')) == 'Emotion/Элемент')
        return $html;
    global $wgSiteExportHandlers, $seEmotionSet;
    $path = preg_replace('#/*pages/*$#is', '', $wgSiteExportHandlers['Emotion/'][1]);
    // preg_replace('/(<!--#include\s+file=")(.*?"\s*-->)/is', '\1'.$path.'/pages/\2',
    $header = file_get_contents("$path/header.htm");
    $footer = file_get_contents("$path/footer.htm");
    return $seEmotionSet . $header . $html . $footer;
}

/* ФУНКЦИИ РАСШИРЕНИЯ */

function sePopupCallback($m)
{
    $title = urldecode($m[2]);
    $img = Image::newFromName($title);
    if ($img)
    {
        $w = $img->getWidth()+0;
        $h = $img->getHeight()+0;
        $url = $img->getFullURL();
        return $url.'" rel="lightbox';
    }
    return '';
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

function seUpdateArticle($article, $text = NULL, $revision = NULL, $track = NULL)
{
    global $wgSiteExportHandlers, $wgParser, $wgContLang;
    if (!$track)
        $track = array();
    $res = false;
    $title = $article->getTitle();
    if ($track[$title->getPrefixedText()])
        return true;
    $dbkey = $title->getDBkey();
    foreach ($wgSiteExportHandlers as $prefix => $hspec)
    {
        if (substr($dbkey, 0, strlen($prefix)) == $prefix)
        {
            $func = $hspec[0];
            $ns = $title->getNamespace();
            $ns = $ns ? $wgContLang->getNsText($ns) . '/' : '';
            $fn = $hspec[1] . $ns . trim(substr($dbkey, strlen($prefix)), "_ \t\n\r") . '.htm';
            if (!mkpath(dirname($fn)))
                continue;
            if ($fp = fopen($fn, "wb"))
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
                $html = $wgParser->parse($text, $title, $options, true, true, $revision)->getText();
                preg_match_all('#<!--EXPORTFLUSH:(.*?)-->#is', $html, $flush, PREG_PATTERN_ORDER);
                $flush = $flush[1];
                if (!($f = $hspec[2][$title->getPrefixedText()]))
                    $f = $func;
                $html = fnSiteExportProcess($article, $html, $f);
                fwrite($fp, $html);
                fclose($fp);
                $track[$title->getPrefixedText()] = true;
            }
            $res = true;
            break;
        }
    }
    if ($flush)
        foreach ($flush as $tt)
            seUpdateArticle(new Article(Title::makeTitleSafe(NS_MAIN, $tt)), NULL, NULL, $track);
    $pages = seGetLinksTo($title);
    foreach ($pages as $page)
        seUpdateArticle(new Article($page), NULL, NULL, $track);
    return $res;
}

function fnSiteExportProcess($article, $mod, $f)
{
    if (is_array($f))
        foreach ($f as $i)
            $mod = fnSiteExportProcess($article, $mod, $i);
    else if (is_string($f))
    {
        list($type, $f) = split(':', $f, 2);
        if (!$f)
        {
            $f = $type;
            $type = 'call';
        }
        $type = strtolower($type);
        if ($type == 'call')
            $mod = $f($article, $mod);
        else if ($type == 'xslt')
            $mod = fnSiteExportXSLT($article, $mod, $f);
    }
    return $mod;
}

function fnSiteExportXSLT($article, $text, $xsltfile)
{
    global $egSiteExportXSLTCache;
    if (!$egSiteExportXSLTCache)
        $egSiteExportXSLTCache = array();
    $xsltfile = dirname(__FILE__) . '/' . str_replace('..', '', $xsltfile);
    if (!($proc = $egSiteExportXSLTCache[$xsltfile]))
    {
        $xsl = new DOMDocument();
        $xsl->load($xsltfile);
        $proc = new XSLTProcessor();
        $proc->importStylesheet($xsl);
        $egSiteExportXSLTCache[$xsltfile] = $proc;
    }
    $dom = new DOMDocument();
    $text = "<root>$text</root>";
    $dom->loadXML($text);
    return $proc->transformToXML($dom);
}

function fnSiteExportAfterTidy(&$article, &$user, $text, $summary, &$minoredit, $watchthis, $sectionanchor, &$flags, $revision)
{
    seUpdateArticle($article, $text, $revision);
    return true;
}

?>
